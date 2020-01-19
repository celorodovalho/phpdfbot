<?php

namespace App\Repositories;

use Illuminate\Support\Collection;
use Prettus\Repository\Eloquent\BaseRepository;
use Prettus\Repository\Criteria\RequestCriteria;
use App\Contracts\Repositories\OpportunityRepository;
use App\Models\Opportunity;
use App\Validators\OpportunityValidator;

/**
 * Class OpportunityRepositoryEloquent.
 */
class OpportunityRepositoryEloquent extends BaseRepository implements OpportunityRepository
{
    /**
     * Specify Model class name
     *
     * @return string
     */
    public function model()
    {
        return Opportunity::class;
    }

    /**
     * Specify Validator class name
     *
     * @return mixed
     */
    public function validator()
    {
        return OpportunityValidator::class;
    }

    /**
     * Boot up the repository, pushing criteria
     */
    public function boot()
    {
        $this->pushCriteria(app(RequestCriteria::class));
    }

    public function make(array $data)
    {
        $opportunity = $this->firstOrNew([
            Opportunity::TITLE => $data[Opportunity::TITLE],
            Opportunity::DESCRIPTION => $data[Opportunity::DESCRIPTION],
        ]);

        $opportunity->{Opportunity::FILES} = new Collection($data[Opportunity::FILES]);
        $opportunity->{Opportunity::POSITION} = $data[Opportunity::POSITION];
        $opportunity->{Opportunity::COMPANY} = $data[Opportunity::COMPANY];
        $opportunity->{Opportunity::LOCATION} = mb_strtoupper($data[Opportunity::LOCATION]);
        $opportunity->{Opportunity::TAGS} = implode(' ', $data[Opportunity::TAGS]);
        $opportunity->{Opportunity::SALARY} = $data[Opportunity::SALARY];
        $opportunity->{Opportunity::URL} = $data[Opportunity::URL];
        $opportunity->{Opportunity::ORIGIN} = $data[Opportunity::ORIGIN];
        $opportunity->{Opportunity::EMAILS} = $data[Opportunity::EMAILS];

        return $opportunity;
    }
}
