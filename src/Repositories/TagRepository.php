<?php

namespace Modules\Glitter\Crm\Repositories;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Glitter\Crm\Models\Contact;
use Modules\Glitter\Crm\Models\Tag;
use Modules\Glitter\Crm\Repositories\Contracts\TagRepositoryInterface;

class TagRepository implements TagRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Tag::query();

        $this->applyFilters($query, $filters);
        $this->applySorting($query, $filters);

        return $query->paginate($perPage);
    }

    public function findByUuid(string $uuid): ?Tag
    {
        return Tag::query()->where('uuid', $uuid)->first();
    }

    public function findByUuidOrFail(string $uuid): Tag
    {
        return Tag::query()->where('uuid', $uuid)->firstOrFail();
    }

    public function findManyByUuidsKeyed(array $uuids): Collection
    {
        return Tag::query()
            ->whereIn('uuid', $uuids)
            ->get()
            ->keyBy('uuid');
    }

    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        return Tag::query()
            ->withTrashed()
            ->where('slug', $slug)
            ->when($exceptId !== null, fn (Builder $query) => $query->whereKeyNot($exceptId))
            ->exists();
    }

    public function create(array $data): Tag
    {
        return Tag::create($data);
    }

    public function update(Tag $tag, array $data): Tag
    {
        $tag->fill($data)->save();

        return $tag->fresh();
    }

    public function delete(Tag $tag): bool
    {
        return (bool) $tag->delete();
    }

    public function attachToContact(Contact $contact, Tag $tag): Contact
    {
        $contact->tags()->syncWithoutDetaching([$tag->getKey()]);

        return $contact->load('tags');
    }

    public function detachFromContact(Contact $contact, Tag $tag): Contact
    {
        $contact->tags()->detach($tag->getKey());

        return $contact->load('tags');
    }

    public function syncForContact(Contact $contact, array $tagIds): Contact
    {
        $contact->tags()->sync($tagIds);

        return $contact->load('tags');
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        $keyword = trim((string) ($filters['keyword'] ?? ''));
        if ($keyword === '') {
            return;
        }

        $query->where(function (Builder $query) use ($keyword): void {
            foreach (['name', 'slug', 'description'] as $index => $column) {
                $method = $index === 0 ? 'where' : 'orWhere';
                $query->{$method}($column, 'like', '%'.$keyword.'%');
            }
        });
    }

    private function applySorting(Builder $query, array $filters): void
    {
        $sort = (string) ($filters['sort'] ?? 'name');
        if (! in_array($sort, TagRepositoryInterface::SORTABLE_COLUMNS, true)) {
            $sort = 'name';
        }

        $direction = strtolower((string) ($filters['direction'] ?? 'asc'));
        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'asc';
        }

        $query->orderBy($sort, $direction)->orderBy('id');
    }
}
