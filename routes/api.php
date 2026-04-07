<?php

use App\Http\Controllers\Api\DocumentsController;
use App\Http\Controllers\Api\ServicesController;
use App\Http\Controllers\Api\ContractTemplateController;
use App\Http\Controllers\Api\DocusignController;
use App\Http\Controllers\Api\KpiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::namespace('App\Http\Controllers')->group(function () {
    Route::post('/login', 'Api\Auth\LoginController@login')->name('login');
    Route::get('/refresh', 'Api\Auth\LoginController@refresh')->name('refresh');
    Route::post('/register', 'Api\Auth\RegisterController@register')->name('register');

    Route::apiResource('/me', 'Api\MeController');
    Route::apiResource('/users', 'Api\UsersController');
    Route::get('/duplicated_email', 'Api\UsersController@duplicated_email')->name('duplicated_email');
    Route::post('/set_user_subscribe_services', 'Api\UsersController@set_user_subscribe_services')->name('set_user_subscribe_services');

    Route::get('/unread_count', 'Api\TasksController@unread_count')->name('unread_count');
    Route::apiResource('/tasks', 'Api\TasksController');
    Route::get('/customer_tasks', 'Api\TasksController@customer_tasks')->name('customer_tasks');

    Route::get('documents/user/{user_id}', [DocumentsController::class, 'show_by_user']);
    Route::apiResource('/documents', 'Api\DocumentsController');

    Route::get('get_contract/{user_id}', [DocumentsController::class, 'get_contract']);
    Route::post('/create_contract', 'Api\DocumentsController@create_contract')->name('create_contract');
    Route::apiResource('/personal_information', 'Api\PersonalInformationsController');
    Route::get('services/{status}', [ServicesController::class, 'show_by_status']);
    Route::apiResource('/services', 'Api\ServicesController');
    Route::apiResource('/contract_templates', 'Api\ContractTemplateController');
    Route::get('get_template/{id}', [ContractTemplateController::class, 'get_template']);

    Route::apiResource('/files', 'Api\FilesController');
    Route::post('/uploadFiles', 'Api\FilesController@uploadFiles')->name('uploadFiles');
    Route::get('/downloadFile', 'Api\FilesController@downloadFile')->name('downloadFile');

    //-------- statistics ---------
    Route::get('/get_statistics_total_income', 'Api\StatisticsController@getStatisticsTotalIncome')->name('get_statistics_total_income');
    Route::get('/getPrestation', 'Api\StatisticsController@getPrestation')->name('getPrestation');
    Route::get('/getMembersPrestation', 'Api\StatisticsController@getMembersPrestation')->name('getMembersPrestation');
    // ---- DocuSign (OptionRetraite) ----
    Route::post('/docusign/request-signature', 'Api\DocusignController@requestSignature')->name('docusign.request_signature');
    Route::post('/docusign/get-signing-link',  'Api\DocusignController@getSigningLink')->name('docusign.get_signing_link');
    // Webhook DocuSign Connect (NE PAS protéger par auth)
    Route::post('/docusign/connect',           'Api\DocusignController@docusignConnectCallback')->name('docusign.connect');
    Route::post('/contracts/send-docusign', 'Api\DocusignController@sendFilledContract')->name('contracts.send-docusign');
    Route::apiResource('/kpis', 'Api\KpiController');

    // ---- FROZEN_DATA (Barrière de données carrière) ----
    Route::get('/frozen_data/{user_id}', 'Api\FrozenDataController@show');
    Route::post('/frozen_data', 'Api\FrozenDataController@store');
    Route::post('/frozen_data/{user_id}/lock', 'Api\FrozenDataController@lock');
    Route::post('/frozen_data/{user_id}/unlock', 'Api\FrozenDataController@unlock');

});
