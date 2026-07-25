<?php

namespace Modules\Glitter\Crm\Tests;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Route;

abstract class FeatureTestCase extends ModuleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('api/modules/glitter-crm')
            ->name('api.modules.glitter-crm.')
            ->middleware('api')
            ->group($this->moduleBasePath().'/src/routes/api.php');
    }

    /**
     * @param  array<int, string>  $permissions
     */
    protected function createAdminUser(array $permissions): User
    {
        $role = Role::firstOrCreate(
            ['identifier' => 'admin'],
            ['name' => ['ko' => '관리자', 'en' => 'Administrator']],
        );
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        foreach ($permissions as $identifier) {
            $permission = Permission::firstOrCreate(
                ['identifier' => $identifier],
                [
                    'name' => ['ko' => $identifier, 'en' => $identifier],
                    'type' => 'admin',
                ],
            );
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        return $user;
    }
}
