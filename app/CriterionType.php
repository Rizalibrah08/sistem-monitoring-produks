<?php

namespace App;

/** Jenis kriteria TOPSIS: benefit makin besar makin diprioritaskan, cost sebaliknya. */
enum CriterionType: string
{
    case Benefit = 'benefit';
    case Cost = 'cost';

    public function label(): string
    {
        return match ($this) {
            self::Benefit => 'Benefit',
            self::Cost => 'Cost',
        };
    }
}
