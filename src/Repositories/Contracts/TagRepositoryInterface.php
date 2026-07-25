<?php

namespace Modules\Glitter\Crm\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Glitter\Crm\Models\Contact;
use Modules\Glitter\Crm\Models\Tag;

interface TagRepositoryInterface
{
    public const SORTABLE_COLUMNS = [
        'name',
        'slug',
        'created_at',
        'updated_at',
    ];

    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator;

    public function findByUuid(string $uuid): ?Tag;

    public function findByUuidOrFail(string $uuid): Tag;

    /**
     * @param  array<int, string>  $uuids
     * @return Collection<string, Tag>
     */
    public function findManyByUuidsKeyed(array $uuids): Collection;

    public function slugExists(string $slug, ?int $exceptId = null): bool;

    public function create(array $data): Tag;

    public function update(Tag $tag, array $data): Tag;

    public function delete(Tag $tag): bool;

    public function attachToContact(Contact $contact, Tag $tag): Contact;

    public function detachFromContact(Contact $contact, Tag $tag): Contact;

    /**
     * @param  array<int, int>  $tagIds
     */
    public function syncForContact(Contact $contact, array $tagIds): Contact;
}
