<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ContactSubmissionController;
use App\Http\Controllers\Api\PortfolioController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('portfolio', PortfolioController::class)->name('portfolio');
    Route::post('contact', [ContactSubmissionController::class, 'store'])
        ->middleware('throttle:contact')->name('contact');
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:login')->name('auth.login');
    Route::post('auth/logout', [AuthController::class, 'logout'])
        ->middleware('auth:sanctum')->name('auth.logout');

    Route::prefix('admin')->name('admin.')->middleware(['auth:sanctum', 'abilities:admin:read'])
        ->group(function (): void {
            Route::get('submissions', [ContactSubmissionController::class, 'index'])->name('submissions.index');
            Route::get('submissions/{submission:uuid}', [ContactSubmissionController::class, 'show'])->name('submissions.show');
            Route::delete('submissions/{submission:uuid}', [ContactSubmissionController::class, 'destroy'])
                ->middleware('abilities:admin:write')->name('submissions.destroy');
            Route::get('audit-logs', AuditLogController::class)->name('audit-logs.index');
        });
});
