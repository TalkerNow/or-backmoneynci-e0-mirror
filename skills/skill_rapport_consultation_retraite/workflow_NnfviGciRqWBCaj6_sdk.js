import { workflow, node, trigger, languageModel, newCredential, expr } from '@n8n/workflow-sdk';

const AGREG_JS_CODE = "// AGREGATION FINALE V9 — enrichi pour le rapport DEMANGE-style\n" +
"const webhookBody = $('Webhook').first().json.body || {};\n" +
"\n" +
"let ctx = webhookBody.simulateur_context;\n" +
"if (typeof ctx === 'string') {\n" +
"  try { ctx = JSON.parse(ctx); } catch (_) { ctx = {}; }\n" +
"}\n" +
"ctx = ctx || {};\n" +
"\n" +
"const frozen = ctx.frozen_data || {};\n" +
"const calculs = Array.isArray(ctx.calculs) ? ctx.calculs : [];\n" +
"const meta = frozen.meta || {};\n" +
"const totaux = frozen.totaux || {};\n" +
"const carriere = Array.isArray(frozen.carriere) ? frozen.carriere : [];\n" +
"\n" +
"const findSkill = (id) => calculs.find(c => (c.skill_id || '').toLowerCase() === String(id).toLowerCase());\n" +
"\n" +
"function getTrimestresRequis(annee) {\n" +
"  if (!annee) return null;\n" +
"  const TABLE = { 1958:167,1959:167,1960:167,1961:168,1962:169,1963:170,1964:170,1965:170,1966:172,1967:172 };\n" +
"  if (annee in TABLE) return TABLE[annee];\n" +
"  if (annee < 1958) return 166;\n" +
"  if (annee >= 1968) return 172;\n" +
"  return 172;\n" +
"}\n" +
"\n" +
"function parseDateLocal(s) {\n" +
"  if (!s) return null;\n" +
"  s = String(s).trim();\n" +
"  if (/^\\d{4}-\\d{2}-\\d{2}/.test(s)) return new Date(s.substring(0, 10) + 'T00:00:00');\n" +
"  if (/^\\d{2}\\/\\d{2}\\/\\d{4}$/.test(s)) {\n" +
"    const p = s.split('/');\n" +
"    return new Date(p[2] + '-' + p[1] + '-' + p[0] + 'T00:00:00');\n" +
"  }\n" +
"  return null;\n" +
"}\n" +
"\n" +
"const synthese_client = {\n" +
"  nom: [meta.prenom, meta.nom].filter(Boolean).join(' ').trim() || null,\n" +
"  prenom: meta.prenom || null,\n" +
"  nom_famille: meta.nom || null,\n" +
"  genre: meta.sexe || null,\n" +
"  date_naissance: meta.date_naissance || null,\n" +
"  nir: meta.nir || webhookBody.nir || null,\n" +
"  enfants: meta.enfants ?? null,\n" +
"};\n" +
"\n" +
"const ddn = parseDateLocal(meta.date_naissance);\n" +
"const annee_naissance = ddn ? ddn.getFullYear() : null;\n" +
"\n" +
"const today = new Date();\n" +
"let age_actuel = null;\n" +
"if (ddn) {\n" +
"  age_actuel = today.getFullYear() - ddn.getFullYear();\n" +
"  const m = today.getMonth() - ddn.getMonth();\n" +
"  if (m < 0 || (m === 0 && today.getDate() < ddn.getDate())) age_actuel--;\n" +
"}\n" +
"const today_dd = String(today.getDate()).padStart(2, '0');\n" +
"const today_mm = String(today.getMonth() + 1).padStart(2, '0');\n" +
"const today_yyyy = today.getFullYear();\n" +
"const today_date = today_dd + '/' + today_mm + '/' + today_yyyy;\n" +
"\n" +
"const enfants_nb = Number(meta.enfants) || 0;\n" +
"const sexeRaw = String(meta.sexe || '').toLowerCase().trim();\n" +
"const nirStr = synthese_client.nir ? String(synthese_client.nir) : '';\n" +
"const isFemme = sexeRaw === 'f' || sexeRaw === 'femme' || sexeRaw === 'female' || sexeRaw === 'mme' || sexeRaw === 'madame' || nirStr.startsWith('2');\n" +
"const trim_majo_enfants = isFemme ? enfants_nb * 8 : 0;\n" +
"const trim_acquis_ris = Number(totaux.trimestres_total ?? totaux.trimestres_cotises ?? 0);\n" +
"const trim_acquis_avec_enfants = trim_acquis_ris + trim_majo_enfants;\n" +
"const trim_requis = getTrimestresRequis(annee_naissance);\n" +
"const trim_manquants = trim_requis !== null ? Math.max(0, trim_requis - trim_acquis_avec_enfants) : null;\n" +
"\n" +
"const trimestres = {\n" +
"  total_tous_regimes: trim_acquis_ris,\n" +
"  trimestres_cotises_rg: totaux.trimestres_cotises_rg ?? totaux.trimestres_cnav ?? 0,\n" +
"  par_regime: totaux.trimestres_par_regime || {},\n" +
"  enfants_nb: enfants_nb,\n" +
"  majoration_enfants_app: trim_majo_enfants,\n" +
"  total_avec_enfants: trim_acquis_avec_enfants,\n" +
"  requis_loi: trim_requis,\n" +
"  manquants: trim_manquants,\n" +
"};\n" +
"\n" +
"let date_releve = null;\n" +
"if (totaux.date_releve) date_releve = totaux.date_releve;\n" +
"else if (carriere.length > 0 && carriere[carriere.length - 1] && carriere[carriere.length - 1].annee) {\n" +
"  date_releve = '31/12/' + carriere[carriere.length - 1].annee;\n" +
"}\n" +
"\n" +
"const simRapport = findSkill('simulation_retraite');\n" +
"const cnavSkill = findSkill('cnav');\n" +
"const arrcoSkill = findSkill('agirc_arrco') || findSkill('arrco') || findSkill('agirc');\n" +
"\n" +
"const safeNumber = (v) => (typeof v === 'number' && Number.isFinite(v)) ? v : 0;\n" +
"\n" +
"const resultats_financiers = {\n" +
"  pension_mensuelle_estimee_actuelle:\n" +
"    simRapport?.calcul_json?.pension_mensuelle_totale\n" +
"    ?? safeNumber(cnavSkill?.result_json?.pension_mensuelle) + safeNumber(arrcoSkill?.result_json?.pension_mensuelle),\n" +
"  detail_cnav: cnavSkill?.result_json || null,\n" +
"  detail_arrco: arrcoSkill?.result_json || null,\n" +
"  sam: totaux.sam ?? null,\n" +
"};\n" +
"\n" +
"const scenarios = Array.isArray(frozen.scenarios_choisis) ? frozen.scenarios_choisis : [];\n" +
"const dates_cles = {\n" +
"  scenarios_retenus: scenarios,\n" +
"  date_retenue_principale: scenarios[0]?.date || frozen.date_retenue || null,\n" +
"  date_releve: date_releve,\n" +
"  age_actuel_annees: age_actuel,\n" +
"  annee_naissance: annee_naissance,\n" +
"  taux_plein_auto_atteint: age_actuel !== null ? age_actuel >= 67 : null,\n" +
"  today_date: today_date,\n" +
"};\n" +
"\n" +
"return [{\n" +
"  json: {\n" +
"    synthese_client,\n" +
"    trimestres,\n" +
"    resultats_financiers,\n" +
"    dates_cles,\n" +
"    today_date,\n" +
"    calculs_skills: calculs.map(c => ({\n" +
"      skill_id: c.skill_id,\n" +
"      result: c.result_json,\n" +
"      alertes: c.alertes_json,\n" +
"    })),\n" +
"    carriere_resume: carriere.length ? {\n" +
"      premiere_annee: carriere[0]?.annee,\n" +
"      derniere_annee: carriere[carriere.length - 1]?.annee,\n" +
"      nombre_annees: carriere.length,\n" +
"    } : null,\n" +
"    user_context: webhookBody.user_context || '',\n" +
"    message: webhookBody.message || '',\n" +
"    locked: !!frozen.locked,\n" +
"  }\n" +
"}];\n";

