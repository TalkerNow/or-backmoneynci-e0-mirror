<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Controller;
use App\Models\PersonalInformations;
use App\Models\Documents;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use DB;
use DateTime;
use Log;

class StatisticsController extends Controller
{

    public function getStatisticsTotalIncome(Request $request)
    {
        $year = isset($request->year) ? $request->year : now()->year;
        $monthData = array(
            'clients_count' => 0,
            'current_total_count' => 0,
            'current_total_amount' => 0,
            'total_ended_count' => 0,
            'current_acompte_count' => 0,
            'current_acompte_amount' => 0,
            'current_solde_count' => 0,
            'current_solde_amount' => 0,
            'opportunite_count' => 0,
            'opportunite_amount' => 0,
        );
        $monthArray = array(1 => 13);
        for ($x = 1; $x < 13; $x++) {
            $monthArray[$x] = $monthData;
        }
        // ? client count
        $clients = DB::table('users')
            ->whereYear('created_at', $year)
            ->where('role', 'Client')
            ->get();
        foreach ($clients as $item) {
            $monthArray[(int) date('n', strtotime($item->created_at))]['clients_count'] += 1;
        }
        // ?  acompte em cours
        $acompte = DB::table('documents')
            ->whereYear('deposit_date', $year)
            ->where('document_state', '!=', 'En attente')
            ->where('status_payment', '!=', 0)
            ->get();
        foreach ($acompte as $item) {
            if ($item->deposit_date === null)
                continue;
            $monthArray[(int) date('n', strtotime($item->deposit_date))]['current_acompte_count'] += 1;
            $monthArray[(int) date('n', strtotime($item->deposit_date))]['current_acompte_amount'] += $item->pre_payment;
        }
        // ?  sold en cours
        $solde = DB::table('documents')
            ->whereYear('sold_date', $year)
            ->where('document_state', '!=', 'En attente')
            ->where('status_payment', '>=', 2)
            ->get();
        foreach ($solde as $item) {
            if ($item->sold_date === null)
                continue;
            $monthArray[(int) date('n', strtotime($item->sold_date))]['current_solde_count'] += 1;
            $monthArray[(int) date('n', strtotime($item->sold_date))]['current_solde_amount'] += $item->end_payment;
        }
        // ? terminer
        $ended = DB::table('documents')
            ->whereYear('updated_at', $year)
            // Correction : on inclut aussi Terminé avec accent au cas où
            ->whereIn('document_state', ['Termine', 'Terminé'])
            ->where('status_payment', 2)
            ->get();
        foreach ($ended as $item) {
            $monthArray[(int) date('n', strtotime($item->updated_at))]['total_ended_count'] += 1;
        }
        // ? total opportunite
        $opportunite = DB::table('documents')
            ->whereYear('created_at', $year)
            ->where('document_state', 'En attente')
            ->get();
        foreach ($opportunite as $item) {
            $monthArray[(int) date('n', strtotime($item->created_at))]['opportunite_count'] += 1;
            $monthArray[(int) date('n', strtotime($item->created_at))]['opportunite_amount'] += $item->advanced_payment;
        }
        for ($x = 1; $x < 13; $x++) {
            $monthArray[$x]['current_total_count'] = $monthArray[$x]['current_acompte_count'];
            $monthArray[$x]['current_total_amount'] = $monthArray[$x]['current_acompte_amount'] + $monthArray[$x]['current_solde_amount'];
        }
        return json_encode($monthArray);
    }

