<?php

namespace App\Http\Controllers\Bot;

use App\Http\Controllers\Controller;
use Dacastro4\LaravelGmail\Services\Message\Mail;
use LaravelGmail;
use Telegram;

class RequestController extends Controller
{
    /**
     * @return string
     */
    public function process(): string
    {

        /** @var \Dacastro4\LaravelGmail\Services\Message $messageService */
        $messageService = LaravelGmail::message();
        $threads = $messageService->service->users_messages->listUsersMessages('me', [
            'q' => '(list:nvagas@googlegroups.com OR list:leonardoti@googlegroups.com ' .
                'OR list:clubinfobsb@googlegroups.com OR to:nvagas@googlegroups.com OR to:vagas@noreply.github.com ' .
                'OR to:clubinfobsb@googlegroups.com OR to:leonardoti@googlegroups.com) is:unread'
        ]);

        $allMessages = $threads->getMessages();
        foreach ($allMessages as $message) {
            $messages[] = new Mail($message, true);
        }
        $messages = collect($messages);

        dump($messages);
        return 'ok';
    }
}
