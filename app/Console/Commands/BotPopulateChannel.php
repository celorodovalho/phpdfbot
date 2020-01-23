<?php

namespace App\Console\Commands;

use App\Contracts\CollectorInterface;
use App\Contracts\Repositories\GroupRepository;
use App\Contracts\Repositories\OpportunityRepository;
use App\Enums\GroupTypes;
use App\Helpers\BotHelper;
use App\Helpers\ExtractorHelper;
use App\Helpers\Helper;
use App\Helpers\SanitizerHelper;
use App\Models\Group;
use App\Notifications\NotifyGroup;
use Illuminate\Notifications\DatabaseNotification as Notification;
use App\Models\Opportunity;
use App\Notifications\PublishedOpportunity;
use App\Notifications\SendOpportunity;
use App\Transformers\FormattedOpportunityTransformer;
use Dacastro4\LaravelGmail\Services\Message\Mail;
use Exception;
use GrahamCampbell\Markdown\Facades\Markdown;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\Emoji\Emoji;
use Telegram\Bot\BotsManager;
use Telegram\Bot\Exceptions\TelegramResponseException;
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

    /** @var array */
    private $collectors;

    /** @var OpportunityRepository */
    private $repository;

    /** @var GroupRepository */
    private $groupRepository;

    /**
     * BotPopulateChannel constructor.
     * @param BotsManager $botsManager
     * @param OpportunityRepository $repository
     * @param GroupRepository $groupRepository
     */
    public function __construct(
        BotsManager $botsManager,
        OpportunityRepository $repository,
        GroupRepository $groupRepository
    )
    {
        parent::__construct($botsManager);
        $this->collectors = Helper::getNamespaceClasses('App\\Services\\Collectors');
        $this->repository = $repository;
        $this->groupRepository = $groupRepository;

        $this->mailing = $this->groupRepository->findWhere([
            ['type', '=', GroupTypes::TYPE_MAILING],
            ['main', '=', true],
        ]);

        $this->groups = $this->groupRepository->findWhere([
            ['type', '=', GroupTypes::TYPE_GROUP],
            ['main', '=', true],
        ]);

        $this->channels = $this->groupRepository->findWhere([
            ['type', '=', GroupTypes::TYPE_CHANNEL]
        ]);
    }

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->appUrl = env('APP_URL');
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
     */
    protected function processOpportunities(): void
    {
        $opportunities = $this->collectOpportunities();
        foreach ($opportunities as $opportunity) {
            $this->sendOpportunityToApproval($opportunity);
        }
        $this->info('Vagas enviadas para aprovação');
    }

    /**
     * Get messages from source and create objects from them
     *
     * @return Collection
     */
    protected function collectOpportunities(): Collection
    {
        $opportunities = new EloquentCollection();
        foreach ($this->collectors as $collector) {
            $collector = App::make($collector);
            if ($collector instanceof CollectorInterface) {
                $collectorOpportunities = $collector->collectOpportunities();
                if ($collectorOpportunities->isEmpty()) {
                    $this->info(sprintf(
                        '%s não contém novas oportunidades',
                        class_basename(get_class($collector))
                    ));
                }
                $opportunities = $opportunities->concat($collectorOpportunities);
            }
        }

        $opportunities->map(function (Opportunity $opportunity) {
            $opportunity->save();
        });

        return $opportunities;
    }

    /**
     * Prepare and send the opportunity to the channel, then update the TelegramId in database
     *
     * @param int $opportunityId
     */
    protected function sendOpportunityToChannels(int $opportunityId): void
    {
        /** @var Opportunity $opportunity */
        $opportunity = $this->repository->find($opportunityId);

        foreach ($this->channels as $channel) {
            if (blank($channel->tags) || ExtractorHelper::hasTags($channel->tags, $opportunity->getText())) {
                $opportunity->notify(new SendOpportunity($channel->name, $this->mailing));
                if ($channel->main && $opportunity->telegram_id && $opportunity->telegram_user_id) {
                    $opportunity->notify(new PublishedOpportunity);
                }
            }
        }
    }

    /**
     * Notifies the group with the latest opportunities in channel
     * Get all the unnoticed opportunities, build a keyboard with the links, sends to the group, update the opportunity
     * and remove the previous notifications from group
     */
    protected function notifyGroup()
    {
        $opportunities = $this->repository->findWhere([['telegram_id', '<>', null]]);

        $groups = $this->groupRepository->findWhere([
            ['type', '=', GroupTypes::TYPE_GROUP],
            ['main', '=', true],
        ]);

        if ($opportunities->isNotEmpty() && $groups->isNotEmpty()) {
            /** @var Group $group */
            foreach ($groups as $group) {
                $lastNotifications = $group->unreadNotifications;

                $channels = $this->channels->concat($this->groups);
                $group->notify(new NotifyGroup($opportunities, $channels));

                foreach ($lastNotifications as $lastNotification) {
                    try {
                        if ($lastNotification->data && $lastNotification->data['telegram_id']) {
                            $this->telegram->deleteMessage([
                                'chat_id' => $group->name,
                                'message_id' => $lastNotification->data['telegram_id']
                            ]);
                            $lastNotification->markAsRead();
                        }
                    } catch (Exception $exception) {
                        $this->error(implode(': ', ['ERRO_AO_DELETAR_NOTIFICACAO', $exception->getMessage()]));
                    }
                }
            }
            $opportunities->each(function ($opportunity) {
                $opportunity->delete();
            });
            $this->info('The group was notified!');
        } else {
            $this->info(sprintf('There is no notification to send - Groups: %s - Opportunities: %s', $groups->count(), $opportunities->count()));
        }
    }

    /**
     * Send opportunity to approval
     *
     * @param Opportunity $opportunity
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

        $options = [
            'reply_markup' => $keyboard,
        ];

        /** @var Opportunity $opportunity */
        $opportunity->notify(new SendOpportunity($this->admin, null, $options));
    }

    /**
     * @param $opportunityId
     */
    protected function sendTelegramOpportunityToApproval($opportunityId): void
    {
        $opportunity = $this->repository->find($opportunityId);
        $this->sendOpportunityToApproval($opportunity);
    }
}
