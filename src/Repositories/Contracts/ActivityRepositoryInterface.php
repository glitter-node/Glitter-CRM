<?php

namespace Modules\Glitter\Crm\Repositories\Contracts;

use Carbon\CarbonInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Glitter\Crm\Models\Activity;
use Modules\Glitter\Crm\Models\Contact;

interface ActivityRepositoryInterface
{
    public const SORTABLE_COLUMNS = [
        'occurred_at',
        'created_at',
        'updated_at',
        'type',
    ];

    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    public function findByUuid(string $uuid): ?Activity;

    public function findByUuidOrFail(string $uuid): Activity;

    public function create(array $data): Activity;

    public function update(Activity $activity, array $data): Activity;

    public function delete(Activity $activity): bool;

    public function maxOccurredAtForContact(Contact $contact): ?CarbonInterface;
}
