<?php

namespace App\Notifications\Channels;

use App\Contracts\IdentifiableNotification;
use App\Helpers\BotHelper;
use App\Models\Group;
use App\Models\Opportunity;
use App\Notifications\PublishedOpportunity;
use App\Services\TelegramMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Config;
use Telegram\Bot\Api as Telegram;
use Telegram\Bot\BotsManager;
use Telegram\Bot\Exceptions\TelegramSDKException;

class TelegramChannel
{
    /**
     * @var Telegram
     */
    protected $telegram;

    /**
     * Channel constructor.
     *
     * @param BotsManager $botsManager
     */
    public function __construct(BotsManager $botsManager)
    {
        $this->telegram = $botsManager->bot(Config::get('telegram.default'));;
    }

    /**
     * Send the given notification.
     *
     * @param mixed $notifiable
     * @param Notification $notification
     *
     * @return void
     * @throws TelegramSDKException
     */
    public function send($notifiable, Notification $notification)
    {
        $message = $notification->toTelegram($notifiable);

        if (is_string($message)) {
            $message = new TelegramMessage($message);
        }

        if ($message->toNotGiven()) {
            if (!$to = $notifiable->routeNotificationFor('telegram')) {
                throw new \Exception('Telegram notification chat ID was not provided. Please refer usage docs.');
            }

            $message->to($to);
        }

        if ($message->sizeLimitExceed()) {
            throw new \Exception('Telegram text limit size was exceeded. Please refer usage docs.');
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
        }

        if ($notifiable instanceof Opportunity && !$notifiable->telegram_id) {
            $chatId = $message->getPayloadValue('chat_id');
            if (Config::get("telegram.channels.{$chatId}.main")) {
                $notifiable->telegram_id = $messageId;
                $notifiable->status = Opportunity::STATUS_ACTIVE;
                $notifiable->save();
            }
        }

        if ($notifiable instanceof Group) {
            $notifiable->telegram_id = $messageId;
            $notifiable->save();
        }
    }
}
