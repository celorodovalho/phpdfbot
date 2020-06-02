<?php

use Dacastro4\LaravelGmail\Facade\LaravelGmail;
use Illuminate\Support\Facades\Route;

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
Route::get('/', static fn() => redirect()->route('opportunity.index'));

Route::group([], static function() {
    Route::get('/oauth/gmail', static fn() => LaravelGmail::redirect());

    Route::get('/oauth/gmail/callback', static function () {
        LaravelGmail::makeToken();
        return redirect()->to('/');
    });

    Route::get('/oauth/gmail/logout', static function () {
        LaravelGmail::logout(); //It returns exception if fails
        return redirect()->to('/');
    });
});

Route::namespace('Web')
    ->group(static function () {
        Route::any('process/messages/{type}/{collectors?}', 'OpportunityController@processMessages');

//        Route::resource('opportunity', 'OpportunityController');
        Route::get('opportunity', 'OpportunityController@index')->name('opportunity.index');
        Route::get('opportunity/{opportunity}/show', 'OpportunityController@show')->name('opportunity.show');
        Route::get('opportunity/create', 'OpportunityController@create')->name('opportunity.create');
        Route::post('opportunity', 'OpportunityController@store')->name('opportunity.store');

        Route::post('send', 'OpportunityController@sendMessage');
        Route::get('valid', 'OpportunityController@testValidation');
        Route::get('test', 'OpportunityController@testCode');
    });

Route::namespace('Bot')
    ->group(static function () {
        Route::get('/setWebhook/{bot}', 'DefaultController@setWebhook');
        Route::any('/webhook/{token}/{bot}', 'DefaultController@webhook');
    });


