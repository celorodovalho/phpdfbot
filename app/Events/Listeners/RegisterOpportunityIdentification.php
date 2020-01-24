<?php

namespace App\Events\Listeners;

use App\Enums\GroupTypes;
use App\Events\TelegramMessageSent;
use App\Models\Group;
use App\Notifications\SendOpportunity;

/**
 * Class RegisterOpportunityIdentification
 *
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
class RegisterOpportunityIdentification
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param TelegramMessageSent $event
     *
     * @return void
     */
    public function handle(TelegramMessageSent $event)
    {
        if ($event->notification instanceof SendOpportunity
            && $event->notifiable instanceof Group
            && $event->notifiable->type === GroupTypes::TYPE_CHANNEL
            && $event->notifiable->main
            && is_numeric($event->data)) {
            $opportunity = $event->notification->getOpportunity();
            $opportunity->update(['telegram_id' => $event->data]);
        }

        if (property_exists($event->notification, 'telegramId') && is_numeric($event->data)) {
            $event->notification::$telegramId = $event->data;
        }
    }
}
