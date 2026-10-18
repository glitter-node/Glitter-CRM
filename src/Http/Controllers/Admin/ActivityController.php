<?php

namespace Modules\Glitter\Crm\Http\Controllers\Admin;

use App\Http\Controllers\Api\Base\AdminBaseController;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Modules\Glitter\Crm\Http\Requests\IndexActivityRequest;
use Modules\Glitter\Crm\Http\Requests\IndexContactActivityRequest;
use Modules\Glitter\Crm\Http\Requests\StoreActivityRequest;
use Modules\Glitter\Crm\Http\Requests\UpdateActivityRequest;
use Modules\Glitter\Crm\Http\Resources\ActivityCollection;
use Modules\Glitter\Crm\Http\Resources\ActivityResource;
use Modules\Glitter\Crm\Services\ActivityService;

class ActivityController extends AdminBaseController
{
    public function __construct(
        private readonly ActivityService $activities,
    ) {
        parent::__construct();
    }

    public function index(IndexActivityRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $activities = $this->activities->paginate(
            array_filter($validated, fn (mixed $value): bool => $value !== null),
            $validated['per_page'] ?? 20,
        );

        return $this->success(
            'glitter-crm::module.messages.activity.fetch_success',
            new ActivityCollection($activities),
        );
    }

    public function contactIndex(
        IndexContactActivityRequest $request,
        string $contact,
    ): JsonResponse {
        try {
            $validated = $request->validated();
            $activities = $this->activities->paginateForContact(
                $contact,
                array_filter($validated, fn (mixed $value): bool => $value !== null),
                $validated['per_page'] ?? 20,
            );

            return $this->success(
                'glitter-crm::module.messages.activity.fetch_success',
                new ActivityCollection($activities),
            );
        } catch (ModelNotFoundException) {
            return $this->notFound('glitter-crm::module.messages.contact.not_found');
        }
    }

    public function store(StoreActivityRequest $request): JsonResponse
    {
        try {
            $activity = $this->activities->createByAdmin(
                $request->validated(),
                (string) $request->user()->uuid,
            );

            return $this->successWithResource(
                'glitter-crm::module.messages.activity.create_success',
                new ActivityResource($activity),
                201,
            );
        } catch (ModelNotFoundException) {
            return $this->notFound('glitter-crm::module.messages.contact.not_found');
        }
    }

    public function show(string $activity): JsonResponse
    {
        try {
            return $this->successWithResource(
                'glitter-crm::module.messages.activity.fetch_success',
                new ActivityResource($this->activities->findByUuid($activity)),
            );
        } catch (ModelNotFoundException) {
            return $this->notFound('glitter-crm::module.messages.activity.not_found');
        }
    }

    public function update(UpdateActivityRequest $request, string $activity): JsonResponse
    {
        try {
            $model = $this->activities->findByUuid($activity);
            $model = $this->activities->updateByAdmin($model, $request->validated());

            return $this->successWithResource(
                'glitter-crm::module.messages.activity.update_success',
                new ActivityResource($model),
            );
        } catch (ModelNotFoundException) {
            return $this->notFound('glitter-crm::module.messages.activity.not_found');
        }
    }

    public function destroy(string $activity): JsonResponse
    {
        try {
            $model = $this->activities->findByUuid($activity);
            $this->activities->deleteByAdmin($model);

            return $this->success('glitter-crm::module.messages.activity.delete_success');
        } catch (ModelNotFoundException) {
            return $this->notFound('glitter-crm::module.messages.activity.not_found');
        }
    }
}
