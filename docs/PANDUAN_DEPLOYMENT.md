# Panduan Deployment — Aisha Monitor

Aplikasi berjalan dalam **dua fase** di satu VPS Tencent Cloud Lighthouse (2 vCPU, RAM 4 GB, Ubuntu Server 24.04):

| Fase | Alamat | Stack Docker | Dipakai untuk |
|---|---|---|---|
| **Test** | `https://test.domain-anda` | `aisha-test` | Uji black box, demo inkremen, mencari bug |
| **Utama** | `https://domain-anda` | `aisha-prod` | Pemakaian perusahaan, UAT, dan SUS |

Kedua fase memiliki basis data, Redis, dan berkas unggahan masing-masing, sehingga data uji tidak pernah tercampur dengan data utama.

```
Internet ──► Caddy (aisha-edge, HTTPS Let's Encrypt)
               ├── test.domain-anda ──► aisha-test: Nginx → PHP-FPM, queue, scheduler, MySQL, Redis, Gotenberg
               └── domain-anda      ──► aisha-prod: Nginx → PHP-FPM, queue, scheduler, MySQL, Redis, Gotenberg

Actions → Deploy → Run workflow   : CI + uji Docker → build image sha-<commit> → fase test
git tag v0.N.0 pada commit itu    : CI → image sha-<commit> yang SAMA → fase utama
```

Fase utama hanya menerima image yang sebelumnya sudah dibuat untuk fase test. Bila tag dibuat pada commit yang belum pernah di-deploy ke fase test, workflow berhenti dengan pesan galat.

## 1. Persiapan sekali di VPS

Jalankan sebagai pengguna dengan `sudo`. Docker Engine dan Docker Compose sudah terpasang (PRD §8).

### 1.1 DNS

Di pengelola domain, buat dua record `A` yang mengarah ke IP publik VPS:

| Nama | Tipe | Nilai |
|---|---|---|
| `test` | A | IP VPS |
| `@` (domain utama) | A | IP VPS |

Pastikan firewall konsol Tencent membuka port 22, 80, dan 443 (TCP), serta 443 UDP bila ingin HTTP/3.

### 1.2 Pengguna deploy dan kunci SSH

Di VPS:

```sh
sudo adduser --disabled-password --gecos "" deploy
sudo usermod -aG docker deploy
sudo mkdir -p /opt/aisha/edge /opt/aisha/test /opt/aisha/prod
sudo chown -R deploy:deploy /opt/aisha
```

Di laptop, buat pasangan kunci khusus GitHub Actions (tanpa passphrase):

```sh
ssh-keygen -t ed25519 -f aisha-deploy -C "github-actions-aisha" -N ""
```

Salin isi `aisha-deploy.pub` ke VPS:

```sh
sudo -u deploy mkdir -p /home/deploy/.ssh
sudo -u deploy tee -a /home/deploy/.ssh/authorized_keys < aisha-deploy.pub
sudo chmod 700 /home/deploy/.ssh && sudo chmod 600 /home/deploy/.ssh/authorized_keys
```

Berkas `aisha-deploy` (kunci privat) hanya disimpan di GitHub Secrets (§2), lalu hapus dari laptop. Jangan kirim lewat chat.

### 1.3 Gerbang HTTPS (Caddy)

Salin `docker/edge/compose.yaml`, `docker/edge/Caddyfile`, dan `docker/edge/edge.env.example` dari repository ke `/opt/aisha/edge/`, lalu:

```sh
cd /opt/aisha/edge
cp edge.env.example .env
nano .env                     # isi ACME_EMAIL, TEST_DOMAIN, PROD_DOMAIN
docker compose up -d
```

Caddy otomatis meminta sertifikat Let's Encrypt saat domain pertama kali diakses. Sebelum stack aplikasi di-deploy, domain akan menampilkan galat 502; itu normal.

### 1.4 Berkas `.env` tiap fase

Salin `docker/deploy/server.env.example` ke `/opt/aisha/test/.env` dan `/opt/aisha/prod/.env`, lalu isi:

| Variabel | Fase test | Fase utama |
|---|---|---|
| `APP_ENV` | `staging` | `production` |
| `APP_URL` | `https://test.domain-anda` | `https://domain-anda` |
| `EDGE_ALIAS` | `aisha-test-web` | `aisha-prod-web` |
| `MYSQL_BUFFER_POOL_SIZE` | `128M` | `256M` |
| `APP_KEY`, `DB_PASSWORD`, `DB_ROOT_PASSWORD` | Buat **berbeda** untuk tiap fase | Buat **berbeda** untuk tiap fase |

Nilai acak dapat dibuat dengan:

```sh
echo "base64:$(openssl rand -base64 32)"   # APP_KEY
openssl rand -hex 24                       # DB_PASSWORD dan DB_ROOT_PASSWORD
```

Lindungi berkasnya: `chmod 600 /opt/aisha/test/.env /opt/aisha/prod/.env`.

## 2. Pengaturan sekali di GitHub

### 2.1 Secrets repository

**Settings → Secrets and variables → Actions → New repository secret**:

