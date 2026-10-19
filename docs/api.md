# API reference

The API is served by Laravel under `/api`. Local examples assume the frontend origin `http://localhost:3000` and backend base URL `http://localhost:8000/api`. In production, substitute the deployed HTTPS URLs. JSON mutation requests require the session cookies and CSRF header described below.

## Session and CSRF sequence

1. Start a browser session with `GET /api/csrf-cookie`. The JSON response contains `csrf_token`; Laravel also initializes the session/XSRF cookies.
2. Retain cookies and send the returned token in `X-CSRF-TOKEN` for each mutation. Browser requests must use `credentials: "include"`.
3. CORS is limited to the configured `FRONTEND_URL` and permits credentials. Do not make the session cookie readable by frontend JavaScript.
4. Log in to establish an authenticated session. A successful logout invalidates it.

Example PowerShell request sequence. Replace the example name/email/password with values for a disposable local account; the angle-bracket values are placeholders, not credentials:

```powershell
$api = 'http://localhost:8000/api'
$origin = 'http://localhost:3000'
$session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
$csrf = Invoke-RestMethod -Method Get -Uri "$api/csrf-cookie" `
  -WebSession $session -Headers @{ Origin = $origin }
$headers = @{
  Origin = $origin
  'X-CSRF-TOKEN' = $csrf.csrf_token
}
$body = @{
  name = 'Demo User'
  email = 'demo@example.test'
  password = '<your-disposable-password>'
  password_confirmation = '<your-disposable-password>'
} | ConvertTo-Json
Invoke-RestMethod -Method Post -Uri "$api/register" -WebSession $session `
  -Headers $headers -ContentType 'application/json' -Body $body
```

Keep the same `$session` and refresh `$csrf` using `/api/csrf-cookie` before a state-changing request if needed. Never share a session object or real credentials as evidence.

## Endpoints

| Method | Path | Access | Request | Purpose |
| --- | --- | --- | --- | --- |
| `GET` | `/api/csrf-cookie` | Public | — | Start/refresh the CSRF session and return `csrf_token` as JSON. |
| `POST` | `/api/register` | Public; rate limited | `name`, `email`, `password`, `password_confirmation` | Validate, create a persistent user, hash the password, and sign in. |
| `POST` | `/api/login` | Public; rate limited | `email`, `password` | Start a session, or respond `202 {"mfa_required":true}` while MFA is pending. |
| `POST` | `/api/mfa/challenge` | Pending MFA session; rate limited | Exactly one of `code` or `recovery_code` | Complete MFA login. |
| `POST` | `/api/forgot-password` | Public; rate limited | `email` | Return a generic `202` response whether or not the account exists; send a reset message when configured. |
| `POST` | `/api/reset-password` | Public; rate limited | `email`, `token`, `password`, `password_confirmation` | Reset password with a valid, unexpired, single-use token. |
| `GET` | `/api/user` | Authenticated | — | Return the current user's public profile. |
| `POST` | `/api/logout` | Authenticated | — | Invalidate the session and rotate the CSRF token. |
| `GET` | `/api/mfa/status` | Authenticated | — | Return whether MFA is enabled and remaining recovery-code count. |
| `POST` | `/api/mfa/setup` | Authenticated; rate limited | `password` | Verify password and return a temporary enrollment `secret` and `otpauth_url`. |
| `POST` | `/api/mfa/enable` | Authenticated; rate limited | `password`, six-digit `code` | Verify setup and enable MFA; returns one-time recovery codes. |
| `POST` | `/api/mfa/disable` | Authenticated; rate limited | `password` and exactly one of `code` or `recovery_code` | Verify both factors and disable MFA. |
| `GET` | `/api/weather?lat=<lat>&lon=<lon>` | Authenticated; rate limited | Latitude and longitude query values | Return current provider weather or a configuration/provider error. |
| `POST` | `/api/maps/search` | Authenticated; rate limited | `query` (2–120 characters) | Search Nominatim and return valid places, or an explicit configuration/provider error. |

### MFA handling

Keep the setup secret and `otpauth_url` private while enrolling. The enable response returns recovery codes only once; save them securely and do not log, commit, or screenshot them. MFA secrets are encrypted at rest, recovery codes are hashed, TOTP steps cannot be replayed, and failed challenges are rate-limited. Login does not create an authenticated user session until the challenge succeeds.

### Example request bodies

```json
{
  "email": "demo@example.test",
  "password": "<your-disposable-password>"
}
```

```json
{
  "code": "123456"
}
```

```json
{
  "query": "Seattle"
}
```

```json
{
  "password": "<your-disposable-password>",
  "code": "123456"
}
```

These values illustrate request shape only. Use a current authenticator code and a disposable test user; never use a sample code as a real MFA factor.

## Responses and common failures

- Validation errors generally return `422` with field-specific errors.
- Unauthenticated protected requests return `401`; rate limits return `429`.
- Weather returns `503` with `weather_not_configured` when its server-side key is absent, and `502` with `weather_unavailable` on provider/network failures.
- Map search returns `503` with `map_search_not_configured` if the User-Agent lacks a valid contact email, `429` if the global provider limit is reached, and `502` with `map_search_unavailable` for provider/network failures.
- Password recovery deliberately avoids confirming whether an email belongs to an account. A `202` response does not prove that SMTP delivery succeeded.

No real Supabase, SMTP, weather, or map provider request has been verified for this project. Automated provider tests use controlled fake HTTP responses.
