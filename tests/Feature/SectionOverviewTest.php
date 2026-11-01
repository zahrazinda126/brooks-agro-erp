<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SectionOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_section_has_an_overview_with_summaries_where_they_apply(): void
    {
        $this->seed();
        $role = Role::updateOrCreate(['code'=>'director'], ['name'=>'Director','is_superuser'=>true,'rank'=>1,'is_active'=>true]);
        $user = User::create(['username'=>'overview-test','name'=>'Overview','password'=>'password','role_id'=>$role->id,'is_active'=>true,'must_change_password'=>false]);

        $this->actingAs($user)->get('/overview/cash-and-banking?name='.urlencode('Cash & Banking'))
            ->assertOk()->assertSee('data-section-overview="cash-and-banking"', false)->assertSee('Cash on hand')->assertSee('Money received per day');
        $this->actingAs($user)->get('/overview/people?name=People')->assertOk()->assertSee('Active staff');
        $this->actingAs($user)->get('/overview/poultry?name=Poultry')->assertOk()->assertSee('Eggs collected per day');
        // Any other section still gets an overview listing its screens.
        $this->actingAs($user)->get('/overview/something-else?name=Something%20Else')->assertOk()->assertSee('Choose a screen from the tabs above');
    }

    public function test_summaries_are_hidden_from_accounts_without_access(): void
    {
        $this->seed();
        $role = Role::create(['code'=>'overview-worker','name'=>'Worker','is_superuser'=>false,'rank'=>90,'is_active'=>true]);
        $user = User::create(['username'=>'overview-worker','name'=>'Worker','password'=>'password','role_id'=>$role->id,'is_active'=>true,'must_change_password'=>false]);

        $this->actingAs($user)->get('/overview/cash-and-banking?name='.urlencode('Cash & Banking'))
            ->assertOk()->assertDontSee('Cash on hand')->assertSee('Choose a screen from the tabs above');
    }
}
