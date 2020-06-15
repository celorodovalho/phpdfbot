<?php

namespace App\Notifications\Channels;

use App\Exceptions\Handler;
use App\Helpers\BotHelper;
use App\Helpers\Helper;
use App\Helpers\SanitizerHelper;
use App\Services\TelegramMessage;
use Exception;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use RuntimeException;
use Telegram\Bot\Api as Telegram;
use Telegram\Bot\BotsManager;
use Telegram\Bot\Exceptions\TelegramResponseException;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\Objects\Message;

/**
 * Class TelegramChannel
 *
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
class TelegramChannel
{

    /** @var Telegram */
    protected $telegram;

    /**
     * Channel constructor.
     *
     * @param BotsManager $botsManager
     *
     * @throws TelegramSDKException
     */
    public function __construct(BotsManager $botsManager)
    {
        $this->telegram = $botsManager->bot(Config::get('telegram.default'));
    }

    /**
     * Send the given notification.
     *
     * @param mixed        $notifiable
     * @param Notification $notification
     *
     * @return Collection
     * @throws TelegramSDKException
     * @throws Exception
     * @throws Exception
     */
    public function send($notifiable, Notification $notification): Collection
    {
        $message = $notification->toTelegram($notifiable);

        if (is_string($message)) {
            $message = new TelegramMessage($message);
        }

        if ($message->chatIdNotGiven()) {
            if (!$chatId = $notifiable->routeNotificationFor('telegram')) {
                throw new RuntimeException('Telegram notification chat ID was not provided. Please refer usage docs.');
            }

            $message->to($chatId);
        }

        if ($message->sizeLimitExceed()) {
            throw new RuntimeException('Telegram text limit size was exceeded. Please refer usage docs.');
        }

        $params = $message->toArray();
        $messages = new Collection;

        if ($message instanceof TelegramMessage) {
            $body = $params['text'];
            $chances = true;
            while ($chances) {
                try {
                    if (is_array($body)) {
                        foreach ($body as $text) {
                            $params['text'] = $text;
                            if ($messages->count()) {
                                $params['reply_to_message_id'] = $messages->first()['message_id'];
                            }
                            /** @var Message $telegramMessage */
                            $telegramMessage = $this->telegram->sendMessage($params);
                            $messages->add($telegramMessage->toArray());
                        }
                    } else {
                        /** @var Message $telegramMessage */
                        $telegramMessage = $this->telegram->sendMessage($params);
                        $messages->add($telegramMessage->toArray());
                    }
                    $chances = false;
                } catch (TelegramResponseException $exception) {
                    if (preg_match_all(
                        '/Too Many Requests: retry after (\d+)/uim',
                        $exception->getMessage(),
                        $matches
                    )) {
                        $seconds = end($matches);
                        $seconds = reset($seconds);
                        sleep($seconds);
                    } else {
                        Handler::log($exception, 'SEND_MESSAGE', $params);
                        unset($params['parse_mode']);
                        $params['text'] = SanitizerHelper::removeMarkdown($params['text']);
                    }
                }
            }
        }
        return $messages;
    }
}
