<?php

namespace Tests\Feature;

use App\Support\BaselineProbe;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthenticationSchemaTest extends TestCase
{
    public function test_compatible_recovery_probe_accepts_the_existing_isolated_schema(): void
    {
        $probe = app(BaselineProbe::class);
        $this->assertTrue($probe->inspect()['ready']);
        $state = $probe->schemaState(DB::connection('mariadb'));
        $this->assertTrue($state['ready']);
        $this->assertTrue($state['canPrepare']);
    }

    public function test_partial_authentication_schema_cannot_pass_as_session_only(): void
    {
        $database = \Mockery::mock();
        $schema = \Mockery::mock();
        $database->shouldReceive('getSchemaBuilder')->andReturn($schema);
        $schema->shouldReceive('getTableListing')->andReturn(['migrations', 'sessions', 'users']);
        $database->shouldReceive('table->where->exists')->andReturn(true);
        $schema->shouldReceive('getColumnListing')->with('sessions')->andReturn(['id', 'user_id', 'ip_address', 'user_agent', 'payload', 'last_activity']);
        $state = app(BaselineProbe::class)->schemaState($database);
        $this->assertFalse($state['ready']);
        $this->assertFalse($state['canPrepare']);
        $this->assertFalse($state['authenticationReady']);
    }
}
