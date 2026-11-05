<?php

use App\Http\Controllers\AuditController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PreviewController;
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

    foreach (config('workspace.modules', []) as $module => $configuration) {
        Route::get($configuration['path'], WorkspaceModuleController::class)
            ->defaults('module', $module)
            ->middleware(EnsureWorkspaceModuleAccess::class.':'.$module)
            ->name($configuration['route']);
    }
});

Route::get('/preview/{screen?}/{entity?}', PreviewController::class)->name('preview');
