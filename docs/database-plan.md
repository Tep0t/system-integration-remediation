# Database and Supabase Setup

The Laravel database connection is configured for PostgreSQL and reads all connection settings from environment variables. No Supabase credentials are stored in source code.

## Required backend environment values

Set these in the ignored `backend/.env` using the connection details from the Supabase project's **Connect** panel:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=aws-0-ap-northeast-1.pooler.supabase.com
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres.dcctuqpldwhgqjbipzdd
DB_PASSWORD=your-supabase-database-password
DB_SCHEMA=public
DB_SSLMODE=require
```

The checked-in example uses this project's supplied pooler host, port, database, and username. Keep the password as a placeholder in the example and enter the real database password only in the ignored `backend/.env` or the hosting provider's secret environment settings. The supplied pooler port is `5432`; verify the current Supabase Connect panel if the project changes connection method. Transaction-pooler restrictions and network allow-listing depend on the selected Supabase plan.

## Migrations

Laravel migrations create:

- `users` with unique email, password hash, verification timestamp, remember token, and timestamps.
- `password_reset_tokens` for hashed, expiring, single-use recovery tokens.
- `sessions` for persistent server-side browser sessions.
- Laravel cache and queue tables.

The migrations use Laravel schema-builder column types and indexes supported by PostgreSQL, including identity primary keys, nullable timestamps, text fields, and unsigned integer/big-integer counters (represented as PostgreSQL integer/bigint columns). No PostgreSQL-specific SQLite syntax was found. After verifying the target and a successful connection, migrations may be run with `php artisan migrate --force` in the production release process. The Supabase project is created, but this workspace has not tested a connection or run migrations because the database password is missing. The user also explicitly prohibited database commands for this step.

### Read-only connection check

After entering the password in `backend/.env`, open PowerShell in `backend/` and use the following. It opens a PostgreSQL connection via PDO and runs no SQL statement, migration, or schema change. It deliberately prints only a generic error class if connection fails:

```powershell
$php = 'C:\Users\user\Downloads\php-8.5.11-nts-Win32-vs17-x64\php.exe'
$code = @'
require "vendor/autoload.php";
Dotenv\Dotenv::createImmutable(getcwd())->safeLoad();
if (empty($_ENV["DB_PASSWORD"])) {
    fwrite(STDERR, "DB_PASSWORD is blank; enter it in backend/.env first.\n");
    exit(2);
}
$dsn = sprintf(
    "pgsql:host=%s;port=%s;dbname=%s;sslmode=%s",
    $_ENV["DB_HOST"],
    $_ENV["DB_PORT"],
    $_ENV["DB_DATABASE"],
    $_ENV["DB_SSLMODE"]
);
try {
    new PDO($dsn, $_ENV["DB_USERNAME"], $_ENV["DB_PASSWORD"], [PDO::ATTR_TIMEOUT => 8]);
    echo "PostgreSQL connection established.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "PostgreSQL connection failed (" . get_class($exception) . "). Check credentials, network access, and TLS settings; details suppressed.\n");
    exit(1);
}
'@
& $php -r $code
```

Run it from the Laravel `backend` directory. A successful connection only confirms authentication/network/TLS; it does not prove the schema is migrated. Do not run migrations until the database target is confirmed and schema changes are approved.

## Frontend boundary

The browser uses only `NEXT_PUBLIC_API_URL`. Do not expose `DB_PASSWORD`, Supabase service-role keys, or other database credentials through any `NEXT_PUBLIC_*` variable.
