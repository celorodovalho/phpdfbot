<?php

use App\Http\Controllers\Web\OpportunityController;

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

Route::get('logs', '\Rap2hpoutre\LaravelLogViewer\LogViewerController@index');

Route::get('/', 'Web\OpportunityController@index');

Route::group(['namespace' => 'Web',], function () {
    Route::resource('opportunities', 'OpportunityController');
});

Route::get('test', function (\GrahamCampbell\GitHub\GitHubManager $github){
    try {
        $opportunity = \App\Models\Opportunity::find(1);
        $opportunity->notify(new \App\Notifications\SendOpportunity('@botphpdf'));
//        dump($github->me());https://github.com/phpdevbr/vagas/issues
//        $github->issues()->create('php-df', 'phpdfbot', array('title' => 'The issue title', 'body' => 'The issue body'));
//        $issues = $github->issues()->all('phpdevbr', 'vagas', [
//            'state' => 'open',
//            'since' => '2019-12-19'
//        ]);
//        repo:USERNAME/REPOSITORY
        $issues = $github->search()->issues('"Undefined property: App\Console\Commands\BotPopulateChannel::$rejectMessages"+repo:php-df/phpdfbot');
        dump($issues);
//        $github->issues()->comments()->create('php-df', 'phpdfbot', $issues['issues'][0]['number'], [
//            'body' => '```'.$issues['issues'][0]['body'].'```'
//        ]);
    } catch (\Exception $exception) {
        dump($exception);die;
    }
});
