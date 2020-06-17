<?php


namespace App\Http\Controllers\Web;


use App\Contracts\Repositories\OpportunityRepository;
use App\Enums\GroupTypes;
use App\Helpers\ExtractorHelper;
use App\Helpers\SanitizerHelper;
use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Opportunity;
use App\Notifications\GroupSummaryOpportunities;
use App\Notifications\SendOpportunity;
use App\Services\MadelineProtoService;
use App\Validators\CollectedOpportunityValidator;
use Carbon\Carbon;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Prettus\Validator\Contracts\ValidatorInterface;
use Telegram\Bot\Api as Telegram;

class TestController extends Controller
{
    /**
     * OpportunityController constructor.
     *
     * @param OpportunityRepository $repository
     * @param CollectedOpportunityValidator $validator
     */
    public function __construct(
        OpportunityRepository $repository,
        CollectedOpportunityValidator $validator
    ) {
        parent::__construct($repository, $validator);
    }

    public function testValidation()
    {
        try {
            $original = file_get_contents(storage_path('app/teste2.txt'));
            $description = SanitizerHelper::sanitizeBody($original);

            $opportunity = [
                Opportunity::TITLE => 'testestes',
                Opportunity::DESCRIPTION => $description,
                Opportunity::ORIGINAL => $original,
                Opportunity::FILES => [],
                Opportunity::POSITION => '',
                Opportunity::COMPANY => '',
                Opportunity::LOCATION => implode(' / ', ExtractorHelper::extractLocation($description)),
                Opportunity::TAGS => ExtractorHelper::extractTags($description),
                Opportunity::SALARY => '',
                Opportunity::URLS => ExtractorHelper::extractUrls($description),
                Opportunity::ORIGIN => [],
                Opportunity::EMAILS => ExtractorHelper::extractEmails($description),
            ];

            $valid = $this->validator->with($opportunity)->passesOrFail(ValidatorInterface::RULE_CREATE);
            dump($valid);
        } catch (\Exception $exception) {
            dump($exception);
        }

        dump($opportunity);
    }

    public function testCode()
    {
        $opportunities = $this->repository->findWhere([['status', '=', Opportunity::STATUS_ACTIVE]]);

        $allGroups = Group::where('type', '=', GroupTypes::GROUP)->get();
        $channels = Group::where('type', '=', GroupTypes::CHANNEL)->get();

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
                $telegramIds = $channels
                    ->where('main', true)
                    ->first()
                    ->notifications()
                    ->where('type', SendOpportunity::class)
                    ->where(static function ($query) use ($opportunitiesIds) {
                        foreach ($opportunitiesIds as $opportunityId) {
                            //$query->orWhereJsonContains('data->opportunity', $opportunityId);
                            dump('%"opportunity":' . $opportunityId . '%');
                            $query->orWhere('data', 'like', '%"opportunity":' . $opportunityId . '%');
                        }
                    })
                    ->pluck('data')
                    ->pluck('telegram_ids', 'opportunity');

                dump($telegramIds);
                die;

                $opportunities = $opportunities->map(static function (Opportunity $opportunity) use ($telegramIds) {
                    if ($telegramIds->has($opportunity->id)) {
                        $opportunity->telegram_id = Arr::first($telegramIds->get($opportunity->id));
                    }
                    return $opportunity;
                });

                $allChannels = $channels->concat($allGroups->where('admin', '=', false));
                dump([
                    'NOTIFY' => [
                        $group, $opportunities, $allChannels
                    ]
                ]);
//                $group->notify(new GroupSummaryOpportunities($opportunities, $allChannels));

                /** @var Notification $lastNotification */
                foreach ($lastNotifications as $lastNotification) {
                    try {
                        if (is_array($lastNotification->data)
                            && array_key_exists('telegram_id', $lastNotification->data)
                        ) {
                            dump([
                                'DELETE_MSG' => [
                                    'chat_id' => $group->name,
                                    'message_id' => $lastNotification->data['telegram_id']
                                ]
                            ]);
                        }
                    } catch (\Exception $exception) {
                        dump(implode(': ', [
                            'Could not delete notification',
                            $exception->getMessage(),
                            $lastNotification
                        ]));
                    }
                    dump(['MARK_AS_READ' => $lastNotification]);
                    //$lastNotification->markAsRead();
                }
            }

