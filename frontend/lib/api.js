export const API_BASE_URL =
  process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000/api";

export const apiConfig = {
  baseUrl: API_BASE_URL,
  timeoutMs: 15000,
};

export class ApiError extends Error {
  constructor(message, status, errors = {}) {
    super(message);
    this.name = "ApiError";
    this.status = status;
    this.errors = errors;
  }
}

export function buildApiUrl(path) {
  const normalizedPath = path.startsWith("/") ? path.slice(1) : path;
  return `${API_BASE_URL.replace(/\/$/, "")}/${normalizedPath}`;
}

function readCookie(name) {
  if (typeof document === "undefined") return null;

  const cookie = document.cookie
    .split("; ")
    .find((entry) => entry.startsWith(`${name}=`));

  return cookie ? decodeURIComponent(cookie.slice(name.length + 1)) : null;
}

async function ensureCsrfCookie() {
  const response = await fetch(buildApiUrl("csrf-cookie"), {
    credentials: "include",
    headers: { Accept: "application/json" },
    signal: AbortSignal.timeout(apiConfig.timeoutMs),
  });

  if (!response.ok) {
    throw new ApiError("Could not initialize the secure API session.", response.status);
  }

  const payload = await response.json();
  if (typeof payload.csrf_token === "string" && payload.csrf_token.length > 0) {
    return { token: payload.csrf_token, header: "X-CSRF-TOKEN" };
  }

  const csrfToken = readCookie("XSRF-TOKEN");
  if (!csrfToken) {
    throw new Error("The API did not provide a CSRF cookie.");
  }

  return { token: csrfToken, header: "X-XSRF-TOKEN" };
}

export function getApiErrorMessage(error) {
  if (error instanceof ApiError) return error.message;
  if (error?.name === "TimeoutError") return "The API request timed out. Please try again.";
  if (error instanceof TypeError) {
    return "Could not reach the Laravel API. Check that it is running and the API URL is correct.";
  }
  return error instanceof Error ? error.message : "The request failed. Please try again.";
}

export async function apiRequest(path, { method = "GET", body, headers, ...options } = {}) {
  const normalizedMethod = method.toUpperCase();
  const requiresCsrf = !["GET", "HEAD", "OPTIONS"].includes(normalizedMethod);
  const requestHeaders = new Headers(headers);
  requestHeaders.set("Accept", "application/json");

  let csrfToken;
  if (requiresCsrf) {
    const csrf = await ensureCsrfCookie();
    requestHeaders.set(csrf.header, csrf.token);
  }

  let requestBody = body;
  if (body !== undefined && !(body instanceof FormData)) {
    requestHeaders.set("Content-Type", "application/json");
    requestBody = JSON.stringify(body);
  }

  const response = await fetch(buildApiUrl(path), {
    ...options,
    method: normalizedMethod,
    body: requestBody,
    credentials: "include",
    headers: requestHeaders,
    signal: options.signal ?? AbortSignal.timeout(apiConfig.timeoutMs),
  });

  const responseText = response.status === 204 ? "" : await response.text();
  let payload = null;
  if (responseText) {
    try {
      payload = JSON.parse(responseText);
    } catch {
      if (response.ok) {
        throw new Error("The Laravel API returned an invalid JSON response.");
      }
    }
  }

  if (!response.ok) {
    const validationMessages = Object.values(payload?.errors ?? {}).flat();
    const message = validationMessages.length
      ? validationMessages.join(" ")
      : payload?.message || `The API request failed with status ${response.status}.`;
    throw new ApiError(message, response.status, payload?.errors ?? {});
  }

  return payload;
}
