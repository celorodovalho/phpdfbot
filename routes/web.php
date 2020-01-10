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
Route::get('/setWebhook/{bot}', 'Bot\DefaultController@setWebhook');
Route::any('/webhook/{token}/{bot}', 'Bot\DefaultController@webhook');
//Route::get('/getMe', 'Bot\DefaultController@getMe');

Route::get('/oauth/gmail', function (){
    return LaravelGmail::redirect();
});

Route::get('/oauth/gmail/callback', function (){
    LaravelGmail::makeToken();
    return redirect()->to('/');
});

Route::get('/oauth/gmail/logout', function (){
    LaravelGmail::logout(); //It returns exception if fails
    return redirect()->to('/');
});

Route::get('me', function (){
    dump(\Telegram\Bot\Laravel\Facades\Telegram::getMe());
    dump(\Telegram\Bot\Laravel\Facades\Telegram::sendMessage([
        'chat_id' => 50,
        'text' => 'sdfadfas'
    ]));
});

Route::get('/', function (){
    dump('show');die;
});
Route::get('opportunities', 'Web\OpportunityController@index');