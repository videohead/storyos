import { NextResponse } from "next/server";
import { worldgraphAdminFetch, worldgraphErrorResponse } from "@/lib/worldgraph-admin";

export async function GET() {
  try {
    const response = await worldgraphAdminFetch<unknown[]>(
      "/connections?provider_type=comfyui&per_page=100"
    );
    return NextResponse.json(response);
  } catch (error) {
    return worldgraphErrorResponse(error, "Failed to load Comfy connections.");
  }
}
