"use client";

import { useEffect, useState } from "react";
import type { StoryMedia } from "@/lib/worldgraph";
import { MediaPlayer } from "@/components/story/media-player";

function thumbnailLabel(media: StoryMedia, index: number): string {
  const intent = media.intent
    ?.replace(/^(character|prop)-/, "")
    .replace(/-/g, " ")
    .replace(/\b\w/g, (letter) => letter.toUpperCase());
  return intent || media.title || media.alt || `Media item ${index + 1}`;
}

export function MediaGallery({
  media,
  label = "Media gallery",
}: {
  media: StoryMedia[];
  label?: string;
}) {
  const [selectedIndex, setSelectedIndex] = useState(0);

  useEffect(() => {
    if (selectedIndex >= media.length) {
      setSelectedIndex(0);
    }
  }, [media.length, selectedIndex]);

  if (!media.length) {
    return null;
  }

  const selected = media[selectedIndex] ?? media[0];
  const hasMultiple = media.length > 1;
  const selectPrevious = () =>
    setSelectedIndex((index) => (index - 1 + media.length) % media.length);
  const selectNext = () => setSelectedIndex((index) => (index + 1) % media.length);

  return (
    <section aria-label={label} className="space-y-3">
      <div className="overflow-hidden rounded-wg border-2 border-wg-espresso bg-white shadow-wg">
        <MediaPlayer key={`${selected.id}:${selected.url}`} media={selected} eager />
        {(selected.caption || hasMultiple) && (
          <div className="flex items-center justify-between gap-4 border-t border-wg-sepia/35 px-4 py-3 text-sm">
            <p className="text-wg-charcoal/75">
              {selected.caption || thumbnailLabel(selected, selectedIndex)}
            </p>
            {hasMultiple && (
              <span className="shrink-0 font-headline text-xs font-semibold uppercase tracking-wider text-wg-espresso">
                {selectedIndex + 1} / {media.length}
              </span>
            )}
          </div>
        )}
      </div>

      {hasMultiple && (
        <div className="space-y-3">
          <div className="flex justify-between gap-3">
            <button
              type="button"
              onClick={selectPrevious}
              className="rounded-wg border border-wg-espresso px-3 py-2 font-headline text-xs font-bold uppercase tracking-wider text-wg-espresso hover:bg-wg-espresso hover:text-wg-ivory"
            >
              Previous
            </button>
            <button
              type="button"
              onClick={selectNext}
              className="rounded-wg border border-wg-espresso px-3 py-2 font-headline text-xs font-bold uppercase tracking-wider text-wg-espresso hover:bg-wg-espresso hover:text-wg-ivory"
            >
              Next
            </button>
          </div>
          <ul className="grid grid-cols-3 gap-2 sm:grid-cols-5">
            {media.map((item, index) => {
              const isImage = item.mimeType.startsWith("image/");
              const isSelected = index === selectedIndex;
              return (
                <li key={`${item.id}:${item.url}`}>
                  <button
                    type="button"
                    aria-label={`Show ${thumbnailLabel(item, index)}`}
                    aria-current={isSelected ? "true" : undefined}
                    onClick={() => setSelectedIndex(index)}
                    className={`min-h-20 w-full overflow-hidden rounded-wg border-2 text-xs font-semibold transition ${
                      isSelected
                        ? "border-wg-sepia bg-wg-sepia/15"
                        : "border-wg-espresso/25 bg-white/60 hover:border-wg-espresso"
                    }`}
                  >
                    {isImage ? (
                      // See MediaPlayer: gallery sources may live on media-offload hosts.
                      // eslint-disable-next-line @next/next/no-img-element
                      <img
                        src={item.thumbnailUrl || item.url}
                        alt=""
                        loading="lazy"
                        className="aspect-square h-full w-full object-cover"
                      />
                    ) : (
                      <span className="flex min-h-20 items-center justify-center px-2 text-wg-espresso">
                        {item.mimeType.startsWith("audio/") ? "Audio" : "Video"} {index + 1}
                      </span>
                    )}
                  </button>
                </li>
              );
            })}
          </ul>
        </div>
      )}
    </section>
  );
}
