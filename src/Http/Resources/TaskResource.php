<?php

namespace Modules\Glitter\Crm\Http\Resources;

use App\Http\Resources\BaseApiResource;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Modules\Glitter\Crm\Enums\TaskStatus;

class TaskResource extends BaseApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status->value,
            'priority' => $this->priority->value,
            'due_at' => $this->due_at?->copy()->utc()->toISOString(),
            'completed_at' => $this->completed_at?->copy()->utc()->toISOString(),
            'is_overdue' => $this->isOverdue(),
            'metadata' => $this->metadata,
            'contact' => $this->whenLoaded('contact', fn () => $this->contact ? [
                'uuid' => $this->contact->uuid,
                'name' => $this->contact->name,
                'company' => $this->contact->company,
            ] : null),
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee ? [
                'uuid' => $this->assignee->uuid,
                'name' => $this->assignee->name,
                'email' => $this->assignee->email,
            ] : null),
            ...$this->formatTimestamps(),
            ...$this->resourceMeta($request),
        ];
    }

    protected function abilityMap(): array
    {
        return [
            'can_update' => 'glitter-crm.tasks.update',
            'can_delete' => 'glitter-crm.tasks.delete',
        ];
    }

    private function isOverdue(): bool
    {
        return $this->due_at !== null
            && in_array($this->status, [TaskStatus::Pending, TaskStatus::InProgress], true)
            && $this->due_at->lt(CarbonImmutable::now('UTC'));
    }
}
