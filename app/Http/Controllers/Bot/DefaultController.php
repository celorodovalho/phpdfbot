<?php

namespace App\Http\Controllers\Bot;

use App\Contracts\Repositories\OpportunityRepository;
use App\Exceptions\Handler;
use App\Http\Controllers\Controller;
use App\Services\CommandsHandler;
use App\Validators\OpportunityValidator;
use Illuminate\Support\Arr;
use Telegram\Bot\Api as Telegram;
use Telegram\Bot\BotsManager;
use Telegram\Bot\Exceptions\TelegramSDKException;

/**
 * Class DefaultController
 *
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
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
     * @var Telegram
     */
    private $telegram;

    /**
     * DefaultController constructor.
     *
     * @param BotsManager           $botsManager
     * @param Telegram              $telegram
     * @param Handler               $handler
     * @param OpportunityRepository $repository
     * @param OpportunityValidator  $validator
     */
    public function __construct(
        BotsManager $botsManager,
        Telegram $telegram,
        Handler $handler,
        OpportunityRepository $repository,
        OpportunityValidator $validator
    ) {
        $this->botsManager = $botsManager;
        $this->telegram = $telegram;
        $this->handler = $handler;
        parent::__construct($repository, $validator);
    }

    /**
     * Set the webhook to the bots
     *
     * @param $botName
     *
     * @return string
     * @throws TelegramSDKException
     */
    public function setWebhook($botName): string
    {
        $telegram = $this->botsManager->bot($botName);
        $config = $this->botsManager->getBotConfig($botName);

        $params = ['url' => Arr::get($config, 'webhook_url')];
        $certificatePath = Arr::get($config, 'certificate_path', false);

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
     *
     * @return string
     */
    public function webhook($token, $botName): string
    {
        try {
            $update = $this->telegram->getWebhookUpdate();
            (new CommandsHandler($this->botsManager, $botName, $this->repository))->processUpdate($update);
        } catch (\Exception $exception) {
            $this->handler->log($exception);
        }
        return 'ok';
    }
}
