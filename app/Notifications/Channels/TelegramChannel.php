<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\Exceptions\CouldNotSendNotification;
use NotificationChannels\Telegram\Telegram;
use NotificationChannels\Telegram\TelegramFile;
use NotificationChannels\Telegram\TelegramLocation;
use NotificationChannels\Telegram\TelegramMessage;

class TelegramChannel
{
    /**
     * @var Telegram
     */
    protected $telegram;

    /**
     * Channel constructor.
     *
     * @param Telegram $telegram
     */
    public function __construct(Telegram $telegram)
    {
        $this->telegram = $telegram;
    }

    /**
     * Send the given notification.
     *
     * @param mixed $notifiable
     * @param Notification $notification
     *
     * @return \Psr\Http\Message\ResponseInterface|null
     * @throws CouldNotSendNotification
     */
    public function send($notifiable, Notification $notification)
    {
        $message = $notification->toTelegram($notifiable);

        if (is_string($message)) {
            $message = TelegramMessage::create($message);
        }

        if ($message->toNotGiven()) {
            if (!$to = $notifiable->routeNotificationFor('telegram')) {
                throw CouldNotSendNotification::chatIdNotProvided();
            }

            $message->to($to);
        }

        $params = $message->toArray();

        if ($message instanceof TelegramMessage) {
            $messageId = $this->telegram->sendMessage($params);
        } elseif ($message instanceof TelegramLocation) {
            $messageId = $this->telegram->sendLocation($params);
        } elseif ($message instanceof TelegramFile) {
            $messageId = $this->telegram->sendFile($params, $message->type, $message->hasFile());
        }

        return $messageId;
    }
}