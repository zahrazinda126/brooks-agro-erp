<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_authenticated_user_can_submit_the_global_search(): void
    {
        $role = Role::create(['code'=>'search-test', 'name'=>'Search Test', 'is_superuser'=>true, 'rank'=>1, 'is_active'=>true]);
        $user = User::create(['username'=>'search-test', 'name'=>'Search Test', 'password'=>'password', 'role_id'=>$role->id, 'is_active'=>true, 'must_change_password'=>false]);

        $this->actingAs($user)->get(route('search.index', ['q'=>'eggs']))
            ->assertOk()
            ->assertSee('Results for')
            ->assertSee('eggs');
    }
}