const CLEAN_JS_CODE = "// CLEAN FOR PROMPT V1 - reduces JSON volume before final prompt\n" +
"const data = JSON.parse(JSON.stringify($input.first().json));\n" +
"delete data.debug_carriere_detaillee_regex;\n" +
"delete data.debug_pdf_text_preview;\n" +
"delete data.debug_extraction_source;\n" +
"delete data._validation;\n" +
"delete data._qualite_extraction;\n" +
"if (data?.sources_techniques?.cnav?.details_calcul) {\n" +
"  const calc = data.sources_techniques.cnav.details_calcul;\n" +
"  if (Array.isArray(calc.meilleures_annees) && calc.meilleures_annees.length > 8) {\n" +
"    const top5 = calc.meilleures_annees.slice(0, 5);\n" +
"    const bottom3 = calc.meilleures_annees.slice(-3);\n" +
"    calc.meilleures_annees_resume = { top_5: top5, bottom_3: bottom3, nombre_total_annees_retenues: calc.meilleures_annees.length };\n" +
"    delete calc.meilleures_annees;\n" +
"  }\n" +
"  delete calc.debug_exclusions;\n" +
"}\n" +
"if (Array.isArray(data.detail_annuel) && data.detail_annuel.length > 10) {\n" +
"  data.detail_annuel_resume = { premiere_annee: data.detail_annuel[0]?.annee, derniere_annee: data.detail_annuel[data.detail_annuel.length - 1]?.annee, nombre_annees: data.detail_annuel.length };\n" +
"  delete data.detail_annuel;\n" +
"}\n" +
"if (Array.isArray(data.carriere_detaillee) && data.carriere_detaillee.length > 10) {\n" +
"  data.carriere_resume = { nombre_annees: data.carriere_detaillee.length, premiere_annee: data.carriere_detaillee[0]?.annee, derniere_annee: data.carriere_detaillee[data.carriere_detaillee.length - 1]?.annee };\n" +
"  delete data.carriere_detaillee;\n" +
"}\n" +
"delete data.parametres_actuels;\n" +
"delete data.alertes_detection;\n" +
"return [{ json: data }];\n";

