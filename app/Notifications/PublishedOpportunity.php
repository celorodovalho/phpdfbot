<?php

namespace App\Notifications;

use App\Helpers\SanitizerHelper;
use App\Models\Opportunity;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;
use Spatie\Emoji\Emoji;

class PublishedOpportunity extends Notification
{
    use Queueable;

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return [TelegramChannel::class];
    }

    /**
     * @param Opportunity $notifiable
     * @return TelegramMessage
     */
    public function toTelegram($notifiable)
    {
        if ($notifiable->telegram_user_id) {
            $link = "https://t.me/VagasBrasil_TI/{$notifiable->telegram_id}";
            return TelegramMessage::create()
                // Optional recipient user id.
                ->to($notifiable->telegram_user_id)
                // Markdown supported.
                ->content(sprintf(
                    "A vaga abaixo foi publicada:\n\n%s",
                    SanitizerHelper::sanitizeSubject(SanitizerHelper::removeBrackets($notifiable->title))
                ))
                // (Optional) Inline Buttons
                ->button('Conferir no canal ' . Emoji::rightArrow(), $link);
        }
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}
