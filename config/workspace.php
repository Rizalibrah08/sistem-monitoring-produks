<?php

return [
    'modules' => [
        'prospects' => [
            'title' => 'Prospek dari chatbot', 'label' => 'Prospek', 'path' => '/prospects', 'route' => 'prospects.index', 'icon' => 'sprout', 'group' => 'Operasional',
            'roles' => ['marketing'],
            'description' => 'Pengajuan awal calon klien dari nomor layanan.', 'empty_title' => 'Belum ada prospek',
            'columns' => ['Nama', 'Kontak', 'Gambaran produk', 'Tanggal masuk', 'Status'],
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
    ],
];