const AUTOFILL_JS_CODE = "// ADD AUTOFILL DATA V7 - extracts HTML from LLM output for frontend\n" +
"var htmlOutput = $input.first().json;\n" +
"var agregData = $('AGREGATION FINALE').first().json;\n" +
"var rawText = '';\n" +
"if (htmlOutput.response && typeof htmlOutput.response.text === 'string') rawText = htmlOutput.response.text;\n" +
"else if (typeof htmlOutput.text === 'string') rawText = htmlOutput.text;\n" +
"else if (typeof htmlOutput.response === 'string') rawText = htmlOutput.response;\n" +
"else if (typeof htmlOutput.output === 'string') rawText = htmlOutput.output;\n" +
"else if (typeof htmlOutput.content === 'string') rawText = htmlOutput.content;\n" +
"else rawText = JSON.stringify(htmlOutput);\n" +
"var trimmed = rawText.trim();\n" +
"if (trimmed.charAt(0) === '\"' && trimmed.charAt(trimmed.length - 1) === '\"') {\n" +
"  try { rawText = JSON.parse(trimmed); } catch(e) { rawText = trimmed.substring(1, trimmed.length - 1); }\n" +
"}\n" +
"for (var pass = 0; pass < 3; pass++) {\n" +
"  if (rawText.indexOf('\\\\n') !== -1) rawText = rawText.split('\\\\n').join('\\n');\n" +
"  if (rawText.indexOf('\\\\t') !== -1) rawText = rawText.split('\\\\t').join('\\t');\n" +
"  if (rawText.indexOf('\\\\\"') !== -1) rawText = rawText.split('\\\\\"').join('\"');\n" +
"}\n" +
"rawText = rawText.trim();\n" +
"if (rawText.substring(0, 7) === '```html') rawText = rawText.substring(7);\n" +
"else if (rawText.substring(0, 3) === '```') rawText = rawText.substring(3);\n" +
"if (rawText.length > 3 && rawText.substring(rawText.length - 3) === '```') rawText = rawText.substring(0, rawText.length - 3);\n" +
"rawText = rawText.trim();\n" +
"return [{ json: { text: rawText, html_report: rawText, synthese_client: (agregData && agregData.synthese_client) || {}, estimation_financiere: (agregData && agregData.estimation_financiere) || {} } }];\n";

const PROMPT1_DATES_USER_TEXT = "Voici les données brutes du RIS (Relevé de situation) au format JSON :\n\n" +
"{{JSON.stringify($json)}}\n\n" +
"Analyse ces données. Utilise bien le champ \"trimestres_total_tous_regimes\" situé dans la section CNAV ou fait la somme intelligente si ce champ est absent. Ignore les erreurs techniques (debug_exclusions) pour le calcul des dates, concentre-toi sur les droits acquis.\nGénère le JSON de sortie.";

