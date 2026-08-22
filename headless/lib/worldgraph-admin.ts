import { NextResponse } from "next/server";

const WORDPRESS_URL = process.env.WORDPRESS_URL;
const ADMIN_USER = process.env.WORLDGRAPH_ADMIN_USER;
const ADMIN_APP_PASSWORD = process.env.WORLDGRAPH_ADMIN_APP_PASSWORD;

function worldgraphBaseUrl(): string {
  if (!WORDPRESS_URL) {
    throw new Error("WORDPRESS_URL is not configured in headless environment.");
  }

  return `${WORDPRESS_URL.replace(/\/$/, "")}/wp-json/worldgraph/v1`;
}

function adminAuthHeader(): string {
  if (!ADMIN_USER || !ADMIN_APP_PASSWORD) {
    throw new Error(
      "Set WORLDGRAPH_ADMIN_USER and WORLDGRAPH_ADMIN_APP_PASSWORD in headless/.env.local to use World Graph admin routes."
    );
  }

  const raw = `${ADMIN_USER}:${ADMIN_APP_PASSWORD}`;
  return `Basic ${Buffer.from(raw, "utf8").toString("base64")}`;
}

export async function worldgraphAdminFetch<T>(
  endpoint: string,
  init: RequestInit = {}
): Promise<T> {
  const url = `${worldgraphBaseUrl()}${endpoint.startsWith("/") ? endpoint : `/${endpoint}`}`;
  const response = await fetch(url, {
    ...init,
    headers: {
      Authorization: adminAuthHeader(),
      "Content-Type": "application/json",
      ...(init.headers ?? {}),
    },
    cache: "no-store",
  });

  const contentType = response.headers.get("content-type") ?? "";
  const payload = contentType.includes("application/json")
    ? await response.json()
    : await response.text();

  if (!response.ok) {
    const message =
      typeof payload === "object" && payload && "message" in payload
        ? String((payload as { message?: string }).message)
        : `World Graph API request failed (${response.status})`;

    throw new Error(message);
  }

  return payload as T;
}

export function worldgraphErrorResponse(error: unknown, fallback: string) {
  const message = error instanceof Error ? error.message : fallback;
  return NextResponse.json({ message }, { status: 500 });
}
