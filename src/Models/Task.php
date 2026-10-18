<?php

namespace Modules\Glitter\Crm\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Glitter\Crm\Enums\TaskPriority;
use Modules\Glitter\Crm\Enums\TaskStatus;

/**
 * Contact에 직접 귀속되는 CRM 후속 작업.
 *
 * metadata는 확장용 JSON 객체이며 임의의 개인정보 저장소로 사용하지 않습니다.
 */
class Task extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'crm_tasks';

    public $incrementing = true;

    protected $keyType = 'int';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'contact_id',
        'assigned_to',
        'title',
        'description',
        'status',
        'priority',
        'due_at',
        'completed_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'priority' => TaskPriority::class,
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
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
     * @return BelongsTo<Contact, Task>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return BelongsTo<User, Task>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
