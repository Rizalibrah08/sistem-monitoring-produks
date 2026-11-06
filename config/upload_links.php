<?php

/*
 * Masa berlaku tautan unggah klien (FR-18). Staf memilih jumlah hari saat membuat tautan;
 * bawaannya 14 hari.
 */

return [
    'default_days' => (int) env('UPLOAD_LINK_DEFAULT_DAYS', 14),
    'max_days' => (int) env('UPLOAD_LINK_MAX_DAYS', 60),
];
