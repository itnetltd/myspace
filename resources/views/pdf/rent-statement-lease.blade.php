<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Rent Statement</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        .row { display: flex; justify-content: space-between; }
        .muted { color: #666; }
        h2 { margin: 0 0 6px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 6px; }
        th { background: #f3f4f6; text-align: left; }
        .right { text-align: right; }
        .totals td { font-weight: bold; }
    </style>
</head>
<body>
    <div class="row">
        <div>
            <h2>Rent Statement</h2>
            <div class="muted">Generated: {{ now()->format('Y-m-d H:i') }}</div>
        </div>
        <div class="right">
            <div><strong>Lease #{{ $lease->id }}</strong></div>
            <div class="muted">Status: {{ ucfirst($lease->status) }}</div>
        </div>
    </div>

    <hr>

    <div class="row">
        <div>
            <div><strong>Tenant:</strong> {{ $lease->tenant?->full_name }}</div>
            <div class="muted">Phone: {{ $lease->tenant?->phone }} | Email: {{ $lease->tenant?->email }}</div>
        </div>
        <div class="right">
            <div><strong>Unit:</strong> {{ $lease->unit?->unit_code }}</div>
            <div class="muted">
                Start: {{ optional($lease->start_date)->format('Y-m-d') }}
                @if($lease->end_date) | End: {{ optional($lease->end_date)->format('Y-m-d') }} @endif
            </div>
        </div>
    </div>

    <div style="margin-top:10px;">
        <div><strong>Monthly Rent:</strong> {{ number_format((float)$lease->monthly_rent, 0) }} RWF</div>
        <div><strong>Deposit:</strong> {{ number_format((float)$lease->deposit, 0) }} RWF</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Period</th>
                <th>Due Date</th>
                <th class="right">Amount</th>
                <th class="right">Late Fee</th>
                <th class="right">Total Due</th>
                <th class="right">Paid</th>
                <th class="right">Balance</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoices as $inv)
                @php
                    $balance = (float)$inv->total_due - (float)$inv->amount_paid;
                @endphp
                <tr>
                    <td>{{ optional($inv->period_start)->format('Y-m-d') }} → {{ optional($inv->period_end)->format('Y-m-d') }}</td>
                    <td>{{ optional($inv->due_date)->format('Y-m-d') }}</td>
                    <td class="right">{{ number_format((float)$inv->amount_due, 0) }}</td>
                    <td class="right">{{ number_format((float)$inv->late_fee, 0) }}</td>
                    <td class="right">{{ number_format((float)$inv->total_due, 0) }}</td>
                    <td class="right">{{ number_format((float)$inv->amount_paid, 0) }}</td>
                    <td class="right">{{ number_format($balance, 0) }}</td>
                    <td>{{ ucfirst($inv->status) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot class="totals">
            <tr>
                <td colspan="2" class="right">Totals</td>
                <td class="right">{{ number_format($totals['amount_due'], 0) }}</td>
                <td class="right">{{ number_format($totals['late_fee'], 0) }}</td>
                <td class="right">{{ number_format($totals['total_due'], 0) }}</td>
                <td class="right">{{ number_format($totals['paid'], 0) }}</td>
                <td class="right">{{ number_format($totals['balance'], 0) }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <p class="muted" style="margin-top:10px;">
        Note: Late fees are calculated using your Rent Policy settings (grace days + fixed/percent).
    </p>
</body>
</html>