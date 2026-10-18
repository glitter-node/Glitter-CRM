<?php

namespace Modules\Glitter\Crm\Services;

use App\Contracts\Repositories\UserRepositoryInterface;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JsonException;
use Modules\Glitter\Crm\Enums\ActivityType;
use Modules\Glitter\Crm\Models\Activity;
use Modules\Glitter\Crm\Models\Contact;
use Modules\Glitter\Crm\Repositories\Contracts\ActivityRepositoryInterface;
use Modules\Glitter\Crm\Repositories\Contracts\ContactRepositoryInterface;
use Throwable;

class ActivityService
{
    private const WRITABLE_FIELDS = [
        'type',
        'subject',
        'body',
        'occurred_at',
        'metadata',
    ];

    public function __construct(
        private readonly ActivityRepositoryInterface $activities,
        private readonly ContactRepositoryInterface $contacts,
        private readonly UserRepositoryInterface $users,
    ) {}

    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        if (array_key_exists('search', $filters)) {
            $filters['keyword'] = $filters['search'];
            unset($filters['search']);
        }

        foreach (['contact_uuid', 'created_by_uuid'] as $field) {
            if (! empty($filters[$field])) {
                $this->assertUuid($filters[$field], $field);
            }
        }

        if (! empty($filters['type'])) {
            $filters['type'] = $this->normalizeType($filters['type'])->value;
        }
        foreach (['occurred_from', 'occurred_to'] as $field) {
            if (! empty($filters[$field])) {
                $filters[$field] = $this->normalizeOccurredAt($filters[$field], $field);
            }
        }

