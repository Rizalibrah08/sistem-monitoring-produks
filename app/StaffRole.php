<?php

namespace App;

enum StaffRole: string
{
    case Admin = 'admin';
    case Marketing = 'marketing';
    case Research = 'rnd';
    case Quality = 'qa';
    case Regulatory = 'ra';
    case Finance = 'finance';
    case Manager = 'manager';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Marketing => 'Marketing',
            self::Research => 'R&D',
            self::Quality => 'QA/QC',
            self::Regulatory => 'Regulatory Affairs',
            self::Finance => 'Finance',
            self::Manager => 'Manajer',
        };
    }

    /** @return list<string> */
    public function permissions(): array
    {
        return match ($this) {
            self::Admin => ['dashboard.view', 'users.manage', 'audit.view'],
            self::Manager => ['dashboard.view', 'audit.view'],
            default => ['dashboard.view'],
        };
    }
}
