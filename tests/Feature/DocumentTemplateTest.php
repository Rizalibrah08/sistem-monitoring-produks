<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\DocumentTemplate;
use App\Models\TemplateVersion;
use App\Models\User;
use App\TemplateKind;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

class DocumentTemplateTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_staff_without_master_access_cannot_view_or_upload_templates(): void
    {
        $regulatory = $this->createStaff('ra');

        $this->actingAs($regulatory)->get('/templates')->assertForbidden();
        $this->post('/templates', ['name' => 'Kontrak', 'kind' => 'kontrak', 'file' => $this->wordFile('Yth. ${nama_klien}')])->assertForbidden();

        $this->assertDatabaseCount('document_templates', 0);
        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    public function test_admin_uploads_a_template_whose_placeholders_are_recorded_as_version_one(): void
    {
        $admin = $this->createStaff('admin');

        $this->actingAs($admin)->from('/templates')->post('/templates', [
            'name' => 'Kontrak Maklon dan Pendaftaran',
            'kind' => 'kontrak',
            'file' => $this->wordFile('Kontrak ${nomor_kontrak} untuk ${nama_klien}', 'Nilai jasa ${nilai_jasa}, klien ${nama_klien}'),
        ])->assertRedirect('/templates')->assertSessionHasNoErrors();

        $template = DocumentTemplate::query()->sole();
        $this->assertSame(TemplateKind::Contract, $template->kind);

        $version = $template->versions()->sole();
        $this->assertSame(1, $version->version);
        $this->assertSame(['nama_klien', 'nilai_jasa', 'nomor_kontrak'], $version->placeholders);
        $this->assertSame($admin->id, $version->uploaded_by);
        $this->assertSame(64, strlen($version->checksum));
        Storage::disk('local')->assertExists($version->path);
        $this->assertStringStartsWith("templates/{$template->id}/", $version->path);
        $this->assertDatabaseHas('activity_log', ['event' => 'template.created', 'subject_id' => $template->id, 'causer_id' => $admin->id]);
    }

    public function test_unknown_placeholders_are_rejected_with_their_names_and_nothing_is_stored(): void
    {
        $admin = $this->createStaff('admin');

        $this->actingAs($admin)->from('/templates')->post('/templates', [
            'name' => 'Surat kuasa',
            'kind' => 'surat_kuasa',
            'file' => $this->wordFile('Yth. ${nama_klien} di ${alamat_gudang}', 'Diskon ${diskon}'),
        ])->assertSessionHasErrors(['file' => 'Penanda tidak dikenal: ${alamat_gudang}, ${diskon}. Gunakan penanda dari daftar variabel yang diizinkan.']);

        $this->assertDatabaseCount('document_templates', 0);
        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    public function test_files_that_are_not_valid_word_documents_are_rejected(): void
    {
        $admin = $this->createStaff('admin');

        $this->actingAs($admin)->from('/templates')
            ->post('/templates', ['name' => 'Tagihan', 'kind' => 'tagihan', 'file' => UploadedFile::fake()->createWithContent('tagihan.txt', 'Tagihan ${nominal_tagihan}')])
            ->assertSessionHasErrors(['file' => 'Templat harus berupa berkas Word (.docx).']);
        $this->post('/templates', ['name' => 'Tagihan', 'kind' => 'tagihan', 'file' => UploadedFile::fake()->createWithContent('tagihan.docx', 'bukan dokumen word')])
            ->assertSessionHasErrors(['file' => 'Berkas bukan dokumen Word (.docx) yang valid.']);
        $this->post('/templates', ['name' => '', 'kind' => 'brosur'])->assertSessionHasErrors(['name', 'kind', 'file']);

        $this->assertDatabaseCount('document_templates', 0);
    }

    public function test_a_new_upload_becomes_the_next_version_and_earlier_versions_are_kept(): void
    {
        $admin = $this->createStaff('admin');
        $this->actingAs($admin)->from('/templates')->post('/templates', [
            'name' => 'Tagihan termin', 'kind' => 'tagihan', 'file' => $this->wordFile('Tagihan ${nomor_tagihan}'),
        ])->assertSessionHasNoErrors();
        $template = DocumentTemplate::query()->sole();

        $this->post("/templates/{$template->id}/versions", ['file' => $this->wordFile('Tagihan ${nomor_tagihan} sebesar ${nominal_tagihan}'), 'notes' => 'Tambah nominal'])
            ->assertRedirect('/templates')->assertSessionHasNoErrors();

        $this->assertSame([1, 2], $template->versions()->orderBy('version')->pluck('version')->all());
        $latest = $template->versions()->where('version', 2)->sole();
        $this->assertSame(['nominal_tagihan', 'nomor_tagihan'], $latest->placeholders);
        $this->assertSame('Tambah nominal', $latest->notes);
        $this->assertCount(2, Storage::disk('local')->allFiles("templates/{$template->id}"));
        $this->assertSame(['template.created', 'template.version_uploaded'], Activity::query()->orderBy('id')->pluck('event')->all());
    }

    public function test_admin_sees_templates_with_their_version_history_and_the_allowed_variables(): void
    {
        $admin = $this->createStaff('admin', ['name' => 'Admin Aisha']);
        $this->actingAs($admin)->from('/templates')->post('/templates', [
            'name' => 'Surat pernyataan keabsahan', 'kind' => 'surat_pernyataan', 'file' => $this->wordFile('Saya ${nama_pic_klien}'),
        ]);
        $template = DocumentTemplate::query()->sole();
        $this->post("/templates/{$template->id}/versions", ['file' => $this->wordFile('Saya ${nama_pic_klien} dari ${nama_klien}')]);

        $this->withoutVite()->get('/templates')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Backend/Templates', false)
            ->has('templates', 1)
            ->where('templates.0.name', 'Surat pernyataan keabsahan')
            ->where('templates.0.kind_label', 'Surat pernyataan')
            ->where('templates.0.current_version', 2)
            ->has('templates.0.versions', 2)
            ->where('templates.0.versions.0.version', 2)
            ->where('templates.0.versions.0.placeholders', ['nama_klien', 'nama_pic_klien'])
            ->where('templates.0.versions.0.uploaded_by', 'Admin Aisha')
            ->where('templates.0.upload_url', "/templates/{$template->id}/versions")
            ->has('kinds', 5)
            ->where('variables', fn ($variables): bool => collect($variables)->pluck('name')->contains('nomor_kontrak'))
            ->where('navigation', fn ($items): bool => collect($items)->contains(fn (array $item): bool => $item['id'] === 'templates' && $item['available'] === true)));
    }

    public function test_only_admin_can_download_a_stored_version(): void
    {
        $admin = $this->createStaff('admin');
        $this->actingAs($admin)->from('/templates')->post('/templates', [
            'name' => 'Kontrak', 'kind' => 'kontrak', 'file' => $this->wordFile('Kontrak ${nomor_kontrak}'),
        ]);
        $version = TemplateVersion::query()->sole();

        $this->get("/templates/versions/{$version->id}/download")->assertOk()->assertDownload('Kontrak v1.docx');

        $regulatory = $this->createStaff('ra', ['email' => 'ra@aisha.test']);
        $this->actingAs($regulatory)->get("/templates/versions/{$version->id}/download")->assertForbidden();
    }

    private function wordFile(string ...$paragraphs): UploadedFile
    {
        $word = new PhpWord;
        $section = $word->addSection();

        foreach ($paragraphs as $paragraph) {
            $section->addText($paragraph);
        }

        $path = tempnam(sys_get_temp_dir(), 'templat');
        IOFactory::createWriter($word, 'Word2007')->save($path);

        return UploadedFile::fake()->createWithContent('templat.docx', (string) file_get_contents($path));
    }

    /** @param array<string, mixed> $attributes */
    private function createStaff(string $role, array $attributes = []): User
    {
        $this->seed();
        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user;
    }
}
