# Aisha Monitor

Sistem Monitoring Pendaftaran Produk untuk PT Aisha Zahra Herbalaboratories: tiket per produk, checklist dokumen dua fase, kontrak dan pembayaran, pemantauan SLA, antrean prioritas AHP-TOPSIS, dan integrasi WhatsApp. Acuan kebutuhan adalah PRD dan SRS versi 1.3 di `docs/`.

Dibangun dengan Laravel 13 (PHP 8.5), Inertia 3, React 19, Tailwind CSS 4, dan MySQL 8.4.

> **Status (6 November 2026):** Inkremen 0–4, 6, dan 7 selesai; Inkremen 5 berjalan (SLA dan notifikasi aplikasi selesai, WhatsApp menunggu gateway). Aplikasi belum siap untuk data produksi.

## Progres per inkremen

| Inkremen | Isi (PRD §8) | Status |
|---|---|---|
| 0 | Fondasi: Docker Compose, CI, deploy dua fase | Selesai; belum di-deploy ke VPS karena domain belum tersedia |
| 1 | Login dan hak akses, master data, log audit, template Word | Selesai |
| 2 | Tiket, dokumen, validasi | Selesai; pencocokan nama produk pada PDF menunggu persetujuan dependensi, notifikasi ke RA menunggu Inkremen 5 |
| 3 | Kontrak (MoU), tagihan, pembayaran, Finance | Selesai; pembayaran lewat Midtrans Snap, pengingat jatuh tempo via WhatsApp menunggu Inkremen 5 |
| 4 | Verifikasi, gerbang fase, BPOM, uji laboratorium, tambahan data, NIE | Selesai; pengingat tenggat BPOM dan notifikasi ke PIC/klien menunggu Inkremen 5 |
| 5 | SLA hari kerja, eskalasi, WhatsApp (notifikasi, chatbot, inbox) | Berjalan; SLA, eskalasi, dan pusat notifikasi selesai; WhatsApp butuh nomor dan gateway |
| 6 | Antrean prioritas AHP-TOPSIS, dasbor, laporan durasi | Selesai; angka percakapan dan prospek di dasbor menunggu WhatsApp |
| 7 | Pelengkap: ekspor log, lampiran chat, fitur *Could* | Selesai; lampiran chat dan ringkasan notifikasi per klien menunggu WhatsApp |
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
- Unggah dokumen berversi (PDF, JPG, PNG, DOCX, XLSX, maksimal 20 MB) dengan validasi otomatis umur dan masa berlaku. Dokumen klien, bahan baku, dan perusahaan disimpan sebagai dokumen bersama, dan unduhan dibatasi untuk staf yang berhak melihat tiketnya.
- Dokumen bersama yang masih berlaku langsung tertaut ke tiket baru.
- R&D memilih bahan baku tiket dari master; dokumen asal peredaran bahan yang ada langsung tertaut, bahan tanpa dokumen menjadi butir checklist.
- Persetujuan dan revisi sampel oleh Marketing dengan nomor revisi; sampel disetujui memindahkan tiket ke tahap Kontrak (MoU).
- Tautan unggah klien bertoken per tiket dan fase (bawaan 14 hari) di `/unggah/{token}`, tanpa login dan hanya untuk dokumen bagian klien.
- Tiket aktif ditandai perlu ditinjau saat dokumen bersama yang dipakainya mendapat versi baru atau kedaluwarsa dalam 30 hari (command harian `documents:flag-expiring`); RA menandai sudah ditinjau.

**Kontrak dan keuangan**
- Kontrak (MoU) dari templat Word dengan nilai jasa dan termin DP bawaan 50%; berkas Word dan PDF (Gotenberg) dibuat otomatis.
- Klien mengunduh dan mengunggah kontrak bertanda tangan lewat tautan; konfirmasi Marketing mengaktifkan kontrak, membuka fase pra-registrasi, dan menerbitkan tagihan DP.
- Pembayaran tagihan lewat Midtrans Snap dari halaman tautan klien; notifikasi Midtrans yang terverifikasi melunasi tagihan otomatis.
- Daftar tagihan dan laporan piutang per klien dengan ekspor CSV untuk Finance dan Manajer.

