'use client';

import { useState } from "react";
import { useRouter } from "next/navigation";
import AuthShell from "../components/AuthShell";
import FormField from "../components/FormField";
import StatusMessage from "../components/StatusMessage";
import { apiRequest, getApiErrorMessage } from "@/lib/api";

export default function RegisterPage() {
  const router = useRouter();
  const [form, setForm] = useState({
    name: "",
    email: "",
    password: "",
    confirmPassword: "",
  });
  const [status, setStatus] = useState({ type: "", text: "" });
  const [isSubmitting, setIsSubmitting] = useState(false);

  function handleChange(event) {
    const { name, value } = event.target;
    setForm((current) => ({ ...current, [name]: value }));
  }

  async function handleSubmit(event) {
    event.preventDefault();
    const name = form.name.trim();
    const email = form.email.trim();
    const password = form.password;
    const confirmPassword = form.confirmPassword;

    if (!name || !email || !password || !confirmPassword) {
      setStatus({ type: "error", text: "All fields are required." });
      return;
    }

    if (password !== confirmPassword) {
      setStatus({ type: "error", text: "Passwords do not match." });
      return;
    }

    const emailValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    if (!emailValid) {
      setStatus({ type: "error", text: "Enter a valid email address." });
      return;
    }

    if (password.length < 12) {
      setStatus({ type: "error", text: "Password must be at least 12 characters long." });
      return;
    }

    setIsSubmitting(true);
    setStatus({ type: "info", text: "Creating your account..." });

    try {
      await apiRequest("register", {
        method: "POST",
        body: { name, email, password, password_confirmation: confirmPassword },
      });
      setForm((current) => ({ ...current, password: "", confirmPassword: "" }));
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
      title="Create account"
      subtitle="Set up your student account for the integration project."
      footerText="Already have an account?"
      footerHref="/login"
      footerLabel="Sign in"
    >
      <form className="space-y-5" onSubmit={handleSubmit} noValidate>
        <FormField
          id="name"
          label="Full name"
          value={form.name}
          onChange={handleChange}
          placeholder="Enter your full name"
          autoComplete="name"
          required
        />

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
          placeholder="Create a password"
          autoComplete="new-password"
          required
          minLength={12}
        />

        <FormField
          id="confirmPassword"
          label="Confirm password"
          type="password"
          value={form.confirmPassword}
          onChange={handleChange}
          placeholder="Repeat your password"
          autoComplete="new-password"
          required
          minLength={12}
        />

        <button
          type="submit"
          disabled={isSubmitting}
          className="inline-flex w-full items-center justify-center rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200 disabled:cursor-not-allowed disabled:opacity-60"
        >
          {isSubmitting ? "Creating account..." : "Register"}
        </button>
      </form>

      <StatusMessage type={status.type} text={status.text} />
    </AuthShell>
  );
}
