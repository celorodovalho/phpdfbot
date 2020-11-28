<?php

namespace App\Services;

use App\Contracts\Repositories\OpportunityRepository;
use App\Contracts\Repositories\UserRepository;
use App\Enums\Arguments;
use App\Enums\Callbacks;
use App\Exceptions\TelegramOpportunityException;
use App\Helpers\BotHelper;
use App\Helpers\ExtractorHelper;
use App\Helpers\Helper;
use App\Helpers\SanitizerHelper;
use App\Models\Group;
use App\Models\Opportunity;
use App\Validators\CollectedOpportunityValidator;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Prettus\Repository\Contracts\RepositoryInterface;
use Prettus\Validator\Contracts\ValidatorInterface;
use Prettus\Validator\Exceptions\ValidatorException;
use Spatie\Emoji\Emoji;
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
    private string $botName;

    /** @var Api */
    private Api $telegram;

    /** @var Update */
    private Update $update;

    /** @var OpportunityRepository */
    private OpportunityRepository $repository;

    /** @var UserRepository */
    private UserRepository $userRepository;

    /** @var CollectedOpportunityValidator */
    private CollectedOpportunityValidator $validator;

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
        } catch (ValidatorException $exception) {
            $errors = $exception->getMessageBag()->all();
            Log::info('VALIDATION_ERRORS', $errors);
            $this->sendMessage(sprintf(
                "Ao menos uma das validações abaixo precisa ser observada:\n%s\n%s %s",
                Emoji::downRightArrow(),
                Emoji::crossMark(),
                implode("\n" . Emoji::crossMark() . ' ', $errors)
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

        /** @var Opportunity $opportunity */
        $opportunity = null;
        if (is_numeric($data[1])) {
            $opportunity = $this->repository->find($data[1]);
            $opportunity->approver = $callbackQuery->from->toJson();
            $opportunity->update();
        }

        switch ($data[0]) {
            case Callbacks::APPROVE:
                if ($opportunity) {
                    Artisan::call(
                        'process:messages',
                        [
                            '--type' => Arguments::SEND,
                            '--opportunity' => $opportunity->id,
                        ]
                    );
                    $this->sendMessage(Artisan::output());
                }
                break;
            case Callbacks::REMOVE:
                if ($opportunity) {
                    $opportunity->delete();
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

        if ($opportunity && in_array($data[0], [Callbacks::APPROVE, Callbacks::REMOVE], true)) {
            /** @var Group $mainGroup */
            $mainGroup = Group::where('main', true)->first();

            /** @var DatabaseNotification|Notification $notification */
            $notification = DatabaseNotification::where('model_id', $opportunity->id)
                ->where('notifiable_id', $mainGroup->id)
                ->orderBy('created_at', 'desc')
                ->first();

            $telegramId = null;
            if (filled($notification) && $notification->data) {
                $telegramId = reset($notification->data['telegram_ids']);
                Log::info('NOTIFICATION_EXISTS', [$notification, $telegramId]);
            }

            $approver = $callbackQuery->from->id;
            if ($callbackQuery->from->has('username')) {
                $approver = '@'.$callbackQuery->from->username;
            } elseif ($callbackQuery->from->has('firstName')) {
                $approver = sprintf(
                    '[%s](tg://user?id=%s)',
                    SanitizerHelper::escapeMarkdown($callbackQuery->from->firstName),
                    $callbackQuery->from->id
                );
            }

            $notifyText = sprintf(
                'Mensagem "%s" %s por %s',
                $data[0] === Callbacks::APPROVE && $telegramId ?
                    sprintf('https://t.me/%s/%s', $mainGroup->title, $telegramId) :
                    route('opportunity.rejected', ['opportunity' => $opportunity->id]),
                $data[0] === Callbacks::APPROVE ? 'aprovada' : 'rejeitada',
                $approver
            );

            /** @var Group $adminGroup */
            $adminGroup = Group::where('admin', true)->first();

            $this->telegram->editMessageText([
                'chat_id' => $adminGroup->title,
                'message_id' => $callbackQuery->message->messageId,
                'text' => $notifyText,
                'reply_markup' => '',
            ]);
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

        $text = $message->text ?: $caption;

        if ($message->chat->type === BotHelper::TG_CHAT_TYPE_PRIVATE) {
            $telegramUser = $message->from;
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
        }

        /** @var bool $isRealUserPvtMsg Check if the message come from a real user in a Private chat */
        $isRealUserPvtMsg = $message->from instanceof User
            && !$message->from->isBot
            && $message->chat instanceof Chat
            && $message->chat->type === BotHelper::TG_CHAT_TYPE_PRIVATE;

        /** Check if is a private message and not a command */
        if ($isRealUserPvtMsg && !in_array($message->text, $this->telegram->getCommands(), true)) {
            $files = [];
            if (filled($photos)) {
                $files[] = $photos->filter(static function ($photo) {
                    return ($photo['width'] + $photo['height']) > 1000;
                })->first();
            }
            if (filled($document)) {
                $files[] = $document->toArray();
            }

            $files = BotHelper::getFiles($files);

            if (filled($files)) {
                $files = array_values($files);

                foreach ($files as $file) {
                    if ($annotation = Helper::getImageAnnotation($file)) {
                        $text .= "\nTranscrição das imagens:\n" . $annotation;
                    }
                }
            }

            $urls = [];
            $emails = [];
            if ($text) {
                $urls = ExtractorHelper::extractUrls($text);
                $emails = ExtractorHelper::extractEmails($text);
            } else {
                $text = '';
            }

            $sender = null;
            $userName = null;
            $from = null;
            if ($message->has('forward_from')) {
                $from = $message->forwardFrom;
            } elseif ($message->has('from')) {
                $from = $message->from;
                $sender = $from->username ?? $from->firstName;
            }

            if ($from) {
                if ($from->has('username')) {
                    $urls[] = sprintf(
                        'https://t.me/%s',
                        SanitizerHelper::escapeMarkdown($from->username)
                    );
                    $userName = $from->username;
                } elseif ($from->has('first_name')) {
                    $urls[] = sprintf(
                        '[%s](tg://user?id=%s)',
                        SanitizerHelper::escapeMarkdown($from->firstName),
                        $from->id
                    );
                    $userName = implode(' ', [$from->firstName, $from->lastName]);
                }
            }

            $title = str_replace("\n", ' ', $text);

            $messageOpportunity = [
                Opportunity::TITLE => SanitizerHelper::sanitizeSubject(Str::limit($title, 50)),
                Opportunity::DESCRIPTION => SanitizerHelper::sanitizeBody($text),
                Opportunity::ORIGINAL => $text,
                Opportunity::FILES => filled($files) ? $files : null,
                Opportunity::URLS => new Collection($urls),
                Opportunity::ORIGIN => new Collection([
                    'bot' => $this->botName,
                    'name' => $userName,
                    'sender' => $sender
                ]),
                Opportunity::LOCATION => implode(' / ', ExtractorHelper::extractLocation($text)),
                Opportunity::TAGS => ExtractorHelper::extractTags($text),
                Opportunity::EMAILS => array_map(fn($email) => SanitizerHelper::replaceMarkdown($email), $emails),
                Opportunity::POSITION => implode(', ', ExtractorHelper::extractPosition($text)),
                Opportunity::SALARY => null,
                Opportunity::COMPANY => null,
            ];

            $this->validator
                ->with($messageOpportunity)
                ->passesOrFail(ValidatorInterface::RULE_CREATE);

            /** @var \Illuminate\Database\Eloquent\Collection $opportunities */
            $opportunities = $this->repository->scopeQuery(static function ($query) {
                return $query->withTrashed();
            })->findWhere([
                Opportunity::TITLE => $messageOpportunity[Opportunity::TITLE],
                Opportunity::DESCRIPTION => $messageOpportunity[Opportunity::DESCRIPTION],
            ]);

            if ($opportunities->isEmpty()) {
                /** @var Opportunity $opportunity */
                $opportunity = $this->repository->make($messageOpportunity);
                $opportunity->update([
                    'telegram_user_id' => $message->from->id
                ]);
            } else {
                $opportunity = $opportunities->first();
                $opportunity->update($messageOpportunity);
                $opportunity->restore();
            }

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

    /**
     * @param $groupId
     * @param $messageId
     * @throws TelegramResponseException
     * @throws TelegramSDKException
     */
    private function removeMessage($groupId, $messageId)
    {
        try {
            $this->telegram->deleteMessage([
                'chat_id' => $groupId,
                'message_id' => $messageId
            ]);
        } catch (TelegramResponseException $exception) {
            /**
             * A message can only be deleted if it was sent less than 48 hours ago.
             * Bots can delete outgoing messages in groups and supergroups.
             * Bots granted can_post_messages permissions can delete outgoing messages in channels.
             * If the bot is an administrator of a group, it can delete any message there.
             * If the bot has can_delete_messages permission in a supergroup or a channel,
             * it can delete any message there. Returns True on success.
             */
            if ($exception->getCode() === 400) {
                try {
                    /** @var \danog\MadelineProto\API $madeline */
                    $madeline = (new MadelineProtoService());
                    $madeline->async(true);
                    $madeline->loop(static function () use ($madeline, $groupId, $messageId) {
                        yield $madeline->start();
                        $messages = $madeline->channels->deleteMessages(['channel' => $groupId, 'id' => [$messageId], ]);
                        yield $madeline->stop();
                        return $messages;
                    });
                    $madeline->stop();
                } catch (Exception $madelineException) {
                    Log::info(TelegramResponseException::class, [
                        'TELEGRAM_EXCEPTION' => $exception,
                        'MADELINE_EXCEPTION' => $madelineException,
                        'CALLBACK_MESSAGE' => $messageId
                    ]);
                }
                return;
            }
            throw $exception;
        }
    }
}
