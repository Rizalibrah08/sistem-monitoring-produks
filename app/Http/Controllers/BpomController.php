<?php

namespace App\Http\Controllers;

use App\Models\BpomRequest;
use App\Models\BpomSubmission;
use App\Models\Ticket;
use App\Services\BpomWorkflow;
use App\StagePhase;
use App\SubmissionStatus;
use App\TicketStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BpomController extends Controller
{
    /**
     * Pengajuan yang menunggu hasil BPOM, tambahan data yang belum dipenuhi, dan tenggat
     * registrasi yang berjalan, diurutkan dari tenggat terdekat (F08).
     */
    public function index(): Response
    {
        $today = now()->startOfDay();

        $submissions = BpomSubmission::query()
            ->whereIn('status', [SubmissionStatus::AwaitingResult, SubmissionStatus::ResultIssued])
            ->whereHas('ticket', fn (Builder $query) => $query->where('status', TicketStatus::Active))
            ->where(fn (Builder $query) => $query->where('status', SubmissionStatus::AwaitingResult)
                ->orWhere(fn (Builder $query) => $query->where('phase', StagePhase::PreRegistration)
                    ->whereDoesntHave('ticket.bpomSubmissions', fn (Builder $query) => $query->where('phase', StagePhase::Registration)->where('status', '!=', SubmissionStatus::Expired))))
            ->with(['ticket.client', 'requests'])
            ->get()
            ->map(function (BpomSubmission $submission) use ($today): array {
                $open = $submission->requests->whereNull('responded_at')->sortBy('deadline')->first();
                $deadline = $open?->deadline ?? $submission->registration_deadline;

                return [
                    'id' => $submission->id,
                    'ticket_code' => $submission->ticket->code,
                    'product_name' => $submission->ticket->product_name,
                    'client' => $submission->ticket->client->name,
                    'ticket_url' => route('tickets.show', $submission->ticket_id, absolute: false),
                    'phase_label' => $submission->phase->label(),
                    'submitted_on' => $submission->submitted_on->format('d/m/Y'),
                    'pnbp' => $submission->pnbp,
                    'status_label' => $open !== null ? "Tambahan data {$open->code()}" : ($submission->status === SubmissionStatus::ResultIssued ? 'Menunggu pengajuan registrasi' : $submission->status->label()),
                    'deadline' => $deadline?->format('d/m/Y'),
                    'deadline_passed' => $deadline !== null && $deadline->lt($today),
                    'rejection_risk' => $submission->requests->max('sequence') === BpomRequest::MAX_SEQUENCE,
                    'sort' => $deadline?->toDateString() ?? '9999-12-31',
                ];
            })
            ->sortBy('sort')->values();

        return Inertia::render('Backend/Bpom', ['submissions' => $submissions]);
    }

    public function submit(Request $request, Ticket $ticket, BpomWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate([
            'submitted_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'pnbp' => ['required', 'integer', 'min:0', 'max:1000000000'],
        ], [
            'submitted_on.required' => 'Tanggal pengajuan wajib diisi.',
            'submitted_on.before_or_equal' => 'Tanggal pengajuan tidak boleh setelah hari ini.',
            'pnbp.required' => 'Nilai PNBP wajib diisi.',
            'pnbp.integer' => 'Nilai PNBP harus berupa angka rupiah.',
        ]);

        $submission = $workflow->submit($ticket, $data, $request->user());

        return back()->with('status', "Pengajuan {$submission->phase->label()} tercatat; versi dokumen yang diajukan dikunci.");
    }

    public function result(Request $request, BpomSubmission $submission, BpomWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate([
            'result_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today', 'after_or_equal:'.$submission->submitted_on->toDateString()],
            'file' => ['bail', 'required', 'file', 'extensions:pdf,jpg,jpeg,png', 'mimes:pdf,jpg,jpeg,png', 'max:20480'],
        ], [
            'result_on.required' => 'Tanggal hasil wajib diisi.',
            'result_on.after_or_equal' => 'Tanggal hasil tidak boleh sebelum tanggal pengajuan.',
            'result_on.before_or_equal' => 'Tanggal hasil tidak boleh setelah hari ini.',
            'file.required' => 'Unggah dokumen hasil pra-registrasi.',
            'file.extensions' => 'Dokumen hasil harus berupa PDF, JPG, atau PNG.',
            'file.mimes' => 'Dokumen hasil harus berupa PDF, JPG, atau PNG.',
            'file.max' => 'Ukuran berkas maksimal 20 MB.',
        ]);

        $submission = $workflow->recordPreRegistrationResult($submission, $data['result_on'], $request->file('file'), $request->user());

        return back()->with('status', "Hasil pra-registrasi tercatat. Registrasi harus diajukan paling lambat {$submission->registration_deadline->format('d/m/Y')}.");
    }

    public function requestData(Request $request, BpomSubmission $submission, BpomWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
            'requested_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'item_ids' => ['array'],
            'item_ids.*' => ['integer', Rule::exists('ticket_documents', 'id')->where('ticket_id', $submission->ticket_id)],
        ], [
            'note.required' => 'Isi catatan atau tambahan data BPOM wajib diisi.',
            'requested_on.required' => 'Tanggal permintaan wajib diisi.',
            'item_ids.*.exists' => 'Dokumen terkait harus berasal dari tiket ini.',
        ]);

        $bpomRequest = $workflow->requestAdditionalData($submission, $data['note'], $data['requested_on'], array_map('intval', $data['item_ids'] ?? []), $request->user());

        return back()->with('status', "Tambahan data {$bpomRequest->code()} tercatat dengan tenggat {$bpomRequest->deadline->format('d/m/Y')}.");
    }

    public function respond(Request $request, BpomRequest $bpomRequest, BpomWorkflow $workflow): RedirectResponse
    {
        $workflow->respond($bpomRequest, $request->user());

        return back()->with('status', "Tanggapan {$bpomRequest->code()} ditandai sudah dikirim ke BPOM.");
    }

    public function reject(Request $request, Ticket $ticket, BpomWorkflow $workflow): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']], ['reason.required' => 'Alasan penolakan wajib diisi.']);
        $workflow->reject($ticket, $data['reason'], $request->user());

        return back()->with('status', "Tiket {$ticket->code} ditutup sebagai ditolak BPOM.");
    }

    public function openRegistration(Request $request, Ticket $ticket, BpomWorkflow $workflow): RedirectResponse
    {
        $workflow->openRegistration($ticket, $request->user());

        return back()->with('status', 'Fase registrasi dibuka.');
    }

    /** Dokumen hasil pra-registrasi untuk staf yang dapat melihat tiketnya. */
    public function resultFile(Request $request, BpomSubmission $submission): StreamedResponse
    {
        abort_unless($submission->ticket->isVisibleTo($request->user()), 403);
        abort_if($submission->result_path === null, 404);

        return Storage::disk('local')->download($submission->result_path, $submission->result_original_name);
    }
}
