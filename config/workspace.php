<?php

return [
    'modules' => [
        'priorities' => [
            'title' => 'Antrean prioritas', 'path' => '/priorities', 'route' => 'priorities.index', 'icon' => 'activity', 'group' => 'Ruang kerja',
            'roles' => ['manager', 'marketing', 'rnd', 'qa', 'ra'],
            'description' => 'Urutan pekerjaan berdasarkan kriteria AHP–TOPSIS.', 'empty_title' => 'Belum ada antrean prioritas',
            'columns' => ['Peringkat', 'Kode tiket', 'Produk', 'C1', 'C2', 'C3', 'C4', 'Skor'],
        ],
        'tasks' => [
            'title' => 'Tugas dokumen', 'path' => '/tasks', 'route' => 'tasks.index', 'icon' => 'clipboard', 'group' => 'Ruang kerja',
            'roles' => ['rnd', 'qa', 'ra'],
            'description' => 'Dokumen yang perlu disiapkan, diperiksa, atau ditanggapi.', 'empty_title' => 'Belum ada tugas dokumen',
            'columns' => ['Tiket', 'Dokumen', 'Fase', 'Tenggat', 'Status'],
        ],
        'prospects' => [
            'title' => 'Prospek dari chatbot', 'label' => 'Prospek', 'path' => '/prospects', 'route' => 'prospects.index', 'icon' => 'sprout', 'group' => 'Operasional',
            'roles' => ['marketing'],
            'description' => 'Pengajuan awal calon klien dari nomor layanan.', 'empty_title' => 'Belum ada prospek',
            'columns' => ['Nama', 'Kontak', 'Gambaran produk', 'Tanggal masuk', 'Status'],
        ],
        'verification' => [
            'title' => 'Verifikasi dokumen', 'path' => '/verification', 'route' => 'verification.index', 'icon' => 'file-check', 'group' => 'Operasional',
            'roles' => ['ra'],
            'description' => 'Pemeriksaan pemakaian dokumen oleh Regulatory Affairs.', 'empty_title' => 'Belum ada dokumen untuk diverifikasi',
            'columns' => ['Tiket', 'Dokumen', 'Versi', 'Validasi otomatis', 'Status'],
        ],
        'qa-review' => [
            'title' => 'Verifikasi dokumen mutu', 'label' => 'Verifikasi mutu', 'path' => '/qa-review', 'route' => 'quality.index', 'icon' => 'shield', 'group' => 'Operasional',
            'roles' => ['qa'],
            'description' => 'Pemeriksaan isi dokumen mutu oleh QA/QC.', 'empty_title' => 'Belum ada dokumen mutu untuk diperiksa',
            'columns' => ['Tiket', 'Dokumen', 'Versi', 'PIC', 'Status'],
        ],
        'shared-documents' => [
            'title' => 'Dokumen bersama', 'path' => '/shared-documents', 'route' => 'shared_documents.index', 'icon' => 'file', 'group' => 'Operasional',
            'roles' => ['qa', 'ra'],
            'description' => 'Dokumen bersama perusahaan dan riwayat versinya.', 'empty_title' => 'Belum ada dokumen bersama',
            'columns' => ['Dokumen', 'Cakupan', 'Versi', 'Masa berlaku', 'Status'], 'actions' => ['Unggah dokumen'],
        ],
        'ingredients' => [
            'title' => 'Bahan baku tiket', 'path' => '/ingredients', 'route' => 'ingredients.index', 'icon' => 'flask', 'group' => 'Operasional',
            'roles' => ['rnd'],
            'description' => 'Bahan baku produk dan dokumen asal peredarannya.', 'empty_title' => 'Belum ada bahan baku tiket',
            'columns' => ['Tiket', 'Bahan baku', 'Dokumen terkait', 'Status'],
        ],
        'bpom' => [
            'title' => 'Pengajuan dan catatan BPOM', 'label' => 'Pengajuan BPOM', 'path' => '/bpom', 'route' => 'bpom.index', 'icon' => 'package', 'group' => 'Operasional',
            'roles' => ['ra'],
            'description' => 'Pengajuan pra-registrasi, registrasi, dan tambahan data BPOM.', 'empty_title' => 'Belum ada pengajuan BPOM',
            'columns' => ['Tiket', 'Fase', 'Tanggal pengajuan', 'PNBP', 'Tenggat', 'Status'], 'actions' => ['Catat pengajuan'],
        ],
        'nie' => [
            'title' => 'Input NIE', 'path' => '/nie', 'route' => 'nie.index', 'icon' => 'shield', 'group' => 'Operasional',
            'roles' => ['ra'],
            'description' => 'Nomor Izin Edar dan penyelesaian pendaftaran produk.', 'empty_title' => 'Belum ada tiket yang siap menerima NIE',
            'columns' => ['Tiket', 'Produk', 'Nomor NIE', 'Tanggal terbit'],
        ],
        'receivables' => [
            'title' => 'Laporan piutang', 'path' => '/receivables', 'route' => 'receivables.index', 'icon' => 'coins', 'group' => 'Operasional',
            'roles' => ['finance', 'manager'],
            'description' => 'Tagihan yang belum lunas, termasuk tiket yang telah ditutup.', 'empty_title' => 'Belum ada data piutang',
            'columns' => ['Klien', 'Tagihan', 'Jatuh tempo', 'Sisa pembayaran', 'Status'],
        ],
        'inbox' => [
            'title' => 'Kotak masuk WhatsApp', 'label' => 'Kotak masuk', 'path' => '/inbox', 'route' => 'inbox.index', 'icon' => 'message', 'group' => 'Komunikasi',
            'roles' => ['marketing', 'ra', 'manager'],
            'description' => 'Percakapan klien dari nomor WhatsApp Marketing.', 'empty_title' => 'Belum terhubung ke WhatsApp',
            'integration' => 'whatsapp', 'actions' => ['Chat baru'],
        ],
        'contacts' => [
            'title' => 'Kontak WhatsApp', 'path' => '/contacts', 'route' => 'contacts.index', 'icon' => 'users', 'group' => 'Komunikasi',
            'roles' => ['marketing'],
            'description' => 'Kontak klien, prospek, dan nomor yang belum dikenal.', 'empty_title' => 'Belum terhubung ke WhatsApp',
            'integration' => 'whatsapp', 'columns' => ['Nama', 'Nomor WhatsApp', 'Label', 'PIC'],
        ],
        'compose' => [
            'title' => 'Chat baru', 'path' => '/compose', 'route' => 'compose.index', 'icon' => 'message', 'group' => 'Komunikasi',
            'roles' => ['marketing'], 'navigation' => false,
            'description' => 'Mulai percakapan dengan klien melalui nomor Marketing.', 'empty_title' => 'Belum terhubung ke WhatsApp',
            'integration' => 'whatsapp', 'actions' => ['Kirim pesan'],
        ],
        'reports' => [
            'title' => 'Laporan durasi', 'path' => '/reports', 'route' => 'reports.index', 'icon' => 'clock', 'group' => 'Pemantauan',
            'roles' => ['manager'],
            'description' => 'Durasi internal, waktu menunggu klien, dan waktu pihak luar.', 'empty_title' => 'Belum ada data laporan durasi',
            'columns' => ['Tahap', 'Jumlah tiket', 'Rata-rata', 'Persentil ke-75'], 'actions' => ['Ekspor laporan'],
        ],
        'escalations' => [
            'title' => 'Daftar eskalasi', 'label' => 'Eskalasi', 'path' => '/escalations', 'route' => 'escalations.index', 'icon' => 'bell', 'group' => 'Pemantauan',
            'roles' => ['manager'],
            'description' => 'Tahapan internal yang melampaui SLA hari kerja.', 'empty_title' => 'Belum ada data eskalasi',
            'columns' => ['Tiket', 'Tahap', 'PIC', 'Tenggat', 'Status'],
        ],
        'sync-checklist' => [
            'title' => 'Sinkronisasi checklist', 'path' => '/sync-checklist', 'route' => 'checklist_sync.index', 'icon' => 'clipboard', 'group' => 'Operasional',
            'roles' => ['ra'], 'navigation' => false,
            'description' => 'Peninjauan aturan dokumen terbaru untuk tiket aktif.', 'empty_title' => 'Belum ada checklist untuk disinkronkan',
        ],
        'notifications' => [
            'title' => 'Pusat notifikasi', 'path' => '/notifications', 'route' => 'notifications.index', 'icon' => 'bell', 'group' => 'Ruang kerja',
            'roles' => ['admin', 'manager', 'marketing', 'rnd', 'qa', 'ra', 'finance'], 'navigation' => false,
            'description' => 'Tugas, tenggat, pesan, dan perubahan status yang memerlukan perhatian.', 'empty_title' => 'Belum ada notifikasi',
        ],
    ],
];
