"use client";

import { useState } from "react";
import type { StoryMedia } from "@/lib/worldgraph";

function mediaKind(mimeType: string): "image" | "video" | "audio" | "other" {
  if (mimeType.startsWith("image/")) {
    return "image";
  }
  if (mimeType.startsWith("video/")) {
    return "video";
  }
  if (mimeType.startsWith("audio/")) {
    return "audio";
  }
  return "other";
}

function pauseOtherMedia(current: HTMLMediaElement): void {
  document.querySelectorAll<HTMLMediaElement>("audio, video").forEach((player) => {
    if (player !== current && !player.paused) {
      player.pause();
    }
  });
}

export function MediaPlayer({
  media,
  compact = false,
  eager = false,
}: {
  media: StoryMedia;
  compact?: boolean;
  eager?: boolean;
}) {
  const [failed, setFailed] = useState(false);
  const kind = mediaKind(media.mimeType);
  const label = media.alt || media.title || "Story media";

  if (failed || kind === "other") {
    return (
      <div className="flex min-h-24 items-center justify-center rounded-wg bg-wg-espresso/5 p-4 text-center text-sm text-wg-charcoal/75">
        <a href={media.url} className="font-semibold text-wg-blueprint">
          Open {media.title || "media file"}
        </a>
      </div>
    );
  }

  if (kind === "image") {
    return (
      // Native images intentionally support WordPress media offload hosts that
      // cannot be known at build time by next/image's remote allowlist.
      // eslint-disable-next-line @next/next/no-img-element
      <img
        src={media.url}
        alt={label}
        loading={eager ? "eager" : "lazy"}
        onError={() => setFailed(true)}
        className={`w-full bg-wg-espresso/5 object-cover ${
          compact ? "aspect-[4/3]" : "max-h-[72vh] object-contain"
        }`}
      />
    );
  }

  if (kind === "video") {
    return (
      <video
        controls
        playsInline
        preload="metadata"
        poster={media.posterUrl || media.thumbnailUrl}
        aria-label={label}
        onPlay={(event) => pauseOtherMedia(event.currentTarget)}
        onError={() => setFailed(true)}
        className={`w-full bg-wg-ink ${compact ? "aspect-video object-cover" : "max-h-[72vh]"}`}
      >
        <source src={media.url} type={media.mimeType} />
        Your browser does not support this video. <a href={media.url}>Open the file.</a>
      </video>
    );
  }

  return (
    <div className="space-y-2 rounded-wg border border-wg-sepia/35 bg-white/65 p-4">
      <p className="truncate text-sm font-semibold text-wg-espresso">{media.title || label}</p>
      <audio
        controls
        preload="metadata"
        aria-label={label}
        onPlay={(event) => pauseOtherMedia(event.currentTarget)}
        onError={() => setFailed(true)}
        className="w-full accent-wg-sepia"
      >
        <source src={media.url} type={media.mimeType} />
        Your browser does not support this audio. <a href={media.url}>Open the file.</a>
      </audio>
    </div>
  );
}