        return $this->activities->paginate($filters, $perPage);
    }

    public function paginateForContact(
        string $contactUuid,
        array $filters = [],
        int $perPage = 20,
    ): LengthAwarePaginator {
        $this->assertUuid($contactUuid, 'contact_uuid');
        $this->contacts->findByUuidOrFail($contactUuid);
        $filters['contact_uuid'] = $contactUuid;

        return $this->paginate($filters, $perPage);
    }

    public function findByUuid(string $uuid): Activity
    {
        $this->assertUuid($uuid, 'uuid');

        return $this->activities->findByUuidOrFail($uuid);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?string $creatorUuid = null): Activity
    {
        if (! isset($data['contact_uuid']) || ! is_string($data['contact_uuid'])) {
            $this->validationError('contact_uuid', 'contact_uuid_invalid');
        }

        $this->assertUuid($data['contact_uuid'], 'contact_uuid');
        $contact = $this->contacts->findByUuidOrFail($data['contact_uuid']);
        $creatorId = $this->resolveCreatorId($creatorUuid);
        $normalized = $this->normalize($data);
        $normalized['contact_id'] = $contact->getKey();
        $normalized['created_by'] = $creatorId;

        return DB::transaction(function () use ($normalized, $contact): Activity {
            $activity = $this->activities->create($normalized);
            $this->refreshLastActivityAt($contact);

            return $activity;
        });
    }

    /**
     * 인증 관리자가 직접 기록할 수 있는 Activity만 생성합니다.
     *
     * @param  array<string, mixed>  $data
     */
    public function createByAdmin(array $data, string $creatorUuid): Activity
    {
        $this->assertAdminWritableType($data['type'] ?? null);

        return $this->create($data, $creatorUuid);
    }

    /**
     * Activity의 Contact와 작성자는 생성 후 변경할 수 없습니다.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Activity $activity, array $data): Activity
    {
        $normalized = $this->normalize($data, $activity);

        return DB::transaction(function () use ($activity, $normalized): Activity {
            $updated = $this->activities->update($activity, $normalized);
            $this->refreshLastActivityAt($activity->contact);

            return $updated;
        });
    }

    /**
     * 관리자 API에서 system Activity와 system type 위장을 차단합니다.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     */
    public function updateByAdmin(Activity $activity, array $data): Activity
    {
        $this->assertNotProtectedSystemActivity($activity, 'system_update_forbidden');
        if (array_key_exists('type', $data)) {
            $this->assertAdminWritableType($data['type']);
        }

        return $this->update($activity, $data);
    }

    public function delete(Activity $activity): bool
    {
        return DB::transaction(function () use ($activity): bool {
            $contact = $activity->contact;
            $deleted = $this->activities->delete($activity);
            $this->refreshLastActivityAt($contact);

            return $deleted;
        });
    }

    /**
     * @throws AuthorizationException
     */
    public function deleteByAdmin(Activity $activity): bool
    {
        $this->assertNotProtectedSystemActivity($activity, 'system_delete_forbidden');

        return $this->delete($activity);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data, ?Activity $existing = null): array
    {
        $data = array_intersect_key($data, array_flip(self::WRITABLE_FIELDS));

        if ($existing === null || array_key_exists('type', $data)) {
            if (! array_key_exists('type', $data)) {
                $this->validationError('type', 'type_invalid');
            }
            $data['type'] = $this->normalizeType($data['type'])->value;
        }

        foreach (['subject', 'body'] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }
            if ($data[$field] !== null && ! is_string($data[$field])) {
                $this->validationError($field, "{$field}_invalid");
            }

            $value = trim((string) $data[$field]);
            if ($field === 'subject' && mb_strlen($value) > 255) {
                $this->validationError($field, 'subject_too_long');
            }
            $data[$field] = $value === '' ? null : $value;
        }

        $subject = $data['subject'] ?? $existing?->subject;
        $body = $data['body'] ?? $existing?->body;
        if ($subject === null && $body === null) {
            $this->validationError('body', 'content_required');
        }

        if ($existing === null || array_key_exists('occurred_at', $data)) {
            if (! array_key_exists('occurred_at', $data)) {
                $this->validationError('occurred_at', 'occurred_at_required');
            }
            $data['occurred_at'] = $this->normalizeOccurredAt($data['occurred_at']);
        }

        if (array_key_exists('metadata', $data)) {
            $data['metadata'] = $this->normalizeMetadata($data['metadata']);
        }

        return $data;
    }

    private function normalizeType(mixed $value): ActivityType
    {
        if ($value instanceof ActivityType) {
            return $value;
        }
        if (! is_string($value)) {
            $this->validationError('type', 'type_invalid');
        }

        $type = ActivityType::tryFrom(strtolower(trim($value)));
        if ($type === null) {
            $this->validationError('type', 'type_invalid');
        }

        return $type;
    }

    private function normalizeOccurredAt(mixed $value, string $field = 'occurred_at'): CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::instance($value)->utc();
        }
        if (! is_string($value) || preg_match('/(?:Z|[+-]\d{2}:\d{2})$/', trim($value)) !== 1) {
            $this->validationError($field, 'occurred_at_invalid');
        }

        try {
            return CarbonImmutable::parse(trim($value))->utc();
        } catch (Throwable) {
            $this->validationError($field, 'occurred_at_invalid');
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeMetadata(mixed $metadata): ?array
    {
        if ($metadata === null) {
            return null;
        }
        if (! is_array($metadata) || ($metadata !== [] && array_is_list($metadata))) {
            $this->validationError('metadata', 'metadata_invalid');
        }

        try {
            json_encode($metadata, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $this->validationError('metadata', 'metadata_invalid');
        }

        return $metadata;
    }

    private function resolveCreatorId(?string $creatorUuid): ?int
    {
        if ($creatorUuid === null) {
            return null;
        }

        $this->assertUuid($creatorUuid, 'creator_uuid');
        $creator = $this->users->findManyByUuidsKeyed([$creatorUuid])->get($creatorUuid);
        if ($creator === null) {
            $this->validationError('creator_uuid', 'creator_not_found');
        }

        return (int) $creator->getKey();
    }

    private function assertAdminWritableType(mixed $value): void
    {
        $type = $this->normalizeType($value);
        if ($type === ActivityType::System) {
            $this->validationError('type', 'system_type_forbidden');
        }
    }

    /**
     * @throws AuthorizationException
     */
    private function assertNotProtectedSystemActivity(Activity $activity, string $messageKey): void
    {
        if ($activity->type === ActivityType::System) {
            throw new AuthorizationException(
                __("glitter-crm::module.validation.activity.{$messageKey}"),
            );
        }
    }

    private function refreshLastActivityAt(Contact $contact): void
    {
        $this->contacts->updateLastActivityAt(
            $contact,
            $this->activities->maxOccurredAtForContact($contact),
        );
    }

    private function assertUuid(mixed $uuid, string $field): void
    {
        if (! is_string($uuid) || ! Str::isUuid($uuid)) {
            $message = $field === 'creator_uuid'
                ? 'creator_uuid_invalid'
                : 'contact_uuid_invalid';
            if ($field === 'uuid') {
                $message = 'uuid_invalid';
            }
            $this->validationError($field, $message);
        }
    }

    private function validationError(string $field, string $messageKey): never
    {
        throw ValidationException::withMessages([
            $field => [__("glitter-crm::module.validation.activity.{$messageKey}")],
        ]);
    }
}
