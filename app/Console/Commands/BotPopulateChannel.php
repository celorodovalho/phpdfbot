<?php

namespace App\Console\Commands;

use App\Helpers\BotHelper;
use App\Helpers\ExtractorHelper;
use App\Helpers\SanitizerHelper;
use App\Models\Notification;
use App\Models\Opportunity;
use App\Services\Collectors\ComoQueTaLaMessages;
use App\Services\Collectors\GitHubMessages;
use App\Services\Collectors\GMailMessages;
use App\Transformers\FormattedOpportunityTransformer;
use Dacastro4\LaravelGmail\Exceptions\AuthException;
use Dacastro4\LaravelGmail\Services\Message\Mail;
use Exception;
use GrahamCampbell\Markdown\Facades\Markdown;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Spatie\Emoji\Emoji;
use Telegram\Bot\BotsManager;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\Keyboard\Keyboard;

/**
 * Class BotPopulateChannel
 */
class BotPopulateChannel extends AbstractCommand
{

    /**
     * Commands
     */
    public const TYPE_NOTIFY = 'notify';
    public const TYPE_PROCESS = 'process';
    public const TYPE_SEND = 'send';
    public const TYPE_APPROVAL = 'approval';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bot:populate:channel {type} {opportunity?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to populate the channel with new content';

    /**
     * The name of bot of this command
     *
     * @var string
     */
    protected $botName = 'phpdfbot';

    /** @var array */
    protected $channels;

    /** @var string */
    protected $appUrl;

    /** @var array */
    protected $groups;

    /** @var array */
    protected $mailing;

    /** @var string */
    protected $admin;

    /** @var GMailMessages */
    private $gMailMessages;

    /** @var GitHubMessages */
    private $gitHubMessages;

    /** @var ComoQueTaLaMessages */
    private $comoQueTaLaMessages;

    /**
     * BotPopulateChannel constructor.
     * @param BotsManager $botsManager
     * @param GMailMessages $gMailMessages
     * @param GitHubMessages $gitHubMessages
     * @param ComoQueTaLaMessages $comoQueTaLaMessages
     */
    public function __construct(
        BotsManager $botsManager,
        GMailMessages $gMailMessages,
        GitHubMessages $gitHubMessages,
        ComoQueTaLaMessages $comoQueTaLaMessages
    ) {
        parent::__construct($botsManager);
        $this->gMailMessages = $gMailMessages;
        $this->gitHubMessages = $gitHubMessages;
        $this->comoQueTaLaMessages = $comoQueTaLaMessages;
    }

    /**
     * Execute the console command.
     *
     * @throws TelegramSDKException
     * @throws AuthException
     */
    public function handle(): void
    {
        $this->channels = Config::get('telegram.channels');
        $this->appUrl = env('APP_URL');
        $this->groups = Config::get('telegram.groups');
        $this->mailing = Config::get('constants.mailing');
        $this->admin = Config::get('telegram.admin');

        switch ($this->argument('type')) {
            case self::TYPE_PROCESS:
                $this->processOpportunities();
                break;
            case self::TYPE_NOTIFY:
                $this->notifyGroup();
                break;
            case self::TYPE_SEND:
                $this->sendOpportunityToChannels($this->argument('opportunity'));
                break;
            case self::TYPE_APPROVAL:
                $this->sendTelegramOpportunityToApproval($this->argument('opportunity'));
                break;
            default:
                // Do something
                break;
        }
    }

    /**
     * Retrieve the Opportunities objects and send them to approval
     *
     * @throws AuthException
     * @throws TelegramSDKException
     */
    protected function processOpportunities(): void
    {
        $opportunities = $this->collectOpportunities();
        foreach ($opportunities as $opportunity) {
            $this->sendOpportunityToApproval($opportunity);
        }
    }

    /**
     * Get messages from source and create objects from them
     *
     * @return Collection
     * @throws AuthException
     */
    protected function collectOpportunities(): Collection
    {
        $opportunities = $this->gMailMessages->collectOpportunities();
        $opportunities->merge($this->gitHubMessages->collectOpportunities());
        $opportunities->merge($this->comoQueTaLaMessages->collectOpportunities());

        $opportunities->map(function (Opportunity $opportunity) {
            $opportunity->save();
        });

        return $opportunities;
    }

    /**
     * Prepare and send the opportunity to the channel, then update the TelegramId in database
     *
     * @param int $opportunityId
     * @throws TelegramSDKException
     * @todo Move to communicate-telegram class
     */
    protected function sendOpportunityToChannels(int $opportunityId): void
    {
        /** @var Opportunity $opportunity */
        $opportunity = Opportunity::find($opportunityId);

        foreach ($this->channels as $channel => $config) {
            if (blank($config['tags']) || ExtractorHelper::hasTags($config['tags'], $opportunity->getText())) {
                $messageSentIds = $this->sendOpportunity($opportunity, $channel);
            }
            $messageSentId = reset($messageSentIds);
            if ($messageSentId && $config['main']) {
                $opportunity->telegram_id = $messageSentId;
                $opportunity->status = Opportunity::STATUS_ACTIVE;
                $opportunity->save();

                $this->notifyUser($opportunity);
            }
        }

        foreach ($this->mailing as $mail => $config) {
            if (
                $config &&
                !Str::contains($opportunity->origin, $mail) &&
                (blank($config['tags']) || ExtractorHelper::hasTags($config['tags'], $opportunity->getText()))
            ) {
                $this->mailOpportunity($opportunity, $mail);
            }
        }
    }

    /**
     * Notify the send user, that opportunity was published on channel
     *
     * @param Opportunity $opportunity
     * @throws TelegramSDKException
     * @todo Move to communicate-telegram class
     */
    protected function notifyUser(Opportunity $opportunity): void
    {
        if ($opportunity->telegram_user_id) {
            $link = "https://t.me/VagasBrasil_TI/{$opportunity->telegram_id}";

            $this->telegram->sendMessage([
                'chat_id' => $opportunity->telegram_user_id,
                'parse_mode' => 'Markdown',
                'text' => sprintf(
                    "A vaga abaixo foi publicada:\n\n%s",
                    SanitizerHelper::sanitizeSubject(SanitizerHelper::removeBrackets($opportunity->title))
                ),
                'reply_markup' => Keyboard::make()
                    ->inline()
                    ->row(
                        Keyboard::inlineButton([
                            'text' => 'Conferir no canal ' . Emoji::rightArrow(),
                            'url' => $link,
                        ])
                    ),
            ]);
        }
    }

    /**
     * @param Opportunity $opportunity
     * @param int $chatId
     * @param array $options
     * @return array
     * @throws TelegramSDKException
     * @todo Move to communicate-telegram class
     */
    protected function sendOpportunity(Opportunity $opportunity, $chatId, array $options = []): array
    {
        $messageTexts = fractal()->item($opportunity)->transformWith(new FormattedOpportunityTransformer())->toArray();
        $messageSentIds = [];
        $lastSentID = null;
        $messageSent = null;

        if ($this->admin === $chatId && Str::contains($opportunity->origin, [$this->botName])) {
            $userNames = explode('|', $opportunity->origin);
            $userName = end($userNames);
            if (!blank($userName)) {
                if (Str::contains($userName, ' ')) {
                    $text = "[$userName](tg://user?id={$opportunity->telegram_user_id}) enviou:";
                } else {
                    $text = '@' . $userName . ' enviou:';
                }
                $this->telegram->sendMessage([
                    'chat_id' => $chatId,
                    'parse_mode' => 'Markdown',
                    'text' => $text
                ]);
            }
        }

        foreach ($messageTexts['data']['body'] as $messageText) {
            $sendMsg = array_merge([
                'chat_id' => $chatId,
                'parse_mode' => 'Markdown',
                'text' => $messageText,
            ], $options);

            if ($lastSentID) {
                $sendMsg['reply_to_message_id'] = $lastSentID;
            }

            try {
                $messageSent = $this->telegram->sendMessage($sendMsg);
                $messageSentIds[] = $messageSent->messageId;
            } catch (Exception $exception) {
                if ($exception->getCode() === 400) {
                    $sendMsg['text'] = SanitizerHelper::removeMarkdown($messageText);
                    unset($sendMsg['Markdown']);
                    $messageSent = $this->telegram->sendMessage($sendMsg);
                    $messageSentIds[] = $messageSent->messageId;
                } else {
                    throw $exception;
                }
            }

            if ($messageSent) {
                $lastSentID = $messageSent->messageId;
            }
        }
        return $messageSentIds;
    }

    /**
     * @param Opportunity $opportunity
     * @param string $email
     * @param array $options
     * @throws Exception
     * @todo Move to communicate-email class
     */
    protected function mailOpportunity(Opportunity $opportunity, string $email, array $options = [])
    {
        $messageTexts = fractal()->item($opportunity)->transformWith(new FormattedOpportunityTransformer(true))->toArray();
        $messageTexts = Markdown::convertToHtml($messageTexts['data']['body']);
        $messageTexts = nl2br($messageTexts);

        $mail = new Mail();
        $mail->to($email)
            ->message($messageTexts)
            ->subject($opportunity->title)
            ->send();
    }

    /**
     * Notifies the group with the latest opportunities in channel
     * Get all the unnoticed opportunities, build a keyboard with the links, sends to the group, update the opportunity
     * and remove the previous notifications from group
     * @todo Move to communicate-telegram class
     */
    protected function notifyGroup()
    {
        $opportunities = Opportunity::whereNotNull('telegram_id');
        $opportunitiesArr = $opportunities->get();
        if ($opportunitiesArr->isNotEmpty()) {
            $lastNotifications = Notification::all();

            $firstOpportunityId = null;

            /** @var Collection $listOpportunities */
            $listOpportunities = $opportunitiesArr->map(function ($opportunity) use (&$firstOpportunityId) {
                $firstOpportunityId = $firstOpportunityId ?? $opportunity->telegram_id;
                return sprintf(
                    '➩ [%s](%s)',
                    SanitizerHelper::sanitizeSubject(SanitizerHelper::removeBrackets($opportunity->title)),
                    'https://t.me/VagasBrasil_TI/' . $opportunity->telegram_id
                );
            });

            $keyboard = Keyboard::make()->inline();
            $keyboard->row(Keyboard::inlineButton([
                'text' => 'Ver vagas',
                'url' => 'https://t.me/VagasBrasil_TI/' . $firstOpportunityId
            ]));

            $mainGroup = $this->admin;
            foreach ($this->groups as $group => $config) {
                if ($config['main']) {
                    $mainGroup = $group;
                }
            }

            $text = sprintf(
                "%s\n\n[%s](%s)",
                "Há novas vagas no canal!\nConfira: " .
                SanitizerHelper::escapeMarkdown(implode(
                    ' | ',
                    array_merge(array_keys($this->channels), array_keys($this->groups)))
                ),
                '🄿🄷🄿🄳🄵',
                str_replace('/index.php', '', $this->appUrl) . '/img/phpdf.webp'
            );

            $listOpportunities->prepend($text);
            $length = 0;
            $opportunitiesText = collect();
            $listOpportunities->map(function ($opportunity) use ($mainGroup, $keyboard, &$length, &$opportunitiesText) {
                $length += strlen($opportunity);
                if ($length >= BotHelper::TELEGRAM_LIMIT) {
                    $notificationMessage = [
                        'chat_id' => $mainGroup,
                        'parse_mode' => 'Markdown',
                        'reply_markup' => $keyboard,
                        'text' => $opportunitiesText->implode("\n")
                    ];

                    $message = $this->telegram->sendMessage($notificationMessage);

                    $notification = new Notification();
                    $notification->telegram_id = $message->messageId;
                    $notification->body = json_encode($notificationMessage);
                    $notification->save();

                    $opportunitiesText = collect();
                    $length = 0;
                }
                $opportunitiesText->add($opportunity);
            });

            foreach ($lastNotifications as $lastNotification) {
                try {
                    $this->telegram->deleteMessage([
                        'chat_id' => $mainGroup,
                        'message_id' => $lastNotification->telegram_id
                    ]);
                } catch (Exception $exception) {
                    $this->error(implode(': ', ['ERRO_AO_DELETAR_NOTIFICACAO', $exception->getMessage()]));
                }
                $lastNotification->delete();
            }
            $opportunities->delete();
        }
        $this->info('The group was notified!');
    }

    /**
     * Send opportunity to approval
     *
     * @param Opportunity $opportunity
     * @throws TelegramSDKException
     * @todo Move to communicate-telegram class
     */
    protected function sendOpportunityToApproval(Opportunity $opportunity): void
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

        $messageToSend = [
            'reply_markup' => $keyboard,
        ];

        /** @var Opportunity $opportunity */
        $this->sendOpportunity($opportunity, $this->admin, $messageToSend);
    }

    /**
     * @param $opportunityId
     * @throws TelegramSDKException
     * @todo Move to communicate-telegram class
     */
    protected function sendTelegramOpportunityToApproval($opportunityId)
    {
        $opportunity = Opportunity::find($opportunityId);
        $this->sendOpportunityToApproval($opportunity);
    }
}
