<?php

namespace App\Notifications;

use App\Enums\Callbacks;
use App\Enums\GroupTypes;
use App\Helpers\BotHelper;
use App\Helpers\ExtractorHelper;
use App\Mail\SendOpportunity as Mailable;
use App\Models\Group;
use App\Models\Opportunity;
use App\Notifications\Channels\TelegramChannel;
use App\Services\TelegramMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use League\CommonMark\CommonMarkConverter;
use Throwable;

/**
 * Class SendOpportunity
 *
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
class SendOpportunity extends Notification
{
    use Queueable;

    /** @var array */
    private $options;

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
        $this->opportunity = $opportunity;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param mixed $notifiable
     *
     * @return array
     */
    public function via($notifiable): array
    {
        $channels = ['database'];
        switch ($notifiable->type) {
            case GroupTypes::CHANNEL:
            case GroupTypes::GROUP:
                $channels = Arr::prepend($channels, TelegramChannel::class);
                break;
            case GroupTypes::MAILING:
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

        $botName = Config::get('telegram.default');

        $messageText = view('notifications.opportunity', [
            'opportunity' => $this->opportunity,
            'isEmail' => false,
            'hasAuthor' => $group->admin && Str::contains($this->opportunity->origin, [$botName])
        ])->render();

        if (strlen($messageText) > BotHelper::TELEGRAM_LIMIT) {
            $messageText = explode(
                '%%%%%%%',
                wordwrap(
                    $messageText,
                    (BotHelper::TELEGRAM_LIMIT - strlen("\n00/00\n")),
                    '%%%%%%%'
                )
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
                Callbacks::APPROVE()->description,
                null,
                implode(' ', [Callbacks::APPROVE, $this->opportunity->id])
            );
            $telegramMessage->button(
                Callbacks::REMOVE()->description,
                null,
                implode(' ', [Callbacks::REMOVE, $this->opportunity->id])
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
            $mailable->to($group->name);
        }

        $messageText = view('notifications.opportunity', [
            'opportunity' => $this->opportunity,
            'isEmail' => true,
            'hasAuthor' => false,
        ])->render();

        $markdown = new CommonMarkConverter();

        $messageText = nl2br($messageText);

        $messageText = $markdown->convertToHtml($messageText);

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
    public function toArray($group): array
    {
        $session = session();
        $telegramIds = $session->get($this->id);
        $data = [
            'group_name' => $group->name,
            'opportunity' => $this->opportunity->id,
            'telegram_ids' => $telegramIds,
        ];

        $data = array_filter($data);

        return $data;
    }
}
