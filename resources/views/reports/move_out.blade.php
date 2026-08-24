<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Move-Out Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        h1 { font-size: 18px; margin: 0 0 10px; }
        .muted { color: #666; }
        .box { border: 1px solid #ddd; padding: 10px; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ddd; padding: 6px; vertical-align: top; }
        th { background: #f5f5f5; text-align: left; }
        .right { text-align: right; }
        .total { font-weight: bold; }
        .small { font-size: 10px; }
    </style>
</head>
<body>
    <h1>MySpaces Estate — Move-Out Inspection Report</h1>
    <p class="muted">Generated on: {{ now()->format('Y-m-d H:i') }}</p>

    <div class="box">
        <strong>Inspection Info</strong><br>
        Inspection ID: {{ $inspection->id }}<br>
        Unit: {{ $inspection->unit?->unit_code ?? '—' }}<br>
        Inspection Date: {{ optional($inspection->inspected_on)->format('Y-m-d') }}<br>
        Inspected By: {{ $inspection->inspected_by ?? '—' }}<br>
        Lease ID: {{ $inspection->lease_id ?? '—' }}<br>
        Notes: {{ $inspection->general_notes ?? '—' }}
    </div>

    <div class="box">
        <strong>Deposit & Deductions Summary</strong><br>
        Deposit: <span class="right">{{ number_format($deposit, 0) }} RWF</span><br>
        Suggested Deductions: <span class="right">{{ number_format($totalDeductionSuggested, 0) }} RWF</span><br>
        Applied Deductions: <span class="right">{{ number_format($totalDeductionApplied, 0) }} RWF</span><br>
        Suggested Refund: <span class="right">{{ number_format($refundSuggested, 0) }} RWF</span><br>
        Applied Refund: <span class="right">{{ number_format($refundApplied, 0) }} RWF</span>

        <p class="muted" style="margin-top: 6px;">
            Note: “Applied” uses manual override amounts when provided; otherwise it uses suggested deductions.
            Suggested deductions are calculated using asset replacement value (if set) or purchase cost, and standard rates (configured in the Inspection model).
        </p>
    </div>

    <strong>Issues Found (Missing/Damaged)</strong>
    <table>
        <thead>
            <tr>
                <th>Asset</th>
                <th class="right">Expected</th>
                <th class="right">Found</th>
                <th class="right">Missing</th>
                <th>Issue</th>
                <th class="right">Unit Value (Used)</th>
                <th class="right">Suggested</th>
                <th class="right">Applied</th>
                <th>Override Reason</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            @forelse($issues as $row)
                <tr>
                    <td>{{ $row['asset'] }}</td>
                    <td class="right">{{ $row['expected_qty'] }}</td>
                    <td class="right">{{ $row['found_qty'] }}</td>
                    <td class="right">{{ $row['missing_qty'] }}</td>
                    <td>{{ $row['issue_label'] }}</td>

                    <td class="right">
                        {{ number_format($row['unit_value_used'] ?? ($row['replacement_value'] ?? 0) ?? ($row['purchase_cost'] ?? 0), 0) }} RWF
                        <div class="muted small">
                            @if(($row['replacement_value'] ?? 0) > 0)
                                Replacement: {{ number_format($row['replacement_value'], 0) }} RWF
                            @else
                                Purchase: {{ number_format($row['purchase_cost'] ?? 0, 0) }} RWF
                            @endif
                        </div>
                    </td>

                    <td class="right">{{ number_format($row['suggested_deduction'], 0) }} RWF</td>
                    <td class="right">{{ number_format($row['applied_deduction'], 0) }} RWF</td>

                    <td>
                        @if($row['override_used'])
                            {{ $row['deduction_reason'] ?? 'Override applied (no reason provided)' }}
                        @else
                            —
                        @endif
                    </td>

                    <td>{{ $row['remarks'] ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10">No missing or damaged assets recorded.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p style="margin-top: 18px;">
        Landlord/Agent Signature: ________________________
        &nbsp;&nbsp;&nbsp;&nbsp;
        Tenant Signature: ________________________
    </p>
</body>
</html>