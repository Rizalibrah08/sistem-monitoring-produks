<?php

use App\Http\Controllers\AuditController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\BpomController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientUploadController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\CriterionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentRuleController;
use App\Http\Controllers\DocumentTemplateController;
use App\Http\Controllers\DocumentTypeController;
use App\Http\Controllers\DocumentVerificationController;
use App\Http\Controllers\DocumentVersionDownloadController;
use App\Http\Controllers\DosageFormController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LabTestController;
use App\Http\Controllers\MasterDataController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\MidtransNotificationController;
use App\Http\Controllers\NieController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PreviewController;
use App\Http\Controllers\PriorityController;
use App\Http\Controllers\ReceivableController;
use App\Http\Controllers\SampleApprovalController;
use App\Http\Controllers\StageController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TicketDocumentVersionController;
use App\Http\Controllers\TicketMaterialController;
use App\Http\Controllers\TicketReviewController;
use App\Http\Controllers\UploadTokenController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WorkspaceModuleController;
use App\Http\Middleware\EnsureActiveStaffSession;
use App\Http\Middleware\EnsureWorkspaceModuleAccess;
use App\Models\Criterion;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::get('/unggah/{token}', [ClientUploadController::class, 'show'])->middleware('throttle:60,1')->name('client-uploads.show');
Route::get('/unggah/{token}/kontrak', [ClientUploadController::class, 'contract'])->middleware('throttle:60,1')->name('client-uploads.contract');
Route::post('/unggah/{token}/kontrak', [ClientUploadController::class, 'signContract'])->middleware('throttle:20,1')->name('client-uploads.contract.signed');
Route::post('/unggah/{token}/tagihan/{invoice}/bayar', [ClientUploadController::class, 'pay'])->middleware('throttle:10,1')->name('client-uploads.invoices.pay');
Route::post('/midtrans/notification', MidtransNotificationController::class)->middleware('throttle:120,1')->name('midtrans.notification');
Route::post('/unggah/{token}/dokumen/{ticketDocument}', [ClientUploadController::class, 'store'])->middleware('throttle:20,1')->name('client-uploads.store');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login')->name('login.store');
});

