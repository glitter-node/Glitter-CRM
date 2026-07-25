<?php

namespace Modules\Glitter\Crm\Repositories\Contracts;

use Carbon\CarbonInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Glitter\Crm\Models\Contact;

interface ContactRepositoryInterface
{
    public const SORTABLE_COLUMNS = [
        'name',
        'company',
        'status',
        'created_at',
        'updated_at',
        'last_activity_at',
        'next_action_at',
    ];

    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    public function findByUuid(string $uuid): ?Contact;

    public function findByUuidOrFail(string $uuid): Contact;

    public function create(array $data): Contact;

    public function update(Contact $contact, array $data): Contact;

    public function delete(Contact $contact): bool;

    public function updateLastActivityAt(Contact $contact, ?CarbonInterface $occurredAt): void;

    public function updateNextTaskAt(Contact $contact, ?CarbonInterface $dueAt): void;
}
