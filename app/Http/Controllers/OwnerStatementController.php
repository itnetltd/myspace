<?php

namespace App\Http\Controllers;

use App\Models\OwnerLedgerEntry;
use App\Models\OwnerStatement;
use App\Support\Money;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;

class OwnerStatementController extends Controller
{
    public function show(OwnerStatement $statement)
    {
        Gate::authorize('view', $statement);
        $statement->loadMissing(['account', 'propertyOwner', 'lines.property', 'lines.unit']);
        $maintenanceMinor = $statement->lines
            ->where('line_type', OwnerLedgerEntry::TYPE_PROPERTY_EXPENSE)
            ->filter(fn ($line) => data_get($line->metadata, 'entry_metadata.category') === 'maintenance')
            ->sum(fn ($line) => Money::toMinor($line->debit));
        $otherExpenseMinor = Money::toMinor($statement->expenses) - $maintenanceMinor;

        return Pdf::loadView('pdf.owner-statement', [
            'statement' => $statement,
            'maintenanceExpenses' => Money::fromMinor($maintenanceMinor),
            'otherExpenses' => Money::fromMinor($otherExpenseMinor),
        ])
            ->setPaper('a4', 'portrait')
            ->download('Owner_Statement_'.$statement->statement_number.'.pdf');
    }
}
