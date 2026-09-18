<?php

namespace Tests\Feature;

use Tests\TestCase;

class AppDoctorTest extends TestCase
{
    public function test_doctor_reports_a_healthy_test_environment(): void
    {
        $this->artisan('app:doctor')
            ->assertExitCode(0);
    }

    public function test_doctor_fails_when_app_key_is_missing(): void
    {
        config(['app.key' => '']);

        $this->artisan('app:doctor')
            ->expectsOutputToContain('APP_KEY is set')
            ->assertExitCode(1);
    }

    public function test_doctor_fails_on_debug_enabled_in_production(): void
    {
        // The exact combination that leaks credentials through the debug page.
        app()->detectEnvironment(fn () => 'production');
        config(['app.debug' => true]);

        $this->artisan('app:doctor')
            ->expectsOutputToContain('APP_DEBUG is off in production')
            ->assertExitCode(1);
    }

    public function test_doctor_fails_on_an_origin_without_a_scheme(): void
    {
        // A bare host can never match a browser Origin header.
        putenv('CORS_ALLOWED_ORIGINS=app.example.com');

        $this->artisan('app:doctor')
            ->expectsOutputToContain('CORS_ALLOWED_ORIGINS')
            ->assertExitCode(1);

        putenv('CORS_ALLOWED_ORIGINS');
    }
}
