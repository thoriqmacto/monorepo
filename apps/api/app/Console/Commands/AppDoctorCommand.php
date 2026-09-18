<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Post-install / post-deploy verification.
 *
 * Every check here exists because its absence produces a failure that points
 * somewhere other than the cause: a 500 on register that is really a log file
 * the web user cannot append to, a CORS rejection that is really a missing
 * https:// prefix, a stale config cache that silently ignores .env edits.
 *
 * FAIL means the app is broken or unsafe right now and the command exits 1.
 * WARN means it works but something is probably not what you intended.
 */
class AppDoctorCommand extends Command
{
    protected $signature = 'app:doctor';

    protected $description = 'Verify this installation can actually serve traffic (env, permissions, database, mail, URLs).';

    private int $failures = 0;

    private int $warnings = 0;

    public function handle(): int
    {
        $this->newLine();
        $this->line('  <options=bold>Environment</>');
        $this->checkAppKey();
        $this->checkDebug();

        $this->newLine();
        $this->line('  <options=bold>Filesystem</>');
        $this->checkWritablePaths();
        $this->checkLogging();

        $this->newLine();
        $this->line('  <options=bold>Database</>');
        $this->checkDatabase();

        $this->newLine();
        $this->line('  <options=bold>URLs and mail</>');
        $this->checkUrls();
        $this->checkMail();

        $this->newLine();
        if ($this->failures > 0) {
            $this->error("  {$this->failures} failure(s), {$this->warnings} warning(s).");

            return self::FAILURE;
        }

        $this->info("  All checks passed. {$this->warnings} warning(s).");

        return self::SUCCESS;
    }

    private function reportPass(string $label, string $detail = ''): void
    {
        $this->line("  <fg=green>PASS</> {$label}".($detail !== '' ? " <fg=gray>({$detail})</>" : ''));
    }

    private function reportWarn(string $label, string $detail): void
    {
        $this->warnings++;
        $this->line("  <fg=yellow>WARN</> {$label}");
        $this->line("       {$detail}");
    }

    private function reportFail(string $label, string $detail): void
    {
        $this->failures++;
        $this->line("  <fg=red>FAIL</> {$label}");
        $this->line("       {$detail}");
    }

    private function isProduction(): bool
    {
        return app()->environment('production');
    }

    private function checkAppKey(): void
    {
        $key = (string) config('app.key');

        if ($key === '') {
            $this->reportFail('APP_KEY is set', 'Empty. Run: php artisan key:generate. Sessions, signed URLs and encrypted values cannot work without it.');

            return;
        }

        $this->reportPass('APP_KEY is set');
    }

    private function checkDebug(): void
    {
        $debug = (bool) config('app.debug');
        $env = (string) config('app.env');

        if ($this->isProduction() && $debug) {
            $this->reportFail(
                'APP_DEBUG is off in production',
                'APP_DEBUG=true renders Laravel\'s debug page on any error, which prints the stack trace AND the environment — database credentials and APP_KEY included — to whoever triggered it. Set APP_DEBUG=false, then: php artisan config:cache'
            );

            return;
        }

        if (! $this->isProduction()) {
            $this->reportWarn("APP_ENV is '{$env}', not 'production'", 'Fine on a development machine. On a server this usually means .env was never switched over.');

            return;
        }

        $this->reportPass('APP_DEBUG is off in production');
    }

