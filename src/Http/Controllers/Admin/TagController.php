<?php

namespace Modules\Glitter\Crm\Http\Controllers\Admin;

use App\Http\Controllers\Api\Base\AdminBaseController;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Modules\Glitter\Crm\Http\Requests\IndexTagRequest;
use Modules\Glitter\Crm\Http\Requests\StoreTagRequest;
use Modules\Glitter\Crm\Http\Requests\UpdateTagRequest;
use Modules\Glitter\Crm\Http\Resources\TagCollection;
use Modules\Glitter\Crm\Http\Resources\TagResource;
use Modules\Glitter\Crm\Services\TagService;

class TagController extends AdminBaseController
{
    public function __construct(
        private readonly TagService $tags,
    ) {
        parent::__construct();
    }

    public function index(IndexTagRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $tags = $this->tags->paginate(
            array_filter($validated, fn (mixed $value): bool => $value !== null),
            $validated['per_page'] ?? 20,
        );

        return $this->success(
            'glitter-crm::module.messages.tag.fetch_success',
            new TagCollection($tags),
        );
    }

    public function store(StoreTagRequest $request): JsonResponse
    {
        $tag = $this->tags->create($request->validated());

        return $this->successWithResource(
            'glitter-crm::module.messages.tag.create_success',
            new TagResource($tag),
            201,
        );
    }

    public function show(string $tag): JsonResponse
    {
        try {
            return $this->successWithResource(
                'glitter-crm::module.messages.tag.fetch_success',
                new TagResource($this->tags->findByUuid($tag)),
            );
        } catch (ModelNotFoundException) {
            return $this->notFound('glitter-crm::module.messages.tag.not_found');
        }
    }

    public function update(UpdateTagRequest $request, string $tag): JsonResponse
    {
        try {
            $model = $this->tags->findByUuid($tag);
            $model = $this->tags->update($model, $request->validated());

            return $this->successWithResource(
                'glitter-crm::module.messages.tag.update_success',
                new TagResource($model),
            );
        } catch (ModelNotFoundException) {
            return $this->notFound('glitter-crm::module.messages.tag.not_found');
        }
    }

    public function destroy(string $tag): JsonResponse
    {
        try {
            $model = $this->tags->findByUuid($tag);
            $this->tags->delete($model);

            return $this->success('glitter-crm::module.messages.tag.delete_success');
        } catch (ModelNotFoundException) {
            return $this->notFound('glitter-crm::module.messages.tag.not_found');
        }
    }
}
