<?php

namespace App\Http\Controllers\Bot;

use App\Http\Controllers\Controller;
use Telegram\Bot\BotsManager;
use Telegram;
use Telegram\Bot\Objects\Update;

class DefaultController extends Controller
{
    /**
     * @var BotsManager
     */
    private $botsManager;

    /**
     * DefaultController constructor.
     * @param BotsManager $botsManager
     */
    public function __construct(BotsManager $botsManager)
    {
        $this->botsManager = $botsManager;
    }

    public function setWebhook($botName)
    {
        $telegram = $this->botsManager->bot($botName);
        $config = $this->botsManager->getBotConfig($botName);

        $params = ['url' => array_get($config, 'webhook_url')];
        $certificatePath = array_get($config, 'certificate_path', false);

        if ($certificatePath) {
            $params['certificate'] = $certificatePath;
        }

        $response = $telegram->setWebhook($params);
        if ($response) {
            return 'Success: Your webhook has been set!';
        }

        return 'Your webhook could not be set!';
    }

    public function webhook($token, $botName)
    {
        try {
            $update = Telegram::commandsHandler(true);
            $this->processUpdate($update);
        } catch (\Exception $exception) {
            return $exception->getMessage();
        }
        return 'ok';
    }

    private function processUpdate(Update $update)
    {
        /** @var Telegram\Bot\Objects\Message $message */
        /** @var Telegram\Bot\Objects\Message $reply */
        $message = $update->getMessage();
        if (filled($message)) {
            $reply = $message->getReplyToMessage();
            if (filled($reply)) {
                \Illuminate\Support\Facades\Log::info('MESSAGE', [$message]);
                \Illuminate\Support\Facades\Log::info('REPLY', [$reply]);
                if ($reply->from->isBot) {
                    \Illuminate\Support\Facades\Log::info('TEXT', [$message->text]);
                }
//                return Telegram::getCommandBus()->execute($command, $arguments, $update);
            }
        }
    }
}
