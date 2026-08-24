<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $statement->statement_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 11px; }
        h1 { margin: 0 0 4px; color: #0f172a; font-size: 22px; }
        h2 { margin: 20px 0 8px; font-size: 14px; color: #0f172a; }
        .muted { color: #64748b; }
        .header, .summary { width: 100%; border-collapse: collapse; }
        .header td { vertical-align: top; padding: 2px 0; }
        .summary td { border: 1px solid #cbd5e1; padding: 7px; }
        .summary .label { color: #475569; }
        .summary .amount { text-align: right; font-weight: bold; }
        .transactions { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .transactions th { background: #e2e8f0; padding: 6px; text-align: left; }
        .transactions td { border-bottom: 1px solid #e2e8f0; padding: 6px; vertical-align: top; }
        .right { text-align: right; }
        .balance { font-size: 14px; font-weight: bold; color: #0f172a; }
    </style>
</head>
<body>
    <h1>MySpaces Estate</h1>
    <div class="muted">Owner Monthly Statement</div>

    <table class="header">
        <tr>
            <td>
                <strong>Property Owner</strong><br>
                {{ $statement->propertyOwner->name }}<br>
                {{ $statement->propertyOwner->phone }}<br>
                {{ $statement->propertyOwner->email }}
            </td>
            <td class="right">
                <strong>{{ $statement->statement_number }}</strong><br>
                {{ $statement->period_start->format('d M Y') }} – {{ $statement->period_end->format('d M Y') }}<br>
                Generated {{ $statement->generated_at->format('d M Y H:i') }}
            </td>
        </tr>
    </table>

    @if($statement->account->isPropertyManagementCompany())
        <p><strong>Managed by:</strong><br>
            {{ $statement->account->name }}<br>
            @if($statement->account->tin) TIN: {{ $statement->account->tin }}<br> @endif
            {{ $statement->account->phone }} {{ $statement->account->email }}
        </p>
    @endif

    <h2>Portfolio Summary</h2>
    <table class="summary">
        <tr><td class="label">Opening Balance</td><td class="amount">{{ number_format((float) $statement->opening_balance, 2) }} {{ $statement->currency }}</td></tr>
        <tr><td class="label">Rent Collected</td><td class="amount">+{{ number_format((float) $statement->rent_collected, 2) }}</td></tr>
        <tr><td class="label">Late Fees Collected</td><td class="amount">+{{ number_format((float) $statement->late_fees_collected, 2) }}</td></tr>
        <tr><td class="label">Other Income</td><td class="amount">+{{ number_format((float) $statement->other_income, 2) }}</td></tr>
        <tr><td class="label">Maintenance Expenses</td><td class="amount">-{{ number_format((float) $maintenanceExpenses, 2) }}</td></tr>
        <tr><td class="label">Other Property Expenses</td><td class="amount">-{{ number_format((float) $otherExpenses, 2) }}</td></tr>
        @if($statement->account->isPropertyManagementCompany())
            <tr><td class="label">Management Fees</td><td class="amount">-{{ number_format((float) $statement->management_fees, 2) }}</td></tr>
        @endif
        <tr><td class="label">Owner Disbursements</td><td class="amount">-{{ number_format((float) $statement->owner_disbursements, 2) }}</td></tr>
        <tr><td class="label">Net Activity</td><td class="amount">{{ number_format((float) $statement->net_activity, 2) }}</td></tr>
        <tr><td class="balance">Closing / Payable Balance</td><td class="amount balance">{{ number_format((float) $statement->closing_balance, 2) }} {{ $statement->currency }}</td></tr>
    </table>

    <h2>Detailed Transactions</h2>
    <table class="transactions">
        <thead><tr><th>Date</th><th>Property</th><th>Unit</th><th>Description</th><th class="right">Credit</th><th class="right">Debit</th></tr></thead>
        <tbody>
        @foreach($statement->lines as $line)
            <tr>
                <td>{{ $line->occurred_on->format('d M Y') }}</td>
                <td>{{ data_get($line->metadata, 'property_name', 'Portfolio') }}</td>
                <td>{{ data_get($line->metadata, 'unit_code', '—') }}</td>
                <td>{{ $line->description }}</td>
                <td class="right">{{ (float) $line->credit > 0 ? number_format((float) $line->credit, 2) : '—' }}</td>
                <td class="right">{{ (float) $line->debit > 0 ? number_format((float) $line->debit, 2) : '—' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>
