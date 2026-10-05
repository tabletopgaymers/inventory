<?php

namespace Tests\Feature;

use Tests\TestCase;

class RevisionTest extends TestCase
{
    public function test_revision_is_visible_on_safe_failure_without_database_access(): void
    {
        config(['app.key' => '', 'baseline.revision' => str_repeat('a', 40)]);
        $this->get('/')->assertStatus(503)->assertSee(str_repeat('a', 40));
    }

    public function test_working_copy_does_not_invent_a_revision(): void
    {
        config(['app.key' => '', 'baseline.revision' => null]);
        $this->get('/')->assertStatus(503)->assertSee('Unrecorded working copy');
    }
}
