<?php

namespace Database\Factories;

use App\Models\Ticket;
use App\Models\UploadToken;
use App\Models\User;
use App\StagePhase;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<UploadToken>
 */
class UploadTokenFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'phase' => StagePhase::PreRegistration,
            'token_hash' => UploadToken::hash(Str::random(UploadToken::TOKEN_LENGTH)),
            'expires_at' => now()->addDays(14),
            'created_by' => User::factory(),
        ];
    }

    /** Token dengan nilai asli yang diketahui, untuk membuka halaman unggah di tes. */
    public function withToken(string $token): static
    {
        return $this->state(['token_hash' => UploadToken::hash($token)]);
    }
}
