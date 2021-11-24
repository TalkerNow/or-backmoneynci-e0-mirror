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

    //----- get clients ------
        $query = "SELECT * FROM users WHERE role='Client' ";
        $result = DB::select($query);
        $total_client_count = count($result);

        $query = "SELECT MIN(created_at) AS min_date, MAX(created_at) AS max_date FROM users WHERE role='Client'";
        $result = DB::select($query);
        $clients_count_list = [];
        if(count($result) > 0) {
            $min_date = new DateTime($result[0]->min_date);
            $max_date = new DateTime($result[0]->max_date);
            $diff_days = $min_date->diff($max_date)->days;
            $interval = 0;
            if($diff_days > 0){
                $interval = intdiv($diff_days, 5);
            }
            $index_date = $min_date;

            $query = "SELECT * FROM users WHERE role ='Client' AND DATE(created_at) <= '".$min_date->format('Y-m-d')."'";
            $result = DB::select($query);
            $clients_count_list[0] = count($result);
            if($interval > 0){
                for($index = 1; $index < 5; $index ++){
                    $index_date->modify('+'. $interval.' day');
                    $query = "select * from users where role = 'Client' and date(created_at) <= '".$index_date->format('Y-m-d')."'";
                    $result = DB::select($query);
                    $clients_count_list[$index] = count($result);
                }
            }
            $query = "SELECT * FROM users WHERE role='Client' AND DATE(created_at) <= '".$max_date->format('Y-m-d')."'";
            $result = DB::select($query);
            if($interval > 0)
                $clients_count_list[5] = count($result);
            else
                $clients_count_list[1] = count($result);
        }
    //------ get Acompte -----
    // TODO ajouter les dates
        $query = "SELECT * FROM documents WHERE (document_state='En cours' OR document_state='Termine') AND status_payment=1";
        $acompte = DB::select($query);

        $total_acompte_count = count($acompte);
        $total_acompte_amount = 0;
        foreach($acompte as $item) {
                $total_acompte_amount += $item->pre_payment;
            }

    //------- get Solde --------
            // TODO ajouter les dates
    $query = "SELECT * FROM documents WHERE (document_state='En cours' OR document_state='Termine') AND status_payment=2";
    $solde = DB::select($query);

    $total_solde_count = count($solde);
    $total_solde_amount = 0;
    foreach($solde as $item) {
            $total_solde_amount += $item->end_payment;
        }

        return response()->json([
            'clients_count' => $total_client_count, 'clients_count_list'=>$clients_count_list,
            'acompte_count'=>$total_acompte_count, 'acompte_amount'=>$total_acompte_amount,
            'solde_count'=>$total_solde_count, 'solde_amount'=>$total_solde_amount
            ]);
    }
    public function getStatisticsPerMonth(Request $request){
        $thisyear = date("y");

        //------- get Acompte list --------
        $lst_acompte_amount = array();
        for($month = 1; $month <=12; $month ++){
            $users= User::with('documents')
                ->where('status','En cours')
                ->where('status_fa',1)
                ->whereYear('status_update_date', '=', "20".$thisyear)
                ->whereMonth('status_update_date', '=', $month)
                ->get();
            $acompte_amount = 0;
            foreach($users as $item){
                $documents = $item->documents;
                $count = count($documents);
                if($count > 0){
                    $acompte_amount += $item->documents[$count - 1]->pre_payment;
                }
            }
            array_push($lst_acompte_amount, $acompte_amount);
        }

        //------- get Solde list--------
        $lst_solde_amount = array();
        for($month = 1; $month <=12; $month ++){
            $users= User::with('documents')
                ->where('status','Termine')
                ->where('status_fa',1)
                ->whereYear('status_update_date', '=', "20".$thisyear)
                ->whereMonth('status_update_date', '=', $month)
                ->get();
            $solde_amount = 0;
            foreach($users as $item){
                $documents = $item->documents;
                $count = count($documents);
                if($count > 0){
                    $solde_amount += $item->documents[$count - 1]->pre_payment;
                }
            }
            array_push($lst_solde_amount, $solde_amount * -1);
        }
        return response()->json([
            'lst_acompte_amount' => $lst_acompte_amount, 'lst_solde_amount'=>$lst_solde_amount,
        ]);
    }

    public function getStatisticsTotalIncome(Request $request){
        $year = isset($request->year)?$request->year:"2021";
        $users= User::with('documents')
            ->whereYear('status_update_date', '=', $year)
			->Where(function($query) {
                $query->where('status', 'En cours')
                      ->orWhere('status', 'Termine');
            })
            ->where('status_fa',1)
            ->get();

        $total_amount = 0;
        foreach($users as $item){
            $documents = $item->documents;
            $count = count($documents);
            if($count > 0){
                if($item->status == "En cours")
                    $total_amount += $item->documents[$count - 1]->pre_payment;
                else
                    $total_amount += $item->documents[$count - 1]->end_payment;
            }
        }

        return response()->json(['total_amount' => $total_amount]);
    }
    public function getPaymentList(Request $request)
    {
        // TODO
        $year = isset($request->year)?$request->year:date('Y');
        $from = isset($request->from)?$request->from: date("y-m-d", strtotime('-1 year'));
        $to = isset($request->to)?$request->to: date("y-m-d");
        $payment_list=DB::table('documents')
            ->whereYear('updated_at', '=', $year)
			->Where(function($query) {
                $query->where('document_state', 'En cours')
                      ->orWhere('document_state', 'Termine');
            })
            ->where('status_payment',1)
            ->orWhere('status_payment',2)
            ->get();

        return response()->json(['payment_list' => $payment_list]);
    }
}
