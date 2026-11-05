<?php

use App\Http\Controllers\AuditController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentTypeController;
use App\Http\Controllers\DosageFormController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\MasterDataController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\PreviewController;
use App\Http\Controllers\StageController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WorkspaceModuleController;
use App\Http\Middleware\EnsureActiveStaffSession;
use App\Http\Middleware\EnsureWorkspaceModuleAccess;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

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
        Route::post('/document-types', [DocumentTypeController::class, 'store'])->name('document-types.store');
        Route::put('/document-types/{documentType}', [DocumentTypeController::class, 'update'])->name('document-types.update');
        Route::post('/stages', [StageController::class, 'store'])->name('stages.store');
        Route::put('/stages/{stage}', [StageController::class, 'update'])->name('stages.update');
        Route::post('/holidays', [HolidayController::class, 'store'])->name('holidays.store');
        Route::put('/holidays/{holiday}', [HolidayController::class, 'update'])->name('holidays.update');
        Route::delete('/holidays/{holiday}', [HolidayController::class, 'destroy'])->name('holidays.destroy');
    });
    Route::post('/master/materials', [MaterialController::class, 'store'])->middleware('can:materials.create')->name('master.materials.store');

    foreach (config('workspace.modules', []) as $module => $configuration) {
        Route::get($configuration['path'], WorkspaceModuleController::class)
            ->defaults('module', $module)
            ->middleware(EnsureWorkspaceModuleAccess::class.':'.$module)
            ->name($configuration['route']);
    }
});

Route::get('/preview/{screen?}/{entity?}', PreviewController::class)->name('preview');
