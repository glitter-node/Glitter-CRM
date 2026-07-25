<?php

namespace Modules\Glitter\Crm\Repositories;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Glitter\Crm\Enums\TaskStatus;
use Modules\Glitter\Crm\Models\Contact;
use Modules\Glitter\Crm\Models\Task;
use Modules\Glitter\Crm\Repositories\Contracts\TaskRepositoryInterface;

class TaskRepository implements TaskRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Task::query()->with(['contact', 'assignee']);

        $this->applyFilters($query, $filters);
        $this->applySorting($query, $filters);

        return $query->paginate($perPage);
    }

    public function findByUuid(string $uuid): ?Task
    {
        return Task::query()
            ->with(['contact', 'assignee'])
            ->where('uuid', $uuid)
            ->first();
    }

    public function findByUuidOrFail(string $uuid): Task
    {
        return Task::query()
            ->with(['contact', 'assignee'])
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    public function create(array $data): Task
    {
        return Task::create($data)->load(['contact', 'assignee']);
    }

    public function update(Task $task, array $data): Task
    {
        $task->fill($data)->save();

        return $task->fresh(['contact', 'assignee']);
    }

    public function delete(Task $task): bool
    {
        return (bool) $task->delete();
    }

    public function minActiveDueAtForContact(Contact $contact): ?CarbonInterface
    {
        $minimum = Task::query()
            ->where('contact_id', $contact->getKey())
            ->whereIn('status', TaskStatus::activeValues())
            ->whereNotNull('due_at')
            ->min('due_at');

        return $minimum === null ? null : Carbon::parse($minimum)->utc();
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['contact_uuid'])) {
            $query->whereHas('contact', fn (Builder $query): Builder => $query->where(
                'uuid',
                $filters['contact_uuid'],
            ));
        }

        if (! empty($filters['assigned_to_uuid'])) {
            $query->whereHas('assignee', fn (Builder $query): Builder => $query->where(
                'uuid',
                $filters['assigned_to_uuid'],
            ));
        }

        foreach (['status', 'priority'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        if (! empty($filters['due_from'])) {
            $query->where('due_at', '>=', $filters['due_from']);
        }
        if (! empty($filters['due_to'])) {
            $query->where('due_at', '<=', $filters['due_to']);
        }

        if (($filters['overdue'] ?? null) === true) {
            $query->whereNotNull('due_at')
                ->where('due_at', '<', $filters['now'] ?? Carbon::now('UTC'))
                ->whereIn('status', TaskStatus::activeValues());
        }

        $keyword = trim((string) ($filters['keyword'] ?? ''));
        if ($keyword !== '') {
            $query->where(function (Builder $query) use ($keyword): void {
                $query->where('title', 'like', '%'.$keyword.'%')
                    ->orWhere('description', 'like', '%'.$keyword.'%');
            });
        }
    }

    private function applySorting(Builder $query, array $filters): void
    {
        $sort = (string) ($filters['sort'] ?? 'created_at');
        if (! in_array($sort, TaskRepositoryInterface::SORTABLE_COLUMNS, true)) {
            $sort = 'created_at';
        }

        $direction = strtolower((string) ($filters['direction'] ?? 'desc'));
        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        $query->orderBy($sort, $direction)->orderBy('id', $direction);
    }
}
