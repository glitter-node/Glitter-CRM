<?php

namespace Modules\Glitter\Crm\Repositories;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Glitter\Crm\Models\Contact;
use Modules\Glitter\Crm\Repositories\Contracts\ContactRepositoryInterface;

class ContactRepository implements ContactRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Contact::query()->with(['user', 'assignedUser', 'tags']);

        $this->applyFilters($query, $filters);
        $this->applySorting($query, $filters);

        return $query->paginate($perPage);
    }

    public function findByUuid(string $uuid): ?Contact
    {
        return Contact::query()
            ->with(['user', 'assignedUser', 'tags'])
            ->where('uuid', $uuid)
            ->first();
    }

    public function findByUuidOrFail(string $uuid): Contact
    {
        return Contact::query()
            ->with(['user', 'assignedUser', 'tags'])
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    public function create(array $data): Contact
    {
        return Contact::create($data)->load(['user', 'assignedUser', 'tags']);
    }

    public function update(Contact $contact, array $data): Contact
    {
        $contact->fill($data)->save();

        return $contact->fresh(['user', 'assignedUser', 'tags']);
    }

    public function delete(Contact $contact): bool
    {
        return (bool) $contact->delete();
    }

    public function updateLastActivityAt(Contact $contact, ?CarbonInterface $occurredAt): void
    {
        $contact->forceFill(['last_activity_at' => $occurredAt])->saveQuietly();
    }

    public function updateNextTaskAt(Contact $contact, ?CarbonInterface $dueAt): void
    {
        $contact->forceFill(['next_task_at' => $dueAt])->saveQuietly();
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        foreach (['status', 'assigned_user_id', 'user_id'] as $field) {
            if (array_key_exists($field, $filters) && $filters[$field] !== '' && $filters[$field] !== null) {
                $query->where($field, $filters[$field]);
            }
        }

        $keyword = trim((string) ($filters['keyword'] ?? ''));
        if ($keyword === '') {
            return;
        }

        $query->where(function (Builder $query) use ($keyword): void {
            foreach (['name', 'company', 'email', 'phone', 'mobile'] as $index => $column) {
                $method = $index === 0 ? 'where' : 'orWhere';
                $query->{$method}($column, 'like', '%'.$keyword.'%');
            }
        });
    }

    private function applySorting(Builder $query, array $filters): void
    {
        $sort = (string) ($filters['sort'] ?? 'created_at');
        if (! in_array($sort, ContactRepositoryInterface::SORTABLE_COLUMNS, true)) {
            $sort = 'created_at';
        }

        $direction = strtolower((string) ($filters['direction'] ?? 'desc'));
        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        $query->orderBy($sort, $direction);
    }
}
