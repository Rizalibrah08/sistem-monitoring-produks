#!/usr/bin/env bash
# Dijalankan di VPS oleh workflow Deploy:
#   bash /opt/aisha/<stack>/remote-deploy.sh <test|prod> <tag-image>
# Urutan: tarik image, nyalakan layanan data, cadangkan basis data,
# migrasi, lalu ganti container aplikasi dan tunggu healthcheck.
set -euo pipefail

STACK="${1:?Gunakan: remote-deploy.sh <test|prod> <tag-image>}"
export APP_IMAGE_TAG="${2:?Tag image wajib diisi}"

case "$STACK" in
    test | prod) ;;
    *) echo "Stack harus test atau prod, bukan '$STACK'." >&2; exit 1 ;;
esac

cd "/opt/aisha/$STACK"

if [ ! -f .env ]; then
    echo "Berkas /opt/aisha/$STACK/.env belum ada. Lihat docs/PANDUAN_DEPLOYMENT.md." >&2
    exit 1
fi

compose() {
    docker compose -p "aisha-$STACK" -f compose.yaml -f compose.server.yaml "$@"
}

echo "==> Menarik image $APP_IMAGE_TAG"
compose pull app web

echo "==> Menyalakan MySQL, Redis, dan Gotenberg"
compose up -d --no-build --wait --wait-timeout 180 mysql redis gotenberg

echo "==> Mencadangkan basis data sebelum migrasi (NFR-09)"
mkdir -p backups
backup="backups/sebelum-deploy-$(date +%Y%m%d-%H%M%S).sql.gz"
# shellcheck disable=SC2016 # variabel MYSQL_* diekspansi di dalam container MySQL
compose exec -T mysql sh -c 'exec mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines --triggers "$MYSQL_DATABASE" 2>/dev/null' | gzip > "$backup"
echo "    $backup"
# Simpan 10 cadangan terbaru; nama berkas dibuat skrip ini sehingga aman diurutkan dengan ls.
# shellcheck disable=SC2012
ls -1t backups/sebelum-deploy-*.sql.gz | tail -n +11 | xargs -r rm --

echo "==> Menjalankan migrasi"
compose run --rm --no-deps app php artisan migrate --force --no-interaction

echo "==> Menyelaraskan role dan hak akses"
compose run --rm --no-deps app php artisan db:seed --class=RolesAndPermissionsSeeder --force --no-interaction

echo "==> Mengganti container aplikasi"
compose up -d --no-build --wait --wait-timeout 180 --remove-orphans

docker image prune -f > /dev/null

# Dipakai perintah manual di server (lihat panduan) dan untuk rollback.
echo "$APP_IMAGE_TAG" > .deployed-tag
echo "$(date -Is) $APP_IMAGE_TAG" >> deploy-history.log
echo "==> Deploy aisha-$STACK selesai dengan image $APP_IMAGE_TAG"
