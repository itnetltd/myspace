<?php

use App\Http\Controllers\LeaseContractController;
use App\Http\Controllers\MoveOutReportController;
use App\Http\Controllers\RentStatementController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

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
});
