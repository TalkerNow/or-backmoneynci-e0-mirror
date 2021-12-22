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
    public function getStatistics(Request $request)
    {
        // ? ----- get clients count------
        $query = "SELECT * FROM users WHERE role='Client' ";
        $result = DB::select($query);
        $total_client_count = count($result);

        $query = "SELECT MIN(created_at) AS min_date, MAX(created_at) AS max_date FROM users WHERE role='Client'";
        $result = DB::select($query);
        $clients_count_list = [];
        if (count($result) > 0) {
            $min_date = new DateTime($result[0]->min_date);
            $max_date = new DateTime($result[0]->max_date);
            $diff_days = $min_date->diff($max_date)->days;
            $interval = 0;
            if ($diff_days > 0) {
                $interval = intdiv($diff_days, 5);
            }
            $index_date = $min_date;

            $query = "SELECT * FROM users WHERE role ='Client' AND DATE(created_at) <= '" . $min_date->format('Y-m-d') . "'";
            $result = DB::select($query);
            $clients_count_list[0] = count($result);
            if ($interval > 0) {
                for ($index = 1; $index < 5; $index++) {
                    $index_date->modify('+' . $interval . ' day');
                    $query = "select * from users where role = 'Client' and date(created_at) <= '" . $index_date->format('Y-m-d') . "'";
                    $result = DB::select($query);
                    $clients_count_list[$index] = count($result);
                }
            }
            $query = "SELECT * FROM users WHERE role='Client' AND DATE(created_at) <= '" . $max_date->format('Y-m-d') . "'";
            $result = DB::select($query);
            if ($interval > 0)
                $clients_count_list[5] = count($result);
            else
                $clients_count_list[1] = count($result);
        }
        $year = date('Y');
        $month = date('m');
            // ?  acompte em cours
            // ! add date
            $acompte = DB::table('documents')
                ->where('document_state', 'En cours')
                ->where('status_payment', 1)
                ->get();
            $total_current_acompte_count = count($acompte);
            $total_current_acompte_amount = 0;
            foreach ($acompte as $item) {
                $total_current_acompte_amount += $item->pre_payment;
            }
            // ?  sold en cours
            // ! add date
            $solde = DB::table('documents')
                ->where('document_state', 'En cours')
                ->where('status_payment', 2)
                ->get();
            $total_current_solde_count = count($solde);
            $total_current_solde_amount = 0;
            foreach ($solde as $item) {
                $total_current_solde_amount += $item->end_payment;
            }
            // ? terminer
            // ! add date 
            $ended = DB::table('documents')
                ->where('document_state', 'Termine')
                ->where('status_payment', 2)
                ->get();
            $total_ended_count = count($ended);
            $total_ended_amount = 0;
            foreach ($ended as $item) {
                $total_ended_amount += $item->advanced_payment;
            }
            // ? total opportunite
            // ! add date
            $opportunite = DB::table('documents')
                ->where('document_state', 'En attente')
                ->get();
            $opportunite_count = count($opportunite);
            $opportunite_amount = 0;
            foreach ($opportunite as $item) {
                $opportunite_amount += $item->advanced_payment;
            }
            // ? total paid amount for no month selected
            $total_current_amount = $total_current_acompte_amount + $total_current_solde_amount;
            $total_current_count = $total_current_solde_count + $total_current_acompte_count;
       
        return response()->json([
            'clients_count' => $total_client_count, 'clients_count_list' => $clients_count_list,
            'current_total_count' => $total_current_count, 'current_total_amount' => $total_current_amount,
            'total_ended_count' => $total_ended_count, 'total_ended_amount' => $total_ended_amount,
            'current_acompte_count' => $total_current_acompte_count, 'current_acompte_amount' => $total_current_acompte_amount,
            'current_solde_count' => $total_current_solde_count, 'current_solde_amount' => $total_current_solde_amount,
            'opportunite_count' => $opportunite_count,'opportunite_amount' => $opportunite_amount,
        ]);
    }
    public function getStatisticsPerMonth(Request $request)
    {
        $thisyear = date("y");
        //------- get Acompte list --------
        $lst_acompte_amount = array();
        for ($month = 1; $month <= 12; $month++) {
            $users = User::with('documents')
                ->where('status', 'En cours')
                ->where('status_fa', 1)
                ->whereYear('status_update_date', '=', "20" . $thisyear)
                ->whereMonth('status_update_date', '=', $month)
                ->get();
            $acompte_amount = 0;
            foreach ($users as $item) {
                $documents = $item->documents;
                $count = count($documents);
                if ($count > 0) {
                    $acompte_amount += $item->documents[$count - 1]->pre_payment;
                }
            }
            array_push($lst_acompte_amount, $acompte_amount);
        }

        //------- get Solde list--------
        $lst_solde_amount = array();
        for ($month = 1; $month <= 12; $month++) {
            $users = User::with('documents')
                ->where('status', 'Termine')
                ->where('status_fa', 1)
                ->whereYear('status_update_date', '=', "20" . $thisyear)
                ->whereMonth('status_update_date', '=', $month)
                ->get();
            $solde_amount = 0;
            foreach ($users as $item) {
                $documents = $item->documents;
                $count = count($documents);
                if ($count > 0) {
                    $solde_amount += $item->documents[$count - 1]->pre_payment;
                }
            }
            array_push($lst_solde_amount, $solde_amount * -1);
        }
        return response()->json([
            'lst_acompte_amount' => $lst_acompte_amount, 'lst_solde_amount' => $lst_solde_amount,
        ]);
    }

    public function getStatisticsTotalIncome(Request $request)
    {
        $year = isset($request->year) ? $request->year : date("y");
        $monthData = array(
            'clients_count' => 0,
            'current_total_count' => 0, 'current_total_amount' => 0,
            'total_ended_count' => 0, 'total_ended_amount' => 0,
            'current_acompte_count' => 0, 'current_acompte_amount' => 0,
            'current_solde_count' => 0, 'current_solde_amount' => 0,
            'opportunite_count' => 0,'opportunite_amount' => 0,
        );
        $monthArray = array(1 => 13);
        for($x = 1; $x < 13; $x++) {
            $monthArray[$x] = $monthData;
          }
        // ? client count
          $clients = DB::table('users')
          ->whereYear('created_at', '=', $year)
          ->where('role', 'Client')
          ->get();
          foreach ($clients as $item) {
            $monthArray[(int)date('n',strtotime($item->created_at))]['clients_count'] += 1;
          }
            
        // ?  acompte em cours
            $acompte = DB::table('documents')
                ->whereYear('updated_at', '=', $year)
                ->where('document_state', 'En cours')
                ->where('status_payment', 1)
                ->orWhere(function($query) {
                    $query->where('document_state', 'En cours')
                    ->where('status_payment', 2);
                })
                ->get();
            foreach ($acompte as $item) {
                $monthArray[(int)date('n',strtotime($item->updated_at))]['current_acompte_count'] += 1;
                $monthArray[(int)date('n',strtotime($item->updated_at))]['current_acompte_amount'] += $item->pre_payment;
            }
            // ?  sold en cours
            $solde = DB::table('documents')
                ->whereYear('updated_at', '=', $year)
                ->where('document_state', 'En cours')
                ->where('status_payment', 2)
                ->get();
            foreach ($solde as $item) {
                $monthArray[(int)date('n',strtotime($item->updated_at))]['current_solde_count'] += 1;
                $monthArray[(int)date('n',strtotime($item->updated_at))]['current_solde_amount'] += $item->end_payment;
            }
            // ? terminer
            $ended = DB::table('documents')
                ->whereYear('updated_at', '=', $year)
                ->where('document_state', 'Termine')
                ->where('status_payment', 2)
                ->get();
            foreach ($ended as $item) {
                $monthArray[(int)date('n',strtotime($item->updated_at))]['total_ended_count'] += 1;
                $monthArray[(int)date('n',strtotime($item->updated_at))]['total_ended_amount'] += $item->advanced_payment;
            }
            // ? total opportunite
            $opportunite = DB::table('documents')
                ->whereYear('updated_at', '=', $year)
                ->where('document_state', 'En attente')
                ->get();
            foreach ($opportunite as $item) {
               $monthArray[(int)date('n',strtotime($item->updated_at))]['opportunite_count'] += 1;
               $monthArray[(int)date('n',strtotime($item->updated_at))]['opportunite_amount'] += $item->advanced_payment;
            }
            for($x = 1; $x < 13; $x++) {
            $monthArray[$x]['current_total_count'] = $monthArray[$x]['current_acompte_count'];
            $monthArray[$x]['current_total_amount'] = $monthArray[$x]['current_acompte_amount'] + $monthArray[$x]['current_solde_amount'];
          }

            return json_encode($monthArray);
    }
    public function getPrestation(Request $request) 
    {
        $year = isset($request->year) ? $request->year : date("y");
        $monthData = array (
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
        for($x = 1; $x < 13; $x++) {
            $monthWaitingArray[$x] = $monthData;
            $monthRunningArray[$x] = $monthData;
            $monthEndedArray[$x] = $monthData;
          }
          $waiting = DB::table('documents')
                ->whereYear('updated_at', '=', $year)
                ->where('document_state', 'En attente')
                ->get();
            foreach ($waiting as $item) {
                switch ($item->subscribe_services) {
                    case str_contains($item->subscribe_services, 'CH'): 
                        $monthWaitingArray[(int)date('n',strtotime($item->updated_at))]['CH'] += 1;
                    case str_contains($item->subscribe_services, 'SIMU'):
                        $monthWaitingArray[(int)date('n',strtotime($item->updated_at))]['SIMU'] += 1;
                    case str_contains($item->subscribe_services, 'AR'):
                        $monthWaitingArray[(int)date('n',strtotime($item->updated_at))]['AR'] += 1;
                    case str_contains($item->subscribe_services, 'TFD'):
                        $monthWaitingArray[(int)date('n',strtotime($item->updated_at))]['TFD'] += 1;
                    case str_contains($item->subscribe_services, 'ACTU'):
                        $monthWaitingArray[(int)date('n',strtotime($item->updated_at))]['ACTU'] += 1;
                    case str_contains($item->subscribe_services, 'RAC'):
                        $monthWaitingArray[(int)date('n',strtotime($item->updated_at))]['RAC'] += 1;
                }
            }
          $running = DB::table('documents')
          ->whereYear('updated_at', '=', $year)
          ->where('document_state', 'En cours')
          ->get();
          foreach ($running as $item) {
            switch ($item->subscribe_services) {
                case str_contains($item->subscribe_services, 'CH'): 
                    $monthRunningArray[(int)date('n',strtotime($item->updated_at))]['CH'] += 1;
                case str_contains($item->subscribe_services, 'SIMU'):
                    $monthRunningArray[(int)date('n',strtotime($item->updated_at))]['SIMU'] += 1;
                case str_contains($item->subscribe_services, 'AR'):
                    $monthRunningArray[(int)date('n',strtotime($item->updated_at))]['AR'] += 1;
                case str_contains($item->subscribe_services, 'TFD'):
                    $monthRunningArray[(int)date('n',strtotime($item->updated_at))]['TFD'] += 1;
                case str_contains($item->subscribe_services, 'ACTU'):
                    $monthRunningArray[(int)date('n',strtotime($item->updated_at))]['ACTU'] += 1;
                case str_contains($item->subscribe_services, 'RAC'):
                    $monthRunningArray[(int)date('n',strtotime($item->updated_at))]['RAC'] += 1;
            }
        }
          $ended = DB::table('documents')
          ->whereYear('updated_at', '=', $year)
          ->where('document_state', 'Termine')
          ->get();
          foreach ($ended as $item) {
            switch ($item->subscribe_services) {
                case str_contains($item->subscribe_services, 'CH'): 
                    $monthEndedArray[(int)date('n',strtotime($item->updated_at))]['CH'] += 1;
                case str_contains($item->subscribe_services, 'SIMU'):
                    $monthEndedArray[(int)date('n',strtotime($item->updated_at))]['SIMU'] += 1;
                case str_contains($item->subscribe_services, 'AR'):
                    $monthEndedArray[(int)date('n',strtotime($item->updated_at))]['AR'] += 1;
                case str_contains($item->subscribe_services, 'TFD'):
                    $monthEndedArray[(int)date('n',strtotime($item->updated_at))]['TFD'] += 1;
                case str_contains($item->subscribe_services, 'ACTU'):
                    $monthEndedArray[(int)date('n',strtotime($item->updated_at))]['ACTU'] += 1;
                case str_contains($item->subscribe_services, 'RAC'):
                    $monthEndedArray[(int)date('n',strtotime($item->updated_at))]['RAC'] += 1;
            }
        }
        $monthArray = array(3);
        $monthArray[0] = $monthWaitingArray;
        $monthArray[1] = $monthRunningArray;
        $monthArray[2] = $monthEndedArray;
        return json_encode($monthArray);
    }
    public function getPaymentList(Request $request)
    {
        // TODO
        $year = isset($request->year) ? $request->year : date('Y');
        $from = isset($request->from) ? $request->from : date("y-m-d", strtotime('-1 year'));
        $to = isset($request->to) ? $request->to : date("y-m-d");
        $payment_list = DB::table('documents')
            ->whereYear('updated_at', '=', $year)
            ->Where(function ($query) {
                $query->where('document_state', 'En cours')
                    ->orWhere('document_state', 'Termine');
            })
            ->where('status_payment', 1)
            ->orWhere('status_payment', 2)
            ->get();

        return response()->json(['payment_list' => $payment_list]);
    }
}
