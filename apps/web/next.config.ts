import type { NextConfig } from "next";

// The browser refuses any fetch/XHR to an origin outside connect-src, so the API
// the app is configured to call has to be listed there. In the deployment this
// starter documents -- frontend on Vercel, Laravel API on its own domain -- that
// is a different origin from the page, and a bare `connect-src 'self'` blocks
// every API call before it leaves the browser. It shows up as `blocked:csp` in
// the network tab with nothing at all in the backend logs.
//
// NEXT_PUBLIC_API_BASE_URL is inlined at build time, so derive the origin from
// it here. Change that env var and you must rebuild -- true of every
// NEXT_PUBLIC_* value, and the reason this cannot be read at runtime.
function apiOrigin(): string | null {
    const raw = process.env.NEXT_PUBLIC_API_BASE_URL;
    if (!raw) return null;
    try {
        return new URL(raw).origin;
    } catch {
        // Relative value (e.g. "/api/v1", routed through the same-origin proxy):
        // 'self' already covers it.
        return null;
    }
}

function connectSrc(): string {
    const origin = apiOrigin();
    // 'self' stays for the same-origin proxy at app/api/[...path]/route.ts.
    return ["'self'", origin].filter(Boolean).join(" ");
}

const securityHeaders = [
    // Prevent browsers from guessing a different MIME type than declared.
    { key: "X-Content-Type-Options", value: "nosniff" },
    // Block the page from being framed by other origins.
    { key: "X-Frame-Options", value: "SAMEORIGIN" },
    // Reduce referrer data sent to third parties.
    { key: "Referrer-Policy", value: "strict-origin-when-cross-origin" },
    // Force HTTPS for one year (applied in production; has no effect over plain HTTP).
    { key: "Strict-Transport-Security", value: "max-age=31536000; includeSubDomains" },
    // Permissions Policy — disable features the app doesn't use.
    { key: "Permissions-Policy", value: "camera=(), microphone=(), geolocation=()" },
    // Content-Security-Policy.
    // NOTE: Next.js App Router injects inline scripts for hydration. Until nonce-based
    // CSP is wired up (see Next.js docs on nonces), script-src keeps 'unsafe-inline'
    // and 'unsafe-eval' to avoid breaking the runtime. Tighten per your threat model.
    {
        key: "Content-Security-Policy",
        value: [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com",
            "img-src 'self' data: blob:",
            `connect-src ${connectSrc()}`,
            "frame-ancestors 'none'",
        ].join("; "),
    },
];

const nextConfig: NextConfig = {
    reactStrictMode: true,
    async headers() {
        return [
            {
                source: "/(.*)",
                headers: securityHeaders,
            },
        ];
    },
};

export default nextConfig;
