# Database and Supabase Setup

The Laravel database connection is configured for PostgreSQL and reads all connection settings from environment variables. No Supabase credentials are stored in source code.

## Required backend environment values

Set these in the ignored `backend/.env` using the connection details from the Supabase project's **Connect** panel:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=your-project.pooler.supabase.com
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres.your-project-ref
DB_PASSWORD=your-supabase-database-password
DB_SCHEMA=public
DB_SSLMODE=require
```

Use the actual host, port, username, and password shown for the selected Supabase connection method. A pooler on port `6543` may have transaction-mode restrictions; use the Supabase-recommended session/direct connection for migrations and persistent Laravel sessions. Network allow-listing and direct-connection IPv6 requirements depend on the selected Supabase plan.

## Migrations

Laravel migrations create:

- `users` with unique email, password hash, verification timestamp, remember token, and timestamps.
- `password_reset_tokens` for hashed, expiring, single-use recovery tokens.
- `sessions` for persistent server-side browser sessions.
- Laravel cache and queue tables.

After valid credentials are configured, run `php artisan migrate`. This workspace has not run those migrations against Supabase because no valid database credentials were configured.

## Frontend boundary

The browser uses only `NEXT_PUBLIC_API_URL`. Do not expose `DB_PASSWORD`, Supabase service-role keys, or other database credentials through any `NEXT_PUBLIC_*` variable.
