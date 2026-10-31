<?php

use App\Http\Controllers\PreviewController;
use Illuminate\Support\Facades\Route;

Route::get('/{screen?}/{entity?}', PreviewController::class)->name('preview');