const PROMPT1_DATES_SYSTEM_MESSAGE = "ROLE : Tu es un actuaire expert en retraite française (Réforme Borne 2023).\n\n" +
"OBJECTIF : Analyser le JSON brut d'un RIS, calculer les stocks réels et projeter les dates de départ exactes.\n\n" +
"RÈGLES DE RÉFÉRENCE (TABLE OFFICIELLE 2023 - LOI BORNE) :\n" +
"- Avant 01/09/1961 : 62 ans / 168 T\n" +
"- 01/09/1961 - 31/12/1961 : 62 ans + 3 mois / 169 T\n" +
"- 1962 : 62 ans + 6 mois / 169 T\n" +
"- 01/01/1963 - 31/08/1963 : 62 ans + 9 mois / 170 T\n" +
"- 01/09/1963 - 31/12/1963 : 63 ans / 170 T\n" +
"- 1964 : 63 ans / 171 T\n" +
"- 1965 : 63 ans + 3 mois / 172 T\n" +
"- 1966 : 63 ans + 6 mois / 172 T\n" +
"- 1967 : 63 ans + 9 mois / 172 T\n" +
"- Dès 1968 : 64 ans / 172 T\n\n" +
"LOGIQUE DYNAMIQUE DE CALCUL :\n\n" +
"1. IDENTIFICATION CLIENT :\n" +
"   - Trouve le NIR (Numéro Sécu). Si commence par '2', c'est une FEMME. Si '1', c'est un HOMME.\n" +
"   - Trouve la \"Dernière Année Validée\" dans le relevé de carrière (le maximum de la colonne 'annee').\n" +
"   - Définit la \"Date Début Projection\" = 01/01 de l'année suivant la \"Dernière Année Validée\".\n\n" +
"2. CALCUL DU STOCK DE TRIMESTRES (RIS + ENFANTS) :\n" +
"   - Stock RIS = champ \"trimestres_total_tous_regimes\" (ou somme des trimestres retenus si champ absent).\n" +
"   - Majoration Enfants (MDA) :\n" +
"     * SI FEMME (NIR '2') : Ajoute 8 trimestres par enfant déclaré.\n" +
"     * SI HOMME (NIR '1') : Ajoute 0 trimestre par enfant (sauf instruction contraire explicite).\n" +
"   - Stock Total Réel = Stock RIS + Majoration Enfants.\n\n" +
"3. CALCUL DU MANQUE A GAGNER :\n" +
"   - Cible = Trimestres Requis selon l'année de naissance.\n" +
"   - Manquants = Cible - Stock Total Réel. (Minimum 0).\n\n" +
"4. CALCUL DES DATES (REGLE DU 1er DU MOIS) :\n" +
"   Toute date de départ est forcée au 1er du mois suivant l'anniversaire ou l'obtention des trimestres.\n   \n" +
"   - Date Age Légal : Date Naissance + Age Légal (Table).\n   \n" +
"   - Date Taux Plein Durée (Calculée) :\n" +
"     * Point de départ : \"Date Début Projection\".\n" +
"     * Durée à projeter : \"Manquants\" (1 trimestre = 3 mois civils, ou 4 trimestres par an).\n" +
"     * Date Fin Projection = Point de départ + Durée à projeter.\n" +
"     * Ajustement : 1er du mois suivant.\n" +
"     * GARDE-FOU : Cette date ne peut être avant la \"Date Age Légal\".\n   \n" +
"   - Date Taux Plein Auto : Date Naissance + 67 ans.\n\n" +
"INSTRUCTIONS DE SORTIE (JSON UNIQUEMENT) :\n" +
"Génère un JSON strict avec cette structure, sans texte avant ni après :\n" +
"{\n" +
"  \"profil\": {\n" +
"    \"genre\": \"Homme/Femme\",\n" +
"    \"annee_naissance\": 0000,\n" +
"    \"derniere_annee_releve\": 0000,\n" +
"    \"date_debut_projection_retenue\": \"JJ/MM/AAAA\"\n" +
"  },\n" +
"  \"trimestres\": {\n" +
"    \"acquis_ris\": 0,\n" +
"    \"enfants_nb\": 0,\n" +
"    \"majoration_enfants_appliquee\": 0,\n" +
"    \"total_avec_enfants\": 0,\n" +
"    \"requis_loi\": 0,\n" +
"    \"manquants_reels\": 0\n" +
"  },\n" +
"  \"dates_finales\": {\n" +
"    \"age_legal_date\": \"JJ/MM/AAAA\",\n" +
"    \"taux_plein_duree_date\": \"JJ/MM/AAAA\",\n" +
"    \"taux_plein_auto_date\": \"JJ/MM/AAAA\"\n" +
"  },\n" +
"  \"analyse_synthetique\": \"Texte explicatif court.\"\n" +
"}";

