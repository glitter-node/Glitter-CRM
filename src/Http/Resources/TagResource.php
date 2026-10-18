<?php

namespace Modules\Glitter\Crm\Http\Resources;

use App\Http\Resources\BaseApiResource;
use Illuminate\Http\Request;

class TagResource extends BaseApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'color' => $this->color,
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
            'can_update' => 'glitter-crm.tags.update',
            'can_delete' => 'glitter-crm.tags.delete',
        ];
    }
}
