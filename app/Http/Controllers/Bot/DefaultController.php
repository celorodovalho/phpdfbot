<?php

namespace App\Http\Controllers\Bot;

use App\Http\Controllers\Controller;
use Telegram\Bot\BotsManager;
use Telegram;

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
            \Illuminate\Support\Facades\Log::info('UPDATE', [$update]);
        } catch (\Exception $exception) {
            return $exception->getMessage();
        }
        return 'ok';
    }
}
