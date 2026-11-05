# Panduan Git Branching — Aisha Monitor

Panduan ini mengatur cara tim (Rizal sebagai Web Developer, Adit sebagai System Analyst/DevOps/QA) bekerja di repository `Rizalibrah08/sistem-monitoring-produks`. Modelnya **trunk-based**: hanya satu branch permanen, yaitu `main`. Setiap pekerjaan dikerjakan di branch pendek lalu digabung ke `main` lewat Pull Request. CI tidak berjalan di setiap PR; CI dijalankan saat masuk tahap uji dan saat deploy (lihat §6). Rilis setiap inkremen PRD v1.3 §8 ditandai dengan tag.

```
feature/...    ●─●     ●─●─●        ●─●
              /   \   /     \      /   \
main  ●──────●─────●─●───────●────●─────●──────►
                   │                    │
               uji: CI → staging    uji: CI → staging
                 v0.1.0               v0.2.0        tag: CI → produksi
```

## 1. Branch `main`

| Branch | Fungsi | Deploy ke | Cara perubahan masuk |
|---|---|---|---|
| `main` | Satu-satunya sumber kebenaran | Staging saat tahap uji dimulai; produksi pada setiap tag rilis | Lewat Pull Request; pembuat PR boleh merge sendiri setelah pemeriksaan lokal lulus |

Aturan `main`:

- Tidak boleh commit atau push langsung; semua perubahan masuk lewat Pull Request (PR).
- Tidak boleh `git push --force`.
- Tidak boleh dihapus.
- Kode di `main` harus selalu bisa dijalankan. Fitur yang belum selesai boleh masuk bila tidak merusak alur yang ada, misalnya menunya belum ditampilkan.

## 2. Branch kerja

Format nama: `<tipe>/inc<N>-<kode>-<deskripsi-singkat>`

- `<tipe>` diambil dari tabel di bawah.
- `inc<N>` adalah nomor inkremen PRD (0–8).
- `<kode>` adalah kode fitur PRD (`F03`) atau kebutuhan SRS (`FR-31`). Boleh dihilangkan untuk pekerjaan umum.
- `<deskripsi-singkat>` ditulis dengan huruf kecil dan tanda hubung, tanpa spasi, maksimal sekitar 5 kata.

Semua branch kerja dibuat dari `main` dan digabung kembali ke `main`.

| Tipe | Dipakai untuk | Contoh |
|---|---|---|
| `feature/` | Fitur baru sesuai PRD/SRS | `feature/inc2-F03-buat-tiket-checklist` |
| `fix/` | Perbaikan bug, termasuk bug mendesak di produksi | `fix/inc3-FR-78-gerbang-mou` |
| `infra/` | Docker, CI/CD, server, backup | `infra/inc0-docker-compose` |
| `test/` | Menambah tes atau skenario uji saja | `test/inc6-topsis-12-skenario` |
| `docs/` | Dokumentasi, PRD, SRS, panduan | `docs/panduan-git-branching` |
| `refactor/` | Merapikan kode tanpa mengubah perilaku | `refactor/inc1-audit-logger` |

Satu branch berisi satu pekerjaan yang dapat diselesaikan dalam 1–3 hari. Makin pendek umur branch, makin kecil konflik. Fitur besar dipecah, misalnya F10 menjadi `feature/inc6-F10-hitung-kriteria` lalu `feature/inc6-F10-antrean-topsis`.

## 3. Pemetaan inkremen

| Inkremen | Isi (PRD §8) | Contoh branch | Tag rilis |
|---|---|---|---|
| 0 | Fondasi: Docker, CI, staging | `infra/inc0-docker-compose`, `infra/inc0-github-actions` | `v0.0.0` |
| 1 | Akses, audit, template (F01, F02, F14, F26) | `feature/inc1-F02-master-data`, `feature/inc1-F26-template-word` | `v0.1.0` |
| 2 | Tiket, dokumen, validasi (F03–F05, F13, F19, F27) | `feature/inc2-F04-unggah-versi` | `v0.2.0` |
| 3 | Kontrak dan pembayaran (F28–F30, gerbang MoU) | `feature/inc3-F28-kontrak-template` | `v0.3.0` |
| 4 | Verifikasi dan BPOM (F06–F08, F37, F38) | `feature/inc4-F38-tenggat-bpom` | `v0.4.0` |
| 5 | Komunikasi dan SLA (F09, F11, F12, F16) | `feature/inc5-F16-chatbot` | `v0.5.0` |
| 6 | Prioritas dan pemantauan (F10, F15) | `feature/inc6-F10-antrean-topsis` | `v0.6.0` |
| 7 | Pelengkap (F17, F18, fitur Could) | `feature/inc7-F18-ekspor-log` | `v0.7.0` |
| 8 | Evaluasi: UAT, SUS, perbaikan | `fix/inc8-...`, `test/inc8-...` | `v1.0.0` (rilis tugas akhir) |

