<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuditExportTest extends TestCase
{
    use LazilyRefreshDatabase;

    private bool $gotenbergDown = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-05 10:00:00');
        $this->seed();
        Http::fake(['*/forms/chromium/convert/html' => fn () => $this->gotenbergDown ? Http::response('down', 503) : Http::response('%PDF-1.7 audit', 200)]);
        app(AuditLogger::class)->record('ticket.created', after: ['product_name' => 'Kapsul Jahe Merah'], source: 'system', ticketCode: 'PRD-2026-0001');
        app(AuditLogger::class)->record('ticket.created', after: ['product_name' => 'Sirup Temulawak'], source: 'system', ticketCode: 'PRD-2026-0002');
    }

    public function test_managers_export_the_filtered_log_as_csv_and_the_export_is_logged(): void
    {
        $manager = $this->staff('manager');

        $csv = $this->actingAs($manager)->get('/audit/export/csv?ticket_code=PRD-2026-0001')->assertOk()
            ->assertDownload('log-audit-20261105-100000.csv')->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('PRD-2026-0001', $csv);
        $this->assertStringContainsString('Kapsul Jahe Merah', $csv);
        $this->assertStringNotContainsString('PRD-2026-0002', $csv);
        $entry = Activity::query()->where('event', 'audit.exported')->sole();
        $this->assertSame($manager->id, $entry->causer_id);
        $this->assertSame(['format' => 'csv', 'entries' => 1], $entry->attribute_changes['attributes']);
        $this->assertSame('PRD-2026-0001', $entry->properties['filters']['ticket_code']);
    }

    public function test_the_pdf_export_is_printed_through_gotenberg_and_failures_fall_back_to_a_message(): void
    {
        $admin = $this->staff('admin');

        $this->actingAs($admin)->get('/audit/export/pdf')->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')->assertDownload('log-audit-20261105-100000.pdf');
        Http::assertSent(fn (HttpRequest $request): bool => str_ends_with($request->url(), '/forms/chromium/convert/html')
            && str_contains($request->body(), 'PRD-2026-0002') && str_contains($request->body(), 'Sirup Temulawak'));
        $this->assertSame('pdf', Activity::query()->where('event', 'audit.exported')->sole()->attribute_changes['attributes']['format']);

        $this->gotenbergDown = true;
        $this->from('/audit')->get('/audit/export/pdf')->assertRedirect('/audit')
            ->assertSessionHasErrors(['export' => 'PDF gagal dibuat. Coba lagi atau gunakan ekspor CSV.']);
    }

    public function test_admins_verify_the_hash_chain_and_see_entries_that_do_not_match(): void
    {
        $admin = $this->staff('admin');

        $this->actingAs($admin)->post('/audit/verify')->assertRedirect('/audit')
            ->assertSessionHas('audit_integrity', fn (array $result): bool => $result['valid'] && $result['checked'] === Activity::query()->count() - 1);

        $last = Activity::query()->orderByDesc('id')->first();
        DB::table('activity_log')->insert([
            'log_name' => 'ticket', 'description' => 'ticket.created', 'event' => 'ticket.created', 'source' => 'system', 'ticket_code' => 'PRD-2026-0099',
            'attribute_changes' => '{"attributes":{"product_name":"Disisipkan"}}', 'properties' => '[]',
            'prev_hash' => $last->hash, 'hash' => str_repeat('f', 64), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->post('/audit/verify');
        $this->withoutVite()->get('/audit')->assertInertia(fn (Assert $page) => $page
            ->where('integrity.valid', false)
            ->where('integrity.mismatches.0.event', 'ticket.created')
            ->where('integrity.mismatches.0.reason', 'isi tidak sesuai hash')
            ->where('urls.verify', '/audit/verify'));
    }

    public function test_only_audit_viewers_export_and_only_admins_verify(): void
    {
        $this->actingAs($this->staff('manager'))->post('/audit/verify')->assertForbidden();
        $this->withoutVite()->get('/audit')->assertInertia(fn (Assert $page) => $page->where('urls.verify', null)->where('urls.csv', '/audit/export/csv'));
        $this->actingAs($this->staff('ra'))->get('/audit/export/csv')->assertForbidden();
    }

    private function staff(string $role): User
    {
        $user = User::factory()->create(['email' => "{$role}@aisha.test"]);
        $user->assignRole($role);

        return $user;
    }
}