const PROMPT_HTML_TEXT = "Tu es consultant senior en audit retraite chez EOR Consultants. Tu rédiges un RAPPORT DE CONSULTATION clair d'une page A4, destiné au client en fin d'entretien.\n\n" +
"DATE DU JOUR (à utiliser pour le footer) : {{ $('AGREGATION FINALE').first().json.today_date }}\n\n" +
"CONTEXTE DOSSIER\n================\n" +
"Données client :\n" +
"- Nom complet : {{ $('AGREGATION FINALE').first().json.synthese_client.nom }}\n" +
"- Prénom : {{ $('AGREGATION FINALE').first().json.synthese_client.prenom }}\n" +
"- Nom de famille : {{ $('AGREGATION FINALE').first().json.synthese_client.nom_famille }}\n" +
"- Genre : {{ $('AGREGATION FINALE').first().json.synthese_client.genre }}\n" +
"- Date de naissance : {{ $('AGREGATION FINALE').first().json.synthese_client.date_naissance }}\n" +
"- Année naissance : {{ $('AGREGATION FINALE').first().json.dates_cles.annee_naissance }}\n" +
"- Âge actuel : {{ $('AGREGATION FINALE').first().json.dates_cles.age_actuel_annees }} ans\n" +
"- Nb enfants : {{ $('AGREGATION FINALE').first().json.synthese_client.enfants }}\n\n" +
"Trimestres :\n" +
"- Total tous régimes (RIS) : {{ $('AGREGATION FINALE').first().json.trimestres.total_tous_regimes }}\n" +
"- Régime général (CNAV) : {{ $('AGREGATION FINALE').first().json.trimestres.trimestres_cotises_rg }}\n" +
"- Majoration enfants (8T/enfant si femme) : {{ $('AGREGATION FINALE').first().json.trimestres.majoration_enfants_app }}\n" +
"- Total avec majoration enfants : {{ $('AGREGATION FINALE').first().json.trimestres.total_avec_enfants }}\n" +
"- Trimestres requis (loi Borne) : {{ $('AGREGATION FINALE').first().json.trimestres.requis_loi }}\n" +
"- Trimestres manquants : {{ $('AGREGATION FINALE').first().json.trimestres.manquants }}\n" +
"- Détail par régime : {{ JSON.stringify($('AGREGATION FINALE').first().json.trimestres.par_regime) }}\n\n" +
"Date de relevé : {{ $('AGREGATION FINALE').first().json.dates_cles.date_releve }}\n" +
"Taux plein auto déjà atteint (>=67 ans) : {{ $('AGREGATION FINALE').first().json.dates_cles.taux_plein_auto_atteint }}\n\n" +
"Résultats financiers :\n" +
"- Pension mensuelle estimée : {{ $('AGREGATION FINALE').first().json.resultats_financiers.pension_mensuelle_estimee_actuelle }}\n" +
"- SAM : {{ $('AGREGATION FINALE').first().json.resultats_financiers.sam }}\n" +
"- Détail CNAV : {{ JSON.stringify($('AGREGATION FINALE').first().json.resultats_financiers.detail_cnav) }}\n" +
"- Détail AGIRC-ARRCO : {{ JSON.stringify($('AGREGATION FINALE').first().json.resultats_financiers.detail_arrco) }}\n\n" +
"Carrière :\n" +
"- Résumé : {{ JSON.stringify($('AGREGATION FINALE').first().json.carriere_resume) }}\n\n" +
"Dates clés calculées par l'agent dates (JSON brut, contient dates_finales.age_legal_date / taux_plein_duree_date / taux_plein_auto_date) :\n" +
"{{ $('prompt1').item.json.text }}\n\n" +
"Calculs de dispositifs lancés : {{ JSON.stringify($('AGREGATION FINALE').first().json.calculs_skills) }}\n\n" +
"Demande spécifique du consultant (peut être vide) : \"{{ $('Webhook').first().json.body.user_context }}\"\n" +
"Thématique : {{ $('Webhook').first().json.body.message }}\n\n" +
"RÈGLE NUMÉRIQUE ABSOLUE (anti-hallucination)\n============================================\n" +
"- Toute valeur chiffrée affichée dans le rapport DOIT exister à l'identique dans le contexte ci-dessus ou dans le JSON \"Dates clés calculées\".\n" +
"- INTERDIT de calculer un total, une somme, une moyenne, un écart, un pourcentage qui n'existerait pas déjà dans le contexte.\n" +
"- Si une valeur est absente (null, undefined, 0 non significatif) : afficher \"—\" ou \"N/A\". JAMAIS inventer, JAMAIS estimer.\n" +
"- Cohérence numérique : si tu affiches une ligne Total : X et que la décomposition est aussi visible, X DOIT correspondre à la somme exacte des composantes. Si tu ne peux pas garantir cette cohérence, n'affiche PAS de ligne Total.\n" +
"- Pour les enfants : la majoration de 8 trimestres par enfant pour les femmes est déjà calculée dans trimestres.majoration_enfants_app. NE recalcule PAS, recopie cette valeur.\n" +
"- Le total avec majoration enfants est déjà calculé dans trimestres.total_avec_enfants. NE recalcule PAS, recopie cette valeur.\n" +
"- POUR LA DATE DU JOUR : utilise EXACTEMENT la valeur DATE DU JOUR fournie en haut de ce contexte. N'invente PAS de date du jour, ne te base PAS sur ta date de coupure.\n\n" +
"DESIGN SYSTEM EOR\n=================\n" +
"Palette : #20295B (bleu marine principal), #2B3E71 (bleu marine secondaire), #F39130 (orange accent), #c62828 (rouge danger .status-danger si manquants > 0).\n" +
"Backgrounds : #f0f2f5 (body), #ffffff (page-container), #f8f9fa (client-box), #fff3e0 (total-row + consultant-note), #e8eaf6 (dash-title).\n" +
"Police : 'Arial', sans-serif partout.\n\n" +
"CSS à inclure dans <style> :\n" +
"body { background-color: #f0f2f5; font-family: 'Arial', sans-serif; padding: 40px; display: flex; justify-content: center; }\n" +
".page-container { background-color: white; width: 21cm; min-height: 29.7cm; padding: 2cm; box-shadow: 0 4px 15px rgba(0,0,0,0.1); color: #333; }\n" +
".header { border-bottom: 4px solid #20295B; padding-bottom: 10px; margin-bottom: 30px; }\n" +
".logo-text { font-size: 20px; font-weight: bold; color: #20295B; text-transform: uppercase; letter-spacing: 1px; }\n" +
".doc-title { font-size: 24px; font-weight: bold; color: #F39130; margin-top: 5px; text-transform: uppercase; }\n" +
".client-box { background-color: #f8f9fa; border-left: 5px solid #2B3E71; padding: 15px 20px; margin-bottom: 30px; display: flex; justify-content: space-between; font-size: 13px; align-items: center; }\n" +
"h2 { font-size: 16px; color: #20295B; border-bottom: 2px solid #eee; padding-bottom: 8px; margin-top: 40px; margin-bottom: 20px; text-transform: uppercase; }\n" +
".dashboard-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px; }\n" +
".dash-box { background-color: #f9fafb; border: 1px solid #d1d5db; border-radius: 6px; overflow: hidden; }\n" +
".dash-title { font-size: 12px; color: #20295B; font-weight: bold; text-transform: uppercase; background-color: #e8eaf6; padding: 8px 10px; border-bottom: 1px solid #d1d5db; }\n" +
".dash-data { padding: 10px; font-size: 12px; line-height: 1.5; }\n" +
".status-danger { color: #c62828; font-weight: bold; }\n" +
"table { width: 100%; border-collapse: collapse; font-size: 11px; margin-top: 15px; table-layout: fixed; }\n" +
"th { padding: 10px 5px; text-align: center; border: 1px solid #20295B; color: white; background-color: #20295B; font-weight: 600; text-transform: uppercase; }\n" +
"td { border: 1px solid #e0e0e0; padding: 8px; text-align: center; vertical-align: middle; }\n" +
".row-label { text-align: left; font-weight: bold; color: #2B3E71; background-color: #fafafa; padding-left: 10px; }\n" +
".highlight-col-header { background-color: #20295B !important; border: 1px solid #20295B; color: #fff; border-bottom: 2px solid #F39130; }\n" +
".highlight-cell { background-color: #e8eaf6; font-weight: bold; color: #20295B; border-left: 1px solid #20295B; border-right: 1px solid #20295B; }\n" +
".total-row { background-color: #fff3e0; font-weight: bold; color: #bf360c; border-top: 2px solid #F39130; font-size: 12px; }\n" +
".consultant-note { background-color: #fff3e0; border: 1px solid #F39130; padding: 20px; margin-top: 35px; border-radius: 4px; font-size: 12px; line-height: 1.5; color: #333; text-align: justify; }\n" +
".footer { margin-top: 60px; padding-top: 20px; border-top: 1px solid #ccc; text-align: center; color: #666; font-size: 9px; }\n\n" +
"STRUCTURE OBLIGATOIRE DU DOCUMENT\n=================================\n\n" +
"1. <header class=\"header\"> contenant :\n" +
"   - <div class=\"logo-text\">Audit Retraite EOR</div>\n" +
"   - <div class=\"doc-title\">…</div> dont le texte est :\n" +
"     * \"Rapport de Consultation - Détection d'Anomalies\" si trimestres.manquants > 0 ou si trimestres.total_avec_enfants == 0 (RIS vierge)\n" +
"     * \"Rapport de Consultation\" sinon (situation saine)\n\n" +
"2. <div class=\"client-box\"> avec EXACTEMENT 3 blocs (PAS de NIR) :\n" +
"   - Bloc 1 : <div><strong>Client :</strong>&nbsp;PRENOM NOM</div>\n" +
"   - Bloc 2 : <div><strong>Date de naissance :</strong> date_naissance (age_actuel_annees ans)</div>\n" +
"   - Bloc 3 : <div><strong>Trimestres Requis :</strong> requis_loi</div>\n" +
"   INTERDICTION ABSOLUE d'inclure le NIR dans le HTML rendu.\n\n" +
"3. <h2>Synthèse de la Situation Actuelle</h2>\n" +
"   + <div class=\"dashboard-grid\"> avec 2 <div class=\"dash-box\"> :\n" +
"     - Box 1 \"Jalons & Dates Clés\" : Âge Légal, Âge Taux Plein Auto (67 ans), Âge Actuel, Date de Relevé. Utilise dates_finales.age_legal_date et dates_finales.taux_plein_auto_date du JSON Dates clés.\n" +
"     - Box 2 \"État du Relevé de Carrière (RIS)\" : Trimestres Acquis (avec date_releve), Trimestres Manquants. Si manquants > 0 wrap les chiffres dans <font color=\"#ff0000\">...</font><span class=\"status-danger\">...</span>.\n\n" +
"4. <h2>Projection à l'Âge du Taux Plein Automatique</h2>\n" +
"   <table> avec EXACTEMENT 7 colonnes : Date de Départ | Âge au Départ | Trimestres Valides | Taux de Liquidation | Pension Base Est. | Pension Compl. Est. | Total Brut Mensuel (avec class=\"highlight-col-header\" sur la dernière).\n" +
"   tbody contient UNE SEULE ligne pour le taux plein auto :\n" +
"   - Date : taux_plein_auto_date au format DD/MM/YYYY\n" +
"   - Âge : 67 ans et 0 mois\n" +
"   - Trimestres : total_avec_enfants / requis_loi (wrapper status-danger si manquants > 0)\n" +
"   - Taux : 50,00 %\n" +
"   - Pension Base : detail_cnav.pension_mensuelle si présent, sinon N/A\n" +
"   - Pension Compl. : detail_arrco.pension_mensuelle si présent, sinon N/A\n" +
"   - Total : pension_mensuelle_estimee_actuelle si présent et > 0, sinon N/A (class=\"highlight-cell\")\n" +
"   <p style=\"font-size: 9px; color: #666; margin-top: 5px;\">* Si une valeur est marquée N/A, le moteur de calcul n'a pas pu la projeter pour ce dossier.</p>\n\n" +
"5. <h2>Note de Synthèse</h2>\n" +
"   <div class=\"consultant-note\">\n" +
"     Commence par <strong>📝 NOTE DE SYNTHÈSE DU CONSULTANT :</strong><br><br>\n" +
"     Puis structure en points numérotés (chaque titre en <strong>) :\n" +
"     1. Statut des droits acquis (compare total_avec_enfants vs requis_loi)\n" +
"     2. Trimestres manquants & règle légale (si manquants > 0)\n" +
"     3. Application du Taux Plein Automatique (si taux_plein_auto_atteint ou age_actuel >= 65, cite l'article L.351-8 CSS et le 50% garanti, attention au prorata)\n" +
"     4. Concordance Régime de Base / Complémentaire (compare detail_cnav et detail_arrco)\n" +
"     Termine par <strong>Recommandation immédiate :</strong> (action concrète, intègre user_context si non-vide)\n" +
"   Si situation saine (manquants == 0 et pension > 0), adapte le ton : éviter \"anomalie\", parler de \"points d'attention\" et pistes d'optimisation.\n\n" +
"6. <div class=\"footer\">Audit Retraite Généré le {DATE_DU_JOUR} — Les calculs sont effectués à partir des éléments inscrits au RIS et soumis à la réglementation en vigueur (Loi n°2023-270). Document de travail confidentiel.</div>\n" +
"   où {DATE_DU_JOUR} est REMPLACÉ par la valeur DATE DU JOUR du contexte (PAS la date du test ni une date inventée).\n\n" +
"RÈGLES DE RENDU\n===============\n" +
"- HTML COMPLET single-file : <!DOCTYPE html><html lang=\"fr\"><head><meta charset=\"UTF-8\"><title>Rapport de Consultation</title><style>…CSS inline complet…</style></head><body><div class=\"page-container\">…</div></body></html>\n" +
"- Pour le footer : utilise OBLIGATOIREMENT la valeur DATE DU JOUR fournie en haut du contexte.\n" +
"- Le NIR ne doit JAMAIS apparaître dans le HTML rendu.\n" +
"- Si user_context est non-vide : intègre ses précisions dans la Note de Synthèse (notamment au point Recommandation).\n" +
"- Renvoie UNIQUEMENT le HTML, encadré dans ```html ... ``` (le node Add Autofill Data extrait ce bloc). Aucun texte avant ou après le bloc markdown.\n";

