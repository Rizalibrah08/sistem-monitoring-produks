<?php

namespace App\Http\Controllers;

use App\Actions\SaveMasterRecord;
use App\Http\Requests\SaveClientRequest;
use App\Models\Client;
use App\Models\User;
use App\StaffRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $search = $filters['search'] ?? null;
        $searchNumber = filled($search) ? Client::normalizeWhatsapp($search) : '';

        $clients = Client::query()->with('marketingUser')
            ->when(filled($search), function (Builder $query) use ($search, $searchNumber): void {
                $query->where(function (Builder $query) use ($search, $searchNumber): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('company', 'like', "%{$search}%")
                        ->orWhere('pic_name', 'like', "%{$search}%")
                        ->when($searchNumber !== '', fn (Builder $query) => $query->orWhere('whatsapp', 'like', "%{$searchNumber}%"));
                });
            })
            ->orderBy('name')->orderBy('id')->paginate(25)->withQueryString()
            ->through(fn (Client $client): array => [
                'id' => $client->id,
                'name' => $client->name,
                'company' => $client->company,
                'pic_name' => $client->pic_name,
                'whatsapp' => $client->whatsapp,
                'email' => $client->email,
                'marketing_user_id' => $client->marketing_user_id,
                'marketing_user' => $client->marketingUser->name,
                'update_url' => route('clients.update', $client, absolute: false),
            ]);

        return Inertia::render('Backend/Clients', [
            'clients' => $clients,
            'marketingStaff' => User::query()->role(StaffRole::Marketing->value)->where('is_active', true)->orderBy('name')->get()
                ->map(fn (User $user): array => ['value' => $user->id, 'label' => $user->name]),
            'filters' => ['search' => $search ?? ''],
            'urls' => ['store' => route('clients.store', absolute: false)],
        ]);
    }

    public function store(SaveClientRequest $request, SaveMasterRecord $save): RedirectResponse
    {
        $save->handle(new Client, 'client', $request->validated(), $request->user(), group: '');

        return back()->with('status', 'Klien berhasil ditambahkan.');
    }

    public function update(SaveClientRequest $request, Client $client, SaveMasterRecord $save): RedirectResponse
    {
        $save->handle($client, 'client', $request->validated(), $request->user(), group: '');

        return back()->with('status', 'Data klien berhasil diperbarui.');
    }
}
