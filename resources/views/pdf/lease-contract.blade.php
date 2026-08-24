<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Lease Contract</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        .muted { color: #666; }
        .header { margin-bottom: 10px; }
        .box { border: 1px solid #ddd; padding: 10px; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h2 style="margin:0;">Lease Contract</h2>
        <div class="muted">
            Contract #{{ $contract->id }} | Language: {{ strtoupper($contract->language) }} |
            Lease #{{ $contract->lease_id }} | Status: {{ ucfirst($contract->status) }}
        </div>
    </div>

    <div class="box">
        {!! $contract->rendered_html !!}
    </div>

    <div class="box">
        <strong>Signatures</strong><br><br>
        Landlord Signature: _______________________ <br><br>
        Tenant Signature: _________________________ <br><br>
        Date: ___________________
    </div>
</body>
</html>