    public function getPrestation(Request $request)
    {
        $year = isset($request->year) ? $request->year : now()->year;
        $monthData = array(
            'CH' => 0,
            'SIMU' => 0,
            'AR' => 0,
            'TFD' => 0,
            'ACTU' => 0,
            'RAC' => 0
        );
        $monthArray = array(1 => 13);
        for ($i = 1; $i < 13; $i++) {
            $monthArray[$i] = $monthData;
        }

        $listDifferentStatus = array("En attente" => $monthArray, "En cours" => $monthArray, "Termine" => $monthArray, "Perdu" => $monthArray);

        $documentsThisYear = DB::table('documents')
            ->whereYear('created_at', $year)
            ->get();

        foreach ($documentsThisYear as $document) {
            // --- CORRECTION : Normalisation de l'état ---
            $state = $document->document_state;
            if ($state === 'Terminé') {
                $state = 'Termine';
            }
            
            // Si l'état n'est pas reconnu (autre chose que les 4 clés), on ignore
            if (!isset($listDifferentStatus[$state])) {
                continue;
            }
            // --------------------------------------------

            $monthIndex = (int) date('n', strtotime($document->created_at));

            if (strpos($document->subscribe_services, 'CH') !== false) {
                $listDifferentStatus[$state][$monthIndex]['CH'] += 1;
            }
            if (strpos($document->subscribe_services, ' SIMU') !== false) {
                $listDifferentStatus[$state][$monthIndex]['SIMU'] += 1;
            }
            if (strpos($document->subscribe_services, 'AR') !== false) {
                $listDifferentStatus[$state][$monthIndex]['AR'] += 1;
            }
            if (strpos($document->subscribe_services, 'TFD') !== false) {
                $listDifferentStatus[$state][$monthIndex]['TFD'] += 1;
            }
            if (strpos($document->subscribe_services, 'ACTU') !== false) {
                $listDifferentStatus[$state][$monthIndex]['ACTU'] += 1;
            }
            if (strpos($document->subscribe_services, 'RAC') !== false) {
                $listDifferentStatus[$state][$monthIndex]['RAC'] += 1;
            }
        }
        return json_encode($listDifferentStatus);
    }

    private function getKeyByID($array, $id)
    {
        if ($id === null) {
            return null;
        }
        for ($num = 0; $num < count($array); $num += 1) {
            if ($array[$num]['id'] === $id) {
                return $num;
            }
        }
        return null;
    }

