'use client';

import { useState } from "react";
import { QRCodeSVG } from "qrcode.react";
import FormField from "./FormField";
import StatusMessage from "./StatusMessage";
import { apiRequest, getApiErrorMessage } from "@/lib/api";

export default function MfaSettings({ enabled, onEnabledChange }) {
  const [password, setPassword] = useState("");
  const [code, setCode] = useState("");
  const [recoveryCode, setRecoveryCode] = useState("");
  const [useRecoveryCode, setUseRecoveryCode] = useState(false);
  const [setup, setSetup] = useState(null);
  const [recoveryCodes, setRecoveryCodes] = useState([]);
  const [status, setStatus] = useState({ type: "", text: "" });
  const [isBusy, setIsBusy] = useState(false);

  async function beginSetup(event) {
    event.preventDefault();
    setIsBusy(true);
    setStatus({ type: "info", text: "Preparing secure authenticator setup..." });

    try {
      const result = await apiRequest("mfa/setup", {
        method: "POST",
        body: { password },
      });
      setSetup(result);
      setCode("");
      setStatus({ type: "info", text: "Scan the QR code or enter the secret in your authenticator app, then verify a current code." });
    } catch (error) {
      setStatus({ type: "error", text: getApiErrorMessage(error) });
    } finally {
      setIsBusy(false);
    }
  }

  async function enableMfa(event) {
    event.preventDefault();
    setIsBusy(true);
    setStatus({ type: "info", text: "Verifying and enabling MFA..." });

    try {
      const result = await apiRequest("mfa/enable", {
        method: "POST",
        body: { password, code },
      });
      setRecoveryCodes(result.recovery_codes);
      setSetup(null);
      setPassword("");
      setCode("");
      onEnabledChange(true);
      setStatus({ type: "success", text: "MFA is enabled. Save these recovery codes now; they are shown only once." });
    } catch (error) {
      setStatus({ type: "error", text: getApiErrorMessage(error) });
    } finally {
      setIsBusy(false);
    }
  }

  async function disableMfa(event) {
    event.preventDefault();
    setIsBusy(true);
    setStatus({ type: "info", text: "Verifying and disabling MFA..." });

    try {
      await apiRequest("mfa/disable", {
        method: "POST",
        body: {
          password,
          ...(useRecoveryCode ? { recovery_code: recoveryCode } : { code }),
        },
      });
      setRecoveryCodes([]);
      setPassword("");
      setCode("");
      setRecoveryCode("");
      onEnabledChange(false);
      setStatus({ type: "success", text: "MFA has been disabled." });
    } catch (error) {
      setStatus({ type: "error", text: getApiErrorMessage(error) });
    } finally {
      setIsBusy(false);
    }
  }

  return (
    <section className="mt-8 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 className="text-lg font-semibold text-slate-900">Multi-factor authentication</h2>
          <p className="mt-1 text-sm text-slate-600">
            {enabled ? "Authenticator verification is required at sign-in." : "Add an authenticator app for an extra sign-in check."}
          </p>
        </div>
        <span className={`rounded-full px-3 py-1 text-xs font-semibold ${enabled ? "bg-green-50 text-green-700" : "bg-slate-100 text-slate-600"}`}>
          {enabled ? "Enabled" : "Disabled"}
        </span>
      </div>

      {!enabled && !setup ? (
        <form className="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end" onSubmit={beginSetup}>
          <div className="w-full">
            <FormField
              id="mfaPassword"
              label="Confirm your password to begin setup"
              type="password"
              value={password}
              onChange={(event) => setPassword(event.target.value)}
              autoComplete="current-password"
              required
            />
          </div>
          <button
            type="submit"
            disabled={isBusy}
            className="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-500 disabled:opacity-60"
          >
            {isBusy ? "Preparing..." : "Set up authenticator"}
          </button>
        </form>
      ) : null}

      {setup && !enabled ? (
        <form className="mt-5 space-y-4" onSubmit={enableMfa}>
          <div className="flex flex-col items-start gap-4 sm:flex-row sm:items-center">
            <div className="rounded-xl border border-slate-200 bg-white p-3" aria-label="Authenticator enrollment QR code">
              <QRCodeSVG value={setup.otpauth_url} size={176} level="M" />
            </div>
            <div className="min-w-0">
              <p className="text-sm font-medium text-slate-800">Scan the QR code or enter this secret manually:</p>
              <code className="mt-2 block break-all rounded-lg bg-slate-100 p-3 text-sm">{setup.secret}</code>
              <p className="mt-2 text-xs text-slate-500">The secret is shown only during setup and is encrypted at rest after you enable MFA.</p>
            </div>
          </div>
          <div className="grid gap-3 sm:grid-cols-2">
            <FormField
              id="mfaSetupPassword"
              label="Confirm your password"
              type="password"
              value={password}
              onChange={(event) => setPassword(event.target.value)}
              autoComplete="current-password"
              required
            />
            <FormField
              id="mfaSetupCode"
              label="Current 6-digit authenticator code"
              value={code}
              onChange={(event) => setCode(event.target.value)}
              autoComplete="one-time-code"
              inputMode="numeric"
              required
              minLength={6}
              maxLength={6}
            />
          </div>
          <button
            type="submit"
            disabled={isBusy}
            className="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-500 disabled:opacity-60"
          >
            {isBusy ? "Verifying..." : "Verify and enable MFA"}
          </button>
        </form>
      ) : null}

      {enabled ? (
        <form className="mt-4 space-y-4" onSubmit={disableMfa}>
          <div className="grid gap-3 sm:grid-cols-2">
            <FormField
              id="mfaDisablePassword"
              label="Confirm your password"
              type="password"
              value={password}
              onChange={(event) => setPassword(event.target.value)}
              autoComplete="current-password"
              required
            />
            {useRecoveryCode ? (
              <FormField
                id="mfaDisableRecovery"
                label="Unused recovery code"
                value={recoveryCode}
                onChange={(event) => setRecoveryCode(event.target.value)}
                required
              />
            ) : (
              <FormField
                id="mfaDisableCode"
                label="Current authenticator code"
                value={code}
                onChange={(event) => setCode(event.target.value)}
                autoComplete="one-time-code"
                inputMode="numeric"
                required
                minLength={6}
                maxLength={6}
              />
            )}
          </div>
          <div className="flex flex-wrap items-center gap-4">
            <button
              type="button"
              onClick={() => setUseRecoveryCode((current) => !current)}
              className="text-sm font-medium text-blue-600 hover:text-blue-500"
            >
              {useRecoveryCode ? "Use authenticator code instead" : "Use a recovery code instead"}
            </button>
            <button
              type="submit"
              disabled={isBusy}
              className="rounded-xl border border-red-200 px-4 py-2.5 text-sm font-semibold text-red-700 hover:bg-red-50 disabled:opacity-60"
            >
              {isBusy ? "Verifying..." : "Disable MFA"}
            </button>
          </div>
        </form>
      ) : null}

      {recoveryCodes.length ? (
        <div className="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4">
          <h3 className="font-semibold text-amber-900">Save your recovery codes</h3>
          <p className="mt-1 text-sm text-amber-800">Each code works once. They will not be displayed again.</p>
          <ul className="mt-3 grid grid-cols-2 gap-2 font-mono text-sm text-slate-800 sm:grid-cols-5">
            {recoveryCodes.map((value) => <li key={value}>{value.slice(0, 6)}-{value.slice(6)}</li>)}
          </ul>
        </div>
      ) : null}

      <StatusMessage type={status.type} text={status.text} />
    </section>
  );
}
