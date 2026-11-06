<?php

use App\Http\Controllers\AuditController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientUploadController;
use App\Http\Controllers\CriterionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentRuleController;
use App\Http\Controllers\DocumentTemplateController;
use App\Http\Controllers\DocumentTypeController;
use App\Http\Controllers\DocumentVersionDownloadController;
use App\Http\Controllers\DosageFormController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\MasterDataController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\PreviewController;
use App\Http\Controllers\StageController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TicketDocumentVersionController;
use App\Http\Controllers\TicketMaterialController;
use App\Http\Controllers\UploadTokenController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WorkspaceModuleController;
use App\Http\Middleware\EnsureActiveStaffSession;
use App\Http\Middleware\EnsureWorkspaceModuleAccess;
use App\Models\Criterion;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::get('/unggah/{token}', [ClientUploadController::class, 'show'])->middleware('throttle:60,1')->name('client-uploads.show');
Route::post('/unggah/{token}/dokumen/{ticketDocument}', [ClientUploadController::class, 'store'])->middleware('throttle:20,1')->name('client-uploads.store');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login')->name('login.store');
});

Route::middleware(['auth', EnsureActiveStaffSession::class])->group(function (): void {
    Route::get('/dashboard', DashboardController::class)->middleware('can:dashboard.view')->name('dashboard');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
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
    });
    Route::post('/tickets', [TicketController::class, 'store'])->middleware('can:tickets.create')->name('tickets.store');
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
