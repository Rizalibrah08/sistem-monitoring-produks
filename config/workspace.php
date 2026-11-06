<?php

return [
    'modules' => [
        'prospects' => [
            'title' => 'Prospek dari chatbot', 'label' => 'Prospek', 'path' => '/prospects', 'route' => 'prospects.index', 'icon' => 'sprout', 'group' => 'Operasional',
            'roles' => ['marketing'],
            'description' => 'Pengajuan awal calon klien dari nomor layanan.', 'empty_title' => 'Belum ada prospek',
            'columns' => ['Nama', 'Kontak', 'Gambaran produk', 'Tanggal masuk', 'Status'],
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
        'sync-checklist' => [
            'title' => 'Sinkronisasi checklist', 'path' => '/sync-checklist', 'route' => 'checklist_sync.index', 'icon' => 'clipboard', 'group' => 'Operasional',
            'roles' => ['ra'], 'navigation' => false,
            'description' => 'Peninjauan aturan dokumen terbaru untuk tiket aktif.', 'empty_title' => 'Belum ada checklist untuk disinkronkan',
        ],
    ],
];