    private function checkWritablePaths(): void
    {
        // A probe write is the only honest test: is_writable() consults the
        // permission bits, which can disagree with reality under SELinux, a
        // read-only mount, or a full disk.
        $paths = [
            storage_path('logs'),
            storage_path('framework/cache'),
            storage_path('framework/views'),
            storage_path('framework/sessions'),
            base_path('bootstrap/cache'),
        ];

        foreach ($paths as $path) {
            $relative = str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);

            if (! is_dir($path)) {
                $this->reportFail("{$relative} exists", 'Missing. Create it (mkdir -p) and give it to the web server group.');

                continue;
            }

            $probe = $path.DIRECTORY_SEPARATOR.'.doctor-probe';

            if (@file_put_contents($probe, 'ok') === false) {
                $this->reportFail(
                    "{$relative} is writable",
                    'Cannot write as '.$this->currentUser().". PHP-FPM runs as a different user than your deploy user, and both need write access. See 'Permissions' in the README."
                );

                continue;
            }

            @unlink($probe);
            $this->reportPass("{$relative} is writable");
        }
    }

    private function checkLogging(): void
    {
        // The failure this catches: the log file already exists, owned by the
        // deploy user with 0644, so php-fpm gets "Permission denied" the first
        // time the app logs anything — which surfaces as a 500 on an endpoint
        // that otherwise did its job (the row is committed, then the mail or
        // the error handler tries to log and dies).
        try {
            Log::info('app:doctor write probe');
            $this->reportPass('Log channel is writable', (string) config('logging.default'));
        } catch (Throwable $e) {
            $this->reportFail('Log channel is writable', 'Writing a log line failed: '.$e->getMessage());

            return;
        }

        $file = storage_path('logs/laravel.log');

        if (! is_file($file)) {
            return;
        }

        $perms = fileperms($file) & 0777;

        if (($perms & 0060) !== 0060) {
            $this->reportWarn(
                'Log file is group-writable',
                sprintf(
                    'storage/logs/laravel.log is %04o. Whichever user did not create it cannot append. Fix now with: sudo chmod 0664 %s && sudo chown :www-data %s',
                    $perms,
                    $file,
                    $file
                )
            );
        }
    }

    private function checkDatabase(): void
    {
        try {
            DB::connection()->getPdo();
            $this->reportPass('Database connection', (string) config('database.default'));
        } catch (Throwable $e) {
            $this->reportFail('Database connection', $e->getMessage());

            return;
        }

        try {
            $pending = app('migrator')->setConnection((string) config('database.default'));
            $files = $pending->getMigrationFiles([database_path('migrations')]);
            $ran = $pending->getRepository()->getRan();
            $outstanding = array_diff(array_keys($files), $ran);

            if ($outstanding !== []) {
                $this->reportWarn(
                    'All migrations have run',
                    count($outstanding).' pending. Run: php artisan migrate --force'
                );

                return;
            }

            $this->reportPass('All migrations have run');
        } catch (Throwable $e) {
            $this->reportWarn('All migrations have run', 'Could not read migration state: '.$e->getMessage());
        }
    }

    private function checkUrls(): void
    {
        // A bare host here is silently wrong: CORS compares allowed_origins
        // literally against the browser's Origin header (always scheme://host),
        // and FRONTEND_URL is concatenated into verification and reset links,
        // where a missing scheme makes them relative paths on the API host.
        $values = [
            'APP_URL' => (string) config('app.url'),
            'FRONTEND_URL' => (string) env('FRONTEND_URL', ''),
        ];

        foreach ($values as $key => $value) {
            if ($value === '') {
                $this->reportWarn("{$key} is set", 'Empty.');

                continue;
            }

            if (! preg_match('#^https?://#', $value)) {
                $this->reportFail("{$key} includes a scheme", "Value is '{$value}'. It must start with http:// or https://.");

                continue;
            }

            if ($this->isProduction() && str_contains($value, 'localhost')) {
                $this->reportWarn("{$key} is not localhost", "Value is '{$value}' in production.");

                continue;
            }

            $this->reportPass("{$key} looks right", $value);
        }

        $origins = array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))));

        if ($origins === []) {
            $this->reportWarn('CORS_ALLOWED_ORIGINS is set', 'Empty — every cross-origin browser request will be blocked.');

            return;
        }

        foreach ($origins as $origin) {
            if (! preg_match('#^https?://#', $origin)) {
                $this->reportFail('CORS_ALLOWED_ORIGINS entries include a scheme', "'{$origin}' has no scheme, so it can never match a browser Origin header.");

                return;
            }
        }

        $this->reportPass('CORS_ALLOWED_ORIGINS entries include a scheme', implode(', ', $origins));
    }

    private function checkMail(): void
    {
        $mailer = (string) config('mail.default');

        if ($this->isProduction() && $mailer === 'log') {
            $this->reportWarn(
                'Mail is deliverable',
                'MAIL_MAILER=log writes mail into storage/logs instead of sending it. Verification and password-reset messages will never reach users. Configure SMTP.'
            );

            return;
        }

        $this->reportPass('Mail transport', $mailer);
    }

    private function currentUser(): string
    {
        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            $info = posix_getpwuid(posix_geteuid());

            if (is_array($info) && isset($info['name'])) {
                return (string) $info['name'];
            }
        }

        return get_current_user() ?: 'the current user';
    }
}