const geminiModelForDates = languageModel({
  type: '@n8n/n8n-nodes-langchain.lmChatGoogleGemini',
  version: 1,
  config: {
    name: 'Google Gemini Chat Model2',
    parameters: { modelName: 'models/gemini-3-flash-preview', options: {} },
    credentials: { googlePalmApi: newCredential('Google Gemini(PaLM) Api account 3') },
    position: [96, 3424]
  }
});

const geminiModelForHtml = languageModel({
  type: '@n8n/n8n-nodes-langchain.lmChatGoogleGemini',
  version: 1,
  config: {
    name: 'Google Gemini Chat Model1',
    parameters: { modelName: 'models/gemini-flash-latest', options: {} },
    credentials: { googlePalmApi: newCredential('Google Gemini(PaLM) Api account 3') },
    position: [720, 3424]
  }
});

const webhookTrigger = trigger({
  type: 'n8n-nodes-base.webhook',
  version: 2.1,
  config: {
    name: 'Webhook',
    parameters: {
      httpMethod: 'POST',
      path: 'f012dfc7-8b2c-479f-af1f-20dcd44cda02',
      responseMode: 'lastNode',
      options: {}
    },
    position: [-384, 3232]
  },
  output: [{ body: { simulateur_context: '{}', user_context: '', message: 'rapport_consultation' } }]
});

