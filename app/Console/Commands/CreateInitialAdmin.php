<?php

namespace App\Console\Commands;

use App\Actions\BootstrapAdministrator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

#[Signature('app:create-admin {email : Email admin awal} {--name= : Nama admin} {--password-stdin : Baca kata sandi dari stdin, bukan argumen command line}')]
#[Description('Membuat admin awal secara aman tanpa akun atau kata sandi bawaan')]
class CreateInitialAdmin extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(BootstrapAdministrator $bootstrap): int
    {
        $name = $this->option('name') ?? $this->ask('Nama admin');
        $password = $this->option('password-stdin')
            ? rtrim(stream_get_contents(STDIN), "\r\n")
            : $this->secret('Kata sandi');

        try {
            $user = $bootstrap->handle((string) $name, (string) $this->argument('email'), $password);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->error($message);
                }
            }

            return self::FAILURE;
        }

        $this->info('Admin awal berhasil dibuat: '.$user->email);

        return self::SUCCESS;
    }
}
