# Aisha Monitor

Sistem Monitoring Pendaftaran Produk untuk PT Aisha Zahra Herbalaboratories: tiket per produk, checklist dokumen dua fase, kontrak dan pembayaran, pemantauan SLA, antrean prioritas AHP-TOPSIS, dan integrasi WhatsApp. Acuan kebutuhan adalah PRD dan SRS versi 1.3 di `docs/`.

Dibangun dengan Laravel 13 (PHP 8.5), Inertia 3, React 19, Tailwind CSS 4, dan MySQL 8.4.

> **Status (5 November 2026):** Inkremen 0 dan 1 selesai, Inkremen 2 berjalan. Aplikasi belum siap untuk data produksi.

## Progres per inkremen

| Inkremen | Isi (PRD §8) | Status |
|---|---|---|
| 0 | Fondasi: Docker Compose, CI, deploy dua fase | Selesai; belum di-deploy ke VPS karena domain belum tersedia |
| 1 | Login dan hak akses, master data, log audit, template Word | Selesai |
| 2 | Tiket, dokumen, validasi | Berjalan |
| 3 | Kontrak (MoU), tagihan, pembayaran, Finance | Belum; Midtrans menunggu persetujuan perubahan PRD/SRS v1.4 |
| 4 | Verifikasi, gerbang fase, BPOM, uji laboratorium, tambahan data, NIE | Belum |
| 5 | SLA hari kerja, eskalasi, WhatsApp (notifikasi, chatbot, inbox) | Belum; butuh nomor dan gateway WhatsApp |
| 6 | Antrean prioritas AHP-TOPSIS, dasbor, laporan durasi | Belum; bobot AHP sudah tersedia di master data |
| 7 | Pelengkap: ekspor log, lampiran chat, fitur *Could* | Belum |
| 8 | UAT, SUS, uji ketepatan perhitungan, deploy akhir | Belum |

## Fitur yang sudah berjalan

**Akses dan audit**
- Login berbasis sesi untuk tujuh role internal. Akun nonaktif ditolak, lima kali gagal mengunci akun 15 menit, dan sesi berakhir setelah 60 menit tidak aktif.
- Hak akses diperiksa di server per role; menu hanya menampilkan halaman yang boleh dibuka.
- Log audit hanya bisa ditambah (trigger database) dan berantai hash SHA-256, dengan filter per tiket, pengguna, aksi, dan tanggal.

**Master data (Admin)**
- Pengguna dan role, termasuk jadwal cuti.
- Sediaan, kategori, bahan baku (R&D juga dapat menambah), dan hari libur.
- Tahapan dan SLA hari kerja, dengan 12 tahap bawaan sesuai BR-08.
- Jenis dokumen: fase, cakupan, sifat, PIC termasuk klien, verifikator, masa berlaku, dan umur maksimal.
- Aturan dokumen kondisional per sediaan dan kategori dengan tanggal mulai berlaku (Admin dan RA).
- Bobot dan jenis kriteria AHP per antrean, dengan bobot bawaan sesuai BR-25.
- Templat Word berversi; penanda isian yang tidak dikenal ditolak.

**Operasional**
- Data klien dengan nomor WhatsApp unik yang dinormalkan ke format `62…` (Marketing dan Manajer).
- Pembuatan tiket berkode `PRD-TAHUN-NOMOR`, dengan checklist dua fase yang disusun otomatis dari dokumen wajib dan aturan kondisional yang berlaku.
- Daftar dan detail tiket: tahapan, checklist per fase dengan progres, dan riwayat. Marketing hanya melihat tiket kliennya.
- Unggah dokumen berversi (PDF, JPG, PNG, DOCX, XLSX, maksimal 20 MB) dengan validasi otomatis umur dan masa berlaku. Dokumen klien dan perusahaan disimpan sebagai dokumen bersama, dan unduhan dibatasi untuk staf yang berhak melihat tiketnya.

Modul yang belum dibangun tetap tampil di menu sesuai role dengan keterangan "belum tersedia", tanpa data demo.

## Menjalankan secara lokal

### Prasyarat

