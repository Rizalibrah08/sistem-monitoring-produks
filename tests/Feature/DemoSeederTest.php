<?php

namespace Tests\Feature;

use App\DocumentRequirement;
use App\Models\Client;
use App\Models\DocumentType;
use App\Models\Ticket;
use App\Models\User;
use App\StaffRole;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

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
        $this->assertCount(3, $tickets);
        $this->assertSame(['PRD-'.now()->year.'-0001', 'PRD-'.now()->year.'-0002', 'PRD-'.now()->year.'-0003'], $tickets->pluck('code')->all());
        $this->assertTrue($tickets->every(fn (Ticket $ticket): bool => $ticket->documents->isNotEmpty()));
        $this->assertTrue($tickets->contains(fn (Ticket $ticket): bool => $ticket->documents->contains(
            fn ($item): bool => $item->requirement === DocumentRequirement::Conditional,
        )));
        $this->assertDatabaseHas('activity_log', ['event' => 'ticket.created']);
    }

    public function test_seeding_the_demo_again_does_not_duplicate_data(): void
    {
        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);

        $this->assertSame(7, User::query()->count());
        $this->assertSame(3, Ticket::query()->count());
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