Perbaikan setelah rilis dinaikkan versi patch-nya: `v0.3.1`, `v1.0.1`.

## 4. Alur kerja harian

### 4.1 Mulai pekerjaan

```sh
git switch main
git pull origin main
git switch -c feature/inc2-F03-buat-tiket-checklist
```

### 4.2 Selama bekerja

```sh
git add <file-yang-diubah>
git commit -m "feat(tiket): susun checklist dua fase saat tiket dibuat"
git push -u origin feature/inc2-F03-buat-tiket-checklist   # push pertama
git push                                                     # push berikutnya
```

CI tidak berjalan saat push maupun saat PR. Karena itu, **pemeriksaan lokal berikut wajib dijalankan sebelum membuka PR**. Inilah satu-satunya penjaga sebelum perubahan masuk `main`:

```sh
vendor/bin/pint --dirty --format agent
php artisan test --compact
npm run typecheck
npm run test
```

### 4.3 Menyamakan dengan main

Bila `main` sudah maju selama Anda bekerja:

```sh
git fetch origin
git rebase origin/main             # selesaikan konflik bila ada, lalu: git rebase --continue
git push --force-with-lease        # aman: hanya untuk branch milik sendiri
```

`--force-with-lease` hanya boleh dipakai di branch kerja milik sendiri, tidak pernah di `main`.

### 4.4 Membuka dan menggabungkan Pull Request

1. Buka GitHub, lalu klik **Compare & pull request**. Base: `main`; compare: branch Anda.
2. Judul PR mengikuti format commit, misalnya `feat(tiket): buat tiket dan checklist dua fase (F03)`.
3. Isi template PR, termasuk checklist Definition of Done.
4. Pastikan pemeriksaan lokal §4.2 lulus, lalu centang butir terkait di template PR.
5. Merge dengan **Squash and merge**. Pembuat PR boleh merge sendiri tanpa menunggu review; tim berdua cukup saling memberi tahu lewat komentar PR atau chat. Review tetap dianjurkan untuk perubahan besar, seperti skema basis data, perhitungan TOPSIS, atau alur gerbang fase.
6. Hapus branch setelah merge (otomatis bila pengaturan §8 aktif), lalu bersihkan lokal:

```sh
git switch main
git pull origin main
git branch -d feature/inc2-F03-buat-tiket-checklist
```

## 5. Format pesan commit

