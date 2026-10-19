# Laravel API

Laravel 13 API for the Next.js frontend. Authentication uses Laravel's web session guard with HttpOnly cookies and CSRF protection; no bearer token is stored in browser storage.

## Local setup

1. Install PHP 8.3+ with `pdo_pgsql` and Composer.
2. Keep the generated `.env` and its `APP_KEY`; copy `.env.example` to `.env` only if no `.env` exists.
3. If this is a new installation with an empty `APP_KEY`, generate it once with `php artisan key:generate`. Keep the generated key stable and private.
4. Fill in `DB_PASSWORD` in `.env` from the Supabase Connect panel. Keep the password only in this ignored file or the hosting secret manager. Confirm connectivity and the database target before running migrations.
5. Run `php artisan migrate` only after you have explicitly confirmed that the selected target is approved for schema changes. Never run `migrate:fresh` against data you need to keep.
6. Start the API with `php artisan serve --host=localhost --port=8000`.

The `users`, `password_reset_tokens`, and `sessions` tables are managed by migrations. Password-reset tokens are hashed, expire according to Laravel's password broker settings, and are deleted after successful use. Configure a real email provider before relying on password recovery; the default Laravel `log` mailer does not deliver email.

## Verify production database connectivity

The temporary `GET /api/diagnostics/database` endpoint runs only `SELECT 1` through Laravel's configured default connection. It returns a generic success or failure message and requires the `X-Database-Diagnostic-Token` request header. Set `DB_DIAGNOSTIC_TOKEN` in Render to a long, random secret using Render's environment dashboard; never put the real token in this file, source control, or a URL. The endpoint bypasses session startup so the diagnostic still works when the database-backed session store cannot connect.

For the existing Supabase setup, verify Render has `DB_CONNECTION=pgsql`, `DB_HOST=aws-0-ap-northeast-1.pooler.supabase.com`, `DB_PORT=5432`, `DB_DATABASE=postgres`, `DB_USERNAME=postgres.dcctuqpldwhgqjbipzdd`, `DB_PASSWORD` set to the Supabase database password, `DB_SCHEMA=public`, and `DB_SSLMODE=require`. Redeploy after setting the variables if Render does not restart the service automatically. Once this change is deployed, send a GET request to `https://<render-service-host>/api/diagnostics/database` with the secret in the `X-Database-Diagnostic-Token` header. Do not put the token in the query string. After confirming connectivity, unset `DB_DIAGNOSTIC_TOKEN` in Render and remove this temporary route and its tests.

## API routes

- `POST /api/register`
- `POST /api/login`
- `POST /api/mfa/challenge` (only after password verification for an MFA-enabled account)
- `POST /api/logout`
- `GET /api/user`
- `GET /api/csrf-cookie`
- `POST /api/forgot-password`
- `POST /api/reset-password`
- `GET /api/mfa/status`
- `POST /api/mfa/setup`, `POST /api/mfa/enable`, and `POST /api/mfa/disable`
- `GET /api/weather?lat=...&lon=...`
- `POST /api/maps/search` with a `query`
- `GET /api/diagnostics/database` (temporary, secret-header protected connectivity check)

The frontend origin is controlled by `FRONTEND_URL`. For local development, use `http://localhost:3000` and `http://localhost:8000` consistently; do not mix `localhost` and `127.0.0.1`. The CSRF endpoint returns the token in JSON for the exact CORS-authorized frontend origin so the browser does not need JavaScript access to an API-host cookie. Production must use HTTPS, a trusted frontend origin, and secure cookie settings.

## MFA, weather, and maps

MFA uses standard six-digit TOTP codes and one-time recovery codes. MFA secrets are encrypted using Laravel's `APP_KEY`; recovery codes are hashed before being stored. Session encryption defaults to enabled; keep `SESSION_ENCRYPT=true` so session data is encrypted at rest. Users should save recovery codes when they are shown because they cannot be retrieved later.

