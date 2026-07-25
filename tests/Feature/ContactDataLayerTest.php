<?php

namespace Modules\Glitter\Crm\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Glitter\Crm\Enums\ContactStatus;
use Modules\Glitter\Crm\Models\Contact;
use Modules\Glitter\Crm\Repositories\Contracts\ContactRepositoryInterface;
use Modules\Glitter\Crm\Services\ContactService;
use Modules\Glitter\Crm\Services\TagService;
use Modules\Glitter\Crm\Tests\ModuleTestCase;

/**
 * @scenario contact-data-layer
 */
class ContactDataLayerTest extends ModuleTestCase
{
    use RefreshDatabase;

    public function test_contact_can_be_created_without_member_or_assignee(): void
    {
        $contact = $this->app->make(ContactService::class)->create([
            'name' => '  비회원 고객  ',
            'metadata' => ['segment' => 'new'],
        ]);

        $this->assertNotNull($contact->uuid);
        $this->assertSame('비회원 고객', $contact->name);
        $this->assertNull($contact->user_id);
        $this->assertNull($contact->assigned_user_id);
        $this->assertSame(ContactStatus::Active, $contact->status);
        $this->assertSame(['segment' => 'new'], $contact->metadata);
    }

    public function test_duplicate_email_phone_and_mobile_are_allowed(): void
    {
        foreach (['첫 고객', '둘째 고객'] as $name) {
            Contact::create([
                'name' => $name,
                'email' => 'shared@example.com',
                'phone' => '02-0000-0000',
                'mobile' => '010-0000-0000',
            ]);
        }

        $this->assertSame(2, Contact::where('email', 'shared@example.com')->count());
        $this->assertSame(2, Contact::where('phone', '02-0000-0000')->count());
        $this->assertSame(2, Contact::where('mobile', '010-0000-0000')->count());
    }

    public function test_member_links_are_optional_and_are_cleared_when_user_is_deleted(): void
    {
        $user = User::factory()->create();
        $contact = Contact::create([
            'name' => '회원 고객',
            'user_id' => $user->id,
            'assigned_user_id' => $user->id,
        ]);

        $user->delete();

        $contact->refresh();
        $this->assertNull($contact->user_id);
        $this->assertNull($contact->assigned_user_id);
    }

    public function test_repository_filters_searches_sorts_and_paginates_contacts(): void
    {
        Contact::create(['name' => 'Alpha 고객', 'status' => ContactStatus::Active]);
        Contact::create(['name' => 'Beta 고객', 'company' => 'Alpha 회사', 'status' => ContactStatus::Inactive]);

        $result = $this->app->make(ContactRepositoryInterface::class)->paginate([
            'keyword' => 'Alpha',
            'status' => ContactStatus::Inactive->value,
            'sort' => 'name',
            'direction' => 'asc',
        ], 10);

        $this->assertSame(1, $result->total());
        $this->assertSame('Beta 고객', $result->items()[0]->name);
    }

    public function test_repository_uuid_update_and_soft_delete_flow(): void
    {
        $repository = $this->app->make(ContactRepositoryInterface::class);
        $contact = $repository->create(['name' => '수정 전']);

        $found = $repository->findByUuidOrFail($contact->uuid);
        $updated = $repository->update($found, ['name' => '수정 후']);

        $this->assertSame('수정 후', $updated->name);
        $this->assertTrue($repository->delete($updated));
        $this->assertNull($repository->findByUuid($contact->uuid));
        $this->assertNotNull(Contact::withTrashed()->where('uuid', $contact->uuid)->first());
    }

    public function test_service_rejects_blank_name(): void
    {
        $service = $this->app->make(ContactService::class);

        $this->expectException(\InvalidArgumentException::class);
        $service->create(['name' => '   ']);
    }

    public function test_service_rejects_invalid_metadata(): void
    {
        $service = $this->app->make(ContactService::class);

        $this->expectException(\InvalidArgumentException::class);
        $service->create(['name' => '고객', 'metadata' => 'invalid']);
    }

    public function test_service_deduplicates_tag_uuids_before_sync(): void
    {
        $tag = $this->app->make(TagService::class)->create(['name' => '중복 제거']);
        $contact = $this->app->make(ContactService::class)->create([
            'name' => '태그 고객',
            'tag_uuids' => [$tag->uuid, $tag->uuid],
        ]);

        $this->assertSame([$tag->id], $contact->tags->modelKeys());
        $this->assertDatabaseCount('crm_contact_tag', 1);
    }
}
