<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Notifications\Notification;

/**
 * Class TelegramMessageSent
 *
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
class TelegramMessageSent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @var Notification */
    public $notification;

    /** @var mixed */
    public $notifiable;

    /** @var mixed */
    public $data;

    /**
     * TelegramMessageSent constructor.
     *
     * @param Notification $notification
     * @param mixed        $notifiable
     * @param mixed        $data
     */
    public function __construct(Notification $notification, $notifiable, $data)
    {
        $this->notification = $notification;
        $this->notifiable = $notifiable;
        $this->data = $data;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return Channel|array
     */
    public function broadcastOn()
    {
        return [];
    }
}
