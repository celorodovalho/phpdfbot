<?php

namespace App\Repositories;

use App\Contracts\Repositories\OpportunityRepository;
use App\Models\Opportunity;
use App\Validators\CollectedOpportunityValidator;
use Illuminate\Support\Collection;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Eloquent\BaseRepository;
use Prettus\Repository\Exceptions\RepositoryException;

/**
 * Class OpportunityRepositoryEloquent
 *
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
class OpportunityRepositoryEloquent extends BaseRepository implements OpportunityRepository
{
    /**
     * Specify Model class name
     *
     * @return string
     */
    public function model(): string
    {
        return Opportunity::class;
    }

    /**
     * Specify Validator class name
     *
     * @return mixed
     */
    public function validator(): string
    {
        return CollectedOpportunityValidator::class;
    }

    /**
     * Boot up the repository, pushing criteria
     *
     * @throws RepositoryException
     */
    public function boot()
    {
        $this->pushCriteria(app(RequestCriteria::class));
    }

    /**
     * @param array $data
     *
     * @return mixed
     */
    public function make(array $data): Opportunity
    {
        $opportunity = $this->model->newInstance([
            Opportunity::TITLE => $data[Opportunity::TITLE],
            Opportunity::ORIGINAL => $data[Opportunity::ORIGINAL],
        ]);

        $opportunity->{Opportunity::FILES} = new Collection($data[Opportunity::FILES]);
        $opportunity->{Opportunity::POSITION} = $data[Opportunity::POSITION];
        $opportunity->{Opportunity::COMPANY} = $data[Opportunity::COMPANY];
        $opportunity->{Opportunity::LOCATION} = mb_strtoupper($data[Opportunity::LOCATION]);
        $opportunity->{Opportunity::TAGS} = new Collection($data[Opportunity::TAGS]);
        $opportunity->{Opportunity::SALARY} = $data[Opportunity::SALARY];
        $opportunity->{Opportunity::URL} = $data[Opportunity::URL];
        $opportunity->{Opportunity::ORIGIN} = $data[Opportunity::ORIGIN];
        $opportunity->{Opportunity::EMAILS} = $data[Opportunity::EMAILS];

        $opportunity->save();

        return $opportunity;
    }
}
