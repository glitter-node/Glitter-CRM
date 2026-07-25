<?php

namespace Modules\Glitter\Crm\Http\Controllers\Admin;

use App\Http\Controllers\Api\Base\AdminBaseController;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Modules\Glitter\Crm\Http\Requests\IndexContactRequest;
use Modules\Glitter\Crm\Http\Requests\StoreContactRequest;
use Modules\Glitter\Crm\Http\Requests\UpdateContactRequest;
use Modules\Glitter\Crm\Http\Resources\ContactCollection;
use Modules\Glitter\Crm\Http\Resources\ContactResource;
use Modules\Glitter\Crm\Services\ContactService;

class ContactController extends AdminBaseController
{
    public function __construct(
        private readonly ContactService $contacts,
    ) {
        parent::__construct();
    }

    public function index(IndexContactRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $contacts = $this->contacts->paginate(
            array_filter($validated, fn (mixed $value): bool => $value !== null),
            $validated['per_page'] ?? 20,
        );

        return $this->success(
            'glitter-crm::module.messages.contact.fetch_success',
            new ContactCollection($contacts),
        );
    }

    public function store(StoreContactRequest $request): JsonResponse
    {
        $contact = $this->contacts->create($request->validated());

        return $this->successWithResource(
            'glitter-crm::module.messages.contact.create_success',
            new ContactResource($contact),
            201,
        );
    }

    public function show(string $contact): JsonResponse
    {
        try {
            return $this->successWithResource(
                'glitter-crm::module.messages.contact.fetch_success',
                new ContactResource($this->contacts->findByUuid($contact)),
            );
        } catch (ModelNotFoundException) {
            return $this->notFound('glitter-crm::module.messages.contact.not_found');
        }
    }

    public function update(UpdateContactRequest $request, string $contact): JsonResponse
    {
        try {
            $model = $this->contacts->findByUuid($contact);
            $model = $this->contacts->update($model, $request->validated());

            return $this->successWithResource(
                'glitter-crm::module.messages.contact.update_success',
                new ContactResource($model),
            );
        } catch (ModelNotFoundException) {
            return $this->notFound('glitter-crm::module.messages.contact.not_found');
        }
    }

    public function destroy(string $contact): JsonResponse
    {
        try {
            $model = $this->contacts->findByUuid($contact);
            $this->contacts->delete($model);

            return $this->success('glitter-crm::module.messages.contact.delete_success');
        } catch (ModelNotFoundException) {
            return $this->notFound('glitter-crm::module.messages.contact.not_found');
        }
    }
}
