<?php

use Dingo\Api\Routing\Router;
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

Route::middleware('auth:api')->get('/user', static function (Request $request) {
    return $request->user();
});

/** @var Router $api */
$api = app(Router::class);

$api->version('v1', static function (Router $api) {
    $api->get('test', static function () {
        dump('test');die;
    });

    $api->group([
        'prefix' => 'opportunity',
        'namespace' => 'App\Http\Controllers\Api',
//        'middleware' => 'web' // We MUST TO keep it for flash sessions messages
    ], static function (Router $api) {
        $api->post('store', 'OpportunityController@store')->name('api.opportunity.store');
    });
});
