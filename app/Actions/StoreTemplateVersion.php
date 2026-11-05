<?php

namespace App\Actions;

use App\Models\DocumentTemplate;
use App\Models\TemplateVersion;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class StoreTemplateVersion
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * Simpan berkas di disk privat sebagai versi berikutnya dari templat (FR-68).
     * Templat yang belum tersimpan dibuat bersama versi pertamanya.
     *
     * @param  list<string>  $placeholders
     */
    public function handle(DocumentTemplate $template, UploadedFile $file, array $placeholders, User $actor, ?string $notes = null): TemplateVersion
    {
        $disk = Storage::disk('local');
        $storedPath = null;

        try {
            return DB::transaction(function () use ($template, $file, $placeholders, $actor, $notes, $disk, &$storedPath): TemplateVersion {
                $creating = ! $template->exists;

                if ($creating) {
                    $template->save();
                } else {
                    DocumentTemplate::query()->whereKey($template->id)->lockForUpdate()->first();
                }

                $number = (int) $template->versions()->max('version') + 1;
                $storedPath = $disk->putFileAs("templates/{$template->id}", $file, "v{$number}-".Str::uuid().'.docx');

                $version = $template->versions()->create([
                    'version' => $number,
                    'path' => $storedPath,
                    'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                    'size' => $file->getSize(),
                    'checksum' => hash_file('sha256', $file->getRealPath()),
                    'placeholders' => $placeholders,
                    'notes' => $notes,
                    'uploaded_by' => $actor->id,
                ]);

                $this->audit->record($creating ? 'template.created' : 'template.version_uploaded', $template, after: [
                    'name' => $template->name,
                    'kind' => $template->kind->value,
                    'version' => $number,
                    'placeholders' => $placeholders,
                    'checksum' => $version->checksum,
                ], actor: $actor);

                return $version;
            });
        } catch (Throwable $exception) {
            if ($storedPath !== null) {
                $disk->delete($storedPath);
            }

            throw $exception;
        }
    }
}
