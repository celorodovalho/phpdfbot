<?php

use Dacastro4\LaravelGmail\Facade\LaravelGmail;
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

Route::get('/oauth/gmail', static function () {
    return LaravelGmail::redirect();
});

Route::get('/oauth/gmail/callback', static function () {
    LaravelGmail::makeToken();
    return redirect()->to('/');
});

Route::get('/oauth/gmail/logout', static function () {
    LaravelGmail::logout(); //It returns exception if fails
    return redirect()->to('/');
});

Route::get('me', static function () {
    dump(Telegram::getMe());
    dump(Telegram::sendMessage([
        'chat_id' => -1001253504077,
        'parse_mode' => 'HTML',
        'text' => '<a href="tg://user?id=144068960">Seasky</a>'
    ]));
});

Route::get('process/messages/{type}/{collectors?}', 'Web\OpportunityController@processMessages');

Route::get('/', 'Web\OpportunityController@index');
Route::get('opportunity/{opportunity}', 'Web\OpportunityController@show');
Route::post('send', 'Web\OpportunityController@sendMessage');

//Route::group(['namespace' => 'Web',], static function () {
//    Route::resource('opportunities', 'OpportunityController');
//});

Route::get('teste', function () {
    $files = [[
        "file_id" => "AgACAgEAAxkBAAIhr15QcktSwBryZ4D8-C6rAAEbhzdm1AAC66gxG6kmgEYIZrwK7DPjer8UFDAABAEAAwIAA3gAA6H_BQABGAQ",
        "file_unique_id" => "AQADvxQUMAAEof8FAAE",
        "file_size" => 119020,
        "width" => 777,
        "height" => 778
    ]];

    $a = \App\Helpers\BotHelper::getFiles($files);
    dump($a);


});

Route::get('logs', '\Rap2hpoutre\LaravelLogViewer\LogViewerController@index');