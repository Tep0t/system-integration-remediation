'use client';

import { useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import AuthShell from "../components/AuthShell";
import FormField from "../components/FormField";
import StatusMessage from "../components/StatusMessage";
import { apiRequest, getApiErrorMessage } from "@/lib/api";

export default function LoginPage() {
  const router = useRouter();
  const [form, setForm] = useState({ email: "", password: "" });
  const [mfaValue, setMfaValue] = useState("");
  const [mfaRequired, setMfaRequired] = useState(false);
  const [useRecoveryCode, setUseRecoveryCode] = useState(false);
  const [status, setStatus] = useState({ type: "", text: "" });
  const [isSubmitting, setIsSubmitting] = useState(false);

  function handleChange(event) {
    const { name, value } = event.target;
    setForm((current) => ({ ...current, [name]: value }));
  }

  async function handleSubmit(event) {
    event.preventDefault();
    const email = form.email.trim();
    const password = form.password;

    if (!email || !password) {
      setStatus({ type: "error", text: "Email and password are required." });
      return;
    }

    const emailValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    if (!emailValid) {
      setStatus({ type: "error", text: "Enter a valid email address." });
      return;
    }

    setIsSubmitting(true);
    setStatus({ type: "info", text: mfaRequired ? "Verifying your code..." : "Signing in..." });

    try {
      const result = mfaRequired
        ? await apiRequest("mfa/challenge", {
            method: "POST",
            body: useRecoveryCode
              ? { recovery_code: mfaValue }
              : { code: mfaValue },
          })
        : await apiRequest("login", { method: "POST", body: { email, password } });

      if (result.mfa_required) {
        setForm((current) => ({ ...current, password: "" }));
        setMfaRequired(true);
        setStatus({ type: "info", text: "Enter an authenticator code or a recovery code to finish signing in." });
        return;
      }

      setForm((current) => ({ ...current, password: "" }));
      setMfaValue("");
      setStatus({ type: "", text: "" });
      router.replace("/dashboard");
    } catch (error) {
      setStatus({ type: "error", text: getApiErrorMessage(error) });
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <AuthShell
      title="Welcome back"
      subtitle="Sign in to continue to the project dashboard view."
      footerText="Need an account?"
      footerHref="/register"
      footerLabel="Register here"
    >
      <form className="space-y-5" onSubmit={handleSubmit} noValidate>
        {!mfaRequired ? (
          <>
            <FormField
              id="email"
              label="Email"
              type="email"
              value={form.email}
              onChange={handleChange}
              placeholder="name@example.com"
              autoComplete="email"
              required
            />

            <FormField
              id="password"
              label="Password"
              type="password"
              value={form.password}
              onChange={handleChange}
              placeholder="Enter your password"
              autoComplete="current-password"
              required
            />

            <div className="flex items-center justify-between text-sm">
              <Link href="/forgot-password" className="font-medium text-blue-600 hover:text-blue-500">
                Forgot password?
              </Link>
            </div>
          </>
        ) : (
          <>
            <p className="rounded-xl bg-slate-50 px-3 py-2 text-sm text-slate-600">
              Password verified for {form.email}. Your session will not start until this step succeeds.
            </p>
            <FormField
              id="mfaValue"
              label={useRecoveryCode ? "Recovery code" : "Authenticator code"}
              value={mfaValue}
              onChange={(event) => setMfaValue(event.target.value)}
              placeholder={useRecoveryCode ? "Enter an unused recovery code" : "6-digit code"}
              autoComplete="one-time-code"
              required
              minLength={useRecoveryCode ? undefined : 6}
            />
            <button
              type="button"
              onClick={() => {
                setUseRecoveryCode((current) => !current);
                setMfaValue("");
              }}
              className="text-sm font-medium text-blue-600 hover:text-blue-500"
            >
              {useRecoveryCode ? "Use an authenticator code" : "Use a recovery code"}
            </button>
          </>
        )}

        <button
          type="submit"
          disabled={isSubmitting}
          className="inline-flex w-full items-center justify-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200 disabled:cursor-not-allowed disabled:opacity-60"
        >
          {isSubmitting ? "Signing in..." : mfaRequired ? "Verify and sign in" : "Login"}
        </button>
      </form>

      <StatusMessage type={status.type} text={status.text} />
    </AuthShell>
  );
}
