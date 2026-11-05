<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class WorkspaceModuleController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(string $module): Response
    {
        $configuration = config('workspace.modules.'.$module);
        abort_if($configuration === null, 404);
        $whatsapp = ($configuration['integration'] ?? null) === 'whatsapp';

        return Inertia::render('Backend/Module', [
            'module' => [
                'id' => $module,
                'title' => $configuration['title'],
                'description' => $configuration['description'],
                'icon' => $configuration['icon'],
                'empty_title' => $configuration['empty_title'],
                'columns' => $configuration['columns'] ?? [],
                'actions' => $configuration['actions'] ?? [],
                'state' => $whatsapp ? 'not_connected' : 'not_implemented',
                'message' => $whatsapp
                    ? 'Integrasi WhatsApp belum diaktifkan. Percakapan, kontak, dan pengiriman pesan akan tersedia setelah gateway terhubung.'
                    : 'Backend modul ini belum diaktifkan. Data akan ditampilkan setelah modul tersedia; tidak ada data demo atau penyimpanan simulasi.',
            ],
        ]);
    }
}
