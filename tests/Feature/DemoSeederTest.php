<?php

namespace Tests\Feature;

use App\ContractStatus;
use App\DocumentRequirement;
use App\DocumentStatus;
use App\InvoiceStatus;
use App\InvoiceTerm;
use App\Models\Client;
use App\Models\DocumentType;
use App\Models\PriorityScore;
use App\Models\Ticket;
use App\Models\User;
use App\SampleDecision;
use App\StaffRole;
use App\StagePhase;
use App\SubmissionStatus;
use App\TicketStatus;
use Database\Seeders\DemoSeeder;
use Database\Seeders\DemoWorkflows;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Http::fake(['*/forms/libreoffice/convert' => Http::response('%PDF-1.7 kontrak', 200)]);
    }

    public function test_the_demo_data_gives_every_role_an_account_and_tickets_with_a_ready_checklist(): void
    {
        $this->seed(DemoSeeder::class);

        foreach (StaffRole::cases() as $role) {
            $user = User::query()->role($role->value)->sole();
            $this->assertTrue($user->is_active);
            $this->assertTrue(Hash::check(DemoSeeder::PASSWORD, $user->password));
        }

        $this->assertGreaterThanOrEqual(2, Client::query()->count());
        $this->assertTrue(DocumentType::query()->where('requirement', DocumentRequirement::Conditional)->exists());

        $tickets = Ticket::query()->with('documents.documentType')->orderBy('code')->get();
        $this->assertCount(5, $tickets);
        $this->assertSame(array_map(fn (int $number): string => 'PRD-'.now()->year.'-000'.$number, [1, 2, 3, 4, 5]), $tickets->pluck('code')->all());
        $this->assertTrue($tickets->every(fn (Ticket $ticket): bool => $ticket->documents->isNotEmpty()));
        $this->assertTrue($tickets->contains(fn (Ticket $ticket): bool => $ticket->documents->contains(
            fn ($item): bool => $item->requirement === DocumentRequirement::Conditional,
        )));
        $this->assertDatabaseHas('activity_log', ['event' => 'ticket.created']);
    }

    public function test_the_demo_tickets_show_every_workflow_from_sample_to_nie(): void
    {
        $this->seed(DemoSeeder::class);
        $ticket = fn (string $product): Ticket => Ticket::query()->where('product_name', $product)->sole();

        $sample = $ticket('Kapsul Jahe Merah');
        $this->assertSame(StagePhase::Initial, $sample->phase);
        $this->assertSame(SampleDecision::NeedsRevision, $sample->sampleApprovals()->sole()->decision);

        $sent = $ticket('Sirup Temulawak');
        $this->assertSame(ContractStatus::Sent, $sent->contract->status);
        $this->get('/unggah/'.DemoWorkflows::CLIENT_LINK_TOKEN)->assertOk();

        $progress = $ticket('Serbuk Kunyit Asam');
        $this->assertSame(StagePhase::PreRegistration, $progress->phase);
        $this->assertSame(ContractStatus::Active, $progress->contract->status);
        $this->assertTrue($progress->invoices()->sole()->isOverdue(now()));
        $this->assertSame(DocumentStatus::NeedsRevision, $progress->documents()->whereHas('documentType', fn ($query) => $query->where('name', 'Master formula'))->sole()->status);
        $this->assertTrue($progress->documents()->where('status', DocumentStatus::UnderReview)->exists());
        $this->assertTrue($progress->stageLogs()->whereNull('finished_at')->whereNotNull('overdue_at')->exists());
        $this->assertSame(1, $progress->materials()->count());

        $registration = $ticket('Tablet Sambiloto');
        $this->assertSame(StagePhase::Registration, $registration->phase);
        $this->assertSame(SubmissionStatus::AwaitingResult, $registration->bpomSubmissions()->where('phase', StagePhase::Registration)->sole()->status);
        $this->assertSame(1, $registration->bpomRequests()->whereNull('responded_at')->count());
        $this->assertTrue($registration->labTests()->where('conforms', true)->exists());

        $done = $ticket('Kapsul Kunyit Putih');
        $this->assertSame(TicketStatus::Completed, $done->status);
        $this->assertNotNull($done->nie_number);
        $this->assertSame([InvoiceTerm::DownPayment, InvoiceTerm::Settlement], $done->invoices()->orderBy('id')->get()->pluck('term')->all());
        $this->assertTrue($done->invoices()->get()->every(fn ($invoice): bool => $invoice->status === InvoiceStatus::Paid));

        $this->assertTrue(PriorityScore::query()->where('queue', 'ra')->exists());
        $this->assertGreaterThan(0, User::query()->where('email', 'ra@aisha.test')->sole()->notifications()->count());
    }

    public function test_seeding_the_demo_again_does_not_duplicate_data(): void
    {
        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);

        $this->assertSame(7, User::query()->count());
        $this->assertSame(5, Ticket::query()->count());
    }

    public function test_the_demo_data_is_refused_outside_local_and_testing(): void
    {
        $this->app['env'] = 'production';

        try {
            $this->app->make(DemoSeeder::class)->run();
            $this->fail('Seeder demo seharusnya menolak berjalan di production.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Data demo hanya boleh dibuat di lingkungan local atau testing.', $exception->getMessage());
        }

        $this->assertSame(0, User::query()->count());
    }
}
