<?php

namespace App\Repositories;

use App\Contracts\Repositories\UserRepository;
use App\Models\User;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Eloquent\BaseRepository;
use Prettus\Repository\Exceptions\RepositoryException;

/**
 * Class UserRepositoryEloquent
 *
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
class UserRepositoryEloquent extends BaseRepository implements UserRepository
{
    /**
     * Specify Model class name
     *
     * @return string
     */
    public function model(): string
    {
        return User::class;
    }


    /**
     * Boot up the repository, pushing criteria
     *
     * @throws RepositoryException
     */
    public function boot(): void
    {
        $this->pushCriteria(app(RequestCriteria::class));
    }
}
