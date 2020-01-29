<?php

namespace App\Events\Listeners;

use App\Notifications\Channels\TelegramChannel;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Session\Store;
use Illuminate\Support\Collection;

/**
 * Class RegisterNotificationIdentification
 *
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
class RegisterNotificationIdentification
{

    /**
     * @var Store
     */
    private $store;

    /**
     * Create the event listener.
     *
     * @param Store $store
     */
    public function __construct(Store $store)
    {
        $this->store = $store;
    }

    /**
     * Handle the event.
     *
     * @param NotificationSent $event
     *
     * @return void
     */
    public function handle(NotificationSent $event): void
    {
        if ($event->channel === TelegramChannel::class && $event->response instanceof Collection) {
            $this->store->put($event->notification->id, $event->response->pluck('message_id')->toArray());
        }
    }
}
