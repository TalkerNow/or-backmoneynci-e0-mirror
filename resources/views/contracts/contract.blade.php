<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <style>
    body { font-family: Arial, sans-serif; font-size: 12px; }
    .contract-div { display:inline-block; min-width:80px; border:1px solid #8d8d8d; padding:3px 6px; }
    .center { text-align:center; }
  </style>
</head>
<body>
  <img src="{{ $logoUrl }}" alt="logo" style="height:130px" />

  <h1 class="center" style="margin-top:30px">
    Contrat de {{ $row['civility'] ?? '' }} {{ $row['first_name'] ?? '' }} {{ $row['last_name'] ?? '' }}
  </h1>

  <p>Total HT: <b>{{ $fv['TOTALHT'] }} €</b> —
     TVA ({{ $fv['TVAP'] }}%): <b>{{ $fv['TVA'] }} €</b> —
     Total TTC: <b>{{ $fv['TOTALTTC'] }} €</b>
  </p>

  <h3 style="margin-top:30px">Échéancier</h3>
  <p>{{ $fv['fp1'] }}% : {{ $fv['FINAL75'] }} € —
     {{ $fv['fp2'] }}% : {{ $fv['FINAL25'] }} €</p>

  @if(!empty($notes))
    <h3 style="margin-top:20px">Notes</h3>
    <div style="white-space:pre-wrap; font-size:13px">{!! nl2br(e($notes)) !!}</div>
  @endif

  @if(!empty($generalCondition))
    <h3 style="margin-top:20px">Conditions Générales</h3>
    <div style="white-space:pre-wrap; font-size:12px">{!! nl2br(e($generalCondition)) !!}</div>
  @endif

  <!-- GARDE EXACTEMENT ce texte pour l’ancrage DocuSign -->
  <div style="margin-top:60px">
    <u>Date & signature du client:</u>
  </div>

  <div style="margin-top:120px" class="center">
    EOR - 36, RUE DE LABORDE 75008 PARIS - SIRET N° 48488721100023 - APE N° 7022Z
  </div>
</body>
</html>