const agregationFinale = node({
  type: 'n8n-nodes-base.code',
  version: 2,
  config: {
    name: 'AGREGATION FINALE',
    parameters: {
      mode: 'runOnceForAllItems',
      language: 'javaScript',
      jsCode: AGREG_JS_CODE
    },
    position: [-128, 3232]
  },
  output: [{
    synthese_client: { nom: 'PRENOM NOM', prenom: 'PRENOM', nom_famille: 'NOM', date_naissance: '15/03/1965', enfants: 2 },
    trimestres: { total_tous_regimes: 159, total_avec_enfants: 175, requis_loi: 172, manquants: 0, majoration_enfants_app: 16 },
    resultats_financiers: { sam: 56809, pension_mensuelle_estimee_actuelle: 3534, detail_cnav: null, detail_arrco: null },
    dates_cles: { date_releve: '31/12/2024', age_actuel_annees: 61, annee_naissance: 1965, taux_plein_auto_atteint: false },
    calculs_skills: [],
    carriere_resume: { premiere_annee: 1985, derniere_annee: 2024, nombre_annees: 40 },
    user_context: '',
    message: 'rapport_consultation'
  }]
});

const prompt1 = node({
  type: '@n8n/n8n-nodes-langchain.chainLlm',
  version: 1.9,
  config: {
    name: 'prompt1',
    parameters: {
      promptType: 'define',
      text: expr(PROMPT1_DATES_USER_TEXT),
      messages: {
        messageValues: [{
          type: 'SystemMessagePromptTemplate',
          message: PROMPT1_DATES_SYSTEM_MESSAGE
        }]
      }
    },
    subnodes: { model: geminiModelForDates },
    position: [96, 3232]
  },
  output: [{ text: '{"profil":{"genre":"Femme","annee_naissance":1965,"derniere_annee_releve":2024,"date_debut_projection_retenue":"01/01/2025"},"trimestres":{"acquis_ris":159,"enfants_nb":2,"majoration_enfants_appliquee":16,"total_avec_enfants":175,"requis_loi":172,"manquants_reels":0},"dates_finales":{"age_legal_date":"01/07/2028","taux_plein_duree_date":"01/07/2028","taux_plein_auto_date":"01/04/2032"},"analyse_synthetique":""}' }]
});