Weather requests are made server-side. Create an API key at [OpenWeather](https://openweathermap.org/api) and set `OPENWEATHERMAP_API_KEY` in the backend `.env`; never put it in a `NEXT_PUBLIC_` variable. A missing or invalid provider key returns an error rather than sample weather.

Place search uses the public OpenStreetMap Nominatim service, and the map is embedded from OpenStreetMap with attribution. Set `MAPS_USER_AGENT` to identify the application and provide a real contact email. Respect Nominatim's usage policy: keep the configured global request throttle, do not bulk-geocode, and use a different provider if production traffic or service terms require it. No browser map key is needed for this setup.

For production, deploy the frontend and API under HTTPS origins that permit credentialed cookies. The API rejects insecure requests in production, and session encryption, HttpOnly, and Secure are forced whenever `APP_ENV=production`. If TLS terminates at a reverse proxy, set `TRUSTED_PROXIES` to the proxy IP addresses/CIDRs so Laravel can safely recognize forwarded HTTPS; do not trust arbitrary forwarded headers. If frontend and API are cross-site, set `SESSION_SAME_SITE=none`. Keep `SESSION_DOMAIN` host-only by default: the frontend obtains its CSRF token from the CORS-protected JSON response and does not need access to the session cookie. Configure `FRONTEND_URL` to the exact frontend origin and retain the CSRF flow. Verify provider credentials, email delivery, Supabase connectivity, and cookie behavior in the deployed environment before relying on these integrations.

Run tests with the configured PHP executable and SQLite extensions enabled if they are not loaded by default: `php artisan test`.

## Deploy to Render with Docker

The Render container is built from this directory's `Dockerfile`. It uses PHP 8.4 with Apache, installs the PostgreSQL and application extensions, installs the locked Composer dependencies, and builds this Laravel app's Vite assets from `package.json` and `package-lock.json`. It does not build the separate Next.js frontend and does not run migrations on image build or container startup.

In Render, create a **Web Service** from the GitHub repository and use:

- **Root Directory:** `backend`
- **Language / Runtime:** `Docker`
- **Dockerfile Path:** `Dockerfile`
- **Docker Command:** leave blank (uses the image entrypoint and Apache command)
- **Health Check Path:** `/up`

Apache serves only Laravel's `public/` directory and listens on Render's `PORT` (default `10000`) on all interfaces. The container health check calls Laravel's `/up` endpoint with an HTTPS-forwarding header; set `TRUSTED_PROXIES=REMOTE_ADDR` as above so the app accepts the trusted direct proxy peer. The health endpoint does not require a database query.

Set service environment variables in Render's environment dashboard, never in the Dockerfile or source:

- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://<render-service-host>`, and a stable Laravel `APP_KEY`.
- `FRONTEND_URL=https://<vercel-frontend-host>` using the exact frontend origin, and `TRUSTED_PROXIES=REMOTE_ADDR` so Laravel trusts only the direct Render proxy peer when interpreting forwarded HTTPS headers.
- `DB_CONNECTION=pgsql`, `DB_HOST=aws-0-ap-northeast-1.pooler.supabase.com`, `DB_PORT=5432`, `DB_DATABASE=postgres`, `DB_USERNAME=postgres.dcctuqpldwhgqjbipzdd`, `DB_PASSWORD` (enter the database password in Render's secret field), `DB_SCHEMA=public`, and `DB_SSLMODE=require`.
- `SESSION_DRIVER=database`, `SESSION_ENCRYPT=true`, `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`; set `SESSION_SAME_SITE=none` for cross-site Vercel/API hosts. Leave `SESSION_DOMAIN` unset.
- Configure mail and provider secrets only when available. Keep `OPENWEATHERMAP_API_KEY` backend-only and set `MAPS_USER_AGENT` with a real contact address.

Ensure the database is approved and reachable before applying schema changes. The image build and startup deliberately do not migrate. After reviewing the target and taking any required backup, run `php artisan migrate --force` once as an explicit release operation using Render's Shell or a controlled release process. The application's database-backed session/cache/queue services require their tables to exist. Then test `/up`, API CORS/CSRF, and a real authenticated request. Do not run `migrate:fresh` against data you need to preserve.
