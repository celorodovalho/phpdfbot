<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::group(['middleware' => ['web']], function () {
  $token = env("TELEGRAM_BOT_TOKEN");
    Route::get('bot', 'Bot\DefaultController@show');
    Route::any("/$token/webhook", 'Bot\CommandHandlerController@webhook');
    Route::get('/setWebhook', 'Bot\DefaultController@setWebhook');
    Route::get('/removeWebhook', 'Bot\DefaultController@removeWebhook');
    Route::get('/getUpdates', 'Bot\DefaultController@getUpdates');
    Route::get('/getWebhookInfo', 'Bot\DefaultController@getWebhookInfo');
    Route::get('/getMe', 'Bot\DefaultController@getMe');
    Route::any('/sendMessage', 'Bot\DefaultController@sendMessage');
    Route::any('/mail', 'Bot\DefaultController@mail');
    Route::any('/sendChannelMessage', 'Bot\DefaultController@sendChannelMessage');
    Route::any('/crawler', 'Bot\DefaultController@crawler');
    Route::any('/resume/{email}', 'Bot\DefaultController@sendResume');
});

