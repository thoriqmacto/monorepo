// apps/web/app/api/[...path]/route.ts
import type { NextRequest } from "next/server";

export const runtime = "nodejs";         // ensure Node runtime (not edge) for widest compat
export const dynamic = "force-dynamic";  // don't cache proxy responses

const API_BASE =
    process.env.NEXT_PUBLIC_API_URL || "http://localhost:8000";

/**
 * Build the target URL from the catch-all route and original query string
 */
function buildTargetUrl(req: NextRequest, path: string[]) {
    const search = req.nextUrl.search; // includes leading "?" if present
    const joined = path.join("/");
    // If your backend expects "/api/..." keep it; otherwise drop it.
    return `${API_BASE}/api/${joined}${search}`;
}

/**
 * Copy headers but drop hop-by-hop / problematic ones
 */
function forwardHeaders(req: NextRequest) {
    const headers = new Headers(req.headers);
    [
        "host",
        "connection",
        "content-length",
        "accept-encoding",
        "x-forwarded-proto",
        "x-forwarded-host",
    ].forEach((h) => headers.delete(h));
    return headers;
}

async function proxy(method: string, req: NextRequest, params: { path: string[] }) {
    const target = buildTargetUrl(req, params.path);
    const headers = forwardHeaders(req);

    // Only pass a body for non-GET/HEAD
    const hasBody = method !== "GET" && method !== "HEAD";
    const body = hasBody ? (await req.blob()) : undefined;

    const resp = await fetch(target, {
        method,
        headers,
        body,
        redirect: "manual",
        cache: "no-store",
    });

    // Pass-through response (filter/set headers if needed)
    const outHeaders = new Headers(resp.headers);
    outHeaders.delete("content-encoding");
    outHeaders.delete("transfer-encoding");

    return new Response(resp.body, {
        status: resp.status,
        statusText: resp.statusText,
        headers: outHeaders,
    });
}

export async function GET(req: NextRequest, { params }: { params: { path: string[] } }) {
    return proxy("GET", req, params);
}
export async function POST(req: NextRequest, { params }: { params: { path: string[] } }) {
    return proxy("POST", req, params);
}
export async function PUT(req: NextRequest, { params }: { params: { path: string[] } }) {
    return proxy("PUT", req, params);
}
export async function PATCH(req: NextRequest, { params }: { params: { path: string[] } }) {
    return proxy("PATCH", req, params);
}
export async function DELETE(req: NextRequest, { params }: { params: { path: string[] } }) {
    return proxy("DELETE", req, params);
}
export async function OPTIONS(req: NextRequest, { params }: { params: { path: string[] } }) {
    return proxy("OPTIONS", req, params);
}