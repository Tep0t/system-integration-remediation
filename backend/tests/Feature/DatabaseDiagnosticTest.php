<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use PDOException;
use Tests\TestCase;

class DatabaseDiagnosticTest extends TestCase
{
    public function test_database_diagnostic_requires_a_configured_token(): void
    {
        config(['services.database_diagnostic.token' => '']);

        $this->getJson('/api/diagnostics/database')
            ->assertNotFound();
    }

    public function test_database_diagnostic_rejects_an_invalid_token(): void
    {
        config(['services.database_diagnostic.token' => 'expected-secret-token']);

        $this->withHeader('X-Database-Diagnostic-Token', 'incorrect-token')
            ->getJson('/api/diagnostics/database')
            ->assertNotFound();
    }

    public function test_database_diagnostic_returns_success_after_select_one(): void
    {
        config(['services.database_diagnostic.token' => 'expected-secret-token']);

        $this->withHeader('X-Database-Diagnostic-Token', 'expected-secret-token')
            ->getJson('/api/diagnostics/database')
            ->assertOk()
            ->assertExactJson(['message' => 'Database connection successful.']);
    }

    public function test_database_diagnostic_hides_database_errors(): void
    {
        config(['services.database_diagnostic.token' => 'expected-secret-token']);
        DB::shouldReceive('select')
            ->once()
            ->with('SELECT 1')
            ->andThrow(new PDOException('Sensitive connection details'));

        $this->withHeader('X-Database-Diagnostic-Token', 'expected-secret-token')
            ->getJson('/api/diagnostics/database')
            ->assertServiceUnavailable()
            ->assertExactJson(['message' => 'Database connection failed.'])
            ->assertDontSee('Sensitive connection details');
    }
}
