<?php

namespace App\Http\Controllers;

use App\Models\LabTest;
use App\Models\Ticket;
use App\Services\AuditLogger;
use App\StagePhase;
use App\TicketStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LabTestController extends Controller
{
    private const STAGE = 'uji_laboratorium';

    /**
     * RA atau R&D mencatat pengiriman sampel ke laboratorium eksternal (FR-87). Uji berjalan
     * sejajar dengan pra-registrasi (FR-88, BR-24); pengiriman setelah hasil tidak sesuai
     * dicatat sebagai analisis ulang dan tahap uji laboratorium tetap berjalan.
     */
    public function store(Request $request, Ticket $ticket, AuditLogger $audit): RedirectResponse
    {
        $this->authorizeTicket($request, $ticket);
        $data = $request->validate([
            'laboratory' => ['required', 'string', 'max:150'],
            'sent_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'estimated_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:sent_on'],
        ], [
            'laboratory.required' => 'Nama laboratorium wajib diisi.',
            'sent_on.required' => 'Tanggal kirim wajib diisi.',
            'sent_on.before_or_equal' => 'Tanggal kirim tidak boleh setelah hari ini.',
            'estimated_on.required' => 'Perkiraan selesai wajib diisi.',
            'estimated_on.after_or_equal' => 'Perkiraan selesai tidak boleh sebelum tanggal kirim.',
        ]);

        $labTest = DB::transaction(function () use ($ticket, $data, $request): LabTest {
            Ticket::query()->whereKey($ticket->id)->lockForUpdate()->first();
            $tests = $ticket->labTests()->get();

            if ($tests->contains(fn (LabTest $test): bool => $test->result_on === null || $test->conforms === true)) {
                throw ValidationException::withMessages(['laboratory' => 'Uji laboratorium sebelumnya masih berjalan atau sudah sesuai spesifikasi.']);
            }

            $labTest = $ticket->labTests()->create([...$data, 'attempt' => $tests->count() + 1, 'recorded_by' => $request->user()->id]);
            $ticket->startParallelStage(self::STAGE);

            return $labTest;
        });

        $audit->record($labTest->attempt > 1 ? 'lab_test.reanalysis' : 'lab_test.sent', $labTest, after: [
            'attempt' => $labTest->attempt,
            'laboratory' => $labTest->laboratory,
            'estimated_on' => $labTest->estimated_on->toDateString(),
        ], ticketCode: $ticket->code);

        return back()->with('status', $labTest->attempt > 1 ? 'Analisis ulang dicatat; tahap uji laboratorium diperpanjang.' : 'Pengiriman sampel ke laboratorium dicatat.');
    }

    /**
     * Unggah LHU dan catat kesesuaiannya dengan spesifikasi. LHU yang sesuai menyelesaikan
     * tahap uji laboratorium dan menjadi syarat pembukaan fase registrasi (FR-88).
     */
    public function result(Request $request, LabTest $labTest, AuditLogger $audit): RedirectResponse
    {
        $ticket = $labTest->ticket;
        $this->authorizeTicket($request, $ticket);
        $data = $request->validate([
            'result_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today', 'after_or_equal:'.$labTest->sent_on->toDateString()],
            'conforms' => ['required', 'boolean'],
            'note' => ['required_if:conforms,0', 'nullable', 'string', 'max:2000'],
            'file' => ['bail', 'required', 'file', 'extensions:pdf,jpg,jpeg,png', 'mimes:pdf,jpg,jpeg,png', 'max:20480'],
        ], [
            'result_on.required' => 'Tanggal LHU wajib diisi.',
            'result_on.after_or_equal' => 'Tanggal LHU tidak boleh sebelum tanggal kirim.',
            'conforms.required' => 'Pilih apakah hasil sesuai spesifikasi.',
            'note.required_if' => 'Jelaskan parameter yang tidak sesuai spesifikasi.',
            'file.required' => 'Unggah lembar hasil uji (LHU).',
            'file.extensions' => 'LHU harus berupa PDF, JPG, atau PNG.',
            'file.mimes' => 'LHU harus berupa PDF, JPG, atau PNG.',
            'file.max' => 'Ukuran berkas maksimal 20 MB.',
        ]);

        if ($labTest->result_on !== null) {
            throw ValidationException::withMessages(['file' => 'LHU untuk pengiriman ini sudah dicatat.']);
        }

        $file = $request->file('file');
        $path = Storage::disk('local')->putFileAs("lab-tests/{$labTest->id}", $file, 'lhu-'.Str::uuid().'.'.strtolower($file->getClientOriginalExtension()));
        $conforms = (bool) $data['conforms'];

        DB::transaction(function () use ($labTest, $data, $file, $path, $conforms, $ticket): void {
            $labTest->update([
                'result_on' => $data['result_on'],
                'conforms' => $conforms,
                'note' => $data['note'] ?? null,
                'result_path' => $path,
                'result_original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            ]);

            if ($conforms) {
                $ticket->finishStage(self::STAGE);
            }
        });

        $audit->record('lab_test.result', $labTest, after: ['attempt' => $labTest->attempt, 'conforms' => $conforms, 'note' => $labTest->note], ticketCode: $ticket->code);

        return back()->with('status', $conforms ? 'LHU sesuai spesifikasi tercatat.' : 'LHU tidak sesuai spesifikasi tercatat; catat analisis ulang setelah sampel dikirim kembali.');
    }

    public function file(Request $request, LabTest $labTest): StreamedResponse
    {
        abort_unless($labTest->ticket->isVisibleTo($request->user()), 403);
        abort_if($labTest->result_path === null, 404);

        return Storage::disk('local')->download($labTest->result_path, $labTest->result_original_name);
    }

    private function authorizeTicket(Request $request, Ticket $ticket): void
    {
        abort_unless($request->user()->can('lab_tests.manage') && $ticket->isVisibleTo($request->user()), 403);

        if ($ticket->status !== TicketStatus::Active || ! in_array($ticket->phase, [StagePhase::PreRegistration, StagePhase::Registration], true)) {
            throw ValidationException::withMessages(['laboratory' => 'Uji laboratorium dicatat untuk tiket aktif di fase pra-registrasi atau registrasi.']);
        }
    }
}
