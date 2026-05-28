// AGREGATION FINALE V9 — enrichi pour le rapport DEMANGE-style
// Ajoute par rapport à V8 : date_releve, trimestres.requis_loi, trimestres.manquants,
// trimestres.total_avec_enfants, dates_cles.age_actuel_annees, dates_cles.annee_naissance,
// dates_cles.taux_plein_auto_atteint, dates_cles.date_releve.

const webhookBody = $('Webhook').first().json.body || {};

let ctx = webhookBody.simulateur_context;
if (typeof ctx === 'string') {
  try { ctx = JSON.parse(ctx); } catch (_) { ctx = {}; }
}
ctx = ctx || {};

const frozen = ctx.frozen_data || {};
const calculs = Array.isArray(ctx.calculs) ? ctx.calculs : [];
const meta = frozen.meta || {};
const totaux = frozen.totaux || {};
const carriere = Array.isArray(frozen.carriere) ? frozen.carriere : [];

const findSkill = (id) => calculs.find(c => (c.skill_id || '').toLowerCase() === String(id).toLowerCase());

// Durée d'assurance requise — lue depuis bareme_depart (injecté par Laravel) si disponible,
// fallback sur table Circulaire 2026-07 si clé absente.
const TRIM_REQUIS_FALLBACK = { 1958:167, 1959:167, 1960:167, 1961:168, 1962:169, 1963:170, 1964:170, 1965:170, 1966:172, 1967:172 };

function getTrimestresRequis(annee) {
  if (!annee) return null;
  const bareme = webhookBody.bareme_depart;
  if (Array.isArray(bareme) && bareme.length > 0) {
    const birthYM = annee * 100 + 12;
    const sorted = bareme
      .filter(r => !r.is_default && r.key_max != null)
      .sort((a, b) => a.key_max - b.key_max);
    for (const row of sorted) {
      if (birthYM <= row.key_max) return row.trim;
    }
    const def = bareme.find(r => r.is_default);
    return def ? def.trim : 172;
  }
  if (annee in TRIM_REQUIS_FALLBACK) return TRIM_REQUIS_FALLBACK[annee];
  if (annee < 1958) return 166;
  return 172;
}

function parseDate(s) {
  if (!s) return null;
  s = String(s).trim();
  if (/^\d{4}-\d{2}-\d{2}/.test(s)) return new Date(s.substring(0, 10) + 'T00:00:00');
  if (/^\d{2}\/\d{2}\/\d{4}$/.test(s)) {
    const p = s.split('/');
    return new Date(p[2] + '-' + p[1] + '-' + p[0] + 'T00:00:00');
  }
  return null;
}

const synthese_client = {
  nom: [meta.prenom, meta.nom].filter(Boolean).join(' ').trim() || null,
  prenom: meta.prenom || null,
  nom_famille: meta.nom || null,
  genre: meta.sexe || null,
  date_naissance: meta.date_naissance || null,
  nir: meta.nir || webhookBody.nir || null,
  enfants: meta.enfants ?? null,
};

const ddn = parseDate(meta.date_naissance);
const annee_naissance = ddn ? ddn.getFullYear() : null;

const today = new Date();
let age_actuel = null;
if (ddn) {
  age_actuel = today.getFullYear() - ddn.getFullYear();
  const m = today.getMonth() - ddn.getMonth();
  if (m < 0 || (m === 0 && today.getDate() < ddn.getDate())) age_actuel--;
}
const today_dd = String(today.getDate()).padStart(2, '0');
const today_mm = String(today.getMonth() + 1).padStart(2, '0');
const today_yyyy = today.getFullYear();
const today_date = today_dd + '/' + today_mm + '/' + today_yyyy;

const enfants_nb = Number(meta.enfants) || 0;
const sexeRaw = String(meta.sexe || '').toLowerCase().trim();
const nirStr = synthese_client.nir ? String(synthese_client.nir) : '';
const isFemme = sexeRaw === 'f' || sexeRaw === 'femme' || sexeRaw === 'female' || sexeRaw === 'mme' || sexeRaw === 'madame' || nirStr.startsWith('2');
const trim_majo_enfants = isFemme ? enfants_nb * 8 : 0;
const trim_acquis_ris = Number(totaux.trimestres_total ?? totaux.trimestres_cotises ?? 0);
const trim_acquis_avec_enfants = trim_acquis_ris + trim_majo_enfants;
const trim_requis = getTrimestresRequis(annee_naissance);
const trim_manquants = trim_requis !== null ? Math.max(0, trim_requis - trim_acquis_avec_enfants) : null;

const trimestres = {
  total_tous_regimes: trim_acquis_ris,
  trimestres_cotises_rg: totaux.trimestres_cotises_rg ?? totaux.trimestres_cnav ?? 0,
  par_regime: totaux.trimestres_par_regime || {},
  enfants_nb: enfants_nb,
  majoration_enfants_app: trim_majo_enfants,
  total_avec_enfants: trim_acquis_avec_enfants,
  requis_loi: trim_requis,
  manquants: trim_manquants,
};

let date_releve = null;
if (totaux.date_releve) date_releve = totaux.date_releve;
else if (carriere.length > 0 && carriere[carriere.length - 1] && carriere[carriere.length - 1].annee) {
  date_releve = '31/12/' + carriere[carriere.length - 1].annee;
}

const simRapport = findSkill('simulation_retraite');
const cnavSkill = findSkill('cnav');
const arrcoSkill = findSkill('agirc_arrco') || findSkill('arrco') || findSkill('agirc');

const safeNumber = (v) => (typeof v === 'number' && Number.isFinite(v)) ? v : 0;

const resultats_financiers = {
  pension_mensuelle_estimee_actuelle:
    simRapport?.calcul_json?.pension_mensuelle_totale
    ?? safeNumber(cnavSkill?.result_json?.pension_mensuelle) + safeNumber(arrcoSkill?.result_json?.pension_mensuelle),
  detail_cnav: cnavSkill?.result_json || null,
  detail_arrco: arrcoSkill?.result_json || null,
  sam: totaux.sam ?? null,
};

const scenarios = Array.isArray(frozen.scenarios_choisis) ? frozen.scenarios_choisis : [];
const dates_cles = {
  scenarios_retenus: scenarios,
  date_retenue_principale: scenarios[0]?.date || frozen.date_retenue || null,
  date_releve: date_releve,
  age_actuel_annees: age_actuel,
  annee_naissance: annee_naissance,
  taux_plein_auto_atteint: age_actuel !== null ? age_actuel >= 67 : null,
  today_date: today_date,
};

return [{
  json: {
    synthese_client,
    trimestres,
    resultats_financiers,
    dates_cles,
    today_date,
    calculs_skills: calculs.map(c => ({
      skill_id: c.skill_id,
      result: c.result_json,
      alertes: c.alertes_json,
    })),
    carriere_resume: carriere.length ? {
      premiere_annee: carriere[0]?.annee,
      derniere_annee: carriere[carriere.length - 1]?.annee,
      nombre_annees: carriere.length,
    } : null,
    user_context: webhookBody.user_context || '',
    message: webhookBody.message || '',
    locked: !!frozen.locked,
  }
}];
