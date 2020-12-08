<?php

namespace App\Http\Controllers\Api;

use App\Models\Documents;
use App\Models\Services;
use App\Models\PersonalInformations;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function __construct()
    {
        auth()->setDefaultDriver('api');
    }

    /**
     * Get the user for a specific id.
     *
     * @param  int  $id
     * @return User $user
     * @return null
     */
    public function get_user($id)
    {
        $user = null;

        foreach (User::all() as $test)
            if ($test->id == $id) {
                $user = $test;
                break;
            }
        return $user;
    }

    public function get_personal_information($id)
    {
        $info = null;

        foreach (PersonalInformations::all() as $test)
            if ($test->id == $id) {
                $info = $test;
                break;
            }
        return $info;
    }

    /**
     * Get the document for a specific user id.
     *
     * @param  int  $id
     * @return array $service
     * @return null
     */
    public function get_documents_by_user_id($id)
    {
        $docs = array();

        foreach (Documents::all() as $tmp)
            if ($tmp->user_id == $id) {
                array_push($docs, $tmp);
            }
        return $docs;
    }


    /**
     * Get the service for a specific id.
     *
     * @param  int  $id
     * @return array $service
     * @return null
     */
    public function get_services_by_doc($id)
    {
        $service = array();

        foreach (Services::all() as $test)
            if ($test->document_id == $id) {
                array_push($service, $test);
            }
        return $service;
    }

    /**
     * Get services that have been selected.
     *
     * @return array $service
     * @return null
     */
    public function get_selected_services($type, $id, $bool)
    {
        $services = array();

        foreach (Services::all() as $tmp)
            if ($tmp->status == $type) {
                if ($bool == true) {
                    $newservice = Services::create([
                        'name' => $tmp['name'],
                        'description' => $tmp['description'],
                        'variable' => $tmp['variable'],
                        'value' => $tmp['value'],
                        'variable1' => $tmp['variable1'],
                        'value1' => $tmp['value1'],
                        'total_ht' => $tmp['total_ht'],
                        'total_ttc' => $tmp['total_ttc'],
                        'tva' => $tmp['tva'],
                        'status' => "unselected",
                        'document_id' => $id,
                        'parent_id' => $id
                    ]);
                    $newservice->parent_id = $newservice->id;
                } else {
                    $newservice = $tmp;
                }
                array_push($services, $newservice);
            }
        return $services;
    }

    /**
     * Get Total ttc for the selected type.
     *
     * @return int $service
     * @return null
     */
    public function get_selected_total($type, $id)
    {
        $total = 0;

        foreach (Services::all() as $tmp)
            if ($tmp->status == $type && $tmp->document_id == $id)
                $total += $tmp->total_ttc;
        return $total;
    }

    /**
     * Get services that have been selected.
     *
     */
    public function delete_services($doc_id)
    {
        foreach (Services::all() as $tmp)
            if ($tmp->document_id == $doc_id)
                $tmp->delete();
    }
}