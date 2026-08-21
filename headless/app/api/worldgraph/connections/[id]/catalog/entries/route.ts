import { NextRequest, NextResponse } from "next/server";
import { worldgraphAdminFetch, worldgraphErrorResponse } from "@/lib/worldgraph-admin";

type RouteContext = {
  params: Promise<{ id: string }>;
};

type EntryAction = "enable" | "disable" | "materialize" | "download";

const validActions: Record<EntryAction, true> = {
  enable: true,
  disable: true,
  materialize: true,
  download: true,
};

export async function POST(request: NextRequest, context: RouteContext) {
  try {
    const body = (await request.json()) as {
      entryId?: string;
      action?: EntryAction;
    };

    const { id } = await context.params;
    const entryId = String(body.entryId ?? "").trim();
    const action = body.action;

    if (!entryId) {
      return NextResponse.json({ message: "entryId is required." }, { status: 400 });
    }

    if (!action || !validActions[action]) {
      return NextResponse.json({ message: "action must be one of enable, disable, materialize, download." }, { status: 400 });
    }

    const encodedEntryId = encodeURIComponent(entryId);
    const response = await worldgraphAdminFetch<unknown>(
      `/connections/${id}/catalog/entries/${encodedEntryId}/${action}`,
      {
        method: "POST",
        body: "{}",
      }
    );

    return NextResponse.json(response);
  } catch (error) {
    return worldgraphErrorResponse(error, "Failed to run catalog entry action.");
  }
}
