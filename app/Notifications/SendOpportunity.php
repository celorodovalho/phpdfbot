<?php

namespace App\Notifications;

use App\Helpers\SanitizerHelper;
use App\Models\Opportunity;
use App\Notifications\Channels\TelegramChannel;
use App\Transformers\FormattedOpportunityTransformer;
use Exception;
use Illuminate\Bus\Queueable;
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

    /** @var Opportunity */
    private $opportunity;

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
     * @param Opportunity $opportunity
     * @param $chatId
     * @param array $options
     */
    public function __construct(Opportunity $opportunity, $chatId, array $options = [])
    {
        $this->opportunity = $opportunity;
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
        $messageSentIds = [];
        $lastSentID = null;
        $messageSent = null;

        foreach ($messageTexts['data']['body'] as $messageText) {
            $sendMsg = array_merge([
                'chat_id' => $chatId,
                'parse_mode' => 'Markdown',
                'text' => $messageText,
            ], $options);

            if ($lastSentID) {
                $sendMsg['reply_to_message_id'] = $lastSentID;
            }

            try {
                $messageSent = $this->telegram->sendMessage($sendMsg);
                $messageSentIds[] = $messageSent->messageId;
            } catch (Exception $exception) {
                if ($exception->getCode() === 400) {
                    $sendMsg['text'] = SanitizerHelper::removeMarkdown($messageText);
                    unset($sendMsg['Markdown']);
                    try {
                        $messageSent = $this->telegram->sendMessage($sendMsg);
                    } catch (TelegramResponseException $exception2) {
                        if ($exception2->getCode() === 400) {
                            Log::error('THROW_MESSAGE2', [$sendMsg]);
                        }
                        throw $exception;
                    }
                    Log::error('THROW_MESSAGE1', [$sendMsg]);
                    $messageSentIds[] = $messageSent->messageId;
                } else {
                    throw $exception;
                }
            }

            if ($messageSent) {
                $lastSentID = $messageSent->messageId;
            }
        }
        return $messageSentIds;
    }





        $telegramMessage = new TelegramMessage;
        if ($notifiable->telegram_user_id) {
            $link = "https://t.me/VagasBrasil_TI/{$notifiable->telegram_id}";
            $telegramMessage
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
        return [
            //
        ];
    }
}