**Verifikasi dan BPOM**
- Verifikasi dua tingkat: QA/QC memeriksa isi dokumen mutu, RA menyetujui atau mengembalikan pemakaiannya; pengembalian membuka tahap perbaikan dokumen.
- Pengajuan BPOM per fase dengan PNBP dan penguncian versi dokumen yang diajukan; hasil pra-registrasi menetapkan tenggat registrasi 30 hari kerja dan pra-registrasi yang lewat tenggat otomatis hangus.
- Tambahan data BPOM TD1–TD3 bertenggat 60 hari kerja, dengan peringatan risiko penolakan pada TD3.
- Uji laboratorium eksternal sejajar dengan pra-registrasi, termasuk analisis ulang; fase registrasi terbuka setelah hasil pra-registrasi dan LHU yang sesuai tercatat.
- Desain kemasan dan input NIE yang menutup tiket serta menerbitkan tagihan pelunasan.

**SLA dan notifikasi**
- Tenggat setiap tahap dihitung otomatis dalam jam kerja 08.00–16.00 WIB tanpa akhir pekan dan hari libur; tahap yang menunggu BPOM atau pihak luar tidak dihitung.
- `sla:check` (tiap jam) menandai tahap terlambat beserta penyebabnya (staf atau klien) dan mengirim eskalasi ke Manajer atau informasi ke Marketing.
- `notifications:deadlines` (tiap jam) mengingatkan tenggat tahap H-1, tambahan data BPOM H-3/H-1, dan pengajuan registrasi H-10/H-5/H-1.
- Pusat notifikasi (ikon lonceng) untuk dokumen dikembalikan atau menunggu verifikasi, kontrak bertanda tangan, tagihan lunas, NIE, dan tiket perlu ditinjau.

**Prioritas dan pemantauan**
- Antrean prioritas AHP-TOPSIS per role dengan nilai C1–C4, skor, dan alasan urutan; dihitung ulang saat halaman dibuka dan setiap jam (`priorities:compute`).
- Dasbor per role dengan angka langsung dari data dan tugas teratas; Manajer melihat sebaran tiket per fase dan tahap.
- Halaman Tugas dokumen, Daftar eskalasi, dan Laporan durasi per tahap (rata-rata, persentil ke-75, waktu internal, menunggu klien, dan pihak luar) dengan ekspor CSV.

**Pelengkap**
- Ekspor log audit ke CSV dan PDF sesuai filter (tercatat di log), dan verifikasi integritas rantai hash oleh Admin.
- Sinkronisasi checklist tiket aktif dengan jenis dan aturan dokumen terbaru oleh RA.
- Halaman Dokumen bersama (QA/QC dan RA) dengan riwayat versi dan unggah dokumen perusahaan, serta halaman Bahan baku tiket (R&D).
- Pembuatan beberapa tiket sekaligus untuk satu klien.

Modul yang belum dibangun tetap tampil di menu sesuai role dengan keterangan "belum tersedia", tanpa data demo.

## Demo lokal (Docker)

Cara tercepat untuk demo: seluruh layanan berjalan di komputer sendiri dengan `compose.yaml` yang sama seperti di server, ditambah data contoh.

1. Siapkan `.env` dari `.env.example`, lalu isi `APP_ENV=local`, `APP_KEY` (`base64:` diikuti hasil `openssl rand -base64 32`), `APP_URL=http://localhost:8080`, `HTTP_PORT=8080`, serta `DB_PASSWORD` dan `DB_ROOT_PASSWORD` acak.
2. Nyalakan layanan, siapkan basis data, dan isi data demo:

   ```sh
   docker compose up -d --build --wait
   docker compose exec app php artisan migrate --no-interaction
   docker compose exec app php artisan db:seed --class=DemoSeeder --no-interaction
   ```

3. Buka `http://localhost:8080` dan masuk dengan salah satu akun berikut. Kata sandi semua akun: `AishaDemo2026`.

   | Role | Email |
   |---|---|
   | Admin | `admin@aisha.test` |
   | Marketing | `marketing@aisha.test` |
   | R&D | `rnd@aisha.test` |
   | QA/QC | `qa@aisha.test` |
   | Regulatory Affairs | `ra@aisha.test` |
   | Finance | `finance@aisha.test` |
   | Manajer | `manajer@aisha.test` |

Data demo berisi master data contoh, dua klien, dan tiga tiket yang checklist-nya tersusun otomatis. Salah satunya, Sirup Temulawak, memperlihatkan dokumen kondisional untuk sediaan cair. Seeder menolak berjalan di luar lingkungan `local` atau `testing`, sehingga tidak dapat terbawa ke server.

Perintah berguna:

```sh
docker compose logs -f app          # melihat log aplikasi
docker compose down                 # mematikan layanan (data tetap tersimpan)
docker compose down -v              # mematikan layanan dan menghapus seluruh data demo
```

Setelah kode berubah, jalankan ulang `docker compose up -d --build --wait`.

## Menjalankan secara lokal tanpa Docker

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
