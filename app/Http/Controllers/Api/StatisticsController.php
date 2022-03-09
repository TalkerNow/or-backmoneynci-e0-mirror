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

class StatisticsController extends Controller
{

    public function getStatisticsTotalIncome(Request $request)
    {
        $year = isset($request->year) ? $request->year : now()->year;
        $monthData = array(
            'clients_count' => 0,
            'current_total_count' => 0, 'current_total_amount' => 0,
            'total_ended_count' => 0,
            'current_acompte_count' => 0, 'current_acompte_amount' => 0,
            'current_solde_count' => 0, 'current_solde_amount' => 0,
            'opportunite_count' => 0, 'opportunite_amount' => 0,
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
            $monthArray[(int)date('n', strtotime($item->created_at))]['clients_count'] += 1;
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
            $monthArray[(int)date('n', strtotime($item->deposit_date))]['current_acompte_count'] += 1;
            $monthArray[(int)date('n', strtotime($item->deposit_date))]['current_acompte_amount'] += $item->pre_payment;
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
            $monthArray[(int)date('n', strtotime($item->sold_date))]['current_solde_count'] += 1;
            $monthArray[(int)date('n', strtotime($item->sold_date))]['current_solde_amount'] += $item->end_payment;
        }
        // ? terminer
        $ended = DB::table('documents')
            ->whereYear('updated_at', $year)
            ->where('document_state', 'Termine')
            ->where('status_payment', 2)
            ->get();
        foreach ($ended as $item) {
            $monthArray[(int)date('n', strtotime($item->updated_at))]['total_ended_count'] += 1;
        }
        // ? total opportunite
        $opportunite = DB::table('documents')
            ->whereYear('created_at', $year)
            ->where('document_state', 'En attente')
            ->get();
        foreach ($opportunite as $item) {
            $monthArray[(int)date('n', strtotime($item->created_at))]['opportunite_count'] += 1;
            $monthArray[(int)date('n', strtotime($item->created_at))]['opportunite_amount'] += $item->advanced_payment;
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
        $monthWaitingArray = array(1 => 13);
        $monthRunningArray = array(1 => 13);
        $monthEndedArray = array(1 => 13);
        for ($x = 1; $x < 13; $x++) {
            $monthWaitingArray[$x] = $monthData;
            $monthRunningArray[$x] = $monthData;
            $monthEndedArray[$x] = $monthData;
        }
        $monthArray = array(3);
        $monthArray[0] = $monthWaitingArray;
        $monthArray[1] = $monthRunningArray;
        $monthArray[2] = $monthEndedArray;
        // TODO optimiser 1 query au lieu de 3, utiliser des for et tableau au lieux de if
        $waiting = DB::table('documents')
            ->whereYear('created_at', '=', $year)
            ->where('document_state', 'En attente')
            ->get();
        foreach ($waiting as $item) {
            if (strpos($item->subscribe_services, 'CH') !== false) {
                $monthArray[0][(int)date('n', strtotime($item->created_at))]['CH'] += 1;
            }
            if (strpos($item->subscribe_services, ' SIMU') !== false) {
                $monthArray[0][(int)date('n', strtotime($item->created_at))]['SIMU'] += 1;
            }
            if (strpos($item->subscribe_services, 'AR') !== false) {
                $monthArray[0][(int)date('n', strtotime($item->created_at))]['AR'] += 1;
            }
            if (strpos($item->subscribe_services, 'TFD') !== false) {
                $monthArray[0][(int)date('n', strtotime($item->created_at))]['TFD'] += 1;
            }
            if (strpos($item->subscribe_services, 'ACTU') !== false) {
                $monthArray[0][(int)date('n', strtotime($item->created_at))]['ACTU'] += 1;
            }
            if (strpos($item->subscribe_services, 'RAC') !== false) {
                $monthArray[0][(int)date('n', strtotime($item->created_at))]['RAC'] += 1;
            }
        }
        $running = DB::table('documents')
            ->whereYear('deposit_date', '=', $year)
            ->where('document_state', 'En cours')
            ->get();
        foreach ($running as $item) {
            if (strpos($item->subscribe_services, 'CH') !== false) {
                $monthArray[1][(int)date('n', strtotime($item->deposit_date))]['CH'] += 1;
            }
            if (strpos($item->subscribe_services, ' SIMU') !== false) {
                $monthArray[1][(int)date('n', strtotime($item->deposit_date))]['SIMU'] += 1;
            }
            if (strpos($item->subscribe_services, 'AR') !== false) {
                $monthArray[1][(int)date('n', strtotime($item->deposit_date))]['AR'] += 1;
            }
            if (strpos($item->subscribe_services, 'TFD') !== false) {
                $monthArray[1][(int)date('n', strtotime($item->deposit_date))]['TFD'] += 1;
            }
            if (strpos($item->subscribe_services, 'ACTU') !== false) {
                $monthArray[1][(int)date('n', strtotime($item->deposit_date))]['ACTU'] += 1;
            }
            if (strpos($item->subscribe_services, 'RAC') !== false) {
                $monthArray[1][(int)date('n', strtotime($item->deposit_date))]['RAC'] += 1;
            }
        }
        $ended = DB::table('documents')
            ->whereYear('updated_at', '=', $year)
            ->where('document_state', 'Termine')
            ->get();
        foreach ($ended as $item) {
            if (strpos($item->subscribe_services, 'CH') !== false) {
                $monthArray[2][(int)date('n', strtotime($item->updated_at))]['CH'] += 1;
            }
            if (strpos($item->subscribe_services, ' SIMU') !== false) {
                $monthArray[2][(int)date('n', strtotime($item->updated_at))]['SIMU'] += 1;
            }
            if (strpos($item->subscribe_services, 'AR') !== false) {
                $monthArray[2][(int)date('n', strtotime($item->updated_at))]['AR'] += 1;
            }
            if (strpos($item->subscribe_services, 'TFD') !== false) {
                $monthArray[2][(int)date('n', strtotime($item->updated_at))]['TFD'] += 1;
            }
            if (strpos($item->subscribe_services, 'ACTU') !== false) {
                $monthArray[2][(int)date('n', strtotime($item->updated_at))]['ACTU'] += 1;
            }
            if (strpos($item->subscribe_services, 'RAC') !== false) {
                $monthArray[2][(int)date('n', strtotime($item->updated_at))]['RAC'] += 1;
            }
        }
        return json_encode($monthArray);
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
            if ($item->document_state === 'En attente' && $key !== null) {
                $memberList[$key]['totalData'][(int)date('n', strtotime($item->created_at))]['En attente'] += 1;
                $memberList[$key]['totalData'][(int)date('n', strtotime($item->created_at))]['CA En attente'] += $item->advanced_payment;
            }
            if ($item->document_state === 'En cours' && $key !== null) {
                $memberList[$key]['totalData'][(int)date('n', strtotime($item->deposit_date))]['En cours'] += 1;
                $memberList[$key]['totalData'][(int)date('n', strtotime($item->deposit_date))]['CA En cours'] += $item->advanced_payment;
            }
            if ($item->document_state === 'Termine' && $key !== null) {
                $memberList[$key]['totalData'][(int)date('n', strtotime($item->updated_at))]['Termine'] += 1;
                $memberList[$key]['totalData'][(int)date('n', strtotime($item->updated_at))]['CA Termine'] += $item->advanced_payment;
            }
            if ($item->creator_id === null)
                continue;
            $creator = $this->getKeyByID($memberList,  $item->creator_id);
            if ($creator !== null && $item->document_state === 'En attente') {
                $memberList[$creator]['totalData'][(int)date('n', strtotime($item->created_at))]['creer En attente'] += 1;
                $memberList[$creator]['totalData'][(int)date('n', strtotime($item->created_at))]['CA creer En attente'] += $item->advanced_payment;
            }
            if ($creator !== null && $item->document_state === 'En cours') {
                $memberList[$creator]['totalData'][(int)date('n', strtotime($item->deposit_date))]['creer En cours'] += 1;
                $memberList[$creator]['totalData'][(int)date('n', strtotime($item->deposit_date))]['CA creer En cours'] += $item->advanced_payment;
            }
            if ($creator !== null && $item->document_state === 'Termine') {
                $memberList[$creator]['totalData'][(int)date('n', strtotime($item->updated_at))]['creer Termine'] += 1;
                $memberList[$creator]['totalData'][(int)date('n', strtotime($item->updated_at))]['CA creer Termine'] += $item->advanced_payment;
            }
        }
        // ? member info for waiting contracts
        $waiting = DB::table('documents')
            ->whereYear('created_at', $year)
            ->get();
        foreach ($waiting as $item) {
            $key = $this->getKeyByID($memberList, $item->parent_id);
            if ($item->document_state === 'En attente' && $key !== null) {
                $memberList[$key]['monthArray'][(int)date('n', strtotime($item->created_at))]['En attente'] += 1;
                $memberList[$key]['monthArray'][(int)date('n', strtotime($item->created_at))]['CA En attente'] += $item->advanced_payment;
            }
            if ($item->creator_id === null)
                continue;
            $creator = $this->getKeyByID($memberList,  $item->creator_id);
            if ($creator !== null && $item->document_state === 'En attente') {
                $memberList[$creator]['monthArray'][(int)date('n', strtotime($item->created_at))]['creer En attente'] += 1;
                $memberList[$creator]['monthArray'][(int)date('n', strtotime($item->created_at))]['CA creer En attente'] += $item->advanced_payment;
            }
        }
        // ? member info for running contracts
        $running = DB::table('documents')
            ->whereYear('deposit_date', $year)
            ->get();
        foreach ($running as $item) {
            $key = $this->getKeyByID($memberList, $item->parent_id);
            if ($item->document_state === 'En cours' && $key !== null) {
                $memberList[$key]['monthArray'][(int)date('n', strtotime($item->deposit_date))]['En cours'] += 1;
                $memberList[$key]['monthArray'][(int)date('n', strtotime($item->deposit_date))]['CA En cours'] += $item->advanced_payment;
            }
            if ($item->creator_id === null)
                continue;
            $creator = $this->getKeyByID($memberList,  $item->creator_id);
            if ($creator !== null && $item->document_state === 'En cours') {
                $memberList[$creator]['monthArray'][(int)date('n', strtotime($item->deposit_date))]['creer En cours'] += 1;
                $memberList[$creator]['monthArray'][(int)date('n', strtotime($item->deposit_date))]['CA creer En cours'] += $item->advanced_payment;
            }
        }
        // ? member info for ended contracts
        $ended = DB::table('documents')
            ->whereYear('updated_at', $year)
            ->get();
        foreach ($ended as $item) {
            $key = $this->getKeyByID($memberList, $item->parent_id);
            if ($item->document_state === 'Termine' && $key !== null) {
                $memberList[$key]['monthArray'][(int)date('n', strtotime($item->updated_at))]['Termine'] += 1;
                $memberList[$key]['monthArray'][(int)date('n', strtotime($item->updated_at))]['CA Termine'] += $item->advanced_payment;
            }
            if ($item->creator_id === null)
                continue;
            $creator = $this->getKeyByID($memberList,  $item->creator_id);
            if ($creator !== null && $item->document_state === 'Termine') {
                $memberList[$creator]['monthArray'][(int)date('n', strtotime($item->updated_at))]['creer Termine'] += 1;
                $memberList[$creator]['monthArray'][(int)date('n', strtotime($item->updated_at))]['CA creer Termine'] += $item->advanced_payment;
            }
        }
        return json_encode($memberList);
    }
}
