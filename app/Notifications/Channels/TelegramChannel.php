<?php

namespace App\Notifications\Channels;

use App\Events\TelegramMessageSent;
use App\Services\TelegramMessage;
use Exception;
use Illuminate\Events\Dispatcher as Event;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Config;
use Telegram\Bot\Api as Telegram;
use Telegram\Bot\BotsManager;
use Telegram\Bot\Exceptions\TelegramSDKException;

/**
 * Class TelegramChannel
 *
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
class TelegramChannel
{

    /** @var Telegram  */
    protected $telegram;

    /** @var Event */
    private $event;

    /**
     * Channel constructor.
     *
     * @param BotsManager $botsManager
     * @param Event       $event
     */
    public function __construct(BotsManager $botsManager, Event $event)
    {
        $this->telegram = $botsManager->bot(Config::get('telegram.default'));
        $this->event = $event;
    }

    /**
     * Send the given notification.
     *
     * @param mixed        $notifiable
     * @param Notification $notification
     *
     * @return void
     * @throws TelegramSDKException
     */
    public function send($notifiable, Notification $notification): void
    {
        $message = $notification->toTelegram($notifiable);

        if (is_string($message)) {
            $message = new TelegramMessage($message);
        }

        if ($message->chatIdNotGiven()) {
            if (!$chatId = $notifiable->routeNotificationFor('telegram')) {
                throw new Exception('Telegram notification chat ID was not provided. Please refer usage docs.');
            }

            $message->to($chatId);
        }

        if ($message->sizeLimitExceed()) {
            throw new Exception('Telegram text limit size was exceeded. Please refer usage docs.');
        }

        $params = $message->toArray();

        if ($message instanceof TelegramMessage) {
            $body = $params['text'];

            if (is_array($body)) {
                $messageIds = [];
                foreach ($body as $text) {
                    $params['text'] = $text;
                    if (count($messageIds) > 1) {
                        $params['reply_to_message_id'] = reset($messageIds);
                    }
                    $messageResponse = $this->telegram->sendMessage($params);
                    $messageIds[] = $messageResponse->messageId;
                }
            } else {
                $messageResponse = $this->telegram->sendMessage($params);
                $messageIds[] = $messageResponse->messageId;
            }
            $messageId = reset($messageIds);
            $this->event->dispatch(new TelegramMessageSent($notification, $notifiable, $messageId));
        }
    }
}
