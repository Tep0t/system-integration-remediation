export default function StatusMessage({ type, text }) {
  if (!text) return null;

  const classes =
    type === "error"
      ? "border-red-200 bg-red-50 text-red-700"
      : type === "success"
        ? "border-green-200 bg-green-50 text-green-700"
        : "border-blue-200 bg-blue-50 text-blue-700";

  return (
    <div
      className={`mt-5 rounded-xl border px-3 py-2 text-sm ${classes}`}
      role={type === "error" ? "alert" : "status"}
      aria-live={type === "error" ? "assertive" : "polite"}
    >
      {text}
    </div>
  );
}