| Nama | Isi |
|---|---|
| `DEPLOY_HOST` | IP publik VPS |
| `DEPLOY_USER` | `deploy` |
| `DEPLOY_SSH_KEY` | Seluruh isi berkas kunci privat `aisha-deploy` |
| `DEPLOY_KNOWN_HOSTS` | Hasil `ssh-keyscan -t ed25519 <IP VPS>` dari laptop |

### 2.2 Environment

**Settings → Environments → New environment**, buat dua environment:

| Environment | Variable `APP_URL` | Pengaturan tambahan |
|---|---|---|
| `test` | `https://test.domain-anda` | Tidak ada |
| `utama` | `https://domain-anda` | **Deployment branches and tags**: pilih *Selected branches and tags*, tambahkan pola tag `v*`. Opsional: **Required reviewers** agar deploy utama harus disetujui dulu. |

Image disimpan di GitHub Container Registry (`ghcr.io`) dan dibuat otomatis oleh workflow. Server login memakai token sementara dari workflow, jadi tidak ada kredensial registry permanen di VPS.

## 3. Deploy ke fase test

Dilakukan saat inkremen masuk tahap uji (panduan git §6.1):

1. Buka tab **Actions → Deploy → Run workflow**, pilih branch `main`, lalu **Run workflow**.
2. Workflow menjalankan CI (± 3 menit), uji Docker (± 8 menit), build image, lalu deploy ke fase test. Sebelum migrasi, basis data dicadangkan otomatis ke `/opt/aisha/test/backups/`.
3. Setelah hijau, buka `https://test.domain-anda` dan lakukan uji black box atau demo.
4. Bila ada bug, perbaiki lewat branch `fix/...`, merge ke `main`, lalu ulangi langkah 1.

Pada deploy pertama, buat akun admin awal (kata sandi diminta secara interaktif):

```sh
ssh deploy@<IP VPS>
cd /opt/aisha/test
export APP_IMAGE_TAG=$(cat .deployed-tag)
docker compose -p aisha-test -f compose.yaml -f compose.server.yaml exec app php artisan app:create-admin admin@perusahaan.test --name="Admin Aisha"
```

## 4. Deploy ke fase utama

Dilakukan setelah fase test dinyatakan bebas bug untuk commit tersebut:

1. Pastikan commit terakhir di `main` adalah commit yang terakhir di-deploy dan diuji di fase test.
2. Buat tag rilis pada commit itu:

   ```sh
   git switch main
   git pull origin main
   git tag -a v0.1.0 -m "Inkremen 1: akses, audit, template"
   git push origin v0.1.0
   ```

3. Workflow **Deploy** berjalan otomatis: CI, lalu deploy image yang sama ke fase utama. Bila **Required reviewers** diaktifkan, setujui deployment di halaman run tersebut.
4. Pada deploy utama pertama, buat akun admin seperti §3, tetapi di `/opt/aisha/prod` dengan `-p aisha-prod`.

Bila workflow berhenti dengan pesan *"belum pernah di-deploy ke fase test"*, artinya tag dibuat pada commit yang belum diuji. Hapus tag yang salah (`git push origin :refs/tags/v0.1.0` lalu `git tag -d v0.1.0`), deploy commit itu ke fase test terlebih dahulu, lalu buat tag ulang.

## 5. Operasi di server

Semua perintah berikut dijalankan sebagai pengguna `deploy` di folder fase terkait (`/opt/aisha/test` atau `/opt/aisha/prod`), dengan menyesuaikan `-p aisha-test` atau `-p aisha-prod`:

```sh
export APP_IMAGE_TAG=$(cat .deployed-tag)
alias dc='docker compose -p aisha-prod -f compose.yaml -f compose.server.yaml'

dc ps                                 # status layanan
dc logs --tail=100 app queue          # log aplikasi dan antrean
dc exec app php artisan about         # ringkasan konfigurasi
cat deploy-history.log                # riwayat image yang pernah di-deploy
ls -lh backups/                       # cadangan basis data sebelum setiap deploy (10 terakhir)
```

### 5.1 Rollback

Bila fase utama bermasalah setelah deploy, kembali ke image sebelumnya yang tercatat di `deploy-history.log`. Login GHCR memerlukan token GitHub milik Anda dengan izin `read:packages`:

```sh
docker login ghcr.io -u <username-github>          # tempel token saat diminta
bash remote-deploy.sh prod sha-<commit-sebelumnya>
docker logout ghcr.io
```

Bila migrasi versi baru sudah mengubah basis data dan tidak cocok dengan versi lama, pulihkan cadangan sebelum deploy terakhir:

```sh
gunzip -c backups/sebelum-deploy-<waktu>.sql.gz \
  | dc exec -T mysql sh -c 'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'
```

Lalu perbaiki bug lewat alur biasa: branch `fix/...`, deploy ke fase test, uji, lalu tag patch (misalnya `v0.1.1`).

## 6. Yang belum tercakup

- **Cadangan di lokasi terpisah (NFR-09).** Cadangan saat ini masih berada di VPS yang sama. Perlu disalin rutin ke penyimpanan lain.
- **Akun basis data terbatas untuk log audit (NFR-10).** Saat ini log audit dilindungi trigger. Akun dengan hak hanya INSERT dan SELECT pada `activity_log` belum dipisahkan dari akun migrasi.
- **Pemantauan ketersediaan (NFR-08).** Uptime Kuma belum dipasang.