Route::middleware(['auth', EnsureActiveStaffSession::class])->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->middleware('can:dashboard.view')->name('dashboard');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/priorities', PriorityController::class)->middleware('can:priorities.view')->name('priorities.index');
    Route::get('/notifications/{notification}/open', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('/notifications/read', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::resource('users', UserController::class)->only(['index', 'store', 'update'])->middleware('can:users.manage');
    Route::get('/audit', AuditController::class)->middleware('can:audit.view')->name('audit.index');

    Route::middleware('can:master.manage')->prefix('master')->name('master.')->group(function (): void {
        Route::get('/', MasterDataController::class)->name('index');
        Route::post('/dosage-forms', [DosageFormController::class, 'store'])->name('dosage-forms.store');
        Route::put('/dosage-forms/{dosageForm}', [DosageFormController::class, 'update'])->name('dosage-forms.update');
        Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
        Route::put('/materials/{material}', [MaterialController::class, 'update'])->name('materials.update');
        Route::put('/criteria/{queue}', [CriterionController::class, 'update'])->whereIn('queue', Criterion::QUEUES)->name('criteria.update');
        Route::post('/document-types', [DocumentTypeController::class, 'store'])->name('document-types.store');
        Route::put('/document-types/{documentType}', [DocumentTypeController::class, 'update'])->name('document-types.update');
        Route::post('/stages', [StageController::class, 'store'])->name('stages.store');
        Route::put('/stages/{stage}', [StageController::class, 'update'])->name('stages.update');
        Route::post('/holidays', [HolidayController::class, 'store'])->name('holidays.store');
        Route::put('/holidays/{holiday}', [HolidayController::class, 'update'])->name('holidays.update');
        Route::delete('/holidays/{holiday}', [HolidayController::class, 'destroy'])->name('holidays.destroy');
    });
    Route::post('/master/materials', [MaterialController::class, 'store'])->middleware('can:materials.create')->name('master.materials.store');

    Route::middleware('can:master.manage')->group(function (): void {
        Route::get('/templates', [DocumentTemplateController::class, 'index'])->name('templates.index');
        Route::post('/templates', [DocumentTemplateController::class, 'store'])->name('templates.store');
        Route::post('/templates/{documentTemplate}/versions', [DocumentTemplateController::class, 'storeVersion'])->name('templates.versions.store');
        Route::get('/templates/versions/{templateVersion}/download', [DocumentTemplateController::class, 'download'])->name('templates.versions.download');
    });

    Route::middleware('can:tickets.view')->group(function (): void {
        Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
        Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
        Route::post('/tickets/{ticket}/documents/{ticketDocument}/versions', [TicketDocumentVersionController::class, 'store'])->name('tickets.documents.versions.store');
        Route::get('/document-versions/{documentVersion}/download', DocumentVersionDownloadController::class)->name('document-versions.download');
        Route::post('/ticket-documents/{ticketDocument}/reviews/{level}', [DocumentVerificationController::class, 'store'])->whereIn('level', ['isi', 'pemakaian'])->name('ticket-documents.reviews.store');
        Route::get('/lab-tests/{labTest}/file', [LabTestController::class, 'file'])->name('lab-tests.file');
        Route::get('/bpom-submissions/{submission}/result', [BpomController::class, 'resultFile'])->name('bpom.result-file');
        Route::get('/contracts/{contract}/files/{file}', [ContractController::class, 'download'])->whereIn('file', ['word', 'pdf', 'signed'])->name('contracts.files');
    });
    Route::post('/tickets', [TicketController::class, 'store'])->middleware('can:tickets.create')->name('tickets.store');
    Route::post('/tickets/{ticket}/reviews/resolve', [TicketReviewController::class, 'resolve'])->middleware('can:ticket_reviews.resolve')->name('tickets.reviews.resolve');
    Route::get('/qa-review', [DocumentVerificationController::class, 'quality'])->middleware('can:documents.verify_content')->name('quality.index');
    Route::get('/verification', [DocumentVerificationController::class, 'usage'])->middleware('can:documents.approve_usage')->name('verification.index');
    Route::middleware('can:lab_tests.manage')->group(function (): void {
        Route::post('/tickets/{ticket}/lab-tests', [LabTestController::class, 'store'])->name('lab-tests.store');
        Route::post('/lab-tests/{labTest}/result', [LabTestController::class, 'result'])->name('lab-tests.result');
    });
    Route::middleware('can:bpom.manage')->group(function (): void {
        Route::get('/nie', [NieController::class, 'index'])->name('nie.index');
        Route::post('/tickets/{ticket}/packaging', [NieController::class, 'packaging'])->name('nie.packaging');
        Route::post('/tickets/{ticket}/nie', [NieController::class, 'store'])->name('nie.store');
        Route::get('/bpom', [BpomController::class, 'index'])->name('bpom.index');
        Route::post('/tickets/{ticket}/bpom-submissions', [BpomController::class, 'submit'])->name('bpom.submit');
        Route::post('/tickets/{ticket}/registration', [BpomController::class, 'openRegistration'])->name('bpom.open-registration');
        Route::post('/tickets/{ticket}/rejection', [BpomController::class, 'reject'])->name('bpom.reject');
        Route::post('/bpom-submissions/{submission}/result', [BpomController::class, 'result'])->name('bpom.result');
        Route::post('/bpom-submissions/{submission}/requests', [BpomController::class, 'requestData'])->name('bpom.requests.store');
        Route::post('/bpom-requests/{bpomRequest}/respond', [BpomController::class, 'respond'])->name('bpom.requests.respond');
    });
    Route::middleware('can:invoices.view')->group(function (): void {
        Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/receivables', [ReceivableController::class, 'index'])->name('receivables.index');
        Route::get('/receivables/export', [ReceivableController::class, 'export'])->name('receivables.export');
    });
    Route::middleware('can:contracts.manage')->group(function (): void {
        Route::get('/contracts', [ContractController::class, 'index'])->name('contracts.index');
        Route::post('/contracts', [ContractController::class, 'store'])->name('contracts.store');
        Route::put('/contracts/{contract}', [ContractController::class, 'update'])->name('contracts.update');
        Route::post('/contracts/{contract}/send', [ContractController::class, 'send'])->name('contracts.send');
        Route::post('/contracts/{contract}/signed', [ContractController::class, 'signed'])->name('contracts.signed');
        Route::post('/contracts/{contract}/confirm', [ContractController::class, 'confirm'])->name('contracts.confirm');
    });
    Route::middleware('can:samples.record')->group(function (): void {
        Route::get('/samples', [SampleApprovalController::class, 'index'])->name('samples.index');
        Route::post('/tickets/{ticket}/samples', [SampleApprovalController::class, 'store'])->name('tickets.samples.store');
    });
    Route::middleware('can:upload_links.manage')->group(function (): void {
        Route::post('/tickets/{ticket}/upload-links', [UploadTokenController::class, 'store'])->name('tickets.upload-links.store');
        Route::delete('/tickets/{ticket}/upload-links/{uploadToken}', [UploadTokenController::class, 'destroy'])->name('tickets.upload-links.destroy');
    });
    Route::put('/tickets/{ticket}/materials', [TicketMaterialController::class, 'update'])->middleware('can:ticket_materials.manage')->name('tickets.materials.update');

    Route::middleware('can:clients.manage')->group(function (): void {
        Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
        Route::post('/clients', [ClientController::class, 'store'])->name('clients.store');
        Route::put('/clients/{client}', [ClientController::class, 'update'])->name('clients.update');
    });

    Route::middleware('can:document_rules.manage')->group(function (): void {
        Route::get('/document-rules', [DocumentRuleController::class, 'index'])->name('document-rules.index');
        Route::post('/document-rules', [DocumentRuleController::class, 'store'])->name('document-rules.store');
        Route::put('/document-rules/{documentRule}', [DocumentRuleController::class, 'update'])->name('document-rules.update');
    });

    foreach (config('workspace.modules', []) as $module => $configuration) {
        Route::get($configuration['path'], WorkspaceModuleController::class)
            ->defaults('module', $module)
            ->middleware(EnsureWorkspaceModuleAccess::class.':'.$module)
            ->name($configuration['route']);
    }
});

Route::get('/preview/{screen?}/{entity?}', PreviewController::class)->name('preview');
