<?php

namespace App\Http\Controllers\Bot;

use App\Http\Controllers\Controller;
use LaravelGmail;
use Telegram;

class RequestController extends Controller
{
    /**
     * (list:nvagas@googlegroups.com OR list:leonardoti@googlegroups.com OR list:clubinfobsb@googlegroups.com OR to:nvagas@googlegroups.com OR to:vagas@noreply.github.com OR to:clubinfobsb@googlegroups.com OR to:leonardoti@googlegroups.com) is:unread
     */
    public function process(): string
    {
        $messages = LaravelGmail::message()
//            ->raw('list:nvagas@googlegroups.com')
//            ->raw('list:leonardoti@googlegroups.com')
//            ->raw('list:clubinfobsb@googlegroups.com')
//            ->to('nvagas@googlegroups.com')
//            ->to('vagas@noreply.github.com')
//            ->to('clubinfobsb@googlegroups.com')
//            ->to('leonardoti@googlegroups.com')
//            ->fromThese()
            ->unread()
//            ->preload()
            ->all();
        dump($messages);
        dump(get_class_methods($messages));
        return 'ok';
    }
}
