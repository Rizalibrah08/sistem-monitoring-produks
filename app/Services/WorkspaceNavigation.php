<?php

namespace App\Services;

use App\Models\User;

class WorkspaceNavigation
{
    /** @return list<array{id: string, label: string, href: string, icon: string, group: string, available: bool}> */
    public function forUser(User $user): array
    {
        $items = [[
            'id' => 'dashboard', 'label' => 'Dasbor', 'href' => route('dashboard', absolute: false),
            'icon' => 'dashboard', 'group' => 'Ruang kerja', 'available' => true,
        ]];

        if ($user->can('tickets.view')) {
            $items[] = ['id' => 'tickets', 'label' => 'Daftar tiket', 'href' => route('tickets.index', absolute: false), 'icon' => 'ticket', 'group' => 'Ruang kerja', 'available' => true];
        }

        foreach (config('workspace.modules', []) as $key => $module) {
            if (($module['navigation'] ?? true) && $user->hasAnyRole($module['roles'])) {
                $items[] = [
                    'id' => $key,
                    'label' => $module['label'] ?? $module['title'],
                    'href' => route($module['route'], absolute: false),
                    'icon' => $module['icon'],
                    'group' => $module['group'],
                    'available' => false,
                ];
            }
        }

        if ($user->can('samples.record')) {
            $items[] = ['id' => 'samples', 'label' => 'Persetujuan sampel', 'href' => route('samples.index', absolute: false), 'icon' => 'flask', 'group' => 'Operasional', 'available' => true];
        }

        if ($user->can('invoices.view')) {
            $items[] = ['id' => 'invoices', 'label' => 'Tagihan', 'href' => route('invoices.index', absolute: false), 'icon' => 'file-clock', 'group' => 'Operasional', 'available' => true];
        }

        if ($user->can('contracts.manage')) {
            $items[] = ['id' => 'contracts', 'label' => 'Kontrak', 'href' => route('contracts.index', absolute: false), 'icon' => 'file', 'group' => 'Operasional', 'available' => true];
        }

        if ($user->can('clients.manage')) {
            $items[] = ['id' => 'clients', 'label' => 'Data klien', 'href' => route('clients.index', absolute: false), 'icon' => 'users', 'group' => 'Operasional', 'available' => true];
        }

        if ($user->can('master.manage')) {
            $items[] = ['id' => 'master', 'label' => 'Master data', 'href' => route('master.index', absolute: false), 'icon' => 'settings', 'group' => 'Administrasi', 'available' => true];
        }

        if ($user->can('master.manage')) {
            $items[] = ['id' => 'templates', 'label' => 'Templat dokumen', 'href' => route('templates.index', absolute: false), 'icon' => 'file', 'group' => 'Administrasi', 'available' => true];
        }

        if ($user->can('document_rules.manage')) {
            $items[] = ['id' => 'document-rules', 'label' => 'Aturan dokumen', 'href' => route('document-rules.index', absolute: false), 'icon' => 'file-check', 'group' => 'Administrasi', 'available' => true];
        }

        if ($user->can('users.manage')) {
            $items[] = ['id' => 'users', 'label' => 'Pengguna dan role', 'href' => route('users.index', absolute: false), 'icon' => 'users', 'group' => 'Administrasi', 'available' => true];
        }

        if ($user->can('audit.view')) {
            $items[] = ['id' => 'audit', 'label' => 'Log audit', 'href' => route('audit.index', absolute: false), 'icon' => 'shield', 'group' => 'Administrasi', 'available' => true];
        }

        return $items;
    }
}