const cleanForPrompt = node({
  type: 'n8n-nodes-base.code',
  version: 2,
  config: {
    name: 'CLEAN FOR PROMPT',
    parameters: {
      mode: 'runOnceForAllItems',
      language: 'javaScript',
      jsCode: CLEAN_JS_CODE
    },
    position: [464, 3232]
  },
  output: [{ text: '{"profil":{},"trimestres":{},"dates_finales":{}}' }]
});

const promptHtml = node({
  type: '@n8n/n8n-nodes-langchain.chainLlm',
  version: 1.9,
  config: {
    name: 'prompt',
    parameters: {
      promptType: 'define',
      text: expr(PROMPT_HTML_TEXT)
    },
    subnodes: { model: geminiModelForHtml },
    position: [720, 3232]
  },
  output: [{ text: '```html\n<!DOCTYPE html><html lang="fr"><head><title>Rapport de Consultation</title></head><body><div class="page-container">...</div></body></html>\n```' }]
});

const addAutofillData = node({
  type: 'n8n-nodes-base.code',
  version: 2,
  config: {
    name: 'Add Autofill Data',
    parameters: {
      mode: 'runOnceForAllItems',
      language: 'javaScript',
      jsCode: AUTOFILL_JS_CODE
    },
    position: [1120, 3232]
  },
  output: [{ text: '<!DOCTYPE html>...', html_report: '<!DOCTYPE html>...', synthese_client: {}, estimation_financiere: {} }]
});

const respondToWebhook = node({
  type: 'n8n-nodes-base.respondToWebhook',
  version: 1.5,
  config: {
    name: 'Respond to Webhook3',
    parameters: { options: {} },
    position: [1408, 3232]
  },
  output: [{ text: '<!DOCTYPE html>...', html_report: '<!DOCTYPE html>...' }]
});

export default workflow('NnfviGciRqWBCaj6', 'Rapport De Consultation Retraite')
  .add(webhookTrigger)
  .to(agregationFinale)
  .to(prompt1)
  .to(cleanForPrompt)
  .to(promptHtml)
  .to(addAutofillData)
  .to(respondToWebhook);
