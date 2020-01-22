<?php

namespace App\Notifications;

use App\Contracts\IdentifiableNotification;
use App\Models\Group;
use App\Models\Opportunity;
use App\Notifications\Channels\TelegramChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Services\TelegramMessage;

class NotifyGroup extends Notification implements IdentifiableNotification
{
    use Queueable;

    /** @var int */
    private $messageId;

    /**
     * SendOpportunity constructor.
     */
    public function __construct()
    {
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return [
            TelegramChannel::class,
            'database'
        ];
    }

    /**
     * @param Group $group
     * @return TelegramMessage
     */
    public function toTelegram($group)
    {
        $telegramMessage = new TelegramMessage;

        $telegramMessage->to($group->name);

        return $telegramMessage;
    }


    /**
     * Get the array representation of the notification.
     *
     * @param  mixed $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        dump($this);
        dump($notifiable);
        return [
            'telegram_id' => $notifiable->telegram_id
        ];
    }

    /**
     * @return int
     */
    public function getMessageId(): ?int
    {
        return $this->messageId;
    }

    /**
     * @param int $messageId
     */
    public function setMessageId(int $messageId): void
    {
        $this->messageId = $messageId;
        dump($this);
    }
}
