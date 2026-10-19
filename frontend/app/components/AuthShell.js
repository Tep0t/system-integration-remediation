import Link from "next/link";

export default function AuthShell({ title, subtitle, children, footerText, footerHref, footerLabel }) {
  return (
    <main className="flex min-h-screen items-center justify-center bg-slate-100 px-4 py-10">
      <div className="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-lg shadow-slate-200/60 sm:p-8">
        <div className="mb-6">
          <p className="text-xs font-semibold uppercase tracking-[0.18em] text-blue-600">System Integration</p>
          <h1 className="mt-3 text-2xl font-bold text-slate-900">{title}</h1>
          {subtitle ? <p className="mt-2 text-sm text-slate-600">{subtitle}</p> : null}
        </div>

        {children}

        {footerText && footerHref && footerLabel ? (
          <p className="mt-6 text-center text-sm text-slate-600">
            {footerText}{" "}
            <Link href={footerHref} className="font-medium text-blue-600 hover:text-blue-500">
              {footerLabel}
            </Link>
          </p>
        ) : null}
      </div>
    </main>
  );
}
