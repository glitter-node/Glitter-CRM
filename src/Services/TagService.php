<?php

namespace Modules\Glitter\Crm\Services;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Modules\Glitter\Crm\Models\Contact;
use Modules\Glitter\Crm\Models\Tag;
use Modules\Glitter\Crm\Repositories\Contracts\ContactRepositoryInterface;
use Modules\Glitter\Crm\Repositories\Contracts\TagRepositoryInterface;

class TagService
{
    private const WRITABLE_FIELDS = [
        'name',
        'slug',
        'description',
        'color',
    ];

    public function __construct(
        private readonly TagRepositoryInterface $tags,
        private readonly ContactRepositoryInterface $contacts,
    ) {}

    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        if (array_key_exists('search', $filters)) {
            $filters['keyword'] = $filters['search'];
            unset($filters['search']);
        }

        return $this->tags->paginate($filters, $perPage);
    }

    public function findByUuid(string $uuid): Tag
    {
        $this->assertUuid($uuid);

        return $this->tags->findByUuidOrFail($uuid);
    }

    public function create(array $data): Tag
    {
        $data = $this->normalize($data);

        try {
            return DB::transaction(fn (): Tag => $this->tags->create($data));
        } catch (QueryException $exception) {
            $this->throwSlugUniqueViolation($exception);
        }
    }

    public function update(Tag $tag, array $data): Tag
    {
        $data = $this->normalize($data, $tag);

        try {
            return DB::transaction(fn (): Tag => $this->tags->update($tag, $data));
        } catch (QueryException $exception) {
            $this->throwSlugUniqueViolation($exception);
        }
    }

    public function delete(Tag $tag): bool
    {
        return DB::transaction(fn (): bool => $this->tags->delete($tag));
    }

    public function attachToContact(string $contactUuid, string $tagUuid): Contact
    {
        return DB::transaction(function () use ($contactUuid, $tagUuid): Contact {
            $contact = $this->resolveContact($contactUuid);
            $tag = $this->resolveTag($tagUuid);

            return $this->tags->attachToContact($contact, $tag);
        });
    }

    public function detachFromContact(string $contactUuid, string $tagUuid): Contact
    {
        return DB::transaction(function () use ($contactUuid, $tagUuid): Contact {
            $contact = $this->resolveContact($contactUuid);
            $tag = $this->resolveTag($tagUuid);

            return $this->tags->detachFromContact($contact, $tag);
        });
    }

    /**
     * Contact의 Tag 연결을 UUID 배열과 정확히 일치시킵니다.
     *
     * 빈 배열은 전체 연결 해제입니다. null은 이 계약에 포함되지 않습니다.
     * 존재하지 않거나 UUID 형식이 아닌 값이 하나라도 있으면 변경하지 않습니다.
     *
     * @param  array<int, string>  $tagUuids
     */
    public function syncForContact(string $contactUuid, array $tagUuids): Contact
    {
        return DB::transaction(function () use ($contactUuid, $tagUuids): Contact {
            $contact = $this->resolveContact($contactUuid);
            $tagUuids = array_values(array_unique($tagUuids));

            foreach ($tagUuids as $tagUuid) {
                if (! is_string($tagUuid)) {
                    throw new InvalidArgumentException(__('glitter-crm::module.validation.tag.uuid_invalid'));
                }
                $this->assertUuid($tagUuid);
            }

            $tags = $this->tags->findManyByUuidsKeyed($tagUuids);
            if ($tags->count() !== count($tagUuids)) {
                throw (new ModelNotFoundException)->setModel(Tag::class, $tagUuids);
            }

            return $this->tags->syncForContact(
                $contact,
                $tags->map(fn (Tag $tag): int => (int) $tag->getKey())->values()->all(),
            );
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function normalize(array $data, ?Tag $existing = null): array
    {
        $data = array_intersect_key($data, array_flip(self::WRITABLE_FIELDS));

        if ($existing === null || array_key_exists('name', $data)) {
            if (! isset($data['name']) || ! is_string($data['name'])) {
                $this->validationError('name', 'name_required');
            }

            $name = trim($data['name']);
            if ($name === '') {
                $this->validationError('name', 'name_required');
            }
            if (mb_strlen($name) > 255) {
                $this->validationError('name', 'name_too_long');
            }
            $data['name'] = $name;
        }

        if (array_key_exists('slug', $data) && ($data['slug'] === null || $data['slug'] === '')) {
            unset($data['slug']);
        }

        if ($existing === null || array_key_exists('slug', $data) || array_key_exists('name', $data)) {
            if (array_key_exists('slug', $data) && ! is_string($data['slug'])) {
                $this->validationError('slug', 'slug_invalid');
            }

            $slugSource = array_key_exists('slug', $data)
                ? $data['slug']
                : ($data['name'] ?? $existing?->name);
            $data['slug'] = $this->normalizeSlug($slugSource);

            if ($this->tags->slugExists($data['slug'], $existing?->getKey())) {
                $this->validationError('slug', 'slug_duplicate');
            }
        }

        if (array_key_exists('description', $data)) {
            if ($data['description'] !== null && ! is_string($data['description'])) {
                $this->validationError('description', 'description_invalid');
            }

            $description = trim((string) $data['description']);
            $data['description'] = $description === '' ? null : $description;
        }

        if (array_key_exists('color', $data)) {
            if ($data['color'] !== null && ! is_string($data['color'])) {
                $this->validationError('color', 'color_invalid');
            }

            $color = strtoupper(trim((string) $data['color']));
            if ($color === '') {
                $data['color'] = null;
            } elseif (preg_match('/^#[0-9A-F]{6}$/', $color) !== 1) {
                $this->validationError('color', 'color_invalid');
            } else {
                $data['color'] = $color;
            }
        }

        return $data;
    }

    private function normalizeSlug(string $value): string
    {
        $slug = preg_replace('/[^\p{L}\p{N}]+/u', '-', Str::lower(trim($value)));
        $slug = trim((string) $slug, '-');

        if ($slug === '') {
            $this->validationError('slug', 'slug_invalid');
        }
        if (mb_strlen($slug) > 191) {
            $this->validationError('slug', 'slug_too_long');
        }

        return $slug;
    }

    private function resolveContact(string $uuid): Contact
    {
        $this->assertUuid($uuid);

        return $this->contacts->findByUuidOrFail($uuid);
    }

    private function resolveTag(string $uuid): Tag
    {
        $this->assertUuid($uuid);

        return $this->tags->findByUuidOrFail($uuid);
    }

    private function assertUuid(string $uuid): void
    {
        if (! Str::isUuid($uuid)) {
            throw new InvalidArgumentException(__('glitter-crm::module.validation.tag.uuid_invalid'));
        }
    }

    private function validationError(string $field, string $messageKey): never
    {
        throw ValidationException::withMessages([
            $field => [__("glitter-crm::module.validation.tag.{$messageKey}")],
        ]);
    }

    private function throwSlugUniqueViolation(QueryException $exception): never
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        $message = strtolower($exception->getMessage());

        if ($sqlState === '23000' && str_contains($message, 'slug')) {
            $this->validationError('slug', 'slug_duplicate');
        }

        throw $exception;
    }
}
