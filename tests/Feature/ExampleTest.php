
<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeatureExampleTest extends TestCase
{
    public function testReturnsSuccessfulResponse()
    {
        $response = $this->get('/');
        $response->assertStatus(200);
    }
}
