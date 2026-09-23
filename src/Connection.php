<?php

namespace QuebecStudioMods\ConsentKit\Register;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Which database the register is kept in: the one named in the configuration,
 * the site's own, or a SQLite file the addon declares and migrates itself.
 */
class Connection
{
    public const OWN = 'cookie-consent';

    private ?string $resolved = null;

    public function name(): string
    {
        return $this->resolved ??= $this->resolve();
    }

    /** Creates the tables, on the connection `name()` resolved. */
    public function migrate(): void
    {
        if (Schema::connection($this->name())->hasTable(Register::DECISIONS)) {
            return;
        }

        $previous = config('database.default');
        config()->set('database.default', $this->name());

        try {
            foreach (glob(self::migrations() . '/*.php') ?: [] as $migration) {
                (require $migration)->up();
            }
        } finally {
            config()->set('database.default', $previous);
        }
    }

    /** Folder of the shipped migrations, for a host that runs them itself. */
    public static function migrations(): string
    {
        return dirname(__DIR__) . '/database/migrations';
    }

    private function resolve(): string
    {
        $configured = config('cookie-consent.register.connection');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return $this->siteConnectionWorks() ? (string)config('database.default') : $this->declareOwn();
    }

    private function siteConnectionWorks(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /** A SQLite file beside the rest of the addon's data. */
    private function declareOwn(): string
    {
        $path = storage_path('cookie-consent/register.sqlite');

        File::ensureDirectoryExists(dirname($path));

        if (!is_file($path)) {
            File::put($path, '');
        }

        config()->set('database.connections.' . self::OWN, [
            'driver' => 'sqlite',
            'database' => $path,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        return self::OWN;
    }
}
