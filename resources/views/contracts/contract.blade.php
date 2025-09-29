<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Contrat de {{ $row['civility'] ?? '' }} {{ $row['first_name'] ?? '' }} {{ $row['last_name'] ?? '' }}</title>
  <style>
    * { box-sizing: border-box }
    body{font-family: Arial, sans-serif; font-size:12px; line-height:1.35; color:#111}
    .head{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:14px}
    .logo{height:42px}
    .small{font-size:10px;color:#444}
    h1{font-size:18px;margin:6px 0 8px}
    h2{font-size:14px;margin:14px 0 6px}
    h3{font-size:13px;margin:10px 0 6px}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
    table{width:100%;border-collapse:collapse;margin-top:6px}
    th,td{border:1px solid #ddd;padding:6px;text-align:left;vertical-align:top}
    .section{margin:14px 0}
    .totals td{font-weight:bold}
    .muted{color:#555}
    .mb-8{margin-bottom:8px}
    .mt-20{margin-top:20px}
    .footer{margin-top:18px;padding-top:8px;border-top:1px solid #ddd;font-size:10px;color:#555;text-align:center}
    .anchor{margin-top:36px}
  </style>
</head>
<body>
  <div class="head">
    <div class="small">
      <div><strong>EOR</strong> – 36, RUE DE LABORDE, 75008 PARIS</div>
      <div>SIRET N° 48488721100023 – APE N° 7022Z</div>
      <div class="mt-20"><strong>Contrat de {{ $row['civility'] ?? '' }} {{ $row['first_name'] ?? '' }} {{ $row['last_name'] ?? '' }}</strong></div>
      <div>Date du contrat : {{ now()->format('d/m/Y') }}</div>
    </div>
    @if(!empty($logoUrl))
      <img class="logo" src="{{ $logoUrl }}" alt="logo">
    @endif
  </div>

  @if(!empty($notes))
    <div class="section">
      <h2>NOTES :</h2>
      <div class="mb-8">{!! nl2br(e($notes)) !!}</div>
    </div>
  @endif

  <div class="section">
    <h2>Fiche Client</h2>
    <table>
      <tr><th style="width:32%">Civilité</th><td>{{ $row['civility'] ?? '' }}</td></tr>
      <tr><th>Nom</th><td>{{ $row['last_name'] ?? '' }}</td></tr>
      <tr><th>Prénom</th><td>{{ $row['first_name'] ?? '' }}</td></tr>
      <tr><th>Date de naissance</th><td>{{ $row['birth_date'] ?? '' }}</td></tr>
      <tr><th>Statut marital</th><td>{{ $row['marital_status'] ?? '' }}</td></tr>
      <tr><th>Enfants</th><td>{{ $row['children'] ?? '' }}</td></tr>
      <tr><th>Tél. mobile</th><td>{{ $row['mobile_number'] ?? '' }}</td></tr>
      <tr><th>Tél. bureau</th><td>{{ $row['work_phone'] ?? '' }}</td></tr>
      <tr><th>Email</th><td>{{ $row['email'] ?? '' }}</td></tr>
      <tr><th>Adresse</th><td>{{ $row['address'] ?? '' }}</td></tr>
      <tr><th>CP / Ville</th><td>{{ $row['zip'] ?? '' }} {{ $row['city'] ?? '' }}</td></tr>
      <tr><th>Pays</th><td>{{ $row['country'] ?? '' }}</td></tr>
    </table>
  </div>

  <div class="section">
    <h2>Prestations</h2>

    <!-- Blocs gratuits (0 €) -->
    <h3 class="muted">Entretien retraite et gestion de fin de carrière</h3>
    <table>
      <tr><td>
        Intervention à périmètre défini<br>
        Simulations selon âges, 2 scénarii<br>
        Analyse de la reconstitution de carrière, décomptes des points caisse par caisse,<br>
        Intervention/validation auprès des caisses, choix des options, calculs des futures pensions,<br>
        Commentaires, conseils et conclusion.
      </td></tr>
      <tr><td class="muted">Total HT 0 € – TTC 0 €</td></tr>
    </table>

    <!-- Prestation payante -->
    <h3>Liquidation et vérification des pensions</h3>
    <table>
      <tr>
        <td>
          <div><strong>Total HT {{ number_format($fv['HT7'] ?? 3000, 0, ',', ' ') }} €</strong></div>
          <div class="muted">(inclus sous réserve d’un départ en retraite dans l’année de la signature de l’audit)</div>
          <div><strong>TTC {{ number_format($fv['TTC7'] ?? 3600, 0, ',', ' ') }} €</strong></div>
        </td>
      </tr>
    </table>

    <p class="small muted mt-20">Toute intervention particulière à la demande du client, n’entrant pas dans le cadre du présent contrat, fera l’objet d’un devis.</p>
  </div>

  <div class="section">
    <h2>Récapitulatif</h2>
    <table class="totals">
      <tr><td style="width:40%">TOTAL HT</td><td>{{ number_format($fv['TOTALHT'] ?? 3000, 0, ',', ' ') }} €</td></tr>
      <tr><td>TVA ({{ $fv['TVAP'] ?? 20 }} %)</td><td>{{ number_format($fv['TVA'] ?? 600, 0, ',', ' ') }} €</td></tr>
      <tr><td>TOTAL TTC</td><td>{{ number_format($fv['TOTALTTC'] ?? 3600, 0, ',', ' ') }} €</td></tr>
    </table>

    <table class="mt-20">
      <tr><td style="width:40%">Acompte à la commande ({{ $fv['fp1'] ?? 100 }} %)</td><td>{{ number_format(($fv['FINAL75'] ?? '3600.00'), 2, ',', ' ') }} €</td></tr>
      <tr><td>Solde fin de mission ({{ $fv['fp2'] ?? 0 }} %)</td><td>{{ number_format(($fv['FINAL25'] ?? '0.00'), 2, ',', ' ') }} €</td></tr>
    </table>
  </div>

  <div class="anchor section">
    <!-- ⚠️ TEXTE D'ANCRAGE EXACT pour DocuSign -->
    <strong>Date & signature du client:</strong>
  </div>

  @if(!empty($generalCondition))
    <div class="section">
      <h2>Conditions Générales de Ventes de {{ $row['first_name'] ?? '' }} {{ $row['last_name'] ?? '' }}</h2>
      {!! $generalCondition !!}
    </div>
  @endif

  <div class="footer">
    EOR – 36, RUE DE LABORDE, 75008 PARIS – SIRET N° 48488721100023 – APE N° 7022Z
  </div>
</body>
</html>
