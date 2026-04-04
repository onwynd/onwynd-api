<?php

namespace Tests\Feature\Therapist;

use Tests\TestCase;

class TherapistTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_example(): void
    {
        $response = $this->get('/api/v1/system/status');

        $response->assertStatus(200);
    }
}
