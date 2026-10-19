# Next.js frontend

This JavaScript App Router client uses the Laravel API for authentication, MFA, weather, and place search. It does not connect directly to Supabase.

For complete workspace setup, route descriptions, security notes, testing results, and deployment preparation, see the [root README](../README.md).

## Local development

From this directory, copy `.env.example` to `.env.local` only if `.env.local` does not already exist, then run:

```powershell
npm ci
npm run dev
```

The local frontend is served at `http://localhost:3000`. Set `NEXT_PUBLIC_API_URL` to the Laravel API URL ending in `/api`, normally `http://localhost:8000/api`. This value is public browser configuration; do not put credentials or provider keys in frontend environment variables.

Available checks:

```powershell
npm run lint
npm run build
```