- PHP 8.5 dengan ekstensi `intl`, `zip`, `bcmath`, `pdo_mysql`, dan `pdo_sqlite`, serta Composer
- Node.js 24 dan npm
- MySQL 8.4 atau MariaDB yang mendukung trigger
- Docker Desktop (opsional, untuk menjalankan seluruh layanan atau tes bila PHP lokal lebih lama dari 8.5)

### Langkah

1. Pasang dependensi dan siapkan `.env`:

   ```sh
   composer install
   npm ci
   cp .env.example .env          # PowerShell: Copy-Item .env.example .env
   php artisan key:generate
   ```

2. Atur koneksi basis data pengembangan di `.env` (`DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`). Untuk MariaDB bawaan XAMPP gunakan `DB_CONNECTION=mariadb`. Jangan memakai kredensial produksi.

3. Buat tabel dan data bawaan (role, tahapan, kriteria AHP), lalu buat admin awal. Kata sandi diminta secara interaktif:

   ```sh
   php artisan migrate --no-interaction
   php artisan db:seed --no-interaction
   php artisan app:create-admin admin@perusahaan.test --name="Admin Aisha"
   ```

4. Build dan jalankan:

   ```sh
   npm run build
   php artisan serve --host=127.0.0.1 --port=8000
   ```

   Buka `http://127.0.0.1:8000/login`. Untuk *hot reload*, jalankan `npm run dev` di terminal terpisah.

### Dengan Docker Compose

Seluruh layanan (aplikasi, Nginx, MySQL, Redis, queue, scheduler, Gotenberg) dapat dijalankan dengan `compose.yaml`. Isi `DB_PASSWORD`, `DB_ROOT_PASSWORD`, dan `HTTP_PORT` di `.env`, lalu:

```sh
docker compose up -d --build
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force
docker compose exec app php artisan app:create-admin admin@perusahaan.test --name="Admin Aisha"
```

## Pengujian

Jalankan pemeriksaan berikut sebelum membuka Pull Request. CI tidak berjalan otomatis pada PR, hanya saat deploy.

```sh
php artisan test --compact
vendor/bin/pint --test
npm run test
npm run typecheck
npm run build
```

Tes PHP memakai SQLite in-memory dan tidak menyentuh basis data pengembangan. Bila PHP lokal belum 8.5, jalankan tes di container `php:8.5-cli`. Uji E2E Playwright tersedia lewat `npm run test:e2e` (perlu `npx playwright install chromium`).

## Dokumentasi

| Dokumen | Isi |
|---|---|
| `docs/PRD_Sistem_Monitoring_Pendaftaran_Produk_v1.3.docx` | Kebutuhan produk, fitur, user story, tahapan rilis |
| `docs/SRS_Sistem_Monitoring_Pendaftaran_Produk_v1.3.docx` | Kebutuhan fungsional dan nonfungsional, aturan bisnis, rancangan |
| [`docs/PANDUAN_GIT_BRANCHING.md`](docs/PANDUAN_GIT_BRANCHING.md) | Alur kerja *trunk-based*, penamaan branch, format commit, rilis |
| [`docs/PANDUAN_DEPLOYMENT.md`](docs/PANDUAN_DEPLOYMENT.md) | Persiapan VPS, fase test dan fase utama, rollback |

## Preview UI

Prototipe antarmuka lama dengan data demo tersedia di `/preview` (misalnya `/preview/tickets?role=marketing`) hanya pada lingkungan `local` dan `testing`. Preview tidak membaca atau menulis data operasional dan bukan alur aplikasi utama.

## Batasan saat ini

- Belum ada integrasi WhatsApp, pembayaran, SLA terjadwal, verifikasi dokumen oleh QA/RA, maupun pengajuan BPOM.
- Pencocokan nama produk pada PDF (bagian dari FR-71) belum tersedia.
- Daftar sediaan, kategori, dan dokumen per sediaan masih menunggu konfirmasi Regulatory Affairs, sehingga master data perlu diisi melalui aplikasi.
- Pemisahan akun basis data dengan hak INSERT dan SELECT saja untuk log audit (NFR-10), backup di lokasi terpisah (NFR-09), dan pemantauan ketersediaan (NFR-08) belum disiapkan.
