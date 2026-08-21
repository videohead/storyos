import { NextRequest, NextResponse } from "next/server";
import { worldgraphAdminFetch, worldgraphErrorResponse } from "@/lib/worldgraph-admin";

type RouteContext = {
  params: Promise<{ id: string }>;
};

export async function POST(_request: NextRequest, context: RouteContext) {
  try {
    const { id } = await context.params;
    const response = await worldgraphAdminFetch<unknown>(`/connections/${id}/catalog/sync`, {
      method: "POST",
      body: "{}",
    });
    return NextResponse.json(response);
  } catch (error) {
    return worldgraphErrorResponse(error, "Failed to sync connection catalog.");
  }
}
