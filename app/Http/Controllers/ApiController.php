<?php

namespace App\Http\Controllers;

use App\Contracts\Validation\CreateUpdateInterface;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Validator\Contracts\ValidatorInterface;

/**
 * Class ApiController
 */
class ApiController extends Controller
{

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
