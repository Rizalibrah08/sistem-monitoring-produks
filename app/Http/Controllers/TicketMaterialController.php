<?php

namespace App\Http\Controllers;

use App\Actions\SyncTicketMaterials;
use App\Http\Requests\SyncTicketMaterialsRequest;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;

class TicketMaterialController extends Controller
{
    public function update(SyncTicketMaterialsRequest $request, Ticket $ticket, SyncTicketMaterials $sync): RedirectResponse
    {
        $result = $sync->handle($ticket, $request->validated('material_ids'), $request->user());

        return back()->with('status', "Bahan baku diperbarui: {$result['items']} butir checklist ditambahkan, {$result['linked']} langsung tertaut ke dokumen yang masih berlaku.");
    }
}
