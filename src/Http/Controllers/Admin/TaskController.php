<?php

namespace Modules\Glitter\Crm\Http\Controllers\Admin;

use App\Http\Controllers\Api\Base\AdminBaseController;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Modules\Glitter\Crm\Http\Requests\IndexContactTaskRequest;
use Modules\Glitter\Crm\Http\Requests\IndexTaskRequest;
use Modules\Glitter\Crm\Http\Requests\StoreTaskRequest;
use Modules\Glitter\Crm\Http\Requests\UpdateTaskRequest;
use Modules\Glitter\Crm\Http\Resources\TaskCollection;
use Modules\Glitter\Crm\Http\Resources\TaskResource;
use Modules\Glitter\Crm\Services\TaskService;

class TaskController extends AdminBaseController
{
    public function __construct(
        private readonly TaskService $tasks,
    ) {
        parent::__construct();
    }

    public function index(IndexTaskRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $tasks = $this->tasks->paginate(
            array_filter($validated, fn (mixed $value): bool => $value !== null),
            $validated['per_page'] ?? 20,
        );

        return $this->success(
            'glitter-crm::module.messages.task.fetch_success',
            new TaskCollection($tasks),
        );
    }

    public function contactIndex(
        IndexContactTaskRequest $request,
        string $contact,
    ): JsonResponse {
        try {
            $validated = $request->validated();
            $tasks = $this->tasks->paginateForContact(
                $contact,
                array_filter($validated, fn (mixed $value): bool => $value !== null),
                $validated['per_page'] ?? 20,
            );

            return $this->success(
                'glitter-crm::module.messages.task.fetch_success',
                new TaskCollection($tasks),
            );
        } catch (ModelNotFoundException) {
            return $this->notFound('glitter-crm::module.messages.contact.not_found');
        }
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        try {
            return $this->successWithResource(
                'glitter-crm::module.messages.task.create_success',
                new TaskResource($this->tasks->create($request->validated())),
                201,
            );
        } catch (ModelNotFoundException) {
            return $this->notFound('glitter-crm::module.messages.contact.not_found');
        }
    }

    public function show(string $task): JsonResponse
    {
        try {
            return $this->successWithResource(
                'glitter-crm::module.messages.task.fetch_success',
                new TaskResource($this->tasks->findByUuid($task)),
            );
        } catch (ModelNotFoundException) {
            return $this->notFound('glitter-crm::module.messages.task.not_found');
        }
    }

    public function update(UpdateTaskRequest $request, string $task): JsonResponse
    {
        try {
            $model = $this->tasks->findByUuid($task);
            $model = $this->tasks->update($model, $request->validated());

            return $this->successWithResource(
                'glitter-crm::module.messages.task.update_success',
                new TaskResource($model),
            );
        } catch (ModelNotFoundException) {
            return $this->notFound('glitter-crm::module.messages.task.not_found');
        }
    }

    public function destroy(string $task): JsonResponse
    {
        try {
            $model = $this->tasks->findByUuid($task);
            $this->tasks->delete($model);

            return $this->success('glitter-crm::module.messages.task.delete_success');
        } catch (ModelNotFoundException) {
            return $this->notFound('glitter-crm::module.messages.task.not_found');
        }
    }
}
