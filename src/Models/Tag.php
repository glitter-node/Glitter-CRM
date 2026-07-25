<?php

namespace Modules\Glitter\Crm\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * CRM Contact를 분류하는 공통 태그.
 */
class Tag extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'crm_tags';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'description',
        'color',
    ];

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * 이 태그가 연결된 CRM 고객.
     *
     * @return BelongsToMany<Contact, Tag>
     */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(
            Contact::class,
            'crm_contact_tag',
            'tag_id',
            'contact_id',
        )->withTimestamps();
    }
}
