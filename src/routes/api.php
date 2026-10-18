<?php

use Illuminate\Support\Facades\Route;
use Modules\Glitter\Crm\Http\Controllers\Admin\ActivityController;
use Modules\Glitter\Crm\Http\Controllers\Admin\ContactController;
use Modules\Glitter\Crm\Http\Controllers\Admin\TagController;
use Modules\Glitter\Crm\Http\Controllers\Admin\TaskController;

/*
|--------------------------------------------------------------------------
| Glitter CRM API Routes
|--------------------------------------------------------------------------
|
| ModuleRouteServiceProvider applies:
| - URL prefix: api/modules/glitter-crm
| - Name prefix: api.modules.glitter-crm.
|
*/
Route::prefix('admin/contacts')
    ->middleware(['auth:sanctum', 'admin', 'throttle:600,1'])
    ->name('admin.contacts.')
    ->group(function (): void {
        Route::get('/{contact}/activities', [ActivityController::class, 'contactIndex'])
            ->whereUuid('contact')
            ->middleware('permission:admin,glitter-crm.activities.read')
            ->name('activities.index');

        Route::get('/{contact}/tasks', [TaskController::class, 'contactIndex'])
            ->whereUuid('contact')
            ->middleware('permission:admin,glitter-crm.tasks.read')
            ->name('tasks.index');

        Route::get('/', [ContactController::class, 'index'])
            ->middleware('permission:admin,glitter-crm.contacts.read')
            ->name('index');

        Route::post('/', [ContactController::class, 'store'])
            ->middleware('permission:admin,glitter-crm.contacts.create')
            ->name('store');

        Route::get('/{contact}', [ContactController::class, 'show'])
            ->whereUuid('contact')
            ->middleware('permission:admin,glitter-crm.contacts.read')
            ->name('show');

        Route::put('/{contact}', [ContactController::class, 'update'])
            ->whereUuid('contact')
            ->middleware('permission:admin,glitter-crm.contacts.update')
            ->name('update');

        Route::delete('/{contact}', [ContactController::class, 'destroy'])
            ->whereUuid('contact')
            ->middleware('permission:admin,glitter-crm.contacts.delete')
            ->name('destroy');
    });

Route::prefix('admin/tasks')
    ->middleware(['auth:sanctum', 'admin', 'throttle:600,1'])
    ->name('admin.tasks.')
    ->group(function (): void {
        Route::get('/', [TaskController::class, 'index'])
            ->middleware('permission:admin,glitter-crm.tasks.read')
            ->name('index');

        Route::post('/', [TaskController::class, 'store'])
            ->middleware('permission:admin,glitter-crm.tasks.create')
            ->name('store');

        Route::get('/{task}', [TaskController::class, 'show'])
            ->whereUuid('task')
            ->middleware('permission:admin,glitter-crm.tasks.read')
            ->name('show');

        Route::put('/{task}', [TaskController::class, 'update'])
            ->whereUuid('task')
            ->middleware('permission:admin,glitter-crm.tasks.update')
            ->name('update');

        Route::delete('/{task}', [TaskController::class, 'destroy'])
            ->whereUuid('task')
            ->middleware('permission:admin,glitter-crm.tasks.delete')
            ->name('destroy');
    });

Route::prefix('admin/activities')
    ->middleware(['auth:sanctum', 'admin', 'throttle:600,1'])
    ->name('admin.activities.')
    ->group(function (): void {
        Route::get('/', [ActivityController::class, 'index'])
            ->middleware('permission:admin,glitter-crm.activities.read')
            ->name('index');

        Route::post('/', [ActivityController::class, 'store'])
            ->middleware('permission:admin,glitter-crm.activities.create')
            ->name('store');

        Route::get('/{activity}', [ActivityController::class, 'show'])
            ->whereUuid('activity')
            ->middleware('permission:admin,glitter-crm.activities.read')
            ->name('show');

        Route::put('/{activity}', [ActivityController::class, 'update'])
            ->whereUuid('activity')
            ->middleware('permission:admin,glitter-crm.activities.update')
            ->name('update');

        Route::delete('/{activity}', [ActivityController::class, 'destroy'])
            ->whereUuid('activity')
            ->middleware('permission:admin,glitter-crm.activities.delete')
            ->name('destroy');
    });

Route::prefix('admin/tags')
    ->middleware(['auth:sanctum', 'admin', 'throttle:600,1'])
    ->name('admin.tags.')
    ->group(function (): void {
        Route::get('/', [TagController::class, 'index'])
            ->middleware('permission:admin,glitter-crm.tags.read')
            ->name('index');

        Route::post('/', [TagController::class, 'store'])
            ->middleware('permission:admin,glitter-crm.tags.create')
            ->name('store');

        Route::get('/{tag}', [TagController::class, 'show'])
            ->whereUuid('tag')
            ->middleware('permission:admin,glitter-crm.tags.read')
            ->name('show');

        Route::put('/{tag}', [TagController::class, 'update'])
            ->whereUuid('tag')
            ->middleware('permission:admin,glitter-crm.tags.update')
            ->name('update');

        Route::delete('/{tag}', [TagController::class, 'destroy'])
            ->whereUuid('tag')
            ->middleware('permission:admin,glitter-crm.tags.delete')
            ->name('destroy');
    });
