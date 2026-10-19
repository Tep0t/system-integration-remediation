'use client';

import { useState } from "react";
import AuthShell from "../components/AuthShell";
import FormField from "../components/FormField";
import StatusMessage from "../components/StatusMessage";
import { apiRequest, getApiErrorMessage } from "@/lib/api";

export default function ForgotPasswordPage() {
  const [email, setEmail] = useState("");
  const [status, setStatus] = useState({ type: "", text: "" });
  const [isSubmitting, setIsSubmitting] = useState(false);

  async function handleSubmit(event) {
    event.preventDefault();
    const trimmed = email.trim();

    if (!trimmed) {
      setStatus({ type: "error", text: "Email address is required." });
      return;
    }

    const emailValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(trimmed);
    if (!emailValid) {
      setStatus({ type: "error", text: "Please enter a valid email." });
      return;
    }

    setIsSubmitting(true);
    setStatus({ type: "info", text: "Submitting recovery request..." });

    try {
      const result = await apiRequest("forgot-password", {
        method: "POST",
        body: { email: trimmed },
      });
      setStatus({ type: "success", text: result.message });
    } catch (error) {
      setStatus({ type: "error", text: getApiErrorMessage(error) });
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <AuthShell
      title="Reset your password"
      subtitle="Enter your registered email to start the recovery process."
      footerText="Back to"
      footerHref="/login"
      footerLabel="Login"
    >
      <form className="space-y-5" onSubmit={handleSubmit} noValidate>
        <FormField
          id="email"
          label="Email"
          type="email"
          value={email}
          onChange={(event) => setEmail(event.target.value)}
          placeholder="name@example.com"
          autoComplete="email"
          required
        />

        <button
          type="submit"
          disabled={isSubmitting}
          className="inline-flex w-full items-center justify-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200 disabled:cursor-not-allowed disabled:opacity-60"
        >
          {isSubmitting ? "Sending request..." : "Send reset link"}
        </button>
      </form>

      <StatusMessage type={status.type} text={status.text} />
    </AuthShell>
  );
}
