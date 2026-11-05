## Ringkasan

<!-- Apa yang berubah dan mengapa. -->

## Referensi

- Inkremen:
- Fitur PRD (F..):
- Kebutuhan SRS (FR/NFR/BR..):

## Cara menguji

<!-- Langkah atau skenario black box yang bisa diikuti reviewer. -->

## Definition of Done (PRD §8)

- [ ] Kriteria penerimaan user story terkait terpenuhi
- [ ] Tes otomatis untuk logika baru ditulis dan lulus **di lokal** (`php artisan test`, `npm run test`); CI tidak berjalan di PR
- [ ] `vendor/bin/pint` dan `npm run typecheck` bersih
- [ ] Setiap aksi baru tercatat di log audit
- [ ] Tidak ada `.env`, kredensial, atau data asli klien yang ikut ter-commit
- [ ] PRD, SRS, atau Dokumen Rancangan diperbarui bila ada perubahan
