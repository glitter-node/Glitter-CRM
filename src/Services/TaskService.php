<?php

namespace Modules\Glitter\Crm\Services;

use App\Contracts\Repositories\UserRepositoryInterface;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JsonException;
use Modules\Glitter\Crm\Enums\TaskPriority;
use Modules\Glitter\Crm\Enums\TaskStatus;
use Modules\Glitter\Crm\Models\Contact;
use Modules\Glitter\Crm\Models\Task;
use Modules\Glitter\Crm\Repositories\Contracts\ContactRepositoryInterface;
use Modules\Glitter\Crm\Repositories\Contracts\TaskRepositoryInterface;
use Throwable;

class TaskService
{
    private const WRITABLE_FIELDS = [
        'assigned_to_uuid',
        'title',
        'description',
        'status',
        'priority',
        'due_at',
        'metadata',
    ];

    public function __construct(
        private readonly TaskRepositoryInterface $tasks,
        private readonly ContactRepositoryInterface $contacts,
        private readonly UserRepositoryInterface $users,
    ) {}

    public function paginate(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        if (array_key_exists('search', $filters)) {
            $filters['keyword'] = $filters['search'];
            unset($filters['search']);
        }

        foreach (['contact_uuid', 'assigned_to_uuid'] as $field) {
            if (! empty($filters[$field])) {
                $this->assertUuid($filters[$field], $field);
            }
        }

        if (! empty($filters['status'])) {
            $filters['status'] = $this->normalizeStatus($filters['status'])->value;
        }
        if (! empty($filters['priority'])) {
            $filters['priority'] = $this->normalizePriority($filters['priority'])->value;
        }
        foreach (['due_from', 'due_to'] as $field) {
            if (! empty($filters[$field])) {
                $filters[$field] = $this->normalizeDateTime($filters[$field], $field);
            }
        }

        if (array_key_exists('overdue', $filters)) {
            if ($filters['overdue'] !== true) {
                $this->validationError('overdue', 'overdue_invalid');
            }
            $filters['now'] = CarbonImmutable::now('UTC');
        }

        return $this->tasks->paginate($filters, max(1, min(100, $perPage)));
    }

