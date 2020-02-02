<?php

use Dacastro4\LaravelGmail\Facade\LaravelGmail;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Telegram\Bot\Laravel\Facades\Telegram;

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
    dump(Telegram::getMe());
    dump(Telegram::sendMessage([
        'chat_id' => 50,
        'text' => 'sdfadfas'
    ]));
});

Route::get('bot/{type}', function (string $type) {
    Artisan::call(
        'bot:populate:channel',
        ['type' => $type]
    );
    return Artisan::output();
});

Route::get('logs', '\Rap2hpoutre\LaravelLogViewer\LogViewerController@index');

Route::get('/', 'Web\OpportunityController@index');

Route::group(['namespace' => 'Web',], function () {
    Route::resource('opportunities', 'OpportunityController');
});
