<?php

namespace App\Notifications;

use App\Helpers\SanitizerHelper;
use App\Models\Opportunity;
use App\Notifications\Channels\TelegramChannel;
use App\Transformers\FormattedOpportunityTransformer;
use Dacastro4\LaravelGmail\Services\Message\Mail;
use Exception;
use GrahamCampbell\Markdown\Facades\Markdown;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use NotificationChannels\Telegram\TelegramMessage;
use Spatie\Emoji\Emoji;
use Telegram\Bot\Exceptions\TelegramResponseException;

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
     * @param  mixed $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return [TelegramChannel::class, 'database', 'mail'];
    }

    /**
     * @param Opportunity $opportunity
     * @return TelegramMessage
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

        $messageTexts = fractal()->item($opportunity)->transformWith(new FormattedOpportunityTransformer())->toArray();
        $lastSentID = null;
        $messageSent = null;

        if (filled($messageTexts['data'])) {
            $telegramMessage
                ->to($this->chatId)
                ->content($messageTexts['data']);
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
     * @param  mixed $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}
