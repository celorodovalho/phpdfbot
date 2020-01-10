<?php

namespace App\Http\Controllers;

use App\Contracts\Validation\CreateUpdateInterface;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Prettus\Repository\Contracts\RepositoryCriteriaInterface;
use Prettus\Repository\Contracts\RepositoryInterface;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Validator\Contracts\ValidatorInterface;

/**
 * Class Controller
 * @property RepositoryInterface|RepositoryCriteriaInterface $repository
 * @property ValidatorInterface $validator
 */
class ApiController extends Controller
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

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $this->repository->pushCriteria(app(RequestCriteria::class));
        return $this->repository->all();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  CreateUpdateInterface $request
     *
     * @return \Illuminate\Http\Response
     *
     * @throws \Prettus\Validator\Exceptions\ValidatorException
     */
    protected function storeDefault(CreateUpdateInterface $request)
    {
        $this->validator->with($request->all())->passesOrFail(ValidatorInterface::RULE_CREATE);

        $data = $this->repository->create($request->all());

        return response()->created(
            'Data stored.',
            $data['data']
        );
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  CreateUpdateInterface $request
     * @param  string              $id
     *
     * @return \Illuminate\Http\Response
     *
     * @throws \Prettus\Validator\Exceptions\ValidatorException
     */
    protected function updateDefault(CreateUpdateInterface $request, $id)
    {
        $this->validator->with($request->all())->passesOrFail(ValidatorInterface::RULE_UPDATE);

        $data = $this->repository->update($request->all(), $id);

        return response()->success(null, $data);
    }

    /**
     * Display the specified resource.
     *
     * @param  int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        return $this->repository->find($id);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int $id
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $deleted = $this->repository->delete($id);
        return response()->success('Successfully deleted!', $deleted);
    }
}
