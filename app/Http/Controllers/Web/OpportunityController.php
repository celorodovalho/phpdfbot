<?php

namespace App\Http\Controllers\Web;

use App\Contracts\Repositories\OpportunityRepository;
use App\Helpers\BotHelper;
use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Opportunity;
use App\Validators\CollectedOpportunityValidator;
use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\View\View;
use Prettus\Validator\Contracts\ValidatorInterface;
use Telegram\Bot\Api as Telegram;
use Illuminate\Http\Request;

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
            $this->validator->with([
                Opportunity::TITLE => 'testestes',
                Opportunity::DESCRIPTION => 'forex teste',
                Opportunity::ORIGINAL => 'teste',
                Opportunity::FILES => [],
                Opportunity::POSITION => '',
                Opportunity::COMPANY => '',
                Opportunity::LOCATION => 'teste',
                Opportunity::TAGS => ['teste'],
                Opportunity::SALARY => '',
                Opportunity::URL => 'fsdf',
                Opportunity::ORIGIN => 'sdfs',
                Opportunity::EMAILS => 'fasdfas',
            ])->passesOrFail(ValidatorInterface::RULE_CREATE);
        } catch (\Exception $exception) {
            dump($exception);
        }
    }
}
