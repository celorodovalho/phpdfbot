<?php

namespace App\Notifications;

use App\Helpers\BotHelper;
use App\Helpers\Helper;
use App\Helpers\SanitizerHelper;
use App\Models\Group;
use App\Notifications\Channels\TelegramChannel;
use App\Services\TelegramMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Notification;
use Illuminate\Session\Store;

/**
 * Class GroupSummaryOpportunities
 *
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
class GroupSummaryOpportunities extends Notification
{
    use Queueable;

    /** @var Collection */
    private $opportunities;

    /** @var Collection */
    private $channels;

    /**
     * SendOpportunity constructor.
     *
     * @param Collection $opportunities
     * @param Collection $channels
     */
    public function __construct(Collection $opportunities, Collection $channels)
    {
        $this->opportunities = $opportunities;
        $this->channels = $channels;
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
        return [
            TelegramChannel::class,
            'database'
        ];
    }

    /**
     * @param Group $group
     *
     * @return TelegramMessage
     */
    public function toTelegram($group)
    {
        $telegramMessage = new TelegramMessage;
        if ($this->opportunities->isNotEmpty()) {
            $listOpportunities = $this->opportunities->map(static function ($opportunity) use ($group) {
                return sprintf(
                    '➩ [%s](%s)',
                    Helper::excerpt(
                        SanitizerHelper::sanitizeSubject(
                            SanitizerHelper::removeBrackets($opportunity->title)
                        ),
                        41 - strlen($opportunity->telegram_id)
                    ),
                    sprintf('https://t.me/%s/%s', $group->title, $opportunity->telegram_id)
                );
            });

            $text = sprintf(
                "[%s](%s)\n%s\n\n",
                '🄿🄷🄿🄳🄵',
                str_replace('/index.php', '', asset('/img/phpdf.webp')),
                'Há novas vagas no canal!'
            );

            $listOpportunities->prepend($text);

            $listSize = $listOpportunities->map(function ($title) {
                return strlen($title);
            });

            $chunkSize = $listSize->sum() >= (BotHelper::TELEGRAM_LIMIT - $listSize->count())
                ? (int)(BotHelper::TELEGRAM_LIMIT / $listSize->max())
                : $listSize->count();
            $listOpportunities = $listSize->chunk($chunkSize)
                ->map(function ($item) use ($listOpportunities) {
                    return $listOpportunities->only($item->keys())->implode("\n");
                });

            $telegramMessage->content($listOpportunities->toArray());

            foreach ($this->channels as $channel) {
                $telegramMessage->button($channel->name, 'https://t.me/' . $channel->title);
            }
        }

        $telegramMessage->to($group->name);
        return $telegramMessage;
    }


    /**
     * Get the array representation of the notification.
     *
     * @param mixed $notifiable
     *
     * @return array
     */
    public function toArray($notifiable)
    {
        $session = session();
        $telegramId = $session->get($this->id);
        return [
            'telegram_id' => $telegramId,
            'opportunities' => $this->opportunities->pluck('id')->toArray()
        ];
    }
}
