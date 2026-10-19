import Link from "next/link";

const featureCards = [
  { title: "Registration", description: "Create an account through the Laravel API with server-side validation." },
  { title: "Login", description: "Sign in through a secure Laravel session cookie." },
  { title: "Dashboard UI", description: "Verify your authenticated session before viewing the dashboard." },
  { title: "Password recovery", description: "Request an expiring, single-use password reset link." },
];

export default function Home() {
  return (
    <main className="min-h-screen bg-slate-100 text-slate-900">
      <section className="mx-auto flex max-w-6xl flex-col gap-8 px-4 py-12 sm:px-6 lg:px-8">
        <header className="flex flex-col gap-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm sm:flex-row sm:items-center sm:justify-between">
          <div>
            <p className="text-xs font-semibold uppercase tracking-[0.2em] text-blue-600">System Integration</p>
            <h1 className="mt-2 text-3xl font-bold tracking-tight sm:text-4xl">Remediation Project</h1>
          </div>
          <div className="flex flex-wrap gap-3">
            <Link href="/login" className="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-500">
              Login
            </Link>
            <Link href="/register" className="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-slate-300">
              Register
            </Link>
          </div>
        </header>

        <section className="grid gap-6 rounded-3xl border border-slate-200 bg-gradient-to-br from-blue-50 to-white p-6 shadow-sm lg:grid-cols-[1.3fr_0.7fr]">
          <div>
            <span className="inline-flex rounded-full border border-blue-200 bg-blue-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.14em] text-blue-700">
              Step 1: backend and authentication
            </span>
            <h2 className="mt-4 text-4xl font-bold tracking-tight text-slate-900">Authenticated integration foundation</h2>
            <p className="mt-4 max-w-xl text-base leading-7 text-slate-600">
              Registration, login, logout, and password recovery are wired to the Laravel API. Supabase and email delivery require your project credentials before they can be verified.
            </p>
            <div className="mt-6 flex flex-wrap gap-3">
              <Link href="/register" className="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800">
                Create account
              </Link>
              <Link href="/dashboard" className="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:border-slate-400">
                Open dashboard
              </Link>
            </div>
          </div>

          <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p className="text-sm font-semibold uppercase tracking-[0.16em] text-slate-500">Architecture</p>
            <div className="mt-5 space-y-4 text-sm text-slate-700">
              <div className="rounded-xl bg-slate-50 p-3">
                <p className="font-medium text-slate-900">Frontend</p>
                <p className="mt-1">Next.js with App Router and responsive UI</p>
              </div>
              <div className="rounded-xl bg-slate-50 p-3">
                <p className="font-medium text-slate-900">Backend</p>
                <p className="mt-1">Laravel REST API with cookie-based sessions</p>
              </div>
              <div className="rounded-xl bg-slate-50 p-3">
                <p className="font-medium text-slate-900">Database</p>
                <p className="mt-1">PostgreSQL migrations prepared; credentials required</p>
              </div>
            </div>
          </div>
        </section>

        <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
          {featureCards.map((card) => (
            <article key={card.title} className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
              <h3 className="text-lg font-semibold text-slate-900">{card.title}</h3>
              <p className="mt-2 text-sm leading-6 text-slate-600">{card.description}</p>
            </article>
          ))}
        </section>
      </section>
    </main>
  );
}
