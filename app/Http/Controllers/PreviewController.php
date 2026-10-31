<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PreviewController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, string $screen = 'dashboard', ?string $entity = null): Response
    {
        abort_unless(app()->environment(['local', 'testing']), 404);

        $screens = ['dashboard', 'tickets', 'priorities', 'clients', 'prospects', 'samples', 'contracts', 'invoices', 'payments', 'receivables', 'verification', 'qa-review', 'shared-documents', 'ingredients', 'sync-checklist', 'bpom', 'nie', 'inbox', 'contacts', 'compose', 'tasks', 'reports', 'escalations', 'users', 'templates', 'audit', 'master', 'portal', 'chatbot', 'login'];
        abort_unless(in_array($screen, $screens, true), 404);

        if ($screen === 'tickets' && $entity !== null) {
            abort_unless($entity === 'new' || preg_match('/^PRD-\d{4}-\d{4}$/', $entity) === 1, 404);
            $screen = $entity === 'new' ? 'ticket-create' : 'ticket-detail';
        }

        if ($screen === 'contracts' && $entity === 'new') {
            $screen = 'contract-create';
        }

        if ($screen === 'portal' && $entity !== 'demo-token') {
            $screen = 'portal-expired';
        }

        $roles = ['manager', 'marketing', 'rnd', 'qa', 'ra', 'finance', 'admin'];
        $role = $request->string('role')->toString();

        return Inertia::render('Workspace', [
            'screen' => $screen,
            'entityId' => $entity,
            'initialRole' => in_array($role, $roles, true) ? $role : 'manager',
            'preview' => true,
        ]);
    }
}
