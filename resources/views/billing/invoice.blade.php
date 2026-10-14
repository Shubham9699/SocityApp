<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $bill->bill_number }}</title>
    <style>
        @page { margin: 36px; }
        body { color: #243247; font-family: DejaVu Sans, sans-serif; font-size: 12px; line-height: 1.5; }
        .header { border-bottom: 2px solid #365fd6; margin-bottom: 28px; padding-bottom: 18px; }
        .brand { color: #243b73; font-size: 20px; font-weight: bold; }
        .subtitle { color: #738096; margin-top: 3px; }
        .invoice-title { color: #243b73; font-size: 24px; margin: 24px 0 4px; }
        .muted { color: #738096; }
        .details { margin: 20px 0; width: 100%; }
        .details td { padding: 4px 0; vertical-align: top; width: 50%; }
        table.charges { border-collapse: collapse; margin-top: 24px; width: 100%; }
        .charges th, .charges td { border-bottom: 1px solid #dfe5ee; padding: 10px; text-align: left; }
        .charges th { background: #f2f5fb; color: #44536a; }
        .charges .amount { text-align: right; }
        .total td { color: #243b73; font-size: 15px; font-weight: bold; }
        .status { background: #eef4ff; border-radius: 4px; display: inline-block; padding: 4px 8px; text-transform: uppercase; }
        .footer { border-top: 1px solid #dfe5ee; color: #738096; margin-top: 36px; padding-top: 12px; }
    </style>
</head>
<body>
    <header class="header">
        <div class="brand">{{ $bill->flat->society->name }}</div>
        <div class="subtitle">Society maintenance invoice</div>
    </header>

    <div class="muted">Invoice number</div>
    <div class="invoice-title">{{ $bill->bill_number }}</div>
    <div>{{ $bill->bill_period->format('F Y') }} maintenance</div>

    <table class="details">
        <tr>
            <td><strong>Billed to</strong><br>{{ $bill->flat->owner->full_name }}<br>{{ $bill->flat->owner->email }}</td>
            <td><strong>Flat</strong><br>{{ $bill->flat->wing }}-{{ $bill->flat->flat_number }}<br><strong>Due date</strong><br>{{ $bill->due_date->format('d M Y') }}</td>
        </tr>
        <tr>
            <td><strong>Billing period</strong><br>{{ $bill->bill_period->format('F Y') }}</td>
            <td><strong>Payment status</strong><br><span class="status">{{ str_replace('_', ' ', $bill->payment_status) }}</span></td>
        </tr>
    </table>

    <table class="charges">
        <thead><tr><th>Charge</th><th class="amount">Amount (INR)</th></tr></thead>
        <tbody>
            <tr><td>Base maintenance</td><td class="amount">{{ number_format((float) $bill->base_maintenance, 2) }}</td></tr>
            <tr><td>Utility charges</td><td class="amount">{{ number_format((float) $bill->utility_charges, 2) }}</td></tr>
            <tr><td>Sinking fund</td><td class="amount">{{ number_format((float) $bill->sinking_fund, 2) }}</td></tr>
            <tr><td>Penalty / fine</td><td class="amount">{{ number_format((float) $bill->penalty_fine, 2) }}</td></tr>
            <tr class="total"><td>Total due</td><td class="amount">{{ number_format((float) $bill->total_amount, 2) }}</td></tr>
        </tbody>
    </table>

    @if ($bill->payment_status === 'paid')
        <p><strong>Paid date:</strong> {{ $bill->paid_date?->format('d M Y, g:i A') ?? 'Recorded' }}</p>
        <p><strong>Payment method:</strong> {{ strtoupper($bill->payment_method ?? 'Not specified') }}</p>
        @if ($bill->payment_transaction_id)<p><strong>Transaction/reference:</strong> {{ $bill->payment_transaction_id }}</p>@endif
    @endif

    @if ($bill->notes)<p><strong>Notes:</strong> {{ $bill->notes }}</p>@endif

    <footer class="footer">Generated {{ now()->format('d M Y, g:i A') }} · {{ $bill->flat->society->name }}</footer>
</body>
</html>
