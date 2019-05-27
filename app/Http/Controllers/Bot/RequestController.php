<?php

namespace App\Http\Controllers\Bot;

use App\Contracts\Interfaces\OpportunityInterface;
use App\Contracts\Opportunity;
use App\Http\Controllers\Controller;
use Dacastro4\LaravelGmail\Services\Message\Attachment;
use Dacastro4\LaravelGmail\Services\Message\Mail;
use LaravelGmail;
use Telegram\Bot\BotsManager;

class RequestController extends Controller
{
    /**
     * @var \Telegram\Bot\Api
     */
    private $telegram;

    /**
     * RequestController constructor.
     * @param BotsManager $botsManager
     */
    public function __construct(BotsManager $botsManager)
    {
        $this->telegram = $botsManager->bot();
    }

    /**
     * @return string
     */
    public function process(): string
    {
        $messages = $this->getMessages();
dump($messages->first()->getBody());
//$attachment = base64_decode($messages->first()->getAttachments(true)[0]);
//echo "<img src='data:image/jpeg;base64,$attachment'>";
        /** @var Attachment $attach */
        $attach = $messages->first()->getAttachments()[0];
        dump($attach->getMimeType());
        dump($attach->getDecodedBody($attach->getData()));
        /** @var Mail $message */
        foreach ($messages as $message) {
            /** TODO: Format message here */
            $opportunity = new Opportunity();
            $opportunity->setTitle($message->getSubject())
                ->setDescription($message->getFromName());
            $this->sendOpportunityToChannel($opportunity);
        }
        return 'ok';
    }

    private function getMessages(): \Illuminate\Support\Collection
    {
        /** @var \Dacastro4\LaravelGmail\Services\Message $messageService */
        $messageService = LaravelGmail::message();
        $threads = $messageService->service->users_messages->listUsersMessages('me', [
            'q' => '(list:nvagas@googlegroups.com OR list:leonardoti@googlegroups.com ' .
                'OR list:clubinfobsb@googlegroups.com OR to:nvagas@googlegroups.com OR to:vagas@noreply.github.com ' .
                'OR to:clubinfobsb@googlegroups.com OR to:leonardoti@googlegroups.com) is:unread',
            'maxResults' => 2
        ]);

        $messages = [];
        $allMessages = $threads->getMessages();
        foreach ($allMessages as $message) {
            $messages[] = new Mail($message, true);
        }
        return collect($messages);
    }

    private function sendOpportunityToChannel(OpportunityInterface $opportunity): void
    {
        $this->telegram->sendMessage([
            'parse_mode' => 'Markdown',
            'chat_id' => env('TELEGRAM_OWNER_ID'),
            'text' => implode("\r\n", [
                $opportunity->getTitle(),
                $opportunity->getDescription()
            ])
        ]);
    }
}
