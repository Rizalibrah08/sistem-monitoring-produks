<?php

namespace App;

/** Asal unggahan versi dokumen (FR-15). */
enum DocumentSource: string
{
    case Internal = 'internal';
    case Client = 'klien';
    case ChatAttachment = 'lampiran_chat';

    public function label(): string
    {
        return match ($this) {
            self::Internal => 'Internal',
            self::Client => 'Klien',
            self::ChatAttachment => 'Lampiran chat',
        };
    }
}
