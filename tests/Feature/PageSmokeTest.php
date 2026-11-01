<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Opens every parameterless GET screen as a superuser and fails on any server error. */
class PageSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_simple_screen_renders_without_a_server_error(): void
    {
        $this->seed(); // modules, permissions and sample records, so screens render real rows
        $role = Role::updateOrCreate(['code'=>'director'], ['name'=>'Director','is_superuser'=>true,'rank'=>1,'is_active'=>true]);
        $user = User::create(['username'=>'smoke-test-director','name'=>'Director','email'=>'smoke-test@example.test','password'=>'password','role_id'=>$role->id,'is_active'=>true,'must_change_password'=>false]);

        $failures = [];
        $checked = $rendered = 0;
        // These screens use PostgreSQL-only SQL (regexp_replace, concat) that the in-memory SQLite test database lacks.
        $postgresOnly = DB::getDriverName() === 'sqlite' ? ['inventory', 'hr/accommodation'] : [];
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (! in_array('GET', $route->methods(), true) || str_contains($uri, '{')) continue;
            if (in_array($uri, $postgresOnly, true)) continue;
            if (preg_match('#^(_|sanctum|storage|up$|logout)#', $uri) || str_contains($uri, 'export') || str_contains($uri, 'download')) continue;
            $response = $this->actingAs($user)->get('/'.ltrim($uri, '/'));
            $checked++;
            if ($response->getStatusCode() === 200) $rendered++;
            if ($response->getStatusCode() >= 500) {
                $failures[] = $uri.' → '.$response->getStatusCode().' '.mb_substr(strip_tags((string) ($response->exception?->getMessage() ?? '')), 0, 200);
            }
        }

        $this->assertGreaterThan(40, $rendered, 'most screens should render for a superuser with seeded modules');
        $this->assertSame([], $failures, implode(PHP_EOL, $failures));
    }
}
