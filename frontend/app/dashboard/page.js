'use client';

import { useEffect, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import MfaSettings from "../components/MfaSettings";
import StatusMessage from "../components/StatusMessage";
import WeatherMapPanel from "../components/WeatherMapPanel";
import { ApiError, apiRequest, getApiErrorMessage } from "@/lib/api";

const cards = [
  { title: "System status", value: "Authenticated", detail: "Laravel session is active." },
];

export default function DashboardPage() {
  const router = useRouter();
  const [user, setUser] = useState(null);
  const [mfaEnabled, setMfaEnabled] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  const [isLoggingOut, setIsLoggingOut] = useState(false);
  const [status, setStatus] = useState({ type: "", text: "" });

  useEffect(() => {
    async function loadUser() {
      try {
        const result = await apiRequest("user");
        setUser(result.user);
        const mfa = await apiRequest("mfa/status");
        setMfaEnabled(mfa.enabled);
      } catch (error) {
        if (error instanceof ApiError && error.status === 401) {
          router.replace("/login");
          return;
        }

        setStatus({ type: "error", text: getApiErrorMessage(error) });
      } finally {
        setIsLoading(false);
      }
    }

    loadUser();
  }, [router]);

  async function handleLogout() {
    setIsLoggingOut(true);
    try {
      await apiRequest("logout", { method: "POST" });
      setIsLoggingOut(false);
      router.replace("/login");
    } catch (error) {
      setStatus({ type: "error", text: getApiErrorMessage(error) });
      setIsLoggingOut(false);
    }
  }

  if (isLoading) {
    return (
      <main className="flex min-h-screen items-center justify-center bg-slate-100 px-4 text-slate-700">
        <p role="status">Checking your sign-in session...</p>
      </main>
    );
  }

  if (!user) {
    return (
      <main className="flex min-h-screen items-center justify-center bg-slate-100 px-4">
        <div className="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
          <StatusMessage type="error" text={status.text || "Your session could not be verified."} />
          <Link href="/login" className="mt-4 inline-block font-medium text-blue-600 hover:text-blue-500">
            Return to sign in
          </Link>
        </div>
      </main>
    );
  }

  return (
    <main className="min-h-screen bg-slate-100 px-4 py-8 text-slate-900">
      <div className="mx-auto max-w-6xl">
        <header className="mb-8 flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between">
          <div>
            <p className="text-xs font-semibold uppercase tracking-[0.18em] text-blue-600">Protected dashboard</p>
            <h1 className="mt-2 text-2xl font-bold">Student Integration Dashboard</h1>
          </div>
          <div className="flex items-center gap-3">
            <span className="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-medium text-amber-700">
              Signed in as {user.name}
            </span>
            <button
              type="button"
              onClick={handleLogout}
              disabled={isLoggingOut}
              className="rounded-xl border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 hover:border-slate-300 disabled:cursor-not-allowed disabled:opacity-60"
            >
              {isLoggingOut ? "Signing out..." : "Sign out"}
            </button>
          </div>
        </header>

        <StatusMessage type={status.type} text={status.text} />

        <section className="grid gap-4 md:grid-cols-3">
          {cards.map((card) => (
            <article key={card.title} className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
              <p className="text-sm text-slate-500">{card.title}</p>
              <h2 className="mt-3 text-2xl font-bold text-slate-900">{card.value}</h2>
              <p className="mt-2 text-sm text-slate-600">{card.detail}</p>
            </article>
          ))}
        </section>

        <MfaSettings enabled={mfaEnabled} onEnabledChange={setMfaEnabled} />
        <WeatherMapPanel />

        <section className="mt-8 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
          <h2 className="text-lg font-semibold text-slate-900">Important notice</h2>
          <p className="mt-3 text-sm leading-6 text-slate-600">
            Your sign-in is verified by the Laravel API. Weather uses OpenWeatherMap; place search and maps use OpenStreetMap.
          </p>
        </section>
      </div>
    </main>
  );
}
