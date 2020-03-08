<?php

namespace App\Http\Controllers\Web;

use App\Contracts\Repositories\OpportunityRepository;
use App\Enums\GroupTypes;
use App\Helpers\BotHelper;
use App\Helpers\ExtractorHelper;
use App\Helpers\SanitizerHelper;
use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Opportunity;
use App\Notifications\GroupSummaryOpportunities;
use App\Notifications\SendOpportunity;
use App\Validators\CollectedOpportunityValidator;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;
use Prettus\Validator\Contracts\ValidatorInterface;
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
     * @param OpportunityRepository         $repository
     * @param CollectedOpportunityValidator $validator
     * @param Telegram                      $telegram
     */
    public function __construct(
        OpportunityRepository $repository,
        CollectedOpportunityValidator $validator,
        Telegram $telegram
    ) {
        parent::__construct($repository, $validator);
        $this->telegram = $telegram;
    }

    /**
     * @return Factory|View
     */
    public function index()
    {

        $opportunities = $this->repository->scopeQuery(static function ($query) {
            return $query->withTrashed()->where('status', '<>', '0');
        })->orderBy('created_at', 'DESC')->paginate();
        return view('home', compact('opportunities'));
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
     * @param string      $type
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
                Opportunity::URL => implode(', ', ExtractorHelper::extractUrls($description)),
                Opportunity::ORIGIN => 'sdfs',
                Opportunity::EMAILS => implode(', ', ExtractorHelper::extractEmail($description)),
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

                dump($telegramIds);die;

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
}
