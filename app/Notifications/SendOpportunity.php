<?php

namespace App\Notifications;

use App\Helpers\BotHelper;
use App\Helpers\ExtractorHelper;
use App\Mail\SendOpportunity as Mailable;
use App\Models\Opportunity;
use App\Notifications\Channels\TelegramChannel;
use App\Services\TelegramMessage;
use GrahamCampbell\Markdown\Facades\Markdown;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Collection;
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

    /** @var array */
    private $mailing;

    /**
     * SendOpportunity constructor.
     * @param $chatId
     * @param Collection $mailing
     * @param array $options
     */
    public function __construct($chatId, ?Collection $mailing, array $options = [])
    {
        $this->chatId = $chatId;
        $this->options = $options;
        $this->admin = Config::get('telegram.admin');
        $this->botName = Config::get('telegram.default');
        $this->mailing = $mailing;
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


        if (strlen($messageText) > BotHelper::TELEGRAM_LIMIT) {
            $messageText = str_split(
                $messageText,
                BotHelper::TELEGRAM_LIMIT - strlen("\n1/1\n")
            );

            $count = count($messageText);

            if ($count > 1) {
                $messageText = array_map(function ($part, $index) use ($count) {
                    $index++;
                    return $part . "\n{$index}/{$count}\n";
                }, $messageText, array_keys($messageText));
            }
        }

        if (filled($messageText)) {
            $telegramMessage
                ->to($this->chatId)
                ->content($messageText)
                ->options($this->options);
        }
        return $telegramMessage;
    }

    /**
     * @param Opportunity $opportunity
     * @return Mailable
     * @throws \Throwable
     */
    public function toMail($opportunity)
    {
        dump($this->mailing->first());
        if ($this->mailing) {
            $mailable = new Mailable;
            foreach ($this->mailing as $mail) {
                if (
                    !Str::contains($opportunity->origin, $mail->name) &&
                    (blank($mail->tags) || ExtractorHelper::hasTags($mail->tags, $opportunity->getText()))
                ) {
                    dump($mail->name);
                    $mailable->to("phpdfbot+teste@gmail.com");
                }
            }

            $messageText = view('notifications.opportunity', [
                'opportunity' => $opportunity,
                'isEmail' => true
            ])->render();

            $messageText = Markdown::convertToHtml($messageText);

            $mailable->subject($opportunity->title)
                ->html($messageText);
            return $mailable;
        }
    }

    /**
     * Get the array representation of the notification.
     *
     * @param mixed $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        dump($this->mailing->first());
        return $notifiable instanceof Opportunity ? [
            'telegram_id' => $notifiable->telegram_id,
            'chat_id' => $this->chatId,
            'telegram_user_id' => $notifiable->telegram_user_id,
            'mailing' => $this->mailing->chunk('name')->toArray(),
        ] : [];
    }
}
