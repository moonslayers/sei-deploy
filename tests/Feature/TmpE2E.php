<?php
namespace Tests\Feature;
use App\Models\Backend;
use App\Models\TerminalSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
class TmpE2E extends TestCase {
    use RefreshDatabase;
    public function test_e2e(): void {
        config(['admin.emails' => ['admin@example.com']]);
        $user = User::factory()->create(['email'=>'admin@example.com']);
        $backend = Backend::create(['repo'=>'x/sei-deploy','path'=>null]);
        $res = $this->actingAs($user)->post('/terminal/'.$backend->id.'/start')->assertStatus(200)->json();
        $uuid = $res['session'];
        $this->actingAs($user)->post('/terminal/'.$uuid.'/input', ['input'=>"echo MARCADOR123\r"])->assertStatus(200);
        sleep(1);
        $out = $this->actingAs($user)->get('/terminal/'.$uuid.'/output')->json('output');
        echo "\n===OUTPUT===\n".$out."\n===END===\n";
        $this->actingAs($user)->delete('/terminal/'.$uuid);
        $this->assertStringContainsString('MARCADOR123', $out);
    }
}