            $opportunities->each(static function (Opportunity $opportunity) {
                dump(['DELETE' => $opportunity]);
                //$opportunity->delete();
            });
            dump(sprintf(
                'The notification were sent: %s opportunities to %s groups',
                $opportunities->count(),
                $groups->count()
            ));
        } else {
            dump(sprintf(
                'There is no notification to send: %s opportunities to %s groups',
                $opportunities->count(),
                $groups->count()
            ));
        }
    }

    public function testNotification()
    {
        $allOpportunities = DatabaseNotification::where([
            ['type', '=', \App\Notifications\GroupSummaryOpportunities::class],
        ])->pluck('data')->pluck('opportunities')->flatten()->unique();

        dump($allOpportunities->contains(12584));
        dump($allOpportunities->contains(12549));
        dump($allOpportunities->filter()->toArray());

        DB::enableQueryLog(); // Enable query log



        // Show results of log

        $opportunities = $this->repository
            ->where([
                ['status', '=', 1],
            ])
            ->whereNotIn('id', $allOpportunities->filter()->toArray())->get();
//            ;

        dump(DB::getQueryLog());

        dd($opportunities);

//        $opportunity = $this->repository->findWhere([['id', '=', 1]])->first();
//        dump(\App\Notifications\SendOpportunity::class);
//        $opportunities = $this->repository->with(['notification'])->scopeQuery(static function($query) {
//            /** @var Builder $query */
//            return $query->whereDoesntHave('notification', function ($query) {
//                return $query->where([
//                    ['type', '=', \App\Notifications\SendOpportunity::class],
//                    ['data', 'LIKE', '%VagasBrasil_TI%'],
//                ]);
//            });
//        })->findWhere([
//            ['status', '=', 1],
//        ])->all();
//
//        $adminGroup = Group::where([
//            ['type', '=', GroupTypes::GROUP],
//            ['admin', '=', true],
//        ])->first();
//
//
//        $adminGroup->notify(new SendOpportunity($opportunity));
        dump($opportunities);die;
    }

    public function testTitle()
    {
        $opportunities = $this->repository->scopeQuery(static function ($query) {
            return $query->withTrashed();
        })->paginate();

        return view('tests.title', compact('opportunities'));
    }

    public function testMadeline()
    {
        dump(65465);
        /** @var \danog\MadelineProto\API $MadelineProto */
        $MadelineProto = (new MadelineProtoService());

//        $dialogs = $MadelineProto->getDialogs();
//        foreach ($dialogs as $dialog) {
//            $MadelineProto->logger($dialog);
//        }
////        $Peers = $MadelineProto->messages->getDialogs([
////            'offset_date' => 0, //Carbon::now()->getTimestamp(),
////            'offset_id' => 0,
////            'limit' => 15,
////            'offset_peer' => ['_' => 'inputPeerEmpty', ]
////        ]);
//
//
//        dump($dialogs);die;
//
        $MadelineProto->async(true);
        $Peers = $MadelineProto->loop(static function () use ($MadelineProto) {
            yield $MadelineProto->start();
//            $Peers = $MadelineProto->channels->getGroupsForDiscussion();
            $Peers = $MadelineProto->messages->getDialogs([
                'offset_date' => Carbon::now()->getTimestamp(),
                'offset_id' => 0,
                'limit' => 150,
            ]);
//            $MadelineProto->logger($Peers);
//            $MadelineProto->stop();
            return $Peers;
        });
        $MadelineProto->stop();
        dd($Peers);
    }
}
