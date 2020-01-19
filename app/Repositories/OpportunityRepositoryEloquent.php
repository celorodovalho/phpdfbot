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
        return $this->firstOrNew([
            Opportunity::TITLE => $data[Opportunity::TITLE],
            Opportunity::DESCRIPTION => $data[Opportunity::DESCRIPTION],
            Opportunity::FILES => new Collection($data[Opportunity::FILES]),
            Opportunity::POSITION => $data[Opportunity::POSITION],
            Opportunity::COMPANY => $data[Opportunity::COMPANY],
            Opportunity::LOCATION => mb_strtoupper($data[Opportunity::LOCATION]),
            Opportunity::TAGS => implode(' ', $data[Opportunity::TAGS]),
            Opportunity::SALARY => $data[Opportunity::SALARY],
            Opportunity::URL => $data[Opportunity::URL],
            Opportunity::ORIGIN => $data[Opportunity::ORIGIN],
            Opportunity::EMAILS => $data[Opportunity::EMAILS],
        ]);
    }
}
