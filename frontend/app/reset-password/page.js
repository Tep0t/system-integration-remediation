'use client';

import { Suspense, useState } from "react";
import { useRouter, useSearchParams } from "next/navigation";
import AuthShell from "../components/AuthShell";
import FormField from "../components/FormField";
import StatusMessage from "../components/StatusMessage";
import { apiRequest, getApiErrorMessage } from "@/lib/api";

export default function ResetPasswordPage() {
  return (
    <Suspense
      fallback={
        <AuthShell title="Set new password" subtitle="Loading your secure reset link...">
          <p role="status" className="text-sm text-slate-600">Loading reset details...</p>
        </AuthShell>
      }
    >
      <ResetPasswordForm />
    </Suspense>
  );
}

function ResetPasswordForm() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const email = searchParams.get("email") || "";
  const token = searchParams.get("token") || "";
  const [form, setForm] = useState({ password: "", confirmPassword: "" });
  const [status, setStatus] = useState({ type: "", text: "" });
  const [isSubmitting, setIsSubmitting] = useState(false);

  function handleChange(event) {
    const { name, value } = event.target;
    setForm((current) => ({ ...current, [name]: value }));
  }

  async function handleSubmit(event) {
    event.preventDefault();
    const password = form.password;
    const confirmPassword = form.confirmPassword;

    if (!email || !token) {
      setStatus({ type: "error", text: "This password reset link is incomplete or invalid." });
      return;
    }

    if (!password || !confirmPassword) {
      setStatus({ type: "error", text: "Both password fields are required." });
      return;
    }

    if (password.length < 12) {
      setStatus({ type: "error", text: "Password must be at least 12 characters long." });
      return;
    }

    if (password !== confirmPassword) {
      setStatus({ type: "error", text: "Passwords do not match." });
      return;
    }

    setIsSubmitting(true);
    setStatus({ type: "info", text: "Updating your password..." });

    try {
      await apiRequest("reset-password", {
        method: "POST",
        body: {
          email,
          token,
          password,
          password_confirmation: confirmPassword,
        },
      });
      setForm({ password: "", confirmPassword: "" });
      setStatus({ type: "", text: "" });
      router.replace("/login");
    } catch (error) {
      setStatus({ type: "error", text: getApiErrorMessage(error) });
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <AuthShell
      title="Set new password"
      subtitle="Use the secure, expiring link sent to your email to choose a new password."
      footerText="Back to"
      footerHref="/login"
      footerLabel="Login"
    >
      {email ? <p className="mb-4 text-sm text-slate-600">Resetting password for {email}</p> : null}
      <form className="space-y-5" onSubmit={handleSubmit} noValidate>
        <FormField
          id="password"
          label="New password"
          type="password"
          value={form.password}
          onChange={handleChange}
          placeholder="Choose a new password"
          autoComplete="new-password"
          required
          minLength={12}
        />

        <FormField
          id="confirmPassword"
          label="Confirm new password"
          type="password"
          value={form.confirmPassword}
          onChange={handleChange}
          placeholder="Repeat your new password"
          autoComplete="new-password"
          required
          minLength={12}
        />

        <button
          type="submit"
          disabled={isSubmitting || !email || !token}
          className="inline-flex w-full items-center justify-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200 disabled:cursor-not-allowed disabled:opacity-60"
        >
          {isSubmitting ? "Saving password..." : "Save password"}
        </button>
      </form>

      <StatusMessage
        type={status.type || (!email || !token ? "error" : "")}
        text={status.text || (!email || !token ? "This password reset link is incomplete or invalid." : "")}
      />
    </AuthShell>
  );
}