Gunakan [Conventional Commits](https://www.conventionalcommits.org/id/v1.0.0/) dengan isi berbahasa Indonesia:

```
<tipe>(<lingkup>): <ringkasan, huruf kecil, tanpa titik>

<penjelasan opsional: apa dan mengapa>

Refs: F03, FR-11
```

| Tipe | Arti |
|---|---|
| `feat` | Fitur baru |
| `fix` | Perbaikan bug |
| `test` | Menambah atau memperbaiki tes |
| `docs` | Dokumentasi |
| `refactor` | Perubahan struktur tanpa mengubah perilaku |
| `style` | Format kode (Pint, Prettier) |
| `chore` | Dependensi, konfigurasi kecil |
| `ci` | GitHub Actions |
| `build` | Docker, Vite, build |

Lingkup yang disarankan: `auth`, `pengguna`, `master`, `audit`, `tiket`, `dokumen`, `validasi`, `template`, `kontrak`, `tagihan`, `bpom`, `sla`, `topsis`, `whatsapp`, `chatbot`, `dasbor`, `infra`.

Contoh:

```
feat(kontrak): buka pra-registrasi setelah MoU ditandatangani
fix(topsis): normalisasi kriteria cost bernilai nol
test(sla): hari kerja melewati libur nasional
ci: jalankan php artisan test dan vitest pada pull request
docs(srs): perbarui daftar pustaka ke PHPUnit
```

Karena PR digabung dengan squash, judul PR menjadi pesan commit di `main`. Pastikan judul PR mengikuti format di atas. Hindari pesan seperti `update`, `fix`, `wip`, atau `perbaikan`. Riwayat commit adalah bagian dari bukti pengerjaan tugas akhir.

## 6. Uji dan rilis per inkremen

CI hanya berjalan di dua titik berikut. Hasilnya terlihat di tab **Actions**; bila ada job yang merah, cuplikan galatnya tampil sebagai anotasi pada halaman run tersebut.

| Workflow | Isi | Perkiraan waktu |
|---|---|---|
| **CI** | `Backend (sqlite)`, `Backend (mysql)`, `Frontend` | ± 3 menit |
| **Docker** | `Build dan uji asap Compose` | ± 8 menit |

### 6.1 Masuk tahap uji

Dilakukan saat fitur inkremen sudah masuk `main` dan siap diuji black box atau didemokan:

1. Buka tab **Actions**, pilih workflow **CI**, klik **Run workflow**, pilih branch `main`, lalu **Run workflow**.
2. Lakukan hal yang sama untuk workflow **Docker**.
3. Bila keduanya hijau, perbarui server staging dan lanjutkan uji black box atau demo.
4. Bila ada yang merah, perbaiki lewat branch `fix/...`, merge, lalu jalankan ulang langkah 1–2.

### 6.2 Rilis dan deploy produksi

Dilakukan Adit setelah semua butir Definition of Done inkremen terpenuhi (PRD §8):

1. Pastikan uji di staging (§6.1) sudah selesai untuk commit terakhir di `main`.
2. Beri tahu anggota lain bahwa inkremen akan dirilis. Tag menjadi bukti rilis dan memicu deployment produksi.
3. Buat tag beranotasi pada `main`:

```sh
git switch main
git pull origin main
git tag -a v0.2.0 -m "Inkremen 2: tiket, dokumen, validasi"
git push origin v0.2.0
```

4. Push tag otomatis menjalankan workflow **CI** dan **Docker**. Deployment produksi hanya boleh dilakukan bila keduanya hijau untuk tag tersebut.
5. Di GitHub, buka **Releases → Draft a new release**, pilih tag tersebut, lalu klik **Generate release notes**. Lengkapi dengan daftar fitur/FR yang selesai, hasil black box, dan catatan masukan demo.

## 7. Perbaikan mendesak di produksi

Tidak ada branch khusus hotfix. Bug produksi diperbaiki seperti pekerjaan biasa, hanya diprioritaskan:

```sh
git switch main
git pull origin main
git switch -c fix/login-terkunci-permanen
# perbaiki, tambahkan tes yang membuktikan bug sudah hilang, commit
git push -u origin fix/login-terkunci-permanen
```

1. Jalankan pemeriksaan lokal §4.2, buka PR ke `main`, beri tahu anggota lain, lalu merge.
2. Jalankan CI dan Docker secara manual (§6.1), lalu periksa perbaikan di staging.
3. Beri tag patch, misalnya `v1.0.1`, agar perbaikan ter-deploy ke produksi.

## 8. Pengaturan proteksi branch di GitHub

Karena CI tidak memeriksa setiap PR, proteksi ini menjadi pengaman agar `main` tidak berubah tanpa PR. Dilakukan sekali oleh anggota yang memiliki akses admin repository.

Buka **Settings → Rules → Rulesets → New branch ruleset**, beri nama `main`, pilih target **Include default branch**, lalu aktifkan:

- **Restrict deletions**.
- **Require a pull request before merging**, dengan **Required approvals: 0**. Tim berdua dapat merge sendiri.
- Jangan aktifkan **Require status checks to pass**. CI tidak berjalan di PR, sehingga PR akan tertahan menunggu selamanya.
- **Block force pushes**.
- **Require linear history**.

Bypass list dibiarkan kosong agar aturan berlaku juga untuk admin.

Untuk melindungi tag rilis, buat ruleset kedua bertipe **tag ruleset** dengan target `v*`, lalu aktifkan **Restrict deletions**, **Restrict updates**, dan **Block force pushes**.

Lalu di **Settings → General → Pull Requests**:

- Aktifkan hanya **Allow squash merging**; nonaktifkan **Allow merge commits** dan **Allow rebase merging**.
- Pada **Default commit message** untuk squash, pilih **Pull request title and description**.
- Aktifkan **Automatically delete head branches**.

## 9. Hal yang tidak boleh di-commit

- `.env` dan semua kredensial: kata sandi database, token WhatsApp, kunci SSH. Gunakan GitHub **Secrets** untuk CI/CD.
- `vendor/`, `node_modules/`, `public/build/`, dan isi `storage/` (sudah ada di `.gitignore`).
- Data asli perusahaan atau dokumen klien. Gunakan data tiruan sesuai PRD §9.
- Berkas besar hasil ekspor (`.zip`, rekaman layar). Simpan di Google Drive tim.

Bila terlanjur meng-commit rahasia, segera ganti kredensial tersebut. Menghapus commit saja tidak cukup karena riwayat sudah tersebar.

## 10. Ringkasan cepat

| Saya ingin... | Perintah |
|---|---|
| Mulai pekerjaan | `git switch main && git pull && git switch -c feature/incN-FXX-nama` |
| Simpan pekerjaan | `git add <file> && git commit -m "feat(lingkup): ringkasan"` |
| Kirim ke GitHub | `git push -u origin <branch>` |
| Ikut perubahan terbaru | `git fetch origin && git rebase origin/main` |
| Lihat status | `git status` dan `git log --oneline --graph -15` |
| Batalkan perubahan file yang belum di-commit | `git restore <file>` |
| Hapus branch lokal setelah merge | `git branch -d <branch>` |
| Rilis inkremen | `git tag -a v0.N.0 -m "Inkremen N: ..."` lalu `git push origin v0.N.0` |
