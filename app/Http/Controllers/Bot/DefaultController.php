<?php

namespace App\Http\Controllers\Bot;

use App\Http\Controllers\Controller;
use App\Services\CommandsHandler;

use Exception;

use Telegram\Bot\BotsManager;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\Laravel\Facades\Telegram;

/**
 * Class DefaultController
 */
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

    /**
     * Set the webhook to the bots
     *
     * @param $botName
     * @return string
     * @throws TelegramSDKException
     */
    public function setWebhook($botName): string
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

    /**
     * Webhook to the bots commands
     *
     * @param $token
     * @param $botName
     * @return string
     * @throws TelegramSDKException
     */
    public function webhook($token, $botName): string
    {
        $update = Telegram::getWebhookUpdate();
        CommandsHandler::make($this->botsManager, $botName, $update);
        return 'ok';
    }
}
