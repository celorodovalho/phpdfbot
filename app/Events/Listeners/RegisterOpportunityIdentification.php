<?php

namespace App\Events\Listeners;

use App\Enums\GroupTypes;
use App\Models\Group;
use App\Notifications\GroupSummaryOpportunities;
use App\Notifications\SendOpportunity;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Session\SessionManager;
use Illuminate\Session\Store;
use Illuminate\Support\Collection;

/**
 * Class RegisterOpportunityIdentification
 *
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
class RegisterOpportunityIdentification
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
    public function handle(NotificationSent $event)
    {
        if ($event->notification instanceof SendOpportunity
            && $event->notifiable instanceof Group
            && $event->notifiable->type === GroupTypes::TYPE_CHANNEL
            && $event->notifiable->main
            && $event->response instanceof Collection) {
            $opportunity = $event->notification->getOpportunity();
            $opportunity->update(['telegram_id' => $event->response->first()->messageId]);
        }

        if ($event->notification instanceof GroupSummaryOpportunities) {
            $this->store->push($event->notification->id, $event->response->first()->messageId);
        }
    }
}
