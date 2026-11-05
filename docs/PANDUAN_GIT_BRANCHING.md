# Panduan Git Branching — Aisha Monitor

Panduan ini mengatur cara tim (Rizal sebagai Web Developer, Adit sebagai System Analyst/DevOps/QA) bekerja di repository `Rizalibrah08/sistem-monitoring-produks`. Modelnya **GitFlow ringan**: dua branch permanen dan branch sementara per pekerjaan, disesuaikan dengan sembilan inkremen pada PRD v1.3 §8.

```
main      ●───────────────●─────────────────●──────── (produksi, tag per inkremen)
           \             / \               /
develop     ●──●──●──●──●───●──●──●──●──●──●───────── (staging, demo inkremen)
               \  /  \  /       \    /
feature/...     ●●    ●●         ●●●              (pekerjaan harian)
```

## 1. Branch permanen

| Branch | Fungsi | Deploy ke | Siapa yang boleh merge |
|---|---|---|---|
| `main` | Kode yang sudah didemokan dan disetujui; selalu siap dipakai | Server produksi (deployment akhir Inkremen 8) | Lewat Pull Request dari `develop` atau `hotfix/*`, disetujui 1 anggota lain |
| `develop` | Integrasi semua pekerjaan inkremen yang sedang berjalan | Server staging (demo ke pengguna) | Lewat Pull Request dari branch sementara, CI wajib lulus |

Aturan untuk keduanya:

- Tidak boleh commit langsung; semua perubahan masuk lewat Pull Request (PR).
- Tidak boleh `git push --force`.
- Tidak boleh dihapus.

## 2. Branch sementara

Format nama: `<tipe>/inc<N>-<kode>-<deskripsi-singkat>`

- `<tipe>` diambil dari tabel di bawah.
- `inc<N>` adalah nomor inkremen PRD (0–8).
- `<kode>` adalah kode fitur PRD (`F03`) atau kebutuhan SRS (`FR-31`). Boleh dihilangkan untuk pekerjaan umum.
- `<deskripsi-singkat>` ditulis dengan huruf kecil dan tanda hubung, tanpa spasi, maksimal sekitar 5 kata.

| Tipe | Dipakai untuk | Dibuat dari | Digabung ke | Contoh |
|---|---|---|---|---|
| `feature/` | Fitur baru sesuai PRD/SRS | `develop` | `develop` | `feature/inc2-F03-buat-tiket-checklist` |
| `fix/` | Perbaikan bug yang ditemukan di staging | `develop` | `develop` | `fix/inc3-FR-78-gerbang-mou` |
| `infra/` | Docker, CI/CD, server, backup | `develop` | `develop` | `infra/inc0-docker-compose` |
| `test/` | Menambah tes atau skenario uji saja | `develop` | `develop` | `test/inc6-topsis-12-skenario` |
| `docs/` | Dokumentasi, PRD, SRS, panduan | `develop` | `develop` | `docs/panduan-git-branching` |
| `refactor/` | Merapikan kode tanpa mengubah perilaku | `develop` | `develop` | `refactor/inc1-audit-logger` |
| `hotfix/` | Bug mendesak di produksi | `main` | `main` **dan** `develop` | `hotfix/login-terkunci-permanen` |

Satu branch berisi satu pekerjaan yang dapat diselesaikan dalam 1–3 hari. Fitur besar dipecah: misalnya F10 menjadi `feature/inc6-F10-hitung-kriteria` lalu `feature/inc6-F10-antrean-topsis`.

## 3. Pemetaan inkremen

| Inkremen | Isi (PRD §8) | Contoh branch | Tag setelah masuk `main` |
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

Perbaikan kecil setelah rilis dinaikkan versi patch-nya: `v0.3.1`, `v1.0.1`.

## 4. Alur kerja harian

### 4.1 Mulai pekerjaan

```sh
git switch develop
git pull origin develop
git switch -c feature/inc2-F03-buat-tiket-checklist
```

### 4.2 Selama bekerja

```sh
git add <file-yang-diubah>
git commit -m "feat(tiket): susun checklist dua fase saat tiket dibuat"
git push -u origin feature/inc2-F03-buat-tiket-checklist   # push pertama
git push                                                     # push berikutnya
```

Sebelum push, jalankan pemeriksaan lokal:

```sh
vendor/bin/pint --dirty --format agent
php artisan test --compact
npm run typecheck
npm run test
```

### 4.3 Menyamakan dengan develop

Bila `develop` sudah maju selama Anda bekerja:

```sh
git fetch origin
git rebase origin/develop          # selesaikan konflik bila ada, lalu: git rebase --continue
git push --force-with-lease        # aman: hanya untuk branch milik sendiri
```

`--force-with-lease` hanya boleh dipakai di branch sementara milik sendiri, tidak pernah di `main` atau `develop`.

