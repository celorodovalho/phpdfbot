<?php

namespace App\Http\Controllers\Bot;

use App\Http\Controllers\Controller;
use Dacastro4\LaravelGmail\Services\Message\Mail;
use LaravelGmail;
use Telegram;

class RequestController extends Controller
{
    /**
     * (list:nvagas@googlegroups.com OR list:leonardoti@googlegroups.com OR list:clubinfobsb@googlegroups.com OR to:nvagas@googlegroups.com OR to:vagas@noreply.github.com OR to:clubinfobsb@googlegroups.com OR to:leonardoti@googlegroups.com) is:unread
     */
    public function process(): string
    {
//        $messages = LaravelGmail::message()
//            ->raw('(list:nvagas@googlegroups.com OR list:leonardoti@googlegroups.com OR list:clubinfobsb@googlegroups.com OR to:nvagas@googlegroups.com OR to:vagas@noreply.github.com OR to:clubinfobsb@googlegroups.com OR to:leonardoti@googlegroups.com) is:unread')
////            ->raw('(list:nvagas@googlegroups.com OR list:leonardoti@googlegroups.com ' .
////                'OR list:clubinfobsb@googlegroups.com OR to:nvagas@googlegroups.com OR to:vagas@noreply.github.com ' .
////                'OR to:clubinfobsb@googlegroups.com OR to:leonardoti@googlegroups.com)')
////            ->unread()
//            ->preload();
//        dump($messages);
//        dump($messages->all());
//        dump(get_class_methods($messages));
//        dump($messages->all()[0]->getSubject());
//        dump($messages->all()[0]->getHtmlBody());

        /** @var \Dacastro4\LaravelGmail\Services\Message $messageService */
        $messageService = LaravelGmail::message();
        $threads = $messageService->service->users_messages->listUsersMessages('me', [
            'q' => '(list:nvagas@googlegroups.com OR list:leonardoti@googlegroups.com OR list:clubinfobsb@googlegroups.com OR to:nvagas@googlegroups.com OR to:vagas@noreply.github.com OR to:clubinfobsb@googlegroups.com OR to:leonardoti@googlegroups.com) is:unread'
        ]);
//        dump($threads->getThreads());

        $allMessages = $threads->getMessages();
        foreach ($allMessages as $message) {
            $messages[] = new Mail($message, true);
        }
        $messages = collect($messages);

        dump($threads->getMessages()[0]->getPayload());
        dump($threads->getMessages()[0]->getPayload()->getBody());
        dump($messages);
        return 'ok';
    }
}
