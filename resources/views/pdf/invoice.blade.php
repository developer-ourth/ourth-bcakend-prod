<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <title>Invoice {{ $order->order_number }}</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; background: #fff; }
    .page { padding: 40px; max-width: 800px; margin: 0 auto; }

    /* Header */
    .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 32px; border-bottom: 2px solid #16a34a; padding-bottom: 20px; }
    .brand { font-size: 26px; font-weight: 700; color: #16a34a; letter-spacing: -0.5px; }
    .brand-tag { font-size: 10px; color: #6b7280; margin-top: 2px; }
    .invoice-title { text-align: right; }
    .invoice-title h2 { font-size: 22px; font-weight: 700; color: #111827; }
    .invoice-title .inv-num { font-size: 13px; color: #6b7280; margin-top: 4px; }
    .invoice-title .inv-date { font-size: 12px; color: #6b7280; margin-top: 2px; }

    /* Parties */
    .parties { display: flex; gap: 32px; margin-bottom: 28px; }
    .party { flex: 1; }
    .party-label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: #9ca3af; margin-bottom: 6px; }
    .party-name { font-size: 14px; font-weight: 700; color: #111827; margin-bottom: 2px; }
    .party-detail { font-size: 11px; color: #4b5563; line-height: 1.6; }
    .gstin { font-family: monospace; background: #f0fdf4; border: 1px solid #bbf7d0; padding: 2px 6px; border-radius: 3px; font-size: 11px; color: #15803d; display: inline-block; margin-top: 4px; }

    /* Status badge */
    .status-row { display: flex; align-items: center; gap: 12px; margin-bottom: 24px; }
    .badge { padding: 4px 12px; border-radius: 999px; font-size: 11px; font-weight: 700; text-transform: capitalize; }
    .badge-pending    { background: #fef3c7; color: #92400e; }
    .badge-confirmed  { background: #dbeafe; color: #1d4ed8; }
    .badge-dispatched { background: #ede9fe; color: #6d28d9; }
    .badge-delivered  { background: #dcfce7; color: #15803d; }
    .badge-cancelled  { background: #fee2e2; color: #991b1b; }
    .badge-b2b { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }

    /* Items table */
    table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
    thead tr { background: #f9fafb; }
    th { padding: 10px 12px; text-align: left; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; color: #6b7280; border-bottom: 1px solid #e5e7eb; }
    th.num { text-align: right; }
    td { padding: 10px 12px; font-size: 12px; color: #374151; border-bottom: 1px solid #f3f4f6; }
    td.num { text-align: right; font-variant-numeric: tabular-nums; }
    tbody tr:last-child td { border-bottom: none; }
    .sku { font-size: 10px; color: #9ca3af; margin-top: 2px; }

    /* Totals */
    .totals { float: right; width: 280px; margin-bottom: 32px; }
    .totals table { margin-bottom: 0; }
    .totals td { border-bottom: none; padding: 5px 12px; }
    .totals .divider td { border-top: 1px solid #e5e7eb; padding-top: 8px; }
    .grand-total td { font-size: 15px; font-weight: 700; color: #111827; padding-top: 8px; border-top: 2px solid #111827; }
    .clearfix::after { content: ''; display: table; clear: both; }

    /* Footer */
    .footer { border-top: 1px solid #e5e7eb; padding-top: 16px; color: #9ca3af; font-size: 10px; text-align: center; margin-top: 40px; }
    .footer strong { color: #4b5563; }
  </style>
</head>
<body>
<div class="page">

  {{-- Header --}}
  <div class="header">
    <div>
      <div class="brand">Ourth</div>
      <div class="brand-tag">B2B Wholesale Platform</div>
    </div>
    <div class="invoice-title">
      <h2>TAX INVOICE</h2>
      <div class="inv-num">{{ $order->order_number }}</div>
      <div class="inv-date">Date: {{ $order->created_at->format('d M Y') }}</div>
      @if($order->confirmed_at)
      <div class="inv-date">Confirmed: {{ $order->confirmed_at->format('d M Y') }}</div>
      @endif
    </div>
  </div>

  {{-- Status badge --}}
  <div class="status-row">
    <span class="badge badge-{{ $order->order_status }}">{{ ucfirst($order->order_status) }}</span>
    @if($order->order_type === 'b2b')
    <span class="badge badge-b2b">B2B Order</span>
    @endif
  </div>

  {{-- Parties --}}
  <div class="parties">
    <div class="party">
      <div class="party-label">Sold By (Supplier)</div>
      <div class="party-name">Ourth Trading Pvt. Ltd.</div>
      <div class="party-detail">
        Mumbai, Maharashtra, India<br/>
        GSTIN: 27AABCO1234H1ZX<br/>
        support@ourth.in
      </div>
    </div>
    <div class="party">
      <div class="party-label">Bill To (Buyer)</div>
      <div class="party-name">{{ $order->vendor?->business_name ?? 'Buyer' }}</div>
      <div class="party-detail">
        {{ $order->delivery_address_line1 }}
        @if($order->delivery_address_line2)<br/>{{ $order->delivery_address_line2 }}@endif
        <br/>{{ $order->delivery_city }}, {{ $order->delivery_state }} {{ $order->delivery_postal_code }}<br/>
        {{ $order->delivery_country }}
        @if($order->buyer_gstin)
        <br/><span class="gstin">GST: {{ $order->buyer_gstin }}</span>
        @elseif($order->vendor?->gstin)
        <br/><span class="gstin">GST: {{ $order->vendor->gstin }}</span>
        @endif
      </div>
    </div>
  </div>

  {{-- Line items --}}
  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Item</th>
        <th class="num">Qty</th>
        <th class="num">Unit Price (₹)</th>
        <th class="num">Tax (₹)</th>
        <th class="num">Total (₹)</th>
      </tr>
    </thead>
    <tbody>
      @foreach($order->items as $i => $item)
      <tr>
        <td>{{ $i + 1 }}</td>
        <td>
          {{ $item->product_name }}
          @if($item->product_sku)
          <div class="sku">SKU: {{ $item->product_sku }}</div>
          @endif
        </td>
        <td class="num">{{ $item->quantity }}</td>
        <td class="num">{{ number_format($item->unit_price, 2) }}</td>
        <td class="num">{{ number_format($item->tax_amount, 2) }}</td>
        <td class="num">{{ number_format($item->total_price, 2) }}</td>
      </tr>
      @endforeach
    </tbody>
  </table>

  {{-- Totals --}}
  <div class="totals">
    <table>
      <tr>
        <td>Subtotal</td>
        <td class="num">₹{{ number_format($order->subtotal ?? 0, 2) }}</td>
      </tr>
      @if(($order->tax_amount ?? 0) > 0)
      <tr>
        <td>GST / Tax</td>
        <td class="num">₹{{ number_format($order->tax_amount, 2) }}</td>
      </tr>
      @endif
      @if(($order->delivery_charge ?? 0) > 0)
      <tr>
        <td>Delivery Charge</td>
        <td class="num">₹{{ number_format($order->delivery_charge, 2) }}</td>
      </tr>
      @endif
      @if(($order->discount_amount ?? 0) > 0)
      <tr>
        <td>Discount</td>
        <td class="num" style="color:#16a34a">-₹{{ number_format($order->discount_amount, 2) }}</td>
      </tr>
      @endif
      <tr class="grand-total">
        <td>Grand Total</td>
        <td class="num">₹{{ number_format($order->total_amount, 2) }}</td>
      </tr>
    </table>
  </div>
  <div class="clearfix"></div>

  {{-- Footer --}}
  <div class="footer">
    <p>This is a computer-generated invoice and does not require a physical signature.</p>
    <p style="margin-top:6px"><strong>Ourth Trading Pvt. Ltd.</strong> &bull; GSTIN: 27AABCO1234H1ZX &bull; support@ourth.in</p>
  </div>

</div>
</body>
</html>
