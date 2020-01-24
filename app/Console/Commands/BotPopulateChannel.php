<?php

namespace App\Console\Commands;

use App\Contracts\CollectorInterface;
use App\Contracts\Repositories\GroupRepository;
use App\Contracts\Repositories\OpportunityRepository;
use App\Enums\GroupTypes;
use App\Helpers\ExtractorHelper;
use App\Helpers\Helper;
use App\Models\Group;
use App\Models\Opportunity;
use App\Notifications\GroupSummaryOpportunities;
use App\Notifications\NotifySenderUser;
use App\Notifications\SendOpportunity;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Notifications\DatabaseNotification as Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Telegram\Bot\Api;
use Telegram\Bot\BotsManager;

/**
 * Class BotPopulateChannel
 *
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
class BotPopulateChannel extends Command
{

    /**
     * Commands
     */
    public const TYPE_APPROVAL = 'approval';
    public const TYPE_NOTIFY = 'notify';
    public const TYPE_PROCESS = 'process';
    public const TYPE_SEND = 'send';

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

    /** @var array */
    protected $channels;

    /** @var array */
    private $collectors;

    /** @var OpportunityRepository */
    private $repository;

    /** @var GroupRepository */
    private $groupRepository;

    /** @var Api */
    private $telegram;

    /**
     * BotPopulateChannel constructor.
     *
     * @param BotsManager           $botsManager
     * @param OpportunityRepository $repository
     * @param GroupRepository       $groupRepository
     */
    public function __construct(
        BotsManager $botsManager,
        OpportunityRepository $repository,
        GroupRepository $groupRepository
    ) {
        $this->telegram = $botsManager->bot(Config::get('telegram.default'));
        $this->collectors = Helper::getNamespaceClasses('App\\Services\\Collectors');
        $this->repository = $repository;
        $this->groupRepository = $groupRepository;

        $this->channels = $this->groupRepository->findWhere([
            ['type', '=', GroupTypes::TYPE_CHANNEL]
        ]);

        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
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
        $this->info('Opportunities sent to approval');
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
                $collection = $collector->collectOpportunities();
                if ($collection->isEmpty()) {
                    $this->info(sprintf(
                        "%s hasn't new opportunities",
                        class_basename(get_class($collector))
                    ));
                }
                $opportunities = $opportunities->concat($collection);
            }
        }

        $opportunities->map(static function (Opportunity $opportunity) {
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

        $mailing = $this->groupRepository->findWhere([
            ['type', '=', GroupTypes::TYPE_MAILING],
            ['main', '=', true],
        ]);

        $channels = $this->channels->concat($mailing);

        /** @var Group $channel */
        foreach ($channels as $channel) {
            if (blank($channel->tags) || ExtractorHelper::hasTags($channel->tags, $opportunity->getText())) {
                $channel->notify(new SendOpportunity($opportunity));
                if ($channel->main && $opportunity->telegram_id && $opportunity->telegram_user_id) {
                    $opportunity->notify(new NotifySenderUser);
                }
            }
        }

        $opportunity->status = Opportunity::STATUS_ACTIVE;
        $opportunity->save();
    }

    /**
     * Notifies the group with the latest opportunities in channel
     * Get all the unnoticed opportunities, build a keyboard with the links, sends to the group, update the opportunity
     * and remove the previous notifications from group
     */
    protected function notifyGroup(): void
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

                $channels = $this->channels->concat($groups);
                $group->notify(new GroupSummaryOpportunities($opportunities, $channels));

                /** @var Notification $lastNotification */
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
                        $this->error(implode(': ', ['Could not delete notification', $exception->getMessage()]));
                    }
                }
            }

            $opportunities->each(static function (Opportunity $opportunity) {
                $opportunity->delete();
            });
            $this->info('The group was notified!');
        } else {
            $this->info(sprintf(
                'There is no notification to send - Groups: %s - Opportunities: %s',
                $groups->count(),
                $opportunities->count()
            ));
        }
    }

    /**
     * Send opportunity to approval
     *
     * @param Opportunity $opportunity
     */
    protected function sendOpportunityToApproval(Opportunity $opportunity): void
    {
        $adminGroup = $this->groupRepository->findWhere([
            ['type', '=', GroupTypes::TYPE_GROUP],
            ['admin', '=', true],
        ])->first();

        /** @var Group $adminGroup */
        $adminGroup->notify(new SendOpportunity($opportunity));
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
