<?php

namespace App\Notifications;

use App\Helpers\BotHelper;
use App\Helpers\Helper;
use App\Helpers\SanitizerHelper;
use App\Models\Group;
use App\Notifications\Channels\DatabaseChannel;
use App\Notifications\Channels\TelegramChannel;
use App\Services\TelegramMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Collection as DatabaseCollection;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Emoji\Emoji;

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
     * @param DatabaseCollection $opportunities
     * @param Collection         $channels
     */
    public function __construct(DatabaseCollection $opportunities, Collection $channels)
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
    public function via($notifiable): array
    {
        return [
            TelegramChannel::class,
            DatabaseChannel::class
        ];
    }

    /**
     * @param Group $group
     *
     * @return TelegramMessage
     */
    public function toTelegram($group): TelegramMessage
    {
        $telegramMessage = new TelegramMessage;
        if ($this->opportunities->isNotEmpty()) {
            /** @var Group $mainChannel */
            $mainChannel = $this->channels->filter(static function ($item) {
                return $item->main;
            })->first();
            /*$listOpportunities = $this->opportunities->map(static function ($opportunity) use ($mainChannel) {
                return sprintf(
                    '➩ [%s](%s)',
                    Str::of(
                        Helper::excerpt(
                            SanitizerHelper::sanitizeSubject(
                                SanitizerHelper::removeBrackets(
                                    (filled($opportunity->position) ? Str::upper($opportunity->position) : $opportunity->title) .
                                    (filled($opportunity->location) ? ' - ' . $opportunity->location : '')
                                )
                            ),
                            //41
                            55 - strlen($opportunity->telegram_id)
                        )
                    )->trim(' ,'),
                    sprintf('https://t.me/%s/%s', $mainChannel->title, $opportunity->telegram_id)
                );
            });*/

            $text = sprintf(
                "[%s](%s)\n%s\n",
                '🄿🄷🄿🄳🄵',
                str_replace('/index.php', '', asset('/img/phpdf.webp')),
                sprintf(
                    'Há %s novas vagas no canal!',
                    $this->opportunities->count()
                )
            );

            /*
            $listOpportunities->prepend($text);

            $listSize = $listOpportunities->map(static function ($title) {
                return strlen($title);
            });*

            $chunkSize = $listSize->sum() >= (BotHelper::TELEGRAM_LIMIT - $listSize->count())
                ? (int)(BotHelper::TELEGRAM_LIMIT / $listSize->max())
                : $listSize->count();
            $listOpportunities = $listSize->chunk($chunkSize)
                ->map(static function ($item) use ($listOpportunities) {
                    return $listOpportunities->only($item->keys())->implode("\n");
                });
            */

            $telegramMessage->content($text);

            foreach ($this->channels as $channel) {
                $telegramMessage->button($channel->name, 'https://t.me/' . $channel->title);
            }

            $firstOpportunity = $this->opportunities->first();

            $telegramMessage->button(
                Emoji::openFileFolder() . ' Ver novas vagas',
                sprintf('https://t.me/%s/%s', $mainChannel->title, $firstOpportunity->telegram_id)
            );
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
    public function toArray($notifiable): array
    {
        $session = session();
        $telegramIds = $session->get($this->id);
        return [
            'group_name' => $notifiable->name,
            'opportunities' => $this->opportunities->pluck('id')->toArray(),
            'telegram_id' => reset($telegramIds),
        ];
    }
}
