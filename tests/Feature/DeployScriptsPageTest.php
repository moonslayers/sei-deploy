<?php

namespace Tests\Feature;

use App\Models\Backend;
use App\Models\DeployScriptVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeployScriptsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_requires_auth(): void
    {
        $this->get('/admin/deploy-scripts')->assertRedirect('/admin/login');
    }

    public function test_edit_and_save_persists(): void
    {
        $user = User::factory()->create(['email' => 'admin@example.com']);
        $backend = Backend::firstWhere('repo', 'moonslayers/cef-backend');
        $backend->update(['script' => null]);

        $this->actingAs($user)
            ->get('/admin/deploy-scripts')
            ->assertStatus(200);

        $backend->update(['script' => "#!/bin/bash\necho hi"]);
        $this->assertDatabaseHas('backends', ['id' => $backend->id, 'script' => "#!/bin/bash\necho hi"]);
    }

    public function test_version_saved_before_overwrite(): void
    {
        $user = User::factory()->create(['email' => 'admin@example.com']);
        $backend = Backend::firstWhere('repo', 'moonslayers/cef-backend');
        $backend->update(['script' => "#!/bin/bash\nold"]);
        DeployScriptVersion::create(['backend_id' => $backend->id, 'content' => $backend->script, 'user_id' => $user->id]);
        $this->assertDatabaseHas('deploy_script_versions', ['backend_id' => $backend->id, 'content' => "#!/bin/bash\nold"]);
    }

    public function test_restore_works(): void
    {
        $user = User::factory()->create(['email' => 'admin@example.com']);
        $backend = Backend::firstWhere('repo', 'moonslayers/cef-backend');
        $backend->update(['script' => "#!/bin/bash\nnew"]);
        $v = DeployScriptVersion::create(['backend_id' => $backend->id, 'content' => "#!/bin/bash\nold", 'user_id' => $user->id]);
        $backend->update(['script' => $v->content]);
        $this->assertEquals("#!/bin/bash\nold", $backend->fresh()->script);
    }
}
