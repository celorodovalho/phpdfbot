<?php

namespace App\Console\Commands;

use App\Helpers\HashTagHelper;
use App\Helpers\SanitizerHelper;
use App\Models\Notification;
use App\Models\Opportunity;
use App\Services\Collect\GMailMessages;
use App\Transformers\FormattedOpportunityTransformer;
use Carbon\Carbon;
use Dacastro4\LaravelGmail\Exceptions\AuthException;
use Dacastro4\LaravelGmail\Services\Message\Mail;
use DateTime;
use DateTimeZone;
use Exception;
use Goutte\Client;
use GrahamCampbell\GitHub\GitHubManager;
use GrahamCampbell\Markdown\Facades\Markdown;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;
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
    public const COMMAND_NOTIFY = 'notify';
    public const COMMAND_PROCESS = 'process';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bot:populate:channel {process} {opportunity?} {message?} {chat?}';

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
    private $mailMessages;

    public function __construct(BotsManager $botsManager, GitHubManager $gitHubManager, GMailMessages $mailMessages)
    {
        parent::__construct($botsManager, $gitHubManager);
        $this->mailMessages = $mailMessages;
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
        $this->mailing = Config::get('telegram.mailing');
        $this->admin = Config::get('telegram.admin');

        switch ($this->argument('process')) {
            case self::COMMAND_PROCESS:
                $this->processOpportunities();
                break;
            case self::COMMAND_NOTIFY:
                $this->notifyGroup();
                break;
            case 'send':
                $this->sendOpportunityToChannels($this->argument('opportunity'));
                break;
            case 'approval':
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
     */
    protected function processOpportunities(): void
    {
        $opportunities = $this->collectOpportunities();
        foreach ($opportunities as $opportunity) {
            $this->sendOpportunityToApproval($opportunity->id);
        }
    }

    /**
     * @param array|Opportunity $rawOpportunity
     * @return Opportunity
     */
    protected function createOrUpdateOpportunity($rawOpportunity)
    {
        if (is_array($rawOpportunity)) {
            $opportunity = new Opportunity();
        } else {
            $opportunity = $rawOpportunity;
            $rawOpportunity = $rawOpportunity->toArray();
        }
        if (array_key_exists(Opportunity::COMPANY, $rawOpportunity)) {
            $opportunity->company = $rawOpportunity[Opportunity::COMPANY];
        }
        if (array_key_exists(Opportunity::LOCATION, $rawOpportunity)) {
            $opportunity->location = $rawOpportunity[Opportunity::LOCATION];
        }
        if (array_key_exists(Opportunity::FILES, $rawOpportunity)) {
            $opportunity->files = collect($rawOpportunity[Opportunity::FILES]);
        }
        $description = SanitizerHelper::sanitizeBody($rawOpportunity[Opportunity::DESCRIPTION]);
        $opportunity->title = SanitizerHelper::sanitizeSubject($rawOpportunity[Opportunity::TITLE]);
        $opportunity->description = $description;
        $opportunity->url = $rawOpportunity[Opportunity::URL];
        $opportunity->origin = $rawOpportunity[Opportunity::ORIGIN];
        $opportunity->tags = HashTagHelper::extractTags($description . $rawOpportunity[Opportunity::TITLE]);
        $opportunity->save();
        return $opportunity;
    }

    /**
     * Get messages from source and create objects from them
     *
     * @return Collection
     * @throws AuthException
     */
    protected function collectOpportunities(): Collection
    {
        $opportunitiesRaw = $this->mailMessages->collectMessages();
        $opportunitiesRaw = array_merge(
            $opportunitiesRaw,
            $this->getMessagesFromGithub(),
            $this->getMessagesFromComoEQueTaLa(),
            $this->getMessagesFromQueroWorkar()
        );

        $opportunities = array_map(function ($rawOpportunity) {
            return $this->createOrUpdateOpportunity($rawOpportunity);
        }, $opportunitiesRaw);

        return collect($opportunities);
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
            if (blank($config['tags']) || HashTagHelper::hasTags($config['tags'], $opportunity->getText())) {
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
                !Str::contains($opportunity->origin, $mail) &&
                (blank($config['tags']) || HashTagHelper::hasTags($config['tags'], $opportunity->getText()))
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
                'text' => "Sua vaga '$link' foi publicada no canal @VagasBrasil_TI.",
            ]);
        }
    }

    /**
     * @param Opportunity $opportunity
     * @param int $chatId
     * @param array $options
     * @return array
     * @todo Move to communicate-telegram class
     */
    protected function sendOpportunity(Opportunity $opportunity, $chatId, array $options = []): array
    {
        $messageTexts = fractal()->item($opportunity)->transformWith(new FormattedOpportunityTransformer())->toArray();
        $messageSentIds = [];
        $lastSentID = null;
        $messageSent = null;
        foreach ($messageTexts['body'] as $messageText) {
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
                    try {
                        $sendMsg['text'] = SanitizerHelper::removeMarkdown($messageText);
                        unset($sendMsg['Markdown']);
                        $messageSent = $this->telegram->sendMessage($sendMsg);
                        $messageSentIds[] = $messageSent->messageId;
                    } catch (Exception $exception2) {
                        $this->error(implode(': ', ['FALHA_AO_ENVIAR_TEXTPLAIN', $chatId, json_encode($sendMsg)]));
                    }
                }
                $this->error(implode(': ', ['FALHA_AO_ENVIAR_MARKDOWN', $chatId, json_encode($sendMsg)]));
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
        $messageTexts = Markdown::convertToHtml($messageTexts);
        $messageTexts = nl2br($messageTexts);

        $mail = new Mail();
        $mail->to($email)
            ->message($messageTexts)
            ->subject($opportunity->title)
            ->send();
    }

    /**
     * Notifies the group with the latest opportunities in channel
     * Get all the unnotified opportunities, build a keyboard with the links, sends to the group, update the opportunity
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

            $channels = array_keys($this->channels);
            $groups = array_keys($this->groups);

            $text = sprintf(
                "%s\n\n[%s](%s)",
                "Há novas vagas no canal!\nConfira: " . SanitizerHelper::escapeMarkdown(implode(' | ', $channels)) . " | " . SanitizerHelper::escapeMarkdown(implode(' | ', $groups)),
                '🄿🄷🄿🄳🄵',
                str_replace('/index.php', '', $this->appUrl) . '/img/phpdf.webp'
            );

            $listOpportunities->prepend($text);
            $length = 0;
            $opportunitiesText = collect();
            $listOpportunities->map(function ($opportunity) use ($mainGroup, $keyboard, &$length, &$opportunitiesText) {
                $length += strlen($opportunity);
                if ($length >= 4096) {
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
     * Get the results from crawler process, merge they and send to the channel
     *
     * @return array
     * @todo Move to collect-github class
     */
    protected function getMessagesFromGithub(): array
    {
        $githubSources = Config::get('constants.gitHubSources');

        $opportunities = [];
        foreach ($githubSources as $username => $repo) {
            $opportunities[] = $this->fetchMessagesFromGithub($username, $repo);
        }
        return array_merge(
            ...$opportunities
        );
    }

    /**
     * Make a crawler in "comoequetala.com.br" website
     *
     * @return array
     * @todo Move to collect-crawler class
     */
    protected function getMessagesFromComoEQueTaLa(): array
    {
        $opportunities = [];
        $client = new Client();
        $crawler = $client->request('GET', 'https://comoequetala.com.br/vagas-e-jobs');
        $crawler->filter('.uk-list.uk-list-space > li')->each(function ($node) use (&$opportunities) {
            $skipDataCheck = env('CRAWLER_SKIP_DATA_CHECK');
            $client = new Client();
            $pattern = '#(' . implode('|', $this->mustIncludeWords) . ')#i';
            $pattern = str_replace('"', '', $pattern);
            if (preg_match_all($pattern, $node->text())) {
                $data = $node->filter('[itemprop="datePosted"]')->attr('content');
                $data = new DateTime($data);
                $today = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
                if ($skipDataCheck || $data->format('Ymd') === $today->format('Ymd')) {
                    $link = $node->filter('[itemprop="url"]')->attr('content');
                    $crawler2 = $client->request('GET', $link);
                    $title = $crawler2->filter('[itemprop="title"],h3')->text();
                    $description = [
                        $crawler2->filter('[itemprop="description"]')->count() ?
                            $crawler2->filter('[itemprop="description"]')->html() : '',
                        $crawler2->filter('.uk-container > .uk-grid-divider > .uk-width-1-1:last-child')->count()
                            ? $crawler2->filter('.uk-container > .uk-grid-divider > .uk-width-1-1:last-child')->html()
                            : '',
                        '*Como se candidatar:* ' . $link
                    ];
                    //$link = $node->filter('.uk-link')->text();
                    $company = $node->filter('.vaga_empresa')->count() ? $node->filter('.vaga_empresa')->text() : '';
                    $location = trim($node->filter('[itemprop="addressLocality"]')->text()) . '/'
                        . trim($node->filter('[itemprop="addressRegion"]')->text());

                    $opportunities[] = [
                        Opportunity::TITLE => $title,
                        Opportunity::DESCRIPTION => implode("\n\n", $description),
                        Opportunity::COMPANY => trim($company),
                        Opportunity::LOCATION => trim($location),
                        Opportunity::URL => trim($link),
                        Opportunity::ORIGIN => 'comoequetala',
                    ];
                }
            }
        });
        return $opportunities;
    }

    /**
     * Make a crawler in "queroworkar.com.br" website
     *
     * @return array
     * @todo Move to collect-crawler class
     */
    protected function getMessagesFromQueroWorkar(): array
    {
        $opportunities = [];
        $client = new Client();
        $crawler = $client->request('GET', 'http://queroworkar.com.br/blog/jobs/');
        $crawler->filter('.loadmore-item')->each(function ($node) use (&$opportunities) {
            $skipDataCheck = env('CRAWLER_SKIP_DATA_CHECK');
            /** @var Crawler $node */
            $client = new Client();
            $jobsPlace = $node->filter('.job-location');
            if ($jobsPlace->count()) {
                $jobsPlace = $jobsPlace->text();
                if (preg_match_all('#(Em qualquer lugar|Brasil)#i', $jobsPlace)) {
                    $data = $node->filter('.job-date .entry-date')->attr('datetime');
                    $data = explode('T', $data);
                    $data = trim($data[0]);
                    $data = new DateTime($data);
                    $today = new DateTime('now', new DateTimeZone('America/Sao_Paulo'));
                    if ($skipDataCheck || $data->format('Ymd') === $today->format('Ymd')) {
                        $link = $node->filter('a')->first()->attr('href');
                        $crawler2 = $client->request('GET', $link);
                        $title = $crawler2->filter('.page-title')->text();
                        $description = $crawler2->filter('.job-desc')->html();
                        $description = str_ireplace(
                            '(adsbygoogle = window.adsbygoogle || []).push({});',
                            '',
                            $description
                        );
                        $description .= "\n\n*Como se candidatar:* " . $link;

                        $opportunities[] = [
                            Opportunity::TITLE => $title,
                            Opportunity::DESCRIPTION => $description,
                            Opportunity::COMPANY => trim($crawler2->filter('.company-title')->text()),
                            Opportunity::LOCATION => trim($crawler2->filter('.job-location')->text()),
                            Opportunity::URL => trim($link),
                            Opportunity::ORIGIN => 'queroworkar',
                        ];
                    }
                }
            }
        });
        return $opportunities;
    }

    /**
     * Make a crawler in github opportunities channels
     *
     * @param string $username
     * @param string $repo
     * @return array
     * @todo Move to collect-github class
     */
    protected function fetchMessagesFromGithub(string $username, string $repo): array
    {
        $opportunities = [];
        $skipDataCheck = env('CRAWLER_SKIP_DATA_CHECK');

        $issues = $this->gitHubManager->issues()->all($username, $repo, [
            'state' => 'open',
            'since' => $skipDataCheck ? '1990-01-01' : Carbon::now()->format('Y-m-d')
        ]);

        if (!blank($issues)) {
            foreach ($issues as $issue) {
                $title = $issue['title'];
                $body = Markdown::convertToHtml($issue['body']);

                $opportunities[] = [
                    Opportunity::TITLE => trim($title),
                    Opportunity::DESCRIPTION => trim($body),
                    Opportunity::URL => trim($issue['url']),
                    Opportunity::ORIGIN => $username . '/' . $repo,
                ];
            }
        }

        return $opportunities;
    }

    /**
     * Send opportunity to approval
     *
     * @param int $opportunityId
     * @todo Move to communicate-telegram class
     */
    protected function sendOpportunityToApproval(int $opportunityId): void
    {
        $keyboard = Keyboard::make()
            ->inline()
            ->row(
                Keyboard::inlineButton([
                    'text' => 'Aprovar',
                    'callback_data' => implode(' ', [Opportunity::CALLBACK_APPROVE, $opportunityId])
                ]),
                Keyboard::inlineButton([
                    'text' => 'Remover',
                    'callback_data' => implode(' ', [Opportunity::CALLBACK_REMOVE, $opportunityId])
                ])
            );

        $messageToSend = [
            'reply_markup' => $keyboard,
        ];

        /** @var Opportunity $opportunity */
        $opportunity = Opportunity::find($opportunityId);
        $this->sendOpportunity($opportunity, $this->admin, $messageToSend);
    }

    /**
     * @param $opportunityId
     * @todo Move to communicate-telegram class
     */
    protected function sendTelegramOpportunityToApproval($opportunityId)
    {
        $opportunity = Opportunity::find($opportunityId);
        $opportunity = $this->createOrUpdateOpportunity($opportunity);
        $this->sendOpportunityToApproval($opportunity->id);
    }
}
