<?php

namespace App\Services;

use App\Contracts\Repositories\OpportunityRepository;
use App\Contracts\Repositories\UserRepository;
use App\Enums\Arguments;
use App\Enums\Callbacks;
use App\Exceptions\TelegramOpportunityException;
use App\Helpers\BotHelper;
use App\Helpers\ExtractorHelper;
use App\Helpers\SanitizerHelper;
use App\Models\Group;
use App\Models\Opportunity;
use App\Validators\CollectedOpportunityValidator;
use Exception;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Prettus\Repository\Contracts\RepositoryInterface;
use Prettus\Validator\Contracts\ValidatorInterface;
use Prettus\Validator\Exceptions\ValidatorException;
use Telegram\Bot\Api;
use Telegram\Bot\BotsManager;
use Telegram\Bot\Exceptions\TelegramResponseException;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\Objects\CallbackQuery;
use Telegram\Bot\Objects\Chat;
use Telegram\Bot\Objects\Document;
use Telegram\Bot\Objects\Message;
use Telegram\Bot\Objects\PhotoSize;
use Telegram\Bot\Objects\Update;
use Telegram\Bot\Objects\User as TelegramUser;
use Telegram\Bot\Objects\User;

/**
 * Class CommandsHandler
 *
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
class CommandsHandler
{

    /** @var string */
    private $botName;

    /** @var Api */
    private $telegram;

    /** @var Update */
    private $update;

    /** @var OpportunityRepository */
    private $repository;

    /** @var UserRepository */
    private $userRepository;

    /** @var CollectedOpportunityValidator */
    private $validator;

    /**
     * CommandsHandler constructor.
     *
     * @param BotsManager                               $botsManager
     * @param string                                    $botName
     * @param OpportunityRepository|RepositoryInterface $repository
     * @param UserRepository                            $userRepository
     *
     * @param CollectedOpportunityValidator             $validator
     *
     * @throws TelegramSDKException
     */
    public function __construct(
        BotsManager $botsManager,
        string $botName,
        OpportunityRepository $repository,
        UserRepository $userRepository,
        CollectedOpportunityValidator $validator
    ) {
        $this->botName = $botName;
        $this->telegram = $botsManager->bot($botName);
        $this->repository = $repository;
        $this->userRepository = $userRepository;
        $this->validator = $validator;
    }

    /**
     * Process the update coming from bot interface
     *
     * @param Update $update
     *
     * @return mixed
     * @throws TelegramSDKException
     * @throws Exception
     */
    public function processUpdate(Update $update): void
    {
        $this->update = $update;
        try {
            /** @var Message $message */
            $message = $update->getMessage();
            /** @var CallbackQuery $callbackQuery */
            $callbackQuery = $update->get('callback_query');

            if (strpos($message->text, '/') === 0) {
                $command = explode(' ', $message->text);
                $this->processCommand($command[0]);
            }
            if (filled($callbackQuery)) {
                $this->processCallbackQuery($callbackQuery);
            } elseif (filled($message) && strpos($message->text, '/') !== 0) {
                $this->processMessage($message);
            }
        } catch (TelegramOpportunityException $exception) {
            $this->sendMessage($exception->getMessage());
        } catch (ValidatorException $exception) {
            $errors = $exception->getMessageBag()->toArray();
            Log::info('VALIDATION_ERRORS', $errors);
            $this->sendMessage(sprintf(
                "Ao menos uma das validações abaixo precisa ser observada: \n\n%s",
                implode("\n", $errors)
            ));
        }
    }

    /**
     * Process the Callback query coming from bot interface
     *
     * @param CallbackQuery $callbackQuery
     *
     * @throws TelegramSDKException
     */
    private function processCallbackQuery(CallbackQuery $callbackQuery): void
    {
        $data = $callbackQuery->get('data');
        $data = explode(' ', $data);
        $opportunity = null;
        if (is_numeric($data[1])) {
            $opportunity = $this->repository->find($data[1]);
        }
        switch ($data[0]) {
            case Callbacks::APPROVE:
                if ($opportunity) {
                    Artisan::call(
                        'process:messages',
                        [
                            '--type' => Arguments::SEND,
                            '--opportunity' => $opportunity->id
                        ]
                    );
                    $this->sendMessage(Artisan::output());
                }
                break;
            case Callbacks::REMOVE:
                if ($opportunity) {
                    //$opportunity->delete();
                    $this->sendMessage(
                        sprintf(
                            'Mensagem rejeitada: %s',
                            url("opportunity/{$opportunity->id}")
                        ),
                        Config::get('constants.owner')
                    );
                }
                break;
            case Callbacks::OPTIONS:
                if (in_array($data[1], [Arguments::PROCESS, Arguments::NOTIFY], true)) {
                    Artisan::call('process:messages', ['--type' => $data[1]]);
                    $this->sendMessage(Artisan::output());
                }
                break;
            default:
                Log::info('SWITCH_DEFAULT', $data);
                $this->processCommand($data[0]);
                break;
        }
        try {
            /** @todo remover isso */
            $group = Group::where('admin', true)->first();
            $this->telegram->deleteMessage([
                'chat_id' => $group->name,
                'message_id' => $callbackQuery->message->messageId
            ]);
        } catch (TelegramResponseException $exception) {
            Log::info(TelegramResponseException::class, [
                'DATA' => $data,
                'EXCEPTION' => $exception,
                'CALLBACK_MESSAGE' => $callbackQuery->message
            ]);
            /**
             * A message can only be deleted if it was sent less than 48 hours ago.
             * Bots can delete outgoing messages in groups and supergroups.
             * Bots granted can_post_messages permissions can delete outgoing messages in channels.
             * If the bot is an administrator of a group, it can delete any message there.
             * If the bot has can_delete_messages permission in a supergroup or a channel,
             * it can delete any message there. Returns True on success.
             */
            if ($exception->getCode() !== 400) {
                throw $exception;
            }
        }
    }

    /**
     * Process the messages coming from bot interface
     *
     * @param Message $message
     *
     * @throws Exception|TelegramOpportunityException
     */
    private function processMessage(Message $message): void
    {
        /** @var Message $reply */
        $reply = $message->getReplyToMessage();
        /** @var PhotoSize $photos */
        /** @var PhotoSize $photo */
        $photos = $message->photo;
        /** @var Document $document */
        $document = $message->document;
        /** @var string $caption */
        $caption = $message->caption;
        /** @var TelegramUser $newMembers */
        $newMembers = $message->newChatMembers;

        Log::info('PROCESS_MESSAGE', [
            'TEXT' => $message->text,
            'REPLY' => $reply,
            'PHOTOS' => $photos,
            'DOCUMENT' => $document,
            'CAPTION' => $caption,
            'NEW_MEMBERS' => $newMembers,
        ]);


        if ($message->chat->type === BotHelper::TG_CHAT_TYPE_PRIVATE) {
            $telegramUser = $message->from;
            $user = $this->userRepository->updateOrCreate(
                ['id' => $telegramUser->id,],
                [
                    'username' => $telegramUser->username,
                    'is_bot' => $telegramUser->isBot,
                    'first_name' => $telegramUser->firstName,
                    'last_name' => $telegramUser->lastName,
                    'language_code' => $telegramUser->languageCode,
                ]
            );
            Log::info('USER_CREATED_processMessage', [$user]);
        }

        /** @var bool $isRealUserPvtMsg Check if the message come from a real user in a Private chat */
        $isRealUserPvtMsg = $message->from instanceof User
            && !$message->from->isBot
            && $message->chat instanceof Chat
            && $message->chat->type === BotHelper::TG_CHAT_TYPE_PRIVATE;

        /** Check if is a private message and not a command */
        if ($isRealUserPvtMsg && !in_array($message->text, $this->telegram->getCommands(), true)) {
            $text = $message->text ?? $caption;

            $urls = ExtractorHelper::extractUrls($text);

            $userName = null;
            if (property_exists('from', $message) && blank($urls)) {
                if (property_exists('username', $message->from)) {
                    $urls[] = sprintf(
                        'https://t.me/%s',
                        SanitizerHelper::escapeMarkdown($message->from->username)
                    );
                    $userName = $message->from->username;
                } elseif (property_exists('firstName', $message->from)) {
                    $urls[] = sprintf(
                        '[%s](tg://user?id=%s)',
                        SanitizerHelper::escapeMarkdown($message->from->firstName),
                        $message->from->id
                    );
                    $userName = $message->from->firstName . ' ' . $message->from->lastName;
                }
            }

            $emails = ExtractorHelper::extractEmail($text);

            $files = [];
            if (filled($photos)) {
                $files = $photos->filter(static function ($photo) {
                    return ($photo['width'] + $photo['height']) > 1000;
                })->toArray();
            }
            if (filled($document)) {
                $files[] = $document->first();
            }

            $files = BotHelper::getFiles($files);

            $title = str_replace("\n", ' ', $text);

            $messageOpportunity = [
                Opportunity::TITLE => SanitizerHelper::sanitizeSubject(Str::limit($title, 50)),
                Opportunity::DESCRIPTION => SanitizerHelper::sanitizeBody($text),
                Opportunity::ORIGINAL => $text,
                Opportunity::FILES => $files,
                Opportunity::URL => implode(', ', $urls),
                Opportunity::ORIGIN => implode('|', [$this->botName, $userName]),
                Opportunity::LOCATION => implode(' / ', ExtractorHelper::extractLocation($text)),
                Opportunity::TAGS => ExtractorHelper::extractTags($text),
                Opportunity::EMAILS => SanitizerHelper::replaceMarkdown(implode(', ', $emails)),
                Opportunity::POSITION => null,
                Opportunity::SALARY => null,
                Opportunity::COMPANY => null,
            ];

            $this->validator
                ->with($messageOpportunity)
                ->passesOrFail(ValidatorInterface::RULE_CREATE);

            $opportunity = $this->repository->make($messageOpportunity);

            $opportunity->status = Opportunity::STATUS_INACTIVE;
            $opportunity->telegram_user_id = $message->from->id;

            $opportunity->save();

            $this->sendOpportunityToApproval($opportunity);
        }

        // TODO: Think more about this
        if ($message->chat->type === BotHelper::TG_CHAT_TYPE_PRIVATE
            && filled($newMembers) && $newMembers->isNotEmpty()) {
            $newMembers->each(function (TelegramUser $telegramUser) {
                $this->userRepository->updateOrCreate(
                    ['id' => $telegramUser->id,],
                    [
                        'username' => $telegramUser->username,
                        'is_bot' => $telegramUser->isBot,
                        'first_name' => $telegramUser->firstName,
                        'last_name' => $telegramUser->lastName,
                        'language_code' => $telegramUser->languageCode,
                    ]
                );
            });
        }
    }

    /**
     * Send opportunity to approval
     *
     * @param Opportunity $opportunity
     *
     * @throws TelegramSDKException
     */
    private function sendOpportunityToApproval(Opportunity $opportunity): void
    {
        Artisan::call(
            'process:messages',
            [
                '--type' => Arguments::APPROVAL,
                '--opportunity' => $opportunity->id,
            ]
        );
        $this->sendMessage('A vaga foi enviada para aprovação. Você receberá uma confirmação assim que for aprovada!');
    }

    /**
     * Process the command
     *
     * @param string $command
     * @param bool   $triggerProcess
     */
    private function processCommand(string $command, bool $triggerProcess = true): void
    {
        $triggerProcess = $triggerProcess ? 'processCommand' : 'triggerCommand';

        $command = str_replace('/', '', $command);

        $commands = $this->telegram->getCommands();

        if (array_key_exists($command, $commands)) {
            $this->telegram->{$triggerProcess}($this->update);
        }
    }

    /**
     * @param null|string $message
     * @param null|int    $chatId
     *
     * @throws TelegramSDKException
     */
    private function sendMessage($message, $chatId = null): void
    {
        if (filled($message)) {
            $param = [
                'chat_id' => $chatId ?? $this->update->getChat()->id,
                'text' => $message
            ];
            if (null === $chatId) {
                $param['reply_to_message_id'] = $this->update->getMessage()->messageId;
            }

            $this->telegram->sendMessage($param);
        }
    }
}
