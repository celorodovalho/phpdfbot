<?php

namespace App\Notifications;

use App\Helpers\SanitizerHelper;
use App\Models\Group;
use App\Models\Opportunity;
use App\Notifications\Channels\DatabaseChannel;
use App\Notifications\Channels\TelegramChannel;
use App\Services\TelegramMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notification;
use Spatie\Emoji\Emoji;

/**
 * Class NotifySenderUser
 *
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
class NotifySenderUser extends Notification
{
    use Queueable;

    /** @var Group */
    private $group;

    /**
     * NotifySenderUser constructor.
     *
     * @param Group $group
     */
    public function __construct(Group $group)
    {
        $this->group = $group;
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
     * @param Opportunity $notifiable
     *
     * @return TelegramMessage
     */
    public function toTelegram($notifiable): TelegramMessage
    {
        $telegramMessage = new TelegramMessage;
        if ($notifiable->telegram_user_id) {
            /** @var DatabaseNotification $notification */
//            $notification = $this->group->notifications()->where('data->opportunity', $notifiable->id)->first();
            $notification = $this->group->notifications()
                ->where('data', 'like', '%"opportunity":' . $notifiable->id . '%')->first();
            $telegramIds = $notification->data['telegram_ids'];
            $link = sprintf('https://t.me/%s/%s', $this->group->title, reset($telegramIds));
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
     * @param mixed $notifiable
     *
     * @return array
     */
    public function toArray($notifiable): array
    {
        $session = session();
        $telegramIds = $session->get($this->id);
        $data = [
            'group_id' => $this->group->id,
            'telegram_id' => reset($telegramIds),
        ];

        $data = array_filter($data);
        return $data;
    }
}
