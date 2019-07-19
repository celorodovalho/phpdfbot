<?php

namespace App\Services;

use App\Commands\NewOpportunityCommand;
use App\Models\Opportunity;

use Illuminate\Support\Facades\Artisan;

use Illuminate\Support\Facades\Log;
use Telegram\Bot\Api;
use Telegram\Bot\BotsManager;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\Keyboard\Keyboard;
use Telegram\Bot\Objects\CallbackQuery;
use Telegram\Bot\Objects\Message;
use Telegram\Bot\Objects\Update;

/**
 * Class CommandsHandler
 */
class CommandsHandler
{

    /** @var BotsManager */
    private $botsManager;

    /** @var string */
    private $token;

    /** @var string */
    private $botName;

    /** @var Api */
    private $telegram;

    /** @var Update */
    private $update;

    /**
     * CommandsHandler constructor.
     * @param BotsManager $botsManager
     * @param string $botName
     * @param string $token
     * @param Update $update
     * @throws TelegramSDKException
     */
    public function __construct(BotsManager $botsManager, string $botName, string $token, Update $update)
    {
        $this->botsManager = $botsManager;
        $this->token = $token;
        $this->botName = $botName;
        $this->update = $update;
        $this->telegram = $this->botsManager->bot($botName);
        $this->processUpdate($update);
    }

    /**
     * Process the update coming from bot interface
     *
     * @param Update $update
     * @throws TelegramSDKException
     */
    private function processUpdate(Update $update): void
    {
        /** @var Message $message */
        $message = $update->getMessage();
        /** @var CallbackQuery $callbackQuery */
        $callbackQuery = $update->get('callback_query');

        if (substr($message, 0, 1) === '/') {
            $command = explode(' ', $message);
            $command = str_replace('/', '', $command[0]);

            $commands = $this->telegram->getCommands();

            if (array_key_exists($command, $commands)) {
                return $this->telegram->getCommandBus()->execute($command, $update, $callbackQuery);
            }
        }
        if (filled($callbackQuery)) {
            $this->processCallbackQuery($callbackQuery);
        } elseif (filled($message)) {
            $this->processMessage($message);
        }
    }

    /**
     * Process the Callback query coming from bot interface
     *
     * @param CallbackQuery $callbackQuery
     * @throws TelegramSDKException
     */
    private function processCallbackQuery(CallbackQuery $callbackQuery): void
    {
        $data = $callbackQuery->get('data');
        $data = explode(' ', $data);
        switch ($data[0]) {
            case Opportunity::CALLBACK_APPROVE:
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
            case Opportunity::CALLBACK_REMOVE:
                Opportunity::find($data[1])->delete();
                break;
            default:
                break;
        }
        $this->telegram->deleteMessage([
            'chat_id' => env('TELEGRAM_OWNER_ID'),
            'message_id' => $callbackQuery->message->messageId
        ]);
    }

    /**
     * Process the messages coming from bot interface
     *
     * @param Message $message
     * @throws TelegramSDKException
     */
    private function processMessage(Message $message): void
    {
        /** @var Message $reply */
        $reply = $message->getReplyToMessage();
        if (filled($reply) && $reply->from->isBot && $reply->text === NewOpportunityCommand::TEXT) {
            $opportunity = new Opportunity();
            $opportunity->title = substr($message->text, 0, 100);
            $opportunity->description = $message->text;
            $opportunity->status = Opportunity::STATUS_INACTIVE;
            $opportunity->save();
            $this->sendOpportunityToApproval($opportunity);
        }
        $newMembers = $message->newChatMembers;
        Log::info('NEW_MEMBER', [$newMembers]);

        // TODO: Think more about this
        /*if (filled($newMembers)) {
            foreach ($newMembers as $newMember) {
                $name = $newMember->getFirstName();
                return $this->telegram->getCommandBus()->execute('start', $this->update, $name);
            }
        }*/
    }

    /**
     * Send opportunity to approval
     *
     * @param Opportunity $opportunity
     * @throws TelegramSDKException
     */
    private function sendOpportunityToApproval(Opportunity $opportunity): void
    {
        $keyboard = Keyboard::make()
            ->inline()
            ->row(
                Keyboard::inlineButton([
                    'text' => 'Aprovar',
                    'callback_data' => implode(' ', [Opportunity::CALLBACK_APPROVE, $opportunity->id])
                ]),
                Keyboard::inlineButton([
                    'text' => 'Remover',
                    'callback_data' => implode(' ', [Opportunity::CALLBACK_REMOVE, $opportunity->id])
                ])
            );

        $this->telegram->sendMessage([
            'parse_mode' => 'Markdown',
            'chat_id' => env('TELEGRAM_OWNER_ID'),
            'text' => $opportunity->description,
            'reply_markup' => $keyboard
        ]);
    }
}
