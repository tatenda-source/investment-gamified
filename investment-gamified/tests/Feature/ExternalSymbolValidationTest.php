<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Stock;

class ExternalSymbolValidationTest extends TestCase
{
    public function test_invalid_symbol_rejected()
    {
        $response = $this->getJson('/api/external/stocks/quote/../../etc');
        $response->assertStatus(422);
    }

    public function test_unknown_symbol_returns_404()
    {
        $response = $this->getJson('/api/external/stocks/quote/FOOBAR');
        $response->assertStatus(404);
    }
}
