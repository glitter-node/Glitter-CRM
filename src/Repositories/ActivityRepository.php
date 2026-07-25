<?php

namespace Modules\Glitter\Crm\Repositories;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Glitter\Crm\Models\Activity;
use Modules\Glitter\Crm\Models\Contact;
use Modules\Glitter\Crm\Repositories\Contracts\ActivityRepositoryInterface;

class ActivityRepository implements ActivityRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Activity::query()->with(['contact', 'creator']);

        $this->applyFilters($query, $filters);
        $this->applySorting($query, $filters);

        return $query->paginate($perPage);
    }

    public function findByUuid(string $uuid): ?Activity
    {
        return Activity::query()
            ->with(['contact', 'creator'])
            ->where('uuid', $uuid)
            ->first();
    }

    public function findByUuidOrFail(string $uuid): Activity
    {
        return Activity::query()
            ->with(['contact', 'creator'])
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    public function create(array $data): Activity
    {
        return Activity::create($data)->load(['contact', 'creator']);
    }

    public function update(Activity $activity, array $data): Activity
    {
        $activity->fill($data)->save();

        return $activity->fresh(['contact', 'creator']);
    }

    public function delete(Activity $activity): bool
    {
        return (bool) $activity->delete();
    }

    public function maxOccurredAtForContact(Contact $contact): ?CarbonInterface
    {
        $maximum = Activity::query()
            ->where('contact_id', $contact->getKey())
            ->max('occurred_at');

        return $maximum === null ? null : Carbon::parse($maximum)->utc();
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['contact_uuid'])) {
            $query->whereHas('contact', fn (Builder $query): Builder => $query->where(
                'uuid',
                $filters['contact_uuid'],
            ));
        }

        if (! empty($filters['created_by_uuid'])) {
            $query->whereHas('creator', fn (Builder $query): Builder => $query->where(
                'uuid',
                $filters['created_by_uuid'],
            ));
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['occurred_from'])) {
            $query->where('occurred_at', '>=', $filters['occurred_from']);
        }
        if (! empty($filters['occurred_to'])) {
            $query->where('occurred_at', '<=', $filters['occurred_to']);
        }

        $keyword = trim((string) ($filters['keyword'] ?? ''));
        if ($keyword !== '') {
            $query->where(function (Builder $query) use ($keyword): void {
                $query->where('subject', 'like', '%'.$keyword.'%')
                    ->orWhere('body', 'like', '%'.$keyword.'%');
            });
        }
    }

    private function applySorting(Builder $query, array $filters): void
    {
        $sort = (string) ($filters['sort'] ?? 'occurred_at');
        if (! in_array($sort, ActivityRepositoryInterface::SORTABLE_COLUMNS, true)) {
            $sort = 'occurred_at';
        }

        $direction = strtolower((string) ($filters['direction'] ?? 'desc'));
        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        $query->orderBy($sort, $direction)->orderBy('id', $direction);
    }
}
