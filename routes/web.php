<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\MoveOutReportController;
use App\Http\Controllers\RentStatementController;
use App\Http\Controllers\LeaseContractController;

Route::get('/', function () {
    return view('welcome');
});

// =========================
// REPORTS (PDF)
// =========================

// Move-Out PDF report (only works for inspections with type = move_out)
Route::get('/reports/move-out/{inspection}', [MoveOutReportController::class, 'show'])
    ->name('reports.moveout');

// Rent Statement PDF per lease
Route::get('/reports/rent-statement/lease/{lease}', [RentStatementController::class, 'lease'])
    ->name('reports.rent.statement.lease');

// =========================
// CONTRACTS (Templates + PDF)
// =========================

// Generate a lease contract from a template (creates LeaseContract + redirects to PDF)
Route::post('/contracts/generate/lease/{lease}', [LeaseContractController::class, 'generate'])
    ->name('contracts.generate');

// Download/print contract PDF
Route::get('/contracts/pdf/{contract}', [LeaseContractController::class, 'pdf'])
    ->name('contracts.pdf');