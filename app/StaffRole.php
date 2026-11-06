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
            self::Admin => ['dashboard.view', 'users.manage', 'audit.view', 'master.manage', 'materials.create', 'document_rules.manage'],
            self::Manager => ['dashboard.view', 'audit.view', 'clients.manage', 'tickets.view'],
            self::Marketing => ['dashboard.view', 'clients.manage', 'tickets.view', 'tickets.create', 'upload_links.manage', 'samples.record'],
            self::Research => ['dashboard.view', 'materials.create', 'tickets.view', 'ticket_materials.manage'],
            self::Quality => ['dashboard.view', 'tickets.view'],
            self::Regulatory => ['dashboard.view', 'document_rules.manage', 'tickets.view', 'upload_links.manage'],
            default => ['dashboard.view'],
        };
    }
}
