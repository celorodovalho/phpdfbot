<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Repositories\OpportunityRepository;
use App\Http\Controllers\ApiController;
use App\Http\Requests\OpportunityCreateRequest;
use App\Http\Requests\OpportunityUpdateRequest;
use App\Validators\OpportunityValidator;
use Illuminate\Http\Response;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Validator\Contracts\ValidatorInterface;
use Prettus\Validator\Exceptions\ValidatorException;

/**
 * Class OpportunityController
 *
 * @author Marcelo Rodovalho <rodovalhomf@gmail.com>
 */
class OpportunityController extends ApiController
{

    /**
     * OpportunityController constructor.
     *
     * @param OpportunityRepository $repository
     * @param OpportunityValidator  $validator
     */
    public function __construct(OpportunityRepository $repository, OpportunityValidator $validator)
    {
        parent::__construct($repository, $validator);
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index(): Response
    {
        $this->repository->pushCriteria(app(RequestCriteria::class));
        $opportunities = $this->repository->all();

        if (request()->wantsJson()) {
            return response()->json([
                'data' => $opportunities,
            ]);
        }

        return view('opportunities.index', compact('opportunities'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param OpportunityCreateRequest $request
     *
     * @return Response
     */
    public function store(OpportunityCreateRequest $request): ?Response
    {
        try {
            $this->validator->with($request->all())->passesOrFail(ValidatorInterface::RULE_CREATE);

            $opportunity = $this->repository->create($request->all());

            $response = [
                'message' => 'Opportunity created.',
                'data' => $opportunity->toArray(),
            ];

            if ($request->wantsJson()) {
                return response()->json($response);
            }

            return redirect()->back()->with('message', $response['message']);
        } catch (ValidatorException $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'error' => true,
                    'message' => $e->getMessageBag()
                ]);
            }

            return redirect()->back()->withErrors($e->getMessageBag())->withInput();
        }
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     *
     * @return Response
     */
    public function show($id): Response
    {
        $opportunity = $this->repository->find($id);

        if (request()->wantsJson()) {
            return response()->json([
                'data' => $opportunity,
            ]);
        }

        return view('opportunities.show', compact('opportunity'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     *
     * @return Response
     */
    public function edit($id): Response
    {
        $opportunity = $this->repository->find($id);

        return view('opportunities.edit', compact('opportunity'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param OpportunityUpdateRequest $request
     * @param string                   $id
     *
     * @return Response
     */
    public function update(OpportunityUpdateRequest $request, $id): ?Response
    {
        try {
            $this->validator->with($request->all())->passesOrFail(ValidatorInterface::RULE_UPDATE);

            $opportunity = $this->repository->update($request->all(), $id);

            $response = [
                'message' => 'Opportunity updated.',
                'data' => $opportunity->toArray(),
            ];

            if ($request->wantsJson()) {
                return response()->json($response);
            }

            return redirect()->back()->with('message', $response['message']);
        } catch (ValidatorException $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'error' => true,
                    'message' => $e->getMessageBag()
                ]);
            }

            return redirect()->back()->withErrors($e->getMessageBag())->withInput();
        }
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     *
     * @return Response
     */
    public function destroy($id): Response
    {
        $deleted = $this->repository->delete($id);

        if (request()->wantsJson()) {
            return response()->json([
                'message' => 'Opportunity deleted.',
                'deleted' => $deleted,
            ]);
        }

        return redirect()->back()->with('message', 'Opportunity deleted.');
    }
}
