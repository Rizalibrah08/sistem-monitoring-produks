<?php

namespace Database\Seeders;

use App\Actions\CreateTicket;
use App\DocumentRequirement;
use App\DocumentScope;
use App\Models\Category;
use App\Models\Client;
use App\Models\DocumentRule;
use App\Models\DocumentType;
use App\Models\DosageForm;
use App\Models\Holiday;
use App\Models\Material;
use App\Models\Ticket;
use App\Models\User;
use App\StagePhase;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Data contoh untuk demo lokal: akun setiap role, master data, klien, dan tiket yang
 * dibuat lewat alur aplikasi sehingga checklist tersusun otomatis. Semua nama diberi
 * label contoh dan nomor WhatsApp fiktif. Seeder menolak berjalan di luar local/testing.
 *
 *   php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'AishaDemo2026';

    /** @var array<string, array{string, string}> role => [email, nama] */
    private const USERS = [
        'admin' => ['admin@aisha.test', 'Admin Demo'],
        'marketing' => ['marketing@aisha.test', 'Rina Marketing'],
        'rnd' => ['rnd@aisha.test', 'Dimas R&D'],
        'qa' => ['qa@aisha.test', 'Sari QA/QC'],
        'ra' => ['ra@aisha.test', 'Aditya Regulatory'],
        'finance' => ['finance@aisha.test', 'Bayu Finance'],
        'manager' => ['manajer@aisha.test', 'Hendra Manajer'],
    ];

    public function __construct(private CreateTicket $createTicket) {}

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Data demo hanya boleh dibuat di lingkungan local atau testing.');
        }

        $this->call(DatabaseSeeder::class);

        $users = $this->users();
        $forms = $this->named(DosageForm::class, ['Kapsul', 'Serbuk', 'Cairan obat dalam', 'Tablet']);
        $categories = $this->named(Category::class, ['Jamu', 'Obat Herbal Terstandar']);
        $this->materials();
        $this->holidays();
        $this->documentTypes($forms['Cairan obat dalam']);
        $this->tickets($users['marketing'], $forms, $categories);
    }

    /** @return array<string, User> */
    private function users(): array
    {
        $users = [];

        foreach (self::USERS as $role => [$email, $name]) {
            $user = User::query()->firstOrCreate(['email' => $email], ['name' => $name, 'password' => self::PASSWORD]);
            $user->forceFill(['is_active' => true])->save();
            $user->syncRoles($role);
            $users[$role] = $user;
        }

        return $users;
    }

    /**
     * @param  class-string<DosageForm|Category>  $model
     * @param  list<string>  $names
     * @return array<string, DosageForm|Category>
     */
    private function named(string $model, array $names): array
    {
        $records = [];

        foreach ($names as $name) {
            $records[$name] = $model::query()->firstOrCreate(['name' => $name]);
        }

        return $records;
    }

    private function materials(): void
    {
        foreach (['Jahe merah' => 'CV Rempah Nusantara (contoh)', 'Temulawak' => 'CV Rempah Nusantara (contoh)', 'Kunyit' => 'PT Herba Lestari (contoh)', 'Sambiloto' => null] as $name => $supplier) {
            Material::query()->firstOrCreate(['name' => $name], ['supplier' => $supplier]);
        }
    }

    /** Hanya libur nasional bertanggal tetap; libur lain diisi Admin sesuai SKB tahun berjalan. */
    private function holidays(): void
    {
        $year = now()->year;

        foreach (["{$year}-01-01" => 'Tahun Baru Masehi', "{$year}-08-17" => 'Hari Kemerdekaan RI', "{$year}-12-25" => 'Hari Raya Natal'] as $date => $name) {
            Holiday::query()->firstOrCreate(['date' => $date], ['name' => $name]);
        }
    }

    private function documentTypes(DosageForm $liquid): void
    {
        $types = [
            ['Surat kuasa', StagePhase::BothPhases, DocumentScope::Client, DocumentRequirement::Required, 'client', null, false, null],
            ['Surat pernyataan keabsahan dokumen', StagePhase::BothPhases, DocumentScope::Client, DocumentRequirement::Required, 'client', null, false, null],
            ['Master formula', StagePhase::PreRegistration, DocumentScope::Ticket, DocumentRequirement::Required, 'rnd', null, false, null],
            ['Spesifikasi produk jadi', StagePhase::PreRegistration, DocumentScope::Ticket, DocumentRequirement::Required, 'qa', 'qa', false, null],
            ['Asal peredaran bahan', StagePhase::PreRegistration, DocumentScope::Material, DocumentRequirement::Required, 'rnd', null, false, null],
            ['CoA bahan baku', StagePhase::Registration, DocumentScope::Ticket, DocumentRequirement::Required, 'qa', 'qa', false, 6],
            ['CoA produk jadi', StagePhase::Registration, DocumentScope::Ticket, DocumentRequirement::Required, 'qa', 'qa', false, 6],
            ['Sistem penomoran batch', StagePhase::Registration, DocumentScope::Company, DocumentRequirement::Required, 'qa', null, false, null],
            ['Sertifikat halal', StagePhase::Registration, DocumentScope::Company, DocumentRequirement::Required, 'qa', null, true, null],
            ['Desain kemasan', StagePhase::Registration, DocumentScope::Ticket, DocumentRequirement::Required, 'marketing', null, false, null],
            ['Uji cemaran mikroba', StagePhase::Registration, DocumentScope::Ticket, DocumentRequirement::Conditional, 'qa', 'qa', false, 6],
        ];

        foreach ($types as [$name, $phase, $scope, $requirement, $pic, $verifier, $requiresExpiry, $maxAge]) {
            DocumentType::query()->firstOrCreate(['name' => $name], [
                'phase' => $phase,
                'scope' => $scope,
                'requirement' => $requirement,
                'pic_role' => $pic,
                'verifier_role' => $verifier,
                'requires_expiry_date' => $requiresExpiry,
                'max_age_months' => $maxAge,
            ]);
        }

        DocumentRule::query()->firstOrCreate([
            'document_type_id' => DocumentType::query()->where('name', 'Uji cemaran mikroba')->value('id'),
            'dosage_form_id' => $liquid->id,
            'category_id' => null,
            'effective_from' => now()->startOfYear()->toDateString(),
        ], ['notes' => 'Contoh: sediaan cair wajib uji cemaran mikroba.']);
    }

    /**
     * @param  array<string, DosageForm|Category>  $forms
     * @param  array<string, DosageForm|Category>  $categories
     */
    private function tickets(User $marketing, array $forms, array $categories): void
    {
        $nusantara = Client::query()->firstOrCreate(['whatsapp' => '6281200000001'], [
            'name' => 'Nusantara Herbal (contoh)', 'company' => 'CV Nusantara Herbal', 'pic_name' => 'Nadia Putri',
            'email' => 'nadia@nusantara.test', 'marketing_user_id' => $marketing->id,
        ]);
        $bumi = Client::query()->firstOrCreate(['whatsapp' => '6281200000002'], [
            'name' => 'Bumi Sehat Lestari (contoh)', 'company' => 'PT Bumi Sehat Lestari', 'pic_name' => 'Agus Santoso',
            'email' => 'agus@bumisehat.test', 'marketing_user_id' => $marketing->id,
        ]);

        if (Ticket::query()->whereIn('client_id', [$nusantara->id, $bumi->id])->exists()) {
            return;
        }

        $tickets = [
            [$nusantara, 'Kapsul Jahe Merah', 'Kapsul', 'Jamu'],
            [$nusantara, 'Sirup Temulawak', 'Cairan obat dalam', 'Jamu'],
            [$bumi, 'Serbuk Kunyit Asam', 'Serbuk', 'Obat Herbal Terstandar'],
        ];

        foreach ($tickets as [$client, $product, $form, $category]) {
            $this->createTicket->handle([
                'client_id' => $client->id,
                'product_name' => $product,
                'dosage_form_id' => $forms[$form]->id,
                'category_id' => $categories[$category]->id,
            ], $marketing);
        }
    }
}
