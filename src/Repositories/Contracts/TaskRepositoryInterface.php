<?php

namespace Modules\Glitter\Crm\Repositories\Contracts;

use Carbon\CarbonInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Glitter\Crm\Models\Contact;
use Modules\Glitter\Crm\Models\Task;

interface TaskRepositoryInterface
{
    public const SORTABLE_COLUMNS = [
        'due_at',
        'created_at',
        'updated_at',
        'status',
        'priority',
        'title',
        'completed_at',
    ];

    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    public function findByUuid(string $uuid): ?Task;

    public function findByUuidOrFail(string $uuid): Task;

    public function create(array $data): Task;

    public function update(Task $task, array $data): Task;

    public function delete(Task $task): bool;

    public function minActiveDueAtForContact(Contact $contact): ?CarbonInterface;
}
