<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AuditHasher;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_each_activity_is_linked_to_the_previous_sha256_hash(): void
    {
        $this->freezeTime();
        $first = activity('access')->event('auth.login')->log('Staf masuk');
        $second = activity('access')->event('auth.logout')->log('Staf keluar');

        $this->assertSame(str_repeat('0', 64), $first->prev_hash);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $first->hash);
        $this->assertSame($first->hash, $second->prev_hash);
        $this->assertNotSame($first->hash, $second->hash);
        $this->assertSame($second->hash, app(AuditHasher::class)->hash($second->fresh()));
    }

    public function test_actor_role_and_name_are_snapshots_and_passwords_are_not_logged(): void
    {
        $this->seed();
        $user = User::factory()->create(['name' => 'Petugas RA']);
        $user->assignRole('ra');

        $entry = activity('users')->causedBy($user)
            ->withChanges(['old' => ['password' => 'old-secret'], 'attributes' => ['password' => 'new-secret', 'name' => 'Nama baru']])
            ->withProperties(['password_confirmation' => 'new-secret', 'nested' => ['access_token' => 'private-token', 'note' => 'aman']])
            ->log('Pengguna diperbarui');
        $user->syncRoles('marketing');

        $this->assertSame('ra', $entry->fresh()->actor_role);
        $this->assertSame('Petugas RA', $entry->actor_name);
        $this->assertStringNotContainsString('secret', $entry->toJson());
        $this->assertStringNotContainsString('private-token', $entry->toJson());
        $this->assertSame('Nama baru', $entry->attribute_changes['attributes']['name']);
        $this->assertSame('aman', $entry->properties['nested']['note']);
    }

    public function test_eloquent_cannot_modify_an_existing_audit_entry(): void
    {
        $entry = activity()->log('Aksi asli');

        $this->assertThrows(fn () => $entry->update(['description' => 'Diubah']), LogicException::class);

        $this->assertSame('Aksi asli', $entry->fresh()->description);
    }

    public function test_database_updates_cannot_bypass_append_only_protection(): void
    {
        $entry = activity()->log('Aksi asli');

        $this->assertThrows(fn () => DB::table('activity_log')->where('id', $entry->id)->update(['description' => 'Diubah']), QueryException::class);

        $this->assertDatabaseHas('activity_log', ['id' => $entry->id, 'description' => 'Aksi asli']);
    }

    public function test_database_deletes_cannot_bypass_append_only_protection(): void
    {
        $entry = activity()->log('Aksi asli');

        $this->assertThrows(fn () => DB::table('activity_log')->where('id', $entry->id)->delete(), QueryException::class);

        $this->assertDatabaseCount('activity_log', 1);
    }

    public function test_business_changes_and_audit_entries_roll_back_together(): void
    {
        try {
            DB::transaction(function (): void {
                $user = User::factory()->create();
                activity()->performedOn($user)->log('Pengguna dibuat');

                throw new LogicException('Operasi dibatalkan');
            });
        } catch (LogicException) {
        }

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('activity_log', 0);
    }
}
