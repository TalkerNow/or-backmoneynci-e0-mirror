<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Contrat</title>
  <style>
    body{font-family: Arial, sans-serif; font-size:12px; line-height:1.4}
    .head{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}
    .logo{height:50px}
    .section{margin:18px 0}
    table{width:100%;border-collapse:collapse}
    th,td{border:1px solid #ddd;padding:6px;text-align:left}
  </style>
</head>
<body>
  <div class="head">
    <div>
      <h2 style="margin:0">Votre contrat</h2>
      <div>{{ now()->format('d/m/Y') }}</div>
    </div>
    @if(!empty($logoUrl))
      <img class="logo" src="{{ $logoUrl }}" alt="logo">
    @endif
  </div>

  <div class="section">
    <strong>Client :</strong>
    {{ $row['civility'] ?? '' }} {{ $row['first_name'] ?? '' }} {{ $row['last_name'] ?? '' }}<br>
    Email : {{ $row['email'] ?? '' }} – Tél : {{ $row['mobile_number'] ?? '' }}
  </div>

  <div class="section">
    <h3>Récapitulatif</h3>
    <table>
      <tr><th>Total HT</th><td>{{ number_format($fv['TOTALHT'] ?? 0, 2, ',', ' ') }} €</td></tr>
      <tr><th>TVA ({{ $fv['TVAP'] ?? 20 }}%)</th><td>{{ number_format($fv['TVA'] ?? 0, 2, ',', ' ') }} €</td></tr>
      <tr><th>Total TTC</th><td>{{ number_format($fv['TOTALTTC'] ?? 0, 2, ',', ' ') }} €</td></tr>
      <tr><th>Acompte ({{ $fv['fp1'] ?? 75 }}%)</th><td>{{ $fv['FINAL75'] ?? '0.00' }} €</td></tr>
      <tr><th>Solde ({{ $fv['fp2'] ?? 25 }}%)</th><td>{{ $fv['FINAL25'] ?? '0.00' }} €</td></tr>
    </table>
  </div>

  @if(!empty($notes))
    <div class="section"><strong>Notes :</strong><br>{!! nl2br(e($notes)) !!}</div>
  @endif

  @if(!empty($generalCondition))
    <div class="section"><strong>Conditions générales :</strong><br>{!! $generalCondition !!}</div>
  @endif

  <div class="section" style="margin-top:50px">
    <!-- ⚠️ TEXTE D'ANCRAGE EXACT pour DocuSign -->
    <strong>Date & signature du client:</strong>
  </div>
</body>
</html>
