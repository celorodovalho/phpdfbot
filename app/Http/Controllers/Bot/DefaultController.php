<?php

namespace App\Http\Controllers\Bot;

use App\Http\Controllers\Controller;
use App\Models\Opportunity;
use Illuminate\Support\Facades\Artisan;
use Telegram\Bot\Api;
use Telegram\Bot\BotsManager;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\Laravel\Facades\Telegram;
use Telegram\Bot\Objects\CallbackQuery;
use Telegram\Bot\Objects\Message;
use Telegram\Bot\Objects\Update;

class DefaultController extends Controller
{
    public const CALLBACK_APROVE = 'aprove';
    public const CALLBACK_REMOVE = 'remove';
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

    private function processUpdate(Update $update, Api $telegram): void
    {
        /** @var \Telegram\Bot\Objects\Message $message */
        $message = $update->getMessage();
        /** @var \Telegram\Bot\Objects\CallbackQuery $callbackQuery */
        $callbackQuery = $update->get('callback_query');
        if (filled($callbackQuery)) {
            $this->processCallbackQuery($callbackQuery, $telegram);
        } elseif (filled($message)) {
            $this->processMessage($message, $telegram);
        }
    }

    private function processCallbackQuery(CallbackQuery $callbackQuery, Api $telegram): void
    {
        $data = $callbackQuery->get('data');
        $data = explode(' ', $data);
        switch ($data[0]) {
            case self::CALLBACK_APROVE:
                $opportunity = Opportunity::find($data[1]);
                $opportunity->status = Opportunity::STATUS_ACTIVE;
                $opportunity->save();
                Artisan::call(
                    'bot:populate:channel',
                    [
                        'process' => 'send',
                        'opportunity' => $opportunity->id
                    ]
                );
                break;
            case self::CALLBACK_REMOVE:
                Opportunity::find($data[1])->delete();
                break;
            default:
                break;
        }
        $telegram->deleteMessage([
            'chat_id' => env('TELEGRAM_OWNER_ID'),
            'message_id' => $callbackQuery->message->messageId
        ]);
    }

    private function processMessage(Message $message, Api $telegram): void
    {
        /** @var \Telegram\Bot\Objects\Message $reply */
        $reply = $message->getReplyToMessage();
        if (filled($reply) && $reply->from->isBot) {
            $opportunity = new Opportunity();
            $opportunity->title = substr($message->text, 0, 100);
            $opportunity->description = $message->text;
            $opportunity->status = Opportunity::STATUS_INACTIVE;
            $opportunity->save();
            $this->sendOpportunityToApproval($opportunity, $telegram);
        }
    }

    private function sendOpportunityToApproval(Opportunity $opportunity, Api $telegram): void
    {
        $keyboard = Keyboard::make()
            ->inline()
            ->row(
                Keyboard::inlineButton([
                    'text' => 'Aprovar',
                    'callback_data' => implode(' ', [self::CALLBACK_APROVE, $opportunity->id])
                ]),
                Keyboard::inlineButton([
                    'text' => 'Remover',
                    'callback_data' => implode(' ', [self::CALLBACK_REMOVE, $opportunity->id])
                ])
            );

        $telegram->sendMessage([
            'parse_mode' => 'Markdown',
            'chat_id' => env('TELEGRAM_OWNER_ID'),
            'text' => $opportunity->description,
            'reply_markup' => $keyboard
        ]);
    }
}
