<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Prettus\Repository\Contracts\RepositoryCriteriaInterface;
use Prettus\Repository\Contracts\RepositoryInterface;
use Prettus\Validator\Contracts\ValidatorInterface;

/**
 * Class Controller
 *
 * @property RepositoryInterface|RepositoryCriteriaInterface $repository
 * @property ValidatorInterface                              $validator
 */
class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * @var RepositoryInterface|RepositoryCriteriaInterface
     */
    protected $repository;

    /**
     * @var ValidatorInterface
     */
    protected $validator;

    /**
     * Controller constructor.
     *
     * @param RepositoryInterface $repository
     * @param ValidatorInterface  $validator
     */
    public function __construct(RepositoryInterface $repository, ValidatorInterface $validator)
    {
        $this->repository = $repository;
        $this->validator = $validator;
    }
}