    public function getMembersPrestation(Request $request)
    {
        $year = isset($request->year) ? $request->year : now()->year;
        $monthData = array(
            'En attente' => 0,
            'En cours' => 0,
            'Termine' => 0,
            'CA En attente' => 0,
            'CA En cours' => 0,
            'CA Termine' => 0,
            'creer En attente' => 0,
            'creer En cours' => 0,
            'creer Termine' => 0,
            'CA creer En attente' => 0,
            'CA creer En cours' => 0,
            'CA creer Termine' => 0,
            'Balance Client' => 0,
        );
        $monthArray = array(1 => 13);
        for ($x = 1; $x < 13; $x++) {
            $monthArray[$x] = $monthData;
        }
        $totalData = array(
            'En attente' => 0,
            'En cours' => 0,
            'Termine' => 0,
            'CA En attente' => 0,
            'CA En cours' => 0,
            'CA Termine' => 0,
            'creer En attente' => 0,
            'creer En cours' => 0,
            'creer Termine' => 0,
            'CA creer En attente' => 0,
            'CA creer En cours' => 0,
            'CA creer Termine' => 0,
            'Balance Client' => 0,
        );
        $memberData = array(
            'id' => 0,
            'name' => '',
            'role' => '',
            'total En cours' => 0,
            'total En attente' => 0,
            'total Termine' => 0,
            'total creer En cours' => 0,
            'total creer En attente' => 0,
            'total creer Termine' => 0,
            'total CA En cours' => 0,
            'total CA En attente' => 0,
            'total CA Termine' => 0,
            'total CA creer En cours' => 0,
            'total CA creer En attente' => 0,
            'total CA creer Termine' => 0,
            'monthArray' => $monthArray,
            'totalData' => $totalData,
        );
        $query = "SELECT * FROM users WHERE role='Expert' OR role='admin' OR role='Consultant'";
        $result = DB::select($query);
        $memberList = array(count($result));
        for ($x = 0; $x < count($result); $x++) {
            $memberList[$x] = $memberData;
        }
        $i = 0;
        foreach ($result as $item) {
            $memberList[$i]['id'] = $item->id;
            $memberList[$i]['name'] = $item->name;
            $memberList[$i]['role'] = $item->role;
            $i++;
        }
        // ? member info for all time contract
        $Total = DB::table('documents')
            ->get();
        foreach ($Total as $item) {
            $key = $this->getKeyByID($memberList, $item->parent_id);
            // Normalisation pour le total
            $state = ($item->document_state === 'Terminé') ? 'Termine' : $item->document_state;

            if ($state === 'En attente' && $key !== null) {
                $memberList[$key]['totalData']['En attente'] += 1;
                $memberList[$key]['totalData']['CA En attente'] += $item->advanced_payment;
            }
            if ($state === 'En cours' && $key !== null) {
                $memberList[$key]['totalData']['En cours'] += 1;
                $memberList[$key]['totalData']['CA En cours'] += $item->advanced_payment;
            }
            if ($state === 'Termine' && $key !== null) {
                $memberList[$key]['totalData']['Termine'] += 1;
                $memberList[$key]['totalData']['CA Termine'] += $item->advanced_payment;
            }
            if ($item->creator_id === null)
                continue;
            $creator = $this->getKeyByID($memberList, $item->creator_id);
            if ($creator !== null && $state === 'En attente') {
                $memberList[$creator]['totalData']['creer En attente'] += 1;
                $memberList[$creator]['totalData']['CA creer En attente'] += $item->advanced_payment;
            }
            if ($creator !== null && $state === 'En cours') {
                $memberList[$creator]['totalData']['creer En cours'] += 1;
                $memberList[$creator]['totalData']['CA creer En cours'] += $item->advanced_payment;
            }
            if ($creator !== null && $state === 'Termine') {
                $memberList[$creator]['totalData']['creer Termine'] += 1;
                $memberList[$creator]['totalData']['CA creer Termine'] += $item->advanced_payment;
            }
        }
        // ? member info for waiting contracts
        $waiting = DB::table('documents')
            ->whereYear('created_at', $year)
            ->get();
        foreach ($waiting as $item) {
            $key = $this->getKeyByID($memberList, $item->parent_id);
            // En attente ne change pas (pas d'accent), mais on garde la logique propre
            if ($item->document_state === 'En attente' && $key !== null) {
                $memberList[$key]['monthArray'][(int) date('n', strtotime($item->created_at))]['En attente'] += 1;
                $memberList[$key]['monthArray'][(int) date('n', strtotime($item->created_at))]['CA En attente'] += $item->advanced_payment;
            }
            if ($item->creator_id === null)
                continue;
            $creator = $this->getKeyByID($memberList, $item->creator_id);
            if ($creator !== null && $item->document_state === 'En attente') {
                $memberList[$creator]['monthArray'][(int) date('n', strtotime($item->created_at))]['creer En attente'] += 1;
                $memberList[$creator]['monthArray'][(int) date('n', strtotime($item->created_at))]['CA creer En attente'] += $item->advanced_payment;
            }
        }
        // ? member info for running contracts
        $running = DB::table('documents')
            ->whereYear('deposit_date', $year)
            ->get();
        foreach ($running as $item) {
            $key = $this->getKeyByID($memberList, $item->parent_id);
            if ($item->document_state === 'En cours' && $key !== null) {
                $memberList[$key]['monthArray'][(int) date('n', strtotime($item->deposit_date))]['En cours'] += 1;
                $memberList[$key]['monthArray'][(int) date('n', strtotime($item->deposit_date))]['CA En cours'] += $item->advanced_payment;
            }
            if ($item->creator_id === null)
                continue;
            $creator = $this->getKeyByID($memberList, $item->creator_id);
            if ($creator !== null && $item->document_state === 'En cours') {
                $memberList[$creator]['monthArray'][(int) date('n', strtotime($item->deposit_date))]['creer En cours'] += 1;
                $memberList[$creator]['monthArray'][(int) date('n', strtotime($item->deposit_date))]['CA creer En cours'] += $item->advanced_payment;
            }
        }
        // ? member info for ended contracts
        $ended = DB::table('documents')
            ->whereYear('updated_at', $year)
            ->get();
        foreach ($ended as $item) {
            $key = $this->getKeyByID($memberList, $item->parent_id);
            // Normalisation ici aussi
            $state = ($item->document_state === 'Terminé') ? 'Termine' : $item->document_state;

            if ($state === 'Termine' && $key !== null) {
                $memberList[$key]['monthArray'][(int) date('n', strtotime($item->updated_at))]['Termine'] += 1;
                $memberList[$key]['monthArray'][(int) date('n', strtotime($item->updated_at))]['CA Termine'] += $item->advanced_payment;
            }
            if ($item->creator_id === null)
                continue;
            $creator = $this->getKeyByID($memberList, $item->creator_id);
            if ($creator !== null && $state === 'Termine') {
                $memberList[$creator]['monthArray'][(int) date('n', strtotime($item->updated_at))]['creer Termine'] += 1;
                $memberList[$creator]['monthArray'][(int) date('n', strtotime($item->updated_at))]['CA creer Termine'] += $item->advanced_payment;
            }
        }
        return json_encode($memberList);
    }
}