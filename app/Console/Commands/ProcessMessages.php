<?php

namespace App\Console\Commands;

use App\Contracts\Collector\CollectorInterface;
use App\Contracts\Repositories\GroupRepository;
use App\Contracts\Repositories\OpportunityRepository;
use App\Enums\Arguments;
use App\Enums\GroupTypes;
use App\Helpers\ExtractorHelper;
use App\Helpers\Helper;
use App\Models\Group;
use App\Models\Opportunity;
use App\Notifications\GroupSummaryOpportunities;
use App\Notifications\NotifySenderUser;
use App\Notifications\SendOpportunity;
use App\Services\Collectors\ComoQueTaLaMessages;
use App\Services\Collectors\GitHubMessages;
use App\Services\Collectors\GMailMessages;
use App\Services\Collectors\TelegramChatMessages;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Notifications\DatabaseNotification as Notification;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Telegram\Bot\Api;
use Telegram\Bot\BotsManager;
use Telegram\Bot\Exceptions\TelegramSDKException;

/**
 * Class BotPopulateChannel
 *
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
class ProcessMessages extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:messages {--type=process} {--opportunity=} {--collectors=*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to populate the channel with new content';

    /** @var Collection */
    protected $channels;

    /** @var array */
    private $collectors = [
        'comoquetala' => ComoQueTaLaMessages::class,
        'github' => GitHubMessages::class,
        'gmail' => GMailMessages::class,
        'telegram' => TelegramChatMessages::class,
    ];

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
     *
     * @throws TelegramSDKException
     */
    public function handle(
        BotsManager $botsManager,
        OpportunityRepository $repository,
        GroupRepository $groupRepository
    ): void {
        $this->telegram = $botsManager->bot(Config::get('telegram.default'));
        $this->repository = $repository;
        $this->groupRepository = $groupRepository;

        $collectorsOption = $this->option('collectors');
        if (filled($collectorsOption)) {
            $this->collectors = array_filter(
                $this->collectors,
                static function ($key) use ($collectorsOption) {
                    return in_array($key, $collectorsOption, true);
                },
                ARRAY_FILTER_USE_KEY
            );
        }

        $this->channels = $this->groupRepository->findWhere([
            ['type', '=', GroupTypes::CHANNEL]
        ]);

        switch ($this->option('type')) {
            case Arguments::PROCESS:
                $this->processOpportunities();
                break;
            case Arguments::NOTIFY:
                $this->notifyGroup();
                break;
            case Arguments::SEND:
                $this->sendOpportunityToChannels($this->option('opportunity'));
                break;
            case Arguments::APPROVAL:
                $this->sendTelegramOpportunityToApproval($this->option('opportunity'));
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
        $this->info(sprintf(
            'Opportunities sent to approval: %s',
            $opportunities->count()
        ));
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
            $collector = resolve($collector, ['output' => array($this, 'info')]);
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
            ['type', '=', GroupTypes::MAILING],
            ['main', '=', true],
        ]);

        $channels = $this->channels->concat($mailing);

        /** @var Group $channel */
        foreach ($channels as $channel) {
            if (blank($channel->tags) || ExtractorHelper::hasTags($channel->tags, $opportunity->getText())) {
                $channel->notify(new SendOpportunity($opportunity));
                if ($channel->main
                    && $channel->type === GroupTypes::CHANNEL
                    && $opportunity->telegram_user_id
                    && !Str::contains($opportunity->origin, 'channel_id')
                ) {
                    $opportunity->notify(new NotifySenderUser($channel));
                }
            }
        }

        $opportunity->update(['status' => Opportunity::STATUS_ACTIVE]);
    }

    /**
     * Notifies the group with the latest opportunities in channel
     * Get all the unnoticed opportunities, build a keyboard with the links, sends to the group, update the opportunity
     * and remove the previous notifications from group
     */
    protected function notifyGroup(): void
    {
        $opportunities = $this->repository->findWhere([['status', '=', Opportunity::STATUS_ACTIVE]]);

        $allGroups = $this->groupRepository->findWhere([
            ['type', '=', GroupTypes::GROUP],
        ]);

        /** @var Collection $groups */
        $groups = $allGroups->filter(static function ($item) {
            return $item->main || $item->admin;
        });

        if ($opportunities->isNotEmpty() && $groups->isNotEmpty()) {
            $opportunitiesIds = $opportunities->pluck('id')->toArray();
            /** @var Group $group */
            foreach ($groups as $group) {
                $lastNotifications = $group
                    ->unreadNotifications()
                    ->where('type', GroupSummaryOpportunities::class)
                    ->get();

                /** @var Collection $telegramIds */
                $telegramIds = $this->channels
                    ->where('main', true)
                    ->first()
                    ->notifications()
                    ->where('type', SendOpportunity::class)
                    ->where(static function (Builder $query) use ($opportunitiesIds) {
                        foreach ($opportunitiesIds as $opportunityId) {
                            //$query->orWhereJsonContains('data->opportunity', $opportunityId);
                            $query->where('data', 'like', '%"opportunity":' . $opportunityId . '%');
                        }
                    })
                    ->pluck('data')
                    ->pluck('telegram_ids', 'opportunity');

                $opportunities = $opportunities->map(static function (Opportunity $opportunity) use ($telegramIds) {
                    if ($telegramIds->has($opportunity->id)) {
                        $opportunity->telegram_id = Arr::first($telegramIds->get($opportunity->id));
                    }
                    return $opportunity;
                });

                $allChannels = $this->channels->concat($allGroups->where('admin', '=', false));
                $group->notify(new GroupSummaryOpportunities($opportunities, $allChannels));

                /** @var Notification $lastNotification */
                foreach ($lastNotifications as $lastNotification) {
                    try {
                        if (is_array($lastNotification->data)
                            && array_key_exists('telegram_id', $lastNotification->data)
                        ) {
                            $this->telegram->deleteMessage([
                                'chat_id' => $group->name,
                                'message_id' => $lastNotification->data['telegram_id']
                            ]);
                        }
                    } catch (Exception $exception) {
                        $this->error(implode(': ', [
                            'Could not delete notification',
                            $exception->getMessage(),
                            $lastNotification
                        ]));
                    }
                    $lastNotification->markAsRead();
                }
            }

            $opportunities->each(static function (Opportunity $opportunity) {
                $opportunity->delete();
            });
            $this->info(sprintf(
                'The notification were sent: %s opportunities to %s groups',
                $opportunities->count(),
                $groups->count()
            ));
        } else {
            $this->info(sprintf(
                'There is no notification to send: %s opportunities to %s groups',
                $opportunities->count(),
                $groups->count()
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
            ['type', '=', GroupTypes::GROUP],
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
