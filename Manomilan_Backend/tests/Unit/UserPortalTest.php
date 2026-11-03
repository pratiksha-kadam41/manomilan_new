<?php

namespace Tests\Unit;

use App\Models\User;
use App\Role;
use Tests\TestCase;

class UserPortalTest extends TestCase
{
    public function test_custom_role_uses_admin_portal_even_when_its_type_is_candidate_type(): void
    {
        $user = new User(['user_type' => '21']);
        $user->setRelation('role', new Role(['name' => 'Mediator', 'user_type' => '21']));

        $this->assertSame('admin', $user->portal());
    }

    public function test_candidate_role_keeps_candidate_portal(): void
    {
        $user = new User(['user_type' => '21']);
        $user->setRelation('role', new Role(['name' => 'Candidate', 'user_type' => '21']));

        $this->assertSame('candidate', $user->portal());
    }

    public function test_unassigned_legacy_candidate_keeps_candidate_portal(): void
    {
        $user = new User(['user_type' => '21']);

        $this->assertSame('candidate', $user->portal());
    }
}
