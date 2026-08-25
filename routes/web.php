<?php

use App\Http\Controllers\LeaseContractController;
use App\Http\Controllers\MoveOutReportController;
use App\Http\Controllers\OwnerStatementController;
use App\Http\Controllers\ProviderStaffInvitationController;
use App\Http\Controllers\RentStatementController;
use App\Http\Controllers\WorkOrderEvidenceController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->group(function () {
    Route::get('/provider-staff-invitations/{token}', [ProviderStaffInvitationController::class, 'show'])
        ->name('provider-staff-invitations.show');
    Route::post('/provider-staff-invitations/{token}/accept', [ProviderStaffInvitationController::class, 'accept'])
        ->name('provider-staff-invitations.accept');
});

Route::get('/work-order-evidence/{evidence}', [WorkOrderEvidenceController::class, 'show'])
    ->middleware('auth')->name('work-order-evidence.show');

// =========================
// REPORTS (PDF)
// =========================

Route::middleware(['auth', 'current.account'])->group(function () {
    Route::get('/reports/move-out/{inspection}', [MoveOutReportController::class, 'show'])
        ->name('reports.moveout');

    Route::get('/reports/rent-statement/lease/{lease}', [RentStatementController::class, 'lease'])
        ->name('reports.rent.statement.lease');

    Route::post('/contracts/generate/lease/{lease}', [LeaseContractController::class, 'generate'])
        ->name('contracts.generate');

    Route::get('/contracts/pdf/{contract}', [LeaseContractController::class, 'pdf'])
        ->name('contracts.pdf');
    Route::get('/reports/owner-statement/{statement}', [OwnerStatementController::class, 'show'])
        ->name('reports.owner-statement');
});
