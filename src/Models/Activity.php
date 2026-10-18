<?php

namespace Modules\Glitter\Crm\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Glitter\Crm\Enums\ActivityType;

/**
 * Contact에 직접 귀속되는 CRM 상호작용 기록.
 *
 * metadata는 확장용 JSON 객체이며 임의의 개인정보 저장소로 사용하지 않습니다.
 */
class Activity extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'crm_activities';

    public $incrementing = true;

    protected $keyType = 'int';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'contact_id',
        'type',
        'subject',
        'body',
        'occurred_at',
        'created_by',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'type' => ActivityType::class,
            'occurred_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

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
     * @return BelongsTo<Contact, Activity>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return BelongsTo<User, Activity>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
