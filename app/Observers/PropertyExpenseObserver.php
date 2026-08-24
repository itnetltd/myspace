<?php

namespace App\Observers;

use App\Models\PropertyExpense;
use App\Services\PropertyExpenseService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PropertyExpenseObserver
{
    public function __construct(private readonly PropertyExpenseService $expenses) {}

    public function creating(PropertyExpense $expense): void
    {
        $expense->expense_number ??= 'EXP-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        $expense->currency ??= $expense->account?->currency ?? 'RWF';
        $expense->created_by ??= Auth::id();
        $this->expenses->applyApprovalRequirement($expense);
    }
}
