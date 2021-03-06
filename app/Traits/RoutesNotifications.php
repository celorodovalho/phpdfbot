<?php

namespace App\Traits;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use Illuminate\Contracts\Notifications\Dispatcher;

/**
 * Trait RoutesNotifications
 *
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 *
 * @property string email
 * @property string phone_number
 */
trait RoutesNotifications
{
    /**
     * Send the given notification.
     *
     * @param mixed $instance
     *
     * @return mixed
     */
    public function notify($instance)
    {
        return app(Dispatcher::class)->send($this, $instance);
    }

    /**
     * Send the given notification immediately.
     *
     * @param mixed      $instance
     * @param array|null $channels
     *
     * @return void
     */
    public function notifyNow($instance, array $channels = null): void
    {
        app(Dispatcher::class)->sendNow($this, $instance, $channels);
    }

    /**
     * Get the notification routing information for the given driver.
     *
     * @param string            $driver
     * @param Notification|null $notification
     *
     * @return mixed
     */
    public function routeNotificationFor($driver, $notification = null)
    {
        if (method_exists($this, $method = 'routeNotificationFor' . Str::studly($driver))) {
            return $this->{$method}($notification);
        }

        switch ($driver) {
            case 'database':
                return $this->notifications();
            case 'mail':
                return $this->email;
            case 'nexmo':
                return $this->phone_number;
        }

        return null;
    }
}
