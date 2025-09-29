<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kpi;
use Illuminate\Http\Request;

class KpiController extends Controller
{
    // GET /api/kpis — liste (pagination simple)
    public function index()
    {
        return response()->json(
            Kpi::query()->latest('id')->paginate(25)
        );
    }

    // GET /api/kpis/{kpi} — voir un KPI
    public function show(Kpi $kpi)
    {
        $kpi->load('admin'); // relation optionnelle
        return response()->json($kpi);
    }

    // POST /api/kpis — ajouter un KPI
    // Aucun champ obligatoire : on prend seulement ce qui vient
    public function store(Request $request)
    {
        $data = $request->only([
            'kpi_date',
            'admin_id',
            'objet',
            'action',
            'nom_prenom',
            'email',
            'telephone',
            'note',
        ]);

        $kpi = Kpi::create($data);
        return response()->json($kpi, 201);
    }

    // PUT/PATCH /api/kpis/{kpi} — modifier un KPI (partiel OK)
    public function update(Request $request, Kpi $kpi)
    {
        $data = $request->only([
            'kpi_date',
            'admin_id',
            'objet',
            'action',
            'nom_prenom',
            'email',
            'telephone',
            'note',
        ]);

        $kpi->fill($data)->save();
        return response()->json($kpi);
    }

    // DELETE /api/kpis/{kpi} — supprimer un KPI
    public function destroy(Kpi $kpi)
    {
        $kpi->delete();
        return response()->json(['message' => 'KPI deleted']);
    }
}
