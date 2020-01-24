<?php

namespace App\Notifications;

use App\Enums\GroupTypes;
use App\Helpers\BotHelper;
use App\Helpers\ExtractorHelper;
use App\Mail\SendOpportunity as Mailable;
use App\Models\Group;
use App\Models\Opportunity;
use App\Notifications\Channels\TelegramChannel;
use App\Services\TelegramMessage;
use GrahamCampbell\Markdown\Facades\Markdown;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Telegram\Bot\Keyboard\Keyboard;
use Throwable;

/**
 * Class SendOpportunity
 */
class SendOpportunity extends Notification
{
    use Queueable;

    /** @var array */
    private $options;

    /** @var string */
    private $admin;

    /** @var string */
    private $botName;

    /** @var Opportunity */
    private $opportunity;

    /**
     * SendOpportunity constructor.
     *
     * @param Opportunity $opportunity
     * @param array       $options
     */
    public function __construct(Opportunity $opportunity, array $options = [])
    {
        $this->options = $options;
        $this->admin = Config::get('telegram.admin');
        $this->botName = Config::get('telegram.default');
        $this->opportunity = $opportunity;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param mixed $notifiable
     *
     * @return array
     */
    public function via($notifiable)
    {
        $channels = ['database'];
        switch ($notifiable->type) {
            case GroupTypes::TYPE_CHANNEL:
            case GroupTypes::TYPE_GROUP:
                $channels = Arr::prepend($channels, TelegramChannel::class);
                break;
            case GroupTypes::TYPE_MAILING:
                $channels = Arr::prepend($channels, 'mail');
                break;
        }
        return $channels;
    }

    /**
     * @param Group $group
     *
     * @return TelegramMessage
     * @throws Throwable
     */
    public function toTelegram($group): ?TelegramMessage
    {
        $telegramMessage = new TelegramMessage;

        if ($this->admin === $group->name && Str::contains($this->opportunity->origin, [$this->botName])) {
            $userNames = explode('|', $this->opportunity->origin);
            $userName = end($userNames);
            if (!blank($userName)) {
                if (Str::contains($userName, ' ')) {
                    $userMention = "[$userName](tg://user?id={$this->opportunity->telegram_user_id})";
                } else {
                    $userMention = '@' . $userName;
                }
                $this->opportunity->description .= "\n\nby $userMention";
            }
        }

        $messageText = view('notifications.opportunity', [
            'opportunity' => $this->opportunity,
            'isEmail' => false
        ])->render();

        if (strlen($messageText) > BotHelper::TELEGRAM_LIMIT) {
            $messageText = str_split(
                $messageText,
                BotHelper::TELEGRAM_LIMIT - strlen("\n00/00\n")
            );

            $count = count($messageText);

            if ($count > 1) {
                $messageText = array_map(static function ($part, $index) use ($count) {
                    $index++;
                    return $part . "\n{$index}/{$count}\n";
                }, $messageText, array_keys($messageText));
            }
        }

        if ($group->admin) {
            $telegramMessage->button(
                'Aprovar',
                null,
                implode(' ', [Opportunity::CALLBACK_APPROVE, $this->opportunity->id])
            );
            $telegramMessage->button(
                'Remover',
                null,
                implode(' ', [Opportunity::CALLBACK_REMOVE, $this->opportunity->id])
            );
        }

        if (filled($messageText)) {
            $telegramMessage
                ->to($group->name)
                ->content($messageText)
                ->options($this->options);
        }

        return $telegramMessage;
    }

    /**
     * @param Group $group
     *
     * @return Mailable
     * @throws Throwable
     */
    public function toMail($group): ?Mailable
    {
        $mailable = new Mailable;
        if (!Str::contains($this->opportunity->origin, $group->name) &&
            (blank($group->tags) || ExtractorHelper::hasTags($group->tags, $this->opportunity->getText()))
        ) {
            dump($group->name);
            $mailable->to('phpdfbot+teste@gmail.com');
        }

        $messageText = view('notifications.opportunity', [
            'opportunity' => $this->opportunity,
            'isEmail' => true
        ])->render();

        $messageText = Markdown::convertToHtml($messageText);

        $mailable->subject($this->opportunity->title)
            ->html($messageText);
        return $mailable;
    }

    /**
     * Get the array representation of the notification.
     *
     * @param Group $group
     *
     * @return array
     */
    public function toArray($group)
    {
        $data = [
            'group_name' => $group->name,
            'telegram_user_id' => $this->opportunity->telegram_user_id,
            'opportunity' => $this->opportunity->id,
        ];

        $data = array_filter($data);

        return $data;
    }

    /**
     * @return Opportunity
     */
    public function getOpportunity(): Opportunity
    {
        return $this->opportunity;
    }
}