    public function findByUuid(string $uuid): Task
    {
        $this->assertUuid($uuid, 'uuid');

        return $this->tasks->findByUuidOrFail($uuid);
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

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Task
    {
        if (! isset($data['contact_uuid']) || ! is_string($data['contact_uuid'])) {
            $this->validationError('contact_uuid', 'contact_uuid_invalid');
        }

        $this->assertUuid($data['contact_uuid'], 'contact_uuid');
        $contact = $this->contacts->findByUuidOrFail($data['contact_uuid']);
        $normalized = $this->normalize($data);
        $normalized['contact_id'] = $contact->getKey();

        return DB::transaction(function () use ($normalized, $contact): Task {
            $task = $this->tasks->create($normalized);
            $this->refreshNextTaskAt($contact);

            return $task;
        });
    }

    /**
     * Task의 Contact는 생성 후 변경할 수 없습니다.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Task $task, array $data): Task
    {
        $normalized = $this->normalize($data, $task);

        return DB::transaction(function () use ($task, $normalized): Task {
            $updated = $this->tasks->update($task, $normalized);
            $this->refreshNextTaskAt($task->contact);

            return $updated;
        });
    }

    public function delete(Task $task): bool
    {
        return DB::transaction(function () use ($task): bool {
            $contact = $task->contact;
            $deleted = $this->tasks->delete($task);
            $this->refreshNextTaskAt($contact);

            return $deleted;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data, ?Task $existing = null): array
    {
        $data = array_intersect_key($data, array_flip(self::WRITABLE_FIELDS));

        if ($existing === null || array_key_exists('title', $data)) {
            if (! array_key_exists('title', $data) || ! is_string($data['title'])) {
                $this->validationError('title', 'title_required');
            }

            $title = trim($data['title']);
            if ($title === '') {
                $this->validationError('title', 'title_required');
            }
            if (mb_strlen($title) > 255) {
                $this->validationError('title', 'title_too_long');
            }
            $data['title'] = $title;
        }

        if (array_key_exists('description', $data)) {
            if ($data['description'] !== null && ! is_string($data['description'])) {
                $this->validationError('description', 'description_invalid');
            }
            $description = trim((string) $data['description']);
            $data['description'] = $description === '' ? null : $description;
        }

        if ($existing === null || array_key_exists('status', $data)) {
            $data['status'] = $this->normalizeStatus(
                $data['status'] ?? TaskStatus::Pending,
            )->value;
        }
        if ($existing === null || array_key_exists('priority', $data)) {
            $data['priority'] = $this->normalizePriority(
                $data['priority'] ?? TaskPriority::Normal,
            )->value;
        }

        if (array_key_exists('due_at', $data)) {
            $data['due_at'] = $data['due_at'] === null
                ? null
                : $this->normalizeDateTime($data['due_at'], 'due_at');
        } elseif ($existing === null) {
            $data['due_at'] = null;
        }

        if (array_key_exists('metadata', $data)) {
            $data['metadata'] = $this->normalizeMetadata($data['metadata']);
        } elseif ($existing === null) {
            $data['metadata'] = null;
        }

        if (array_key_exists('assigned_to_uuid', $data)) {
            $data['assigned_to'] = $this->resolveAssigneeId($data['assigned_to_uuid']);
            unset($data['assigned_to_uuid']);
        } elseif ($existing === null) {
            $data['assigned_to'] = null;
        }

        $this->applyCompletedAt($data, $existing);

        return $data;
    }

    private function applyCompletedAt(array &$data, ?Task $existing): void
    {
        if ($existing === null) {
            $data['completed_at'] = $data['status'] === TaskStatus::Completed->value
                ? CarbonImmutable::now('UTC')
                : null;

            return;
        }

        if (! array_key_exists('status', $data)) {
            return;
        }

        $nextStatus = TaskStatus::from($data['status']);
        if ($nextStatus === TaskStatus::Completed) {
            if ($existing->status !== TaskStatus::Completed) {
                $data['completed_at'] = CarbonImmutable::now('UTC');
            }

            return;
        }

        $data['completed_at'] = null;
    }

    private function normalizeStatus(mixed $value): TaskStatus
    {
        if ($value instanceof TaskStatus) {
            return $value;
        }
        if (! is_string($value)) {
            $this->validationError('status', 'status_invalid');
        }

        $status = TaskStatus::tryFrom(strtolower(trim($value)));
        if ($status === null) {
            $this->validationError('status', 'status_invalid');
        }

        return $status;
    }

    private function normalizePriority(mixed $value): TaskPriority
    {
        if ($value instanceof TaskPriority) {
            return $value;
        }
        if (! is_string($value)) {
            $this->validationError('priority', 'priority_invalid');
        }

        $priority = TaskPriority::tryFrom(strtolower(trim($value)));
        if ($priority === null) {
            $this->validationError('priority', 'priority_invalid');
        }

        return $priority;
    }

    private function normalizeDateTime(mixed $value, string $field): CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::instance($value)->utc();
        }
        if (! is_string($value) || preg_match('/(?:Z|[+-]\d{2}:\d{2})$/', trim($value)) !== 1) {
            $this->validationError($field, "{$field}_invalid");
        }

        try {
            return CarbonImmutable::parse(trim($value))->utc();
        } catch (Throwable) {
            $this->validationError($field, "{$field}_invalid");
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

    private function resolveAssigneeId(mixed $assigneeUuid): ?int
    {
        if ($assigneeUuid === null) {
            return null;
        }

        $this->assertUuid($assigneeUuid, 'assigned_to_uuid');
        $assignee = $this->users->findManyByUuidsKeyed([$assigneeUuid])->get($assigneeUuid);
        if ($assignee === null) {
            $this->validationError('assigned_to_uuid', 'assignee_not_found');
        }

        return (int) $assignee->getKey();
    }

    private function refreshNextTaskAt(Contact $contact): void
    {
        $this->contacts->updateNextTaskAt(
            $contact,
            $this->tasks->minActiveDueAtForContact($contact),
        );
    }

    private function assertUuid(mixed $uuid, string $field): void
    {
        if (is_string($uuid) && Str::isUuid($uuid)) {
            return;
        }

        $message = match ($field) {
            'contact_uuid' => 'contact_uuid_invalid',
            'assigned_to_uuid' => 'assignee_uuid_invalid',
            default => 'uuid_invalid',
        };
        $this->validationError($field, $message);
    }

    private function validationError(string $field, string $messageKey): never
    {
        throw ValidationException::withMessages([
            $field => [__("glitter-crm::module.validation.task.{$messageKey}")],
        ]);
    }
}
