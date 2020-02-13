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
        'chat_id' => 144068960,
        'parse_mode' => 'HTML',
        'text' => '<a href="tg://user?id=se45ky">Seasky</a>'
    ]));
});

Route::get('process/messages/{type}/{collectors?}', 'Web\OpportunityController@processMessages');

Route::get('/', 'Web\OpportunityController@index');

Route::group(['namespace' => 'Web',], static function () {
    Route::resource('opportunities', 'OpportunityController');
});

Route::get('teste', function () {
    $a = file_get_contents(base_path('teste'));

    $b = mb_substr($a, 431, 1);

    dump(mb_strlen($a));
    dump($a);
    dump($b);

    Telegram::sendMessage([
        'chat_id' => 144068960,
        'parse_mode' => 'Markdown',
        'text' => $a
    ]);
});
