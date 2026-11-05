#!/usr/bin/env bash
# Persiapan sekali jalan VPS Ubuntu yang baru berisi Docker (docs/PANDUAN_DEPLOYMENT.md §1):
# pengguna deploy, folder /opt/aisha, kunci SSH GitHub Actions, berkas .env tiap fase,
# dan Caddy sebagai gerbang HTTPS. Aman dijalankan ulang: berkas yang sudah ada tidak ditimpa.
#
# Pemakaian (sebagai root atau dengan sudo):
#   sudo bash setup-server.sh --test-domain test.domain-anda --prod-domain domain-anda \
#        --email email-anda@domain-anda --public-ip 203.0.113.10
set -euo pipefail

REPO_RAW="${REPO_RAW:-https://raw.githubusercontent.com/Rizalibrah08/sistem-monitoring-produks/main}"
BASE_DIR="${BASE_DIR:-/opt/aisha}"
DEPLOY_USER="${DEPLOY_USER:-deploy}"
TEST_DOMAIN=""
PROD_DOMAIN=""
ACME_EMAIL=""
PUBLIC_IP=""

usage() {
    sed -n '2,9p' "$0" | sed 's/^# \{0,1\}//'
    exit 1
}

while [ $# -gt 0 ]; do
    case "$1" in
        --test-domain) TEST_DOMAIN="${2:-}"; shift 2 ;;
        --prod-domain) PROD_DOMAIN="${2:-}"; shift 2 ;;
        --email) ACME_EMAIL="${2:-}"; shift 2 ;;
        --public-ip) PUBLIC_IP="${2:-}"; shift 2 ;;
        -h | --help) usage ;;
        *) echo "Argumen tidak dikenal: $1" >&2; usage ;;
    esac
done

[ -n "$TEST_DOMAIN" ] && [ -n "$PROD_DOMAIN" ] && [ -n "$ACME_EMAIL" ] && [ -n "$PUBLIC_IP" ] || usage
[ "$(id -u)" -eq 0 ] || { echo "Jalankan dengan sudo atau sebagai root." >&2; exit 1; }
command -v docker > /dev/null || { echo "Docker belum terpasang." >&2; exit 1; }
docker compose version > /dev/null 2>&1 || { echo "Docker Compose plugin belum terpasang." >&2; exit 1; }
command -v openssl > /dev/null || { echo "openssl belum terpasang (apt install openssl)." >&2; exit 1; }
command -v curl > /dev/null || { echo "curl belum terpasang (apt install curl)." >&2; exit 1; }

step() { printf '\n==> %s\n' "$1"; }

step "Pengguna ${DEPLOY_USER}"
if id "$DEPLOY_USER" > /dev/null 2>&1; then
    echo "    sudah ada"
else
    useradd --create-home --shell /bin/bash "$DEPLOY_USER"
    echo "    dibuat"
fi
usermod -aG docker "$DEPLOY_USER"
DEPLOY_HOME="$(getent passwd "$DEPLOY_USER" | cut -d: -f6)"

step "Folder ${BASE_DIR}"
install -d -m 755 -o "$DEPLOY_USER" -g "$DEPLOY_USER" "$BASE_DIR" "$BASE_DIR/edge" "$BASE_DIR/test" "$BASE_DIR/prod"

step "Kunci SSH untuk GitHub Actions"
install -d -m 700 -o "$DEPLOY_USER" -g "$DEPLOY_USER" "$DEPLOY_HOME/.ssh"
KEY_FILE="/root/aisha-deploy-key"
if [ ! -f "$KEY_FILE" ] && ! grep -q "github-actions-aisha" "$DEPLOY_HOME/.ssh/authorized_keys" 2> /dev/null; then
    ssh-keygen -q -t ed25519 -N "" -C "github-actions-aisha" -f "$KEY_FILE"
    cat "${KEY_FILE}.pub" >> "$DEPLOY_HOME/.ssh/authorized_keys"
    echo "    kunci baru dibuat di ${KEY_FILE}"
else
    echo "    kunci sudah terpasang sebelumnya"
fi
chown "$DEPLOY_USER:$DEPLOY_USER" "$DEPLOY_HOME/.ssh/authorized_keys"
chmod 600 "$DEPLOY_HOME/.ssh/authorized_keys"

download() {
    curl -fsSL "$REPO_RAW/$1" -o "$2"
    chown "$DEPLOY_USER:$DEPLOY_USER" "$2"
}

# write_env <fase> <APP_ENV> <domain> <alias> <buffer pool>
write_env() {
    local target="$BASE_DIR/$1/.env"

    if [ -f "$target" ]; then
        echo "    $target sudah ada, tidak ditimpa"
        return
    fi

    download docker/deploy/server.env.example "$target"
    sed -i \
        -e "s|^APP_ENV=.*|APP_ENV=$2|" \
        -e "s|^APP_KEY=.*|APP_KEY=base64:$(openssl rand -base64 32)|" \
        -e "s|^APP_URL=.*|APP_URL=https://$3|" \
        -e "s|^DB_PASSWORD=.*|DB_PASSWORD=$(openssl rand -hex 24)|" \
        -e "s|^DB_ROOT_PASSWORD=.*|DB_ROOT_PASSWORD=$(openssl rand -hex 24)|" \
        -e "s|^EDGE_ALIAS=.*|EDGE_ALIAS=$4|" \
        -e "s|^MYSQL_BUFFER_POOL_SIZE=.*|MYSQL_BUFFER_POOL_SIZE=$5|" \
        "$target"
    chmod 600 "$target"
    echo "    $target dibuat"
}

step "Berkas .env fase test dan fase utama"
write_env test staging "$TEST_DOMAIN" aisha-test-web 128M
write_env prod production "$PROD_DOMAIN" aisha-prod-web 256M

step "Gerbang HTTPS (Caddy)"
download docker/edge/compose.yaml "$BASE_DIR/edge/compose.yaml"
download docker/edge/Caddyfile "$BASE_DIR/edge/Caddyfile"
printf 'ACME_EMAIL=%s\nTEST_DOMAIN=%s\nPROD_DOMAIN=%s\n' "$ACME_EMAIL" "$TEST_DOMAIN" "$PROD_DOMAIN" > "$BASE_DIR/edge/.env"
chown "$DEPLOY_USER:$DEPLOY_USER" "$BASE_DIR/edge/.env"
docker compose --project-directory "$BASE_DIR/edge" -f "$BASE_DIR/edge/compose.yaml" up -d

HOST_KEY="$(cut -d' ' -f1,2 /etc/ssh/ssh_host_ed25519_key.pub)"

cat << EOF

============================================================
Selesai. Isi GitHub → Settings → Secrets and variables → Actions:

  DEPLOY_HOST          ${PUBLIC_IP}
  DEPLOY_USER          ${DEPLOY_USER}
  DEPLOY_KNOWN_HOSTS   ${PUBLIC_IP} ${HOST_KEY}
  DEPLOY_SSH_KEY       seluruh isi berkas ${KEY_FILE}
                       (tampilkan dengan: sudo cat ${KEY_FILE})

Setelah DEPLOY_SSH_KEY tersimpan di GitHub, hapus kunci privat dari server:
  sudo rm ${KEY_FILE} ${KEY_FILE}.pub

Pastikan record DNS A untuk ${TEST_DOMAIN} dan ${PROD_DOMAIN} mengarah ke ${PUBLIC_IP},
dan firewall membuka port 22, 80, dan 443.
============================================================
EOF
