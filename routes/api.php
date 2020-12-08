<?php

use App\Http\Controllers\Api\DocumentsController;
use App\Http\Controllers\Api\ServicesController;
use App\Http\Controllers\Api\ContractTemplateController;
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
    Route::get('documents/user/{user_id}', [DocumentsController::class, 'show_by_user']);
    Route::apiResource('/documents', 'Api\DocumentsController');
    Route::get('get_contract/{user_id}', [DocumentsController::class, 'get_contract']);
    Route::post('/create_contract', 'Api\DocumentsController@create_contract')->name('create_contract');
    Route::apiResource('/personal_information', 'Api\PersonalInformationsController');
    Route::get('services/{status}', [ServicesController::class, 'show_by_status']);
    Route::apiResource('/services', 'Api\ServicesController');
    Route::apiResource('/contract_templates', 'Api\ContractTemplateController');
    Route::get('get_template/{id}', [ContractTemplateController::class, 'get_template']);
});
