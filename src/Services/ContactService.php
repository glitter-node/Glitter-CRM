<?php

namespace Modules\Glitter\Crm\Services;

use App\Contracts\Repositories\UserRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Modules\Glitter\Crm\Enums\ContactStatus;
use Modules\Glitter\Crm\Models\Contact;
use Modules\Glitter\Crm\Models\Tag;
use Modules\Glitter\Crm\Repositories\Contracts\ContactRepositoryInterface;
use Modules\Glitter\Crm\Repositories\Contracts\TagRepositoryInterface;

class ContactService
{
    private const WRITABLE_FIELDS = [
        'user_id',
        'status',
        'name',
        'company',
        'job_title',
        'email',
        'phone',
        'mobile',
        'locale',
        'timezone',
        'source',
        'assigned_user_id',
        'next_action_at',
        'metadata',
    ];

    private const NULLABLE_STRING_FIELDS = [
        'company',
        'job_title',
        'email',
        'phone',
        'mobile',
        'locale',
        'timezone',
        'source',
    ];

    public function __construct(
        private readonly ContactRepositoryInterface $contacts,
        private readonly UserRepositoryInterface $users,
        private readonly TagRepositoryInterface $tags,
    ) {}

    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $filters = $this->resolveUserReferences($filters);

        return $this->contacts->paginate($filters, $perPage);
    }

    public function findByUuid(string $uuid): Contact
    {
        return $this->contacts->findByUuidOrFail($uuid);
    }

    public function create(array $data): Contact
    {
        [$data, $tagUuids, $syncTags] = $this->extractTagUuids($data);
        $data = $this->normalize($data, creating: true);

        return DB::transaction(function () use ($data, $tagUuids, $syncTags): Contact {
            $contact = $this->contacts->create($data);

            return $syncTags
                ? $this->syncTags($contact, $tagUuids)
                : $contact;
        });
    }

    public function update(Contact $contact, array $data): Contact
    {
        [$data, $tagUuids, $syncTags] = $this->extractTagUuids($data);
        $data = $this->normalize($data, creating: false);

        return DB::transaction(function () use ($contact, $data, $tagUuids, $syncTags): Contact {
            $contact = $this->contacts->update($contact, $data);

            return $syncTags
                ? $this->syncTags($contact, $tagUuids)
                : $contact;
        });
    }

    public function delete(Contact $contact): bool
    {
        return DB::transaction(fn (): bool => $this->contacts->delete($contact));
    }

    /**
     * FormRequest가 도입되기 전에도 도메인의 최소 불변 조건을 보장합니다.
     *
     * 이메일은 표시값의 대소문자를 보존하고 공백만 제거합니다. 별도의
     * normalized_email은 중복 병합 정책이 확정되는 후속 단계에서 검토합니다.
     *
     * @return array<string, mixed>
     */
    private function normalize(array $data, bool $creating): array
    {
        $data = array_intersect_key($data, array_flip(self::WRITABLE_FIELDS));
        $data = $this->resolveUserReferences($data);

        if ($creating || array_key_exists('name', $data)) {
            $name = trim((string) ($data['name'] ?? ''));
            if ($name === '') {
                throw new InvalidArgumentException(__('glitter-crm::module.validation.contact.name_required'));
            }
            $data['name'] = $name;
        }

        foreach (self::NULLABLE_STRING_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $value = trim((string) $data[$field]);
                $data[$field] = $value === '' ? null : $value;
            }
        }

        if (! array_key_exists('status', $data) && $creating) {
            $data['status'] = ContactStatus::Active->value;
        } elseif (array_key_exists('status', $data)) {
            $status = $data['status'] instanceof ContactStatus
                ? $data['status']
                : ContactStatus::tryFrom((string) $data['status']);

            if ($status === null) {
                throw new InvalidArgumentException(__('glitter-crm::module.validation.contact.status_invalid'));
            }
            $data['status'] = $status->value;
        }

        if (array_key_exists('metadata', $data) && ! is_array($data['metadata']) && $data['metadata'] !== null) {
            throw new InvalidArgumentException(__('glitter-crm::module.validation.contact.metadata_array'));
        }

        return $data;
    }

    /**
     * API의 공개 User UUID를 Contact의 내부 bigint FK로 변환합니다.
     *
     * 데이터 계층 내부 호출의 정수 ID는 그대로 유지합니다.
     *
     * @return array<string, mixed>
     */
    private function resolveUserReferences(array $data): array
    {
        $fields = ['user_id', 'assigned_user_id'];
        $uuids = [];

        foreach ($fields as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $uuids[] = $data[$field];
            }
        }

        if ($uuids === []) {
            return $data;
        }

        $users = $this->users->findManyByUuidsKeyed(array_values(array_unique($uuids)));

        foreach ($fields as $field) {
            if (! isset($data[$field]) || ! is_string($data[$field])) {
                continue;
            }

            $user = $users->get($data[$field]);
            if ($user === null) {
                throw new InvalidArgumentException(__('glitter-crm::module.validation.contact.user_not_found'));
            }

            $data[$field] = $user->id;
        }

        return $data;
    }

    /**
     * @return array{0: array<string, mixed>, 1: array<int, string>, 2: bool}
     */
    private function extractTagUuids(array $data): array
    {
        if (! array_key_exists('tag_uuids', $data)) {
            return [$data, [], false];
        }

        $tagUuids = $data['tag_uuids'];
        unset($data['tag_uuids']);

        if ($tagUuids === null) {
            return [$data, [], true];
        }
        if (! is_array($tagUuids)) {
            $this->tagValidationError();
        }

        $tagUuids = array_values(array_unique($tagUuids));
        foreach ($tagUuids as $tagUuid) {
            if (! is_string($tagUuid) || ! Str::isUuid($tagUuid)) {
                $this->tagValidationError();
            }
        }

        return [$data, $tagUuids, true];
    }

    /**
     * @param  array<int, string>  $tagUuids
     */
    private function syncTags(Contact $contact, array $tagUuids): Contact
    {
        $tags = $this->tags->findManyByUuidsKeyed($tagUuids);
        if ($tags->count() !== count($tagUuids)) {
            $this->tagValidationError('tag_not_found');
        }

        $contact = $this->tags->syncForContact(
            $contact,
            $tags->map(fn (Tag $tag): int => (int) $tag->getKey())->values()->all(),
        );

        return $contact->loadMissing(['user', 'assignedUser', 'tags']);
    }

    private function tagValidationError(string $message = 'tag_uuid_invalid'): never
    {
        throw ValidationException::withMessages([
            'tag_uuids' => [__("glitter-crm::module.validation.contact.{$message}")],
        ]);
    }
}
