<?php

/*
 * Penanda isian yang boleh dipakai di templat Word (FR-67), ditulis sebagai ${nama_variabel}.
 * Daftar awal diturunkan dari data klien, tiket, kontrak, dan tagihan di SRS; daftar final
 * menunggu konfirmasi Marketing, RA, dan Finance (PRD §10).
 */

return [
    'company_name' => env('COMPANY_NAME', 'PT Aisha Zahra Herbalaboratories'),

    'variables' => [
        'nama_perusahaan' => 'Nama perusahaan maklon',
        'tanggal_dokumen' => 'Tanggal dokumen dibuat (dd/mm/yyyy)',
        'nama_klien' => 'Nama klien pemilik merek',
        'nama_pic_klien' => 'Nama PIC klien',
        'email_klien' => 'Email klien',
        'nomor_whatsapp_klien' => 'Nomor WhatsApp klien',
        'kode_tiket' => 'Kode tiket pendaftaran',
        'nama_produk' => 'Nama produk',
        'sediaan' => 'Sediaan produk',
        'kategori' => 'Kategori produk',
        'nomor_kontrak' => 'Nomor kontrak (MoU)',
        'nilai_jasa' => 'Nilai jasa kontrak (Rp)',
        'persen_dp' => 'Persentase DP',
        'nilai_dp' => 'Nilai DP (Rp)',
        'persen_pelunasan' => 'Persentase pelunasan',
        'nilai_pelunasan' => 'Nilai pelunasan (Rp)',
        'nomor_tagihan' => 'Nomor tagihan',
        'termin_tagihan' => 'Termin tagihan (DP atau pelunasan)',
        'nominal_tagihan' => 'Nominal tagihan (Rp)',
        'jatuh_tempo_tagihan' => 'Tanggal jatuh tempo tagihan (dd/mm/yyyy)',
    ],
];
