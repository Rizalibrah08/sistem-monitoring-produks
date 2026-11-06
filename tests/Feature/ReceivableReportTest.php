<?php

namespace Tests\Feature;

use App\InvoiceTerm;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\Ticket;
use App\Models\User;
use App\TicketStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReceivableReportTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Client $herbal;

    private Client $jamu;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-05 10:00:00');
        $this->herbal = Client::factory()->create(['name' => 'Nusantara Herbal']);
        $this->jamu = Client::factory()->create(['name' => 'Jamu Sehat']);

        $herbalContract = $this->contractFor($this->herbal);
        Invoice::factory()->paid()->create(['contract_id' => $herbalContract->id, 'number' => 'INV/2026/0001', 'amount' => 15_000_000, 'created_at' => '2026-10-01']);
        Invoice::factory()->create(['contract_id' => $herbalContract->id, 'number' => 'INV/2026/0002', 'term' => InvoiceTerm::Settlement, 'amount' => 15_000_000, 'due_date' => '2026-11-01', 'created_at' => '2026-10-20']);
        $herbalContract->ticket->update(['status' => TicketStatus::Completed]);
        Invoice::factory()->create(['contract_id' => $this->contractFor($this->jamu)->id, 'number' => 'INV/2026/0003', 'amount' => 8_000_000, 'due_date' => '2026-11-20', 'created_at' => '2026-11-04']);
    }

    public function test_finance_sees_billed_paid_outstanding_and_overdue_amounts_per_client(): void
    {
        $this->actingAs($this->createStaff('finance'))->withoutVite()->get('/receivables')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Backend/Receivables', false)
                ->has('clients', 2)
                ->where('clients.0.client', 'Nusantara Herbal')
                ->where('clients.0.billed', 30_000_000)
                ->where('clients.0.paid', 15_000_000)
                ->where('clients.0.outstanding', 15_000_000)
                ->where('clients.0.overdue', 15_000_000)
                ->where('clients.0.oldest_due', '01/11/2026')
                ->where('clients.1.outstanding', 8_000_000)
                ->where('clients.1.overdue', 0)
                ->where('totals.billed', 38_000_000)
                ->where('totals.outstanding', 23_000_000)
                ->where('navigation', fn ($items): bool => collect($items)->contains(fn (array $item): bool => $item['id'] === 'receivables' && $item['available'] === true)));

        $this->get('/receivables?from=2026-11-01&search=jamu')
            ->assertInertia(fn (Assert $page) => $page->has('clients', 1)->where('clients.0.client', 'Jamu Sehat')->where('urls.export', '/receivables/export?search=jamu&from=2026-11-01'));
        $this->from('/receivables')->get('/receivables?from=2026-11-05&until=2026-11-01')->assertSessionHasErrors(['until' => 'Tanggal akhir tidak boleh sebelum tanggal awal.']);
    }

    public function test_the_report_exports_invoice_details_per_client_as_csv(): void
    {
        $response = $this->actingAs($this->createStaff('manager'))->get('/receivables/export?search=herbal')->assertOk()
            ->assertDownload('laporan-piutang-20261105.csv');

        $lines = array_map('str_getcsv', explode("\n", trim(str_replace("\xEF\xBB\xBF", '', $response->streamedContent()))));
        $this->assertSame('Klien', $lines[0][0]);
        $this->assertCount(3, $lines);
        $this->assertSame(['Nusantara Herbal', 'INV/2026/0002', 'Pelunasan 50%', '15000000', 'Belum dibayar', 'Ya'], [$lines[2][0], $lines[2][1], $lines[2][4], $lines[2][5], $lines[2][8], $lines[2][9]]);
    }

    public function test_only_finance_and_managers_open_the_report(): void
    {
        $this->actingAs($this->createStaff('marketing'))->get('/receivables')->assertForbidden();
        $this->get('/receivables/export')->assertForbidden();
    }

    private function contractFor(Client $client): Contract
    {
        return Contract::factory()->create(['ticket_id' => Ticket::factory()->create(['client_id' => $client->id])->id]);
    }

    private function createStaff(string $role): User
    {
        $this->seed();
        $user = User::factory()->create(['email' => "{$role}@aisha.test"]);
        $user->assignRole($role);

        return $user;
    }
}
