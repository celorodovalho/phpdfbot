<?php

namespace App\Http\Controllers\Web;

use App\Contracts\Repositories\OpportunityRepository;
use App\Enums\Arguments;
use App\Enums\GroupTypes;
use App\Helpers\BotHelper;
use App\Helpers\ExtractorHelper;
use App\Helpers\SanitizerHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\OpportunityCreateRequest;
use App\Models\Group;
use App\Models\Opportunity;
use App\Notifications\GroupSummaryOpportunities;
use App\Notifications\SendOpportunity;
use App\Validators\CollectedOpportunityValidator;
use Illuminate\Contracts\View\Factory;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;
use Prettus\Validator\Contracts\ValidatorInterface;
use Prettus\Validator\Exceptions\ValidatorException;
use Symfony\Component\HttpFoundation\Response as BaseResponse;
use Telegram\Bot\Api as Telegram;

/**
 * Class OpportunityController
 *
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
class OpportunityController extends Controller
{
    /** @var Telegram */
    private $telegram;

    /**
     * OpportunityController constructor.
     *
     * @param OpportunityRepository $repository
     * @param CollectedOpportunityValidator $validator
     * @param Telegram $telegram
     */
    public function __construct(
        OpportunityRepository $repository,
        CollectedOpportunityValidator $validator,
        Telegram $telegram
    )
    {
        parent::__construct($repository, $validator);
        $this->telegram = $telegram;
    }

    /**
     * @return Factory|View
     */
    public function index()
    {
        $opportunities = $this->repository->scopeQuery(static function (Opportunity $query) {
            /** @var Builder $query */
            return $query
                ->where('updated_at', '>=', Carbon::now()->subDays(30))
                ->where('status', '<>', 0)
                ->orderBy('updated_at', 'DESC');
        })->paginate(60);
        return view('opportunities.index', compact('opportunities'));
    }

    /**
     * @param Opportunity $opportunity
     *
     * @return Factory|View
     */
    public function show(Opportunity $opportunity)
    {
        return view('opportunities.show', compact('opportunity'));
    }

    /**
     * @return Factory|View
     */
    public function create()
    {
        /**
         * Because withInput is not working, so I'm forcing persisting old_input in session
         */
//        return view('opportunities.create', (array)Session::get('_old_input'));
        return view('opportunities.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param OpportunityCreateRequest $request
     *
     * @return BaseResponse
     */
    public function store(OpportunityCreateRequest $request): ?BaseResponse
    {
        try {
            $opportunity = $this->repository->createOpportunity(
                $request
                    ->merge([Opportunity::ORIGIN => $request->ip()])
                    ->all()
            );

            Artisan::call(
                'process:messages',
                [
                    '--type' => Arguments::APPROVAL,
                    '--opportunity' => $opportunity->id,
                ]
            );

            $response = [
                'message' => 'Enviada com sucesso! '
                    . 'A oportunidade sera publicada no canal https://t.me/VagasBrasil_TI assim que aprovada.',
                'data' => $opportunity->toArray(),
            ];

            if ($request->wantsJson()) {
                return response()->json($response);
            }

            return redirect()->back()->with(['success' => $response['message']]);
        } catch (ValidatorException $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'error' => true,
                    'message' => $e->getMessageBag()
                ]);
            }

            return redirect()->back()->withErrors($e->getMessageBag())->withInput();
        }
    }

    /**
     * @param string $type
     * @param string|null $collectors
     *
     * @return string
     */
    public function processMessages(
        string $type,
        ?string $collectors = null
    ): string {
        Artisan::call(
            'process:messages',
            [
                '--type' => $type,
                '--collectors' => [$collectors],
            ]
        );
        return Artisan::output();
    }

    /**
     * @param \Illuminate\Http\Request $request
     *
     * @throws \Telegram\Bot\Exceptions\TelegramSDKException
     */
    public function sendMessage(Request $request): void
    {
        $texts = $request->get('text');
        $group = Group::where('admin', true)->first();

        $texts = explode(
            '%%%%%%%',
            wordwrap(
                $texts,
                BotHelper::TELEGRAM_LIMIT,
                '%%%%%%%'
            )
        );

        foreach ($texts as $text) {
            $this->telegram->sendMessage([
                'chat_id' => $group->name,
                'text' => $text
            ]);
        }
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
}
