import { revalidateTag } from "next/cache";
import { NextRequest, NextResponse } from "next/server";

export const maxDuration = 30;

/**
 * WordPress webhook handler for content revalidation.
 * Compatible with the "headless-revalidate" plugin (wordpress/wp-content/plugins/worldgraph/plugins/headless-revalidate)
 * and with 9d8dev/next-wp's next-revalidate plugin.
 */
export async function POST(request: NextRequest) {
  try {
    const requestBody = await request.json();
    const secret = request.headers.get("x-webhook-secret");

    if (secret !== process.env.WORDPRESS_WEBHOOK_SECRET) {
      console.error("Invalid webhook secret");
      return NextResponse.json(
        { message: "Invalid webhook secret" },
        { status: 401 }
      );
    }

    const { contentType, contentId, slug } = requestBody;

    if (!contentType) {
      return NextResponse.json(
        { message: "Missing content type" },
        { status: 400 }
      );
    }

    revalidateTag("wordpress");
    revalidateTag(contentType);

    if (contentId) {
      revalidateTag(`${contentType.replace(/s$/, "")}:${contentId}`);
    }

    if (slug) {
      revalidateTag(`${contentType.replace(/s$/, "")}:${slug}`);
    }

    return NextResponse.json({ revalidated: true, now: Date.now() });
  } catch (error) {
    console.error("Error revalidating", error);
    return NextResponse.json(
      { message: "Error revalidating", error: String(error) },
      { status: 500 }
    );
  }
}