### 4.4 Membuka Pull Request

1. Buka GitHub, lalu klik **Compare & pull request**. Base: `develop`; compare: branch Anda.
2. Judul PR mengikuti format commit, misalnya `feat(tiket): buat tiket dan checklist dua fase (F03)`.
3. Isi template PR, termasuk checklist Definition of Done.
4. Minta review dari anggota lain: Rizal mereview infra/docs, Adit mereview fitur dan menguji di staging.
5. Setelah disetujui dan CI lulus, pilih **Squash and merge**.
6. Hapus branch setelah merge (tombol **Delete branch**), lalu bersihkan lokal:

```sh
git switch develop
git pull origin develop
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

Hindari pesan seperti `update`, `fix`, `wip`, atau `perbaikan`. Riwayat commit adalah bagian dari bukti pengerjaan tugas akhir.

## 6. Rilis per inkremen

Dilakukan Adit setelah semua butir Definition of Done inkremen terpenuhi (PRD §8):

1. Pastikan `develop` lulus CI dan sudah didemokan di staging.
2. Buka PR dari `develop` ke `main` dengan judul `release: inkremen 2 — tiket, dokumen, validasi`.
3. Isi deskripsi dengan daftar fitur/FR yang selesai, hasil black box, dan catatan masukan demo.
4. Merge dengan **Create a merge commit** (bukan squash), agar riwayat `develop` tetap terlihat di `main`.
5. Beri tag dan catatan rilis:

```sh
git switch main
git pull origin main
git tag -a v0.2.0 -m "Inkremen 2: tiket, dokumen, validasi"
git push origin v0.2.0
```

6. Di GitHub, buka **Releases → Draft a new release**, pilih tag tersebut, lalu klik **Generate release notes**.

## 7. Hotfix produksi

Hanya untuk bug di `main` yang tidak bisa menunggu inkremen berikutnya:

```sh
git switch main
git pull origin main
git switch -c hotfix/login-terkunci-permanen
# perbaiki, tambahkan tes, commit
git push -u origin hotfix/login-terkunci-permanen
```

1. Buka PR ke `main`, review, lalu merge. Beri tag patch (misalnya `v1.0.1`).
2. Bawa perbaikan ke `develop`:

```sh
git switch develop
git pull origin develop
git merge --no-ff origin/main
git push origin develop
```

## 8. Pengaturan proteksi branch di GitHub

Dilakukan sekali oleh pemilik repository (Rizal). Buka **Settings → Branches → Add branch ruleset** (atau **Add rule**), lalu buat aturan untuk `main` dan `develop`:

- **Require a pull request before merging**, dengan **Required approvals: 1**.
- **Require status checks to pass**: pilih job CI setelah GitHub Actions dibuat (Inkremen 0).
- **Block force pushes**.
- **Restrict deletions**.
- Khusus `main`: aktifkan juga **Require linear history** = *tidak*. Merge commit dibutuhkan untuk rilis.

Lalu di **Settings → General → Pull Requests**:

- Aktifkan **Allow squash merging** dan **Allow merge commits**; nonaktifkan **Allow rebase merging**.
- Aktifkan **Automatically delete head branches**.

Terakhir, ubah **Default branch** ke `develop` agar PR baru otomatis mengarah ke `develop`.

## 9. Hal yang tidak boleh di-commit

- `.env` dan semua kredensial: kata sandi database, token WhatsApp, kunci SSH. Gunakan GitHub **Secrets** untuk CI/CD.
- `vendor/`, `node_modules/`, `public/build/`, dan isi `storage/` (sudah ada di `.gitignore`).
- Data asli perusahaan atau dokumen klien. Gunakan data tiruan sesuai PRD §9.
- Berkas besar hasil ekspor (`.zip`, rekaman layar). Simpan di Google Drive tim.

Bila terlanjur meng-commit rahasia, segera ganti kredensial tersebut. Menghapus commit saja tidak cukup karena riwayat sudah tersebar.

## 10. Ringkasan cepat

| Saya ingin... | Perintah |
|---|---|
| Mulai fitur | `git switch develop && git pull && git switch -c feature/incN-FXX-nama` |
| Simpan pekerjaan | `git add <file> && git commit -m "feat(lingkup): ringkasan"` |
| Kirim ke GitHub | `git push -u origin <branch>` |
| Ikut perubahan terbaru | `git fetch origin && git rebase origin/develop` |
| Lihat status | `git status` dan `git log --oneline --graph -15` |
| Batalkan perubahan file yang belum di-commit | `git restore <file>` |
| Hapus branch lokal setelah merge | `git branch -d <branch>` |
| Rilis inkremen | PR `develop` → `main`, lalu `git tag -a v0.N.0` |
