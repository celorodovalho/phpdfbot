<?php

namespace App\Http\Controllers\Bot;

use App\Contracts\Repositories\OpportunityRepository;
use App\Exceptions\Handler;
use App\Http\Controllers\Controller;
use App\Services\CommandsHandler;
use App\Validators\OpportunityValidator;
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
     * @var Handler
     */
    private $handler;

    /**
     * DefaultController constructor.
     * @param BotsManager $botsManager
     * @param Handler $handler
     * @param OpportunityRepository $repository
     * @param OpportunityValidator $validator
     */
    public function __construct(
        BotsManager $botsManager,
        Handler $handler,
        OpportunityRepository $repository,
        OpportunityValidator $validator
    )
    {
        $this->botsManager = $botsManager;
        $this->handler = $handler;
        parent::__construct($repository, $validator);
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
     * @param Handler $handler
     * @return string
     */
    public function webhook($token, $botName, Handler $handler): string
    {
        try {
            $update = Telegram::getWebhookUpdate();
            CommandsHandler::make($this->botsManager, $botName, $update);
        } catch (\Exception $exception) {
            $this->handler->log($exception);
        }
        return 'ok';
    }
}
