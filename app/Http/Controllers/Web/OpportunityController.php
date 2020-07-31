<?php

namespace App\Http\Controllers\Web;

use App\Contracts\Repositories\OpportunityRepository;
use App\Enums\Arguments;
use App\Helpers\BotHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\OpportunityCreateRequest;
use App\Models\Group;
use App\Models\Opportunity;
use App\Validators\CollectedOpportunityValidator;
use Illuminate\Contracts\View\Factory;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;
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
                    . 'A oportunidade sera publicada no canal https://t.me/VagasBRTI assim que aprovada.',
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

//        foreach ($texts as $text) {
            $this->telegram->sendMessage([
                'chat_id' => $group->name,
                'text' => $texts
            ]);
//        }
    }
}
