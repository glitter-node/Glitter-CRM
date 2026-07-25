<?php

namespace Modules\Glitter\Crm\Http\Resources;

use App\Http\Resources\BaseApiResource;
use Illuminate\Http\Request;
use Modules\Glitter\Crm\Enums\ActivityType;

class ActivityResource extends BaseApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'type' => $this->type->value,
            'subject' => $this->subject,
            'body' => $this->body,
            'occurred_at' => $this->occurred_at?->copy()->utc()->toISOString(),
            'metadata' => $this->metadata,
            'contact' => $this->whenLoaded('contact', fn () => $this->contact ? [
                'uuid' => $this->contact->uuid,
                'name' => $this->contact->name,
                'company' => $this->contact->company,
            ] : null),
            'creator' => $this->whenLoaded('creator', fn () => $this->creator ? [
                'uuid' => $this->creator->uuid,
                'name' => $this->creator->name,
                'email' => $this->creator->email,
            ] : null),
            ...$this->formatTimestamps(),
            ...$this->resourceMeta($request),
        ];
    }

    /**
     * system Activity는 permission 보유 여부와 무관하게 불변 기록입니다.
     *
     * @return array<string, bool>
     */
    protected function resolveAbilities(Request $request): array
    {
        $abilities = parent::resolveAbilities($request);
        if ($this->type === ActivityType::System) {
            $abilities['can_update'] = false;
            $abilities['can_delete'] = false;
        }

        return $abilities;
    }

    /**
     * @return array<string, string>
     */
    protected function abilityMap(): array
    {
        return [
            'can_update' => 'glitter-crm.activities.update',
            'can_delete' => 'glitter-crm.activities.delete',
        ];
    }
}
