<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use stdClass;
use Tests\TestCase;

class ErrorHandlingTest extends TestCase
{
    public function test_not_found_returns_standard_error_response(): void
    {
        $this->assertApiErrorFormat($this->getJson('/api/v1/nonexistent'));
    }

    public function test_method_not_allowed_returns_standard_error_response(): void
    {
        $this->assertApiErrorFormat($this->getJson('/api/v1/login'));
    }

    public function test_unauthenticated_returns_standard_error_response(): void
    {
        $this->assertApiErrorFormat($this->getJson('/api/v1/me'));
    }

    public function test_validation_returns_standard_error_response(): void
    {
        $this->assertApiErrorFormat($this->postJson('/api/v1/register', []));
    }

    public function test_internal_error_does_not_expose_exception_details(): void
    {
        Route::get('/api/v1/test-internal-error', function (): never {
            throw new RuntimeException('Sensitive internal information');
        });

        $response = $this->getJson('/api/v1/test-internal-error');

        $this->assertApiErrorFormat($response);

        $response
            ->assertDontSee('Sensitive internal information')
            ->assertDontSee('trace')
            ->assertDontSee('RuntimeException')
            ->assertDontSee('/var/www/html/');
    }

    private function assertApiErrorFormat(TestResponse $response): void
    {
        $content = $response->getContent();
        $this->assertIsString($content);

        $data = json_decode($content, false, 512, JSON_THROW_ON_ERROR);

        $this->assertInstanceOf(stdClass::class, $data);
        $this->assertEqualsCanonicalizing(['error'], array_keys(get_object_vars($data)));

        $this->assertInstanceOf(stdClass::class, $data->error);
        $this->assertEqualsCanonicalizing(
            ['code', 'message', 'details'],
            array_keys(get_object_vars($data->error)),
        );

        $this->assertIsInt($data->error->code);
        $this->assertIsString($data->error->message);
        $this->assertInstanceOf(stdClass::class, $data->error->details);
    }
}
