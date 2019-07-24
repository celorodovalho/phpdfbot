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
use Telegram\Bot\Laravel\Facades\Telegram;
use Telegram\Bot\Objects\CallbackQuery;
use Telegram\Bot\Objects\Document;
use Telegram\Bot\Objects\Message;
use Telegram\Bot\Objects\PhotoSize;
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
     * @param BotsManager $botsManager
     * @param string $botName
     * @param string $token
     * @param Update $update
     * @return CommandsHandler
     * @throws TelegramSDKException
     */
    public static function make(BotsManager $botsManager, string $botName, string $token, Update $update)
    {
        return new static($botsManager, $botName, $token, $update);
    }

    /**
     * Process the update coming from bot interface
     *
     * @param Update $update
     * @return mixed
     * @throws TelegramSDKException
     */
    private function processUpdate(Update $update)
    {
        try {
            /** @var Message $message */
            $message = $update->getMessage();
            /** @var CallbackQuery $callbackQuery */
            $callbackQuery = $update->get('callback_query');

            Log::info('$callbackQuery', [$callbackQuery]);

            if (strpos($message->text, '/') === 0) {
                $command = explode(' ', $message->text);
                $command = str_replace('/', '', $command[0]);

                $commands = $this->telegram->getCommands();

                if (array_key_exists($command, $commands)) {
                    Telegram::processCommand($this->update);
                }
            }
            if (filled($callbackQuery)) {
                $this->processCallbackQuery($callbackQuery);
            } elseif (filled($message)) {
                $this->processMessage($message);
            }
        } catch (Exception $exception) {
            $this->error($exception);
        }

        return null;
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
        /** @var PhotoSize $photos */
        $photos = $message->photo;
        /** @var Document $document */
        $document = $message->document;
        /** @var string $caption */
        $caption = $message->caption;

        \Log::info('reply', [$reply]);
        \Log::info('photos', [$photos]);
        \Log::info('document', [$document]);

        if (filled($reply) && $reply->from->isBot && $reply->text === NewOpportunityCommand::TEXT) {
            $opportunity = new Opportunity();
            if (blank($message->text) && blank($caption)) {
                throw new Exception('Envie um texto para a vaga, ou o nome da vaga na legenda da imagem/documento.');
            }
            $text = $message->text ?? $caption;
            $opportunity->title = substr($text, 0, 100);
            $opportunity->description = $text;
            $opportunity->status = Opportunity::STATUS_INACTIVE;

            if (filled($photos)) {
                foreach ($photos as $photo) {
                    \Log::info('$photo', [$photo]);
                    $opportunity->addFile($photo);
                }
            }

            Log::info('$hoos', [optional($opportunity->getFilesList())->toJson()]);

            $opportunity->save();

            $this->sendOpportunityToApproval($opportunity, $message);
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
     * @param Message $message
     * @throws TelegramSDKException
     */
    private function sendOpportunityToApproval(Opportunity $opportunity, Message $message): void
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

        $fwdMessage = $this->telegram->forwardMessage([
            'chat_id' => env('TELEGRAM_OWNER_ID'),
            'from_chat_id' => $message->chat->id,
            'message_id' => $message->messageId
        ]);

        $this->telegram->sendMessage([
            'parse_mode' => 'Markdown',
            'chat_id' => env('TELEGRAM_OWNER_ID'),
            'text' => 'Aprovar?',
            'reply_markup' => $keyboard,
            'reply_to_message_id' => $fwdMessage->messageId
        ]);
    }

    private function error(Exception $exception): void
    {
        $this->telegram->replyWithMessage([
            'parse_mode' => 'Markdown',
            'text' => $exception->getMessage()
        ]);
    }
}
