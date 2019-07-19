<?php

namespace App\Http\Controllers\Bot;

use App\Http\Controllers\Controller;
use App\Models\Opportunity;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\BotsManager;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\Laravel\Facades\Telegram;
use Telegram\Bot\Objects\Update;
use Telegram\Bot\Api;

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
            $telegram = $this->botsManager->bot($botName);
            $this->processUpdate($update, $telegram);
        } catch (\Exception $exception) {
            return $exception->getMessage();
        }
        return 'ok';
    }

    private function processUpdate(Update $update, Api $telegram)
    {
        /** @var \Telegram\Bot\Objects\Message $message */
        $message = $update->getMessage();
        /** @var \Telegram\Bot\Objects\CallbackQuery $callbackQuery */
        $callbackQuery = $update->get('callback_query');
        if (filled($callbackQuery)) {
            $data = $callbackQuery->get('data');
            Log::info('DATA', [$data]);
        } elseif (filled($message)) {
            /** @var \Telegram\Bot\Objects\Message $reply */
            $reply = $message->getReplyToMessage();
            if (filled($reply) && $reply->from->isBot) {
                $opportunity = new Opportunity();
                $opportunity->title = substr($message->text, 0, 100);
                $opportunity->description = $message->text;
                $opportunity->status = Opportunity::STATUS_INACTIVE;
                $opportunity->save();
                $this->sendOpportunityToApproval($opportunity, $telegram);
//                return Telegram::getCommandBus()->execute($command, $arguments, $update);
            }
        }
    }

    private function sendOpportunityToApproval(Opportunity $opportunity, Api $telegram)
    {
        //Artisan::call("infyom:scaffold", ['name' => $request['name'], '--fieldsFile' => 'public/Product.json']);
        $keyboard = Keyboard::make()
            ->inline()
            ->row(
                Keyboard::inlineButton(['text' => 'Aprovar', 'callback_data' => 'aprove']),
                Keyboard::inlineButton(['text' => 'Remover', 'callback_data' => 'remove'])
            );

        $telegram->sendMessage([
            'parse_mode' => 'Markdown',
            'chat_id' => env('TELEGRAM_OWNER_ID'),
            'text' => $opportunity->description,
            'reply_markup' => $keyboard
        ]);
    }
}
