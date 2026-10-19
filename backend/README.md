# Laravel API

Laravel 13 API for the Next.js frontend. Authentication uses Laravel's web session guard with HttpOnly cookies and CSRF protection; no bearer token is stored in browser storage.

## Local setup

1. Install PHP 8.3+ with `pdo_pgsql` and Composer.
2. Keep the generated `.env` and its `APP_KEY`; copy `.env.example` to `.env` only if no `.env` exists.
3. If this is a new installation with an empty `APP_KEY`, generate it once with `php artisan key:generate`. Keep the generated key stable and private.
4. Fill in the Supabase PostgreSQL variables and SMTP credentials in `.env`.
5. Run `php artisan migrate`.
6. Start the API with `php artisan serve --host=localhost --port=8000`.

The `users`, `password_reset_tokens`, and `sessions` tables are managed by migrations. Password-reset tokens are hashed, expire according to Laravel's password broker settings, and are deleted after successful use. Configure a real email provider before relying on password recovery; the default Laravel `log` mailer does not deliver email.

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

The frontend origin is controlled by `FRONTEND_URL`. For local development, use `http://localhost:3000` and `http://localhost:8000` consistently; do not mix `localhost` and `127.0.0.1`. The CSRF endpoint returns the token in JSON for the exact CORS-authorized frontend origin so the browser does not need JavaScript access to an API-host cookie. Production must use HTTPS, a trusted frontend origin, and secure cookie settings.

## MFA, weather, and maps

MFA uses standard six-digit TOTP codes and one-time recovery codes. MFA secrets are encrypted using Laravel's `APP_KEY`; recovery codes are hashed before being stored. Session encryption defaults to enabled; keep `SESSION_ENCRYPT=true` so session data is encrypted at rest. Users should save recovery codes when they are shown because they cannot be retrieved later.

Weather requests are made server-side. Create an API key at [OpenWeather](https://openweathermap.org/api) and set `OPENWEATHERMAP_API_KEY` in the backend `.env`; never put it in a `NEXT_PUBLIC_` variable. A missing or invalid provider key returns an error rather than sample weather.

Place search uses the public OpenStreetMap Nominatim service, and the map is embedded from OpenStreetMap with attribution. Set `MAPS_USER_AGENT` to identify the application and provide a real contact email. Respect Nominatim's usage policy: keep the configured global request throttle, do not bulk-geocode, and use a different provider if production traffic or service terms require it. No browser map key is needed for this setup.

For production, deploy the frontend and API under HTTPS origins that permit credentialed cookies. The API rejects insecure requests in production, and session encryption, HttpOnly, and Secure are forced whenever `APP_ENV=production`. If TLS terminates at a reverse proxy, set `TRUSTED_PROXIES` to the proxy IP addresses/CIDRs so Laravel can safely recognize forwarded HTTPS; do not trust arbitrary forwarded headers. If frontend and API are cross-site, set `SESSION_SAME_SITE=none`. Keep `SESSION_DOMAIN` host-only by default: the frontend obtains its CSRF token from the CORS-protected JSON response and does not need access to the session cookie. Configure `FRONTEND_URL` to the exact frontend origin and retain the CSRF flow. Verify provider credentials, email delivery, Supabase connectivity, and cookie behavior in the deployed environment before relying on these integrations.

Run tests with the configured PHP executable and SQLite extensions enabled if they are not loaded by default: `php artisan test`.
