<?php

namespace App\Http\Controllers\Web;

use App\Contracts\Repositories\OpportunityRepository;
use App\Http\Controllers\Controller;
use App\Validators\OpportunityValidator;

/**
 * Class DefaultController
 */
class OpportunityController extends Controller
{

    /**
     * ExperienceController constructor.
     *
     * @param OpportunityRepository $repository
     * @param OpportunityValidator $validator
     */
    public function __construct(OpportunityRepository $repository, OpportunityValidator $validator)
    {
        parent::__construct($repository, $validator);
    }

    public function index()
    {
        $opportunities = $this->repository->scopeQuery(function ($query) {
            return $query->withTrashed()->where('status', '<>', '0');
        })->orderBy('created_at', 'DESC')->paginate();
        return view('home', compact('opportunities'));
    }
}
