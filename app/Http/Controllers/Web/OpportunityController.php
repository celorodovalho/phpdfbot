<?php

namespace App\Http\Controllers\Web;

use App\Contracts\Repositories\OpportunityRepository;
use App\Http\Controllers\Controller;
use App\Models\Opportunity;
use App\Validators\CollectedOpportunityValidator;
use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

/**
 * Class OpportunityController
 *
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
class OpportunityController extends Controller
{

    /**
     * OpportunityController constructor.
     *
     * @param OpportunityRepository         $repository
     * @param CollectedOpportunityValidator $validator
     */
    public function __construct(OpportunityRepository $repository, CollectedOpportunityValidator $validator)
    {
        parent::__construct($repository, $validator);
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
}
