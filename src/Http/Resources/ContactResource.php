<?php

namespace Modules\Glitter\Crm\Http\Resources;

use App\Http\Resources\BaseApiResource;
use Illuminate\Http\Request;
use Modules\Glitter\Crm\Models\Tag;

class ContactResource extends BaseApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'user_id' => $this->whenLoaded('user', fn () => $this->user?->uuid),
            'assigned_user_id' => $this->whenLoaded('assignedUser', fn () => $this->assignedUser?->uuid),
            'status' => $this->status->value,
            'name' => $this->name,
            'company' => $this->company,
            'job_title' => $this->job_title,
            'email' => $this->email,
            'phone' => $this->phone,
            'mobile' => $this->mobile,
            'locale' => $this->locale,
            'timezone' => $this->timezone,
            'source' => $this->source,
            'last_activity_at' => $this->last_activity_at
                ? $this->formatDateTimeStringForUser($this->last_activity_at)
                : null,
            'next_action_at' => $this->next_action_at
                ? $this->formatDateTimeLocalStringForSite($this->next_action_at)
                : null,
            'next_task_at' => $this->next_task_at?->utc()->toISOString(),
            'metadata' => $this->metadata,
            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'uuid' => $this->user->uuid,
                'name' => $this->user->name,
            ] : null),
            'assigned_user' => $this->whenLoaded('assignedUser', fn () => $this->assignedUser ? [
                'uuid' => $this->assignedUser->uuid,
                'name' => $this->assignedUser->name,
            ] : null),
            'tags' => $this->whenLoaded('tags', fn () => $this->tags
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
                ->map(fn (Tag $tag): array => [
                    'uuid' => $tag->uuid,
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                    'color' => $tag->color,
                ])
                ->all()),
            ...$this->formatTimestamps(),
            ...$this->resourceMeta($request),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function abilityMap(): array
    {
        return [
            'can_update' => 'glitter-crm.contacts.update',
            'can_delete' => 'glitter-crm.contacts.delete',
        ];
    }
}
