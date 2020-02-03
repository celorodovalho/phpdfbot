<?php

namespace App\Repositories;

use Prettus\Repository\Eloquent\BaseRepository;
use Prettus\Repository\Criteria\RequestCriteria;
use App\Contracts\Repositories\GroupRepository;
use App\Models\Group;

/**
 * Class GroupRepositoryEloquent
 *
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
class GroupRepositoryEloquent extends BaseRepository implements GroupRepository
{
    /**
     * Specify Model class name
     *
     * @return string
     */
    public function model(): string
    {
        return Group::class;
    }

    /**
     * Boot up the repository, pushing criteria
     */
    public function boot(): void
    {
        $this->pushCriteria(app(RequestCriteria::class));
    }
}
