<?php

namespace Modules\Glitter\Crm\Providers;

use App\Extension\BaseModuleServiceProvider;
use Modules\Glitter\Crm\Repositories\ActivityRepository;
use Modules\Glitter\Crm\Repositories\ContactRepository;
use Modules\Glitter\Crm\Repositories\Contracts\ActivityRepositoryInterface;
use Modules\Glitter\Crm\Repositories\Contracts\ContactRepositoryInterface;
use Modules\Glitter\Crm\Repositories\Contracts\TagRepositoryInterface;
use Modules\Glitter\Crm\Repositories\Contracts\TaskRepositoryInterface;
use Modules\Glitter\Crm\Repositories\TagRepository;
use Modules\Glitter\Crm\Repositories\TaskRepository;

/**
 * CRM 모듈 서비스 프로바이더.
 *
 * Repository 바인딩과 기본 모듈 boot를 부모 구현에 위임합니다.
 */
class CrmServiceProvider extends BaseModuleServiceProvider
{
    /**
     * 모듈 식별자.
     */
    protected string $moduleIdentifier = 'glitter-crm';

    /**
     * @var array<class-string, class-string>
     */
    protected array $repositories = [
        ActivityRepositoryInterface::class => ActivityRepository::class,
        ContactRepositoryInterface::class => ContactRepository::class,
        TagRepositoryInterface::class => TagRepository::class,
        TaskRepositoryInterface::class => TaskRepository::class,
    ];
}
