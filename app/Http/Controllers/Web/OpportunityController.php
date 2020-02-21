<?php

namespace App\Http\Controllers\Web;

use App\Contracts\Repositories\OpportunityRepository;
use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Opportunity;
use App\Validators\CollectedOpportunityValidator;
use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\View\View;
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
        $group = Group::where('admin', true)->first();
        $this->telegram->sendMessage([
            'chat_id' => $group->name,
            'text' => $request->get('text')
        ]);
    }
}
