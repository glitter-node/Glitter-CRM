<?php

namespace Modules\Glitter\Crm\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Glitter\Crm\Enums\ContactStatus;

/**
 * 회원 여부와 무관하게 관리되는 CRM 고객.
 *
 * metadata는 확장용 비정형 값이며 민감정보의 임의 저장소로 사용하지 않습니다.
 */
class Contact extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'crm_contacts';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
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
        'last_activity_at',
        'next_action_at',
        'next_task_at',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ContactStatus::class,
            'last_activity_at' => 'datetime',
            'next_action_at' => 'datetime',
            'next_task_at' => 'datetime',
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
     * 선택적으로 연결된 회원.
     *
     * @return BelongsTo<User, Contact>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * 선택적으로 지정된 CRM 담당자.
     *
     * @return BelongsTo<User, Contact>
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    /**
     * 고객 분류에 사용되는 CRM 태그.
     *
     * @return BelongsToMany<Tag, Contact>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(
            Tag::class,
            'crm_contact_tag',
            'contact_id',
            'tag_id',
        )->withTimestamps();
    }

    /**
     * 고객에게 직접 귀속된 CRM 활동.
     *
     * 정렬은 조회 목적에 따라 Repository에서 적용합니다.
     *
     * @return HasMany<Activity, Contact>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    /**
     * 고객에게 직접 귀속된 CRM 후속 작업.
     *
     * 정렬은 조회 목적에 따라 Repository에서 적용합니다.
     *
     * @return HasMany<Task, Contact>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
