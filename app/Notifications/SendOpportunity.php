<?php

namespace App\Notifications;

use App\Models\Opportunity;
use App\Notifications\Channels\TelegramChannel;
use App\Services\TelegramMessage;
use GrahamCampbell\Markdown\Facades\Markdown;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

class SendOpportunity extends Notification
{
    use Queueable;

    /** @var string|int Telegram chat id */
    private $chatId;

    /** @var array */
    private $options;

    /** @var string */
    private $admin;

    /** @var string */
    private $botName;

    /**
     * SendOpportunity constructor.
     * @param $chatId
     * @param array $options
     */
    public function __construct($chatId, array $options = [])
    {
        $this->chatId = $chatId;
        $this->options = $options;
        $this->admin = Config::get('telegram.admin');
        $this->botName = Config::get('telegram.default');
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return [
            TelegramChannel::class,
            'database',
            'mail'
        ];
    }

    /**
     * @param Opportunity $opportunity
     * @return TelegramMessage
     * @throws \Throwable
     */
    public function toTelegram($opportunity)
    {
        $telegramMessage = new TelegramMessage;

        if ($this->admin === $this->chatId && Str::contains($opportunity->origin, [$this->botName])) {
            $userNames = explode('|', $opportunity->origin);
            $userName = end($userNames);
            if (!blank($userName)) {
                if (Str::contains($userName, ' ')) {
                    $userMention = "[$userName](tg://user?id={$opportunity->telegram_user_id})";
                } else {
                    $userMention = '@' . $userName;
                }
                $opportunity->description .= "\n\nby $userMention";
            }
        }

        $messageText = view('notifications.opportunity', [
            'opportunity' => $opportunity,
            'isEmail' => false
        ])->render();

        if (filled($messageText)) {
            $telegramMessage
                ->to($this->chatId)
                ->content($messageText);
        }
        return $telegramMessage;
    }

    public function toMail($opportunity)
    {
        $messageText = view('notifications.opportunity', [
            'opportunity' => $opportunity,
            'isEmail' => true
        ])->render();

        $messageText = Markdown::convertToHtml($messageText);

        return (new MailMessage)
            ->subject($opportunity->title)
            ->view($messageText);
    }

    /**
     * Get the array representation of the notification.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return $notifiable instanceof Opportunity ? [
            'telegram_id' => $notifiable->telegram_id,
            'chat_id' => $this->chatId,
            'telegram_user_id' => $notifiable->telegram_user_id,
        ] : [];
    }
}
