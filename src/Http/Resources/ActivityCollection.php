<?php

namespace Modules\Glitter\Crm\Http\Resources;

use App\Http\Resources\BaseApiCollection;
use Illuminate\Http\Request;

class ActivityCollection extends BaseApiCollection
{
    public $collects = ActivityResource::class;

    /**
     * @return array<string, string>
     */
    protected function abilityMap(): array
    {
        return [
            'can_create' => 'glitter-crm.activities.create',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'current_page' => $this->resource->currentPage(),
                'last_page' => $this->resource->lastPage(),
                'per_page' => $this->resource->perPage(),
                'total' => $this->resource->total(),
            ],
            'abilities' => $this->resolveCollectionAbilities($request),
        ];
    }
}
