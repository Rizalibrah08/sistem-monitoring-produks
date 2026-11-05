<?php

namespace App;

enum StagePhase: string
{
    case Initial = 'awal';
    case PreRegistration = 'pra_registrasi';
    case Registration = 'registrasi';
    case BothPhases = 'kedua_fase';

    public function label(): string
    {
        return match ($this) {
            self::Initial => 'Awal',
            self::PreRegistration => 'Pra-registrasi',
            self::Registration => 'Registrasi',
            self::BothPhases => 'Kedua fase',
        };
    }
}
