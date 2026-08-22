"use client";

import Link from "next/link";
import { useState } from "react";
import type { StoryMedia } from "@/lib/worldgraph";
import { MediaPlayer } from "@/components/story/media-player";

export function CharacterFlipCard({
  href,
  titleHtml,
  name,
  media,
  age,
  roles,
  biographyHtml,
  personalityHtml,
  motivationHtml,
}: {
  href: string;
  titleHtml: string;
  name: string;
  media?: StoryMedia;
  age?: string;
  roles: string[];
  biographyHtml?: string;
  personalityHtml?: string;
  motivationHtml?: string;
}) {
  const [flipped, setFlipped] = useState(false);
  const backHtml = personalityHtml || motivationHtml || biographyHtml;

  return (
    <article className="wg-character-card rounded-wg border-2 border-wg-espresso bg-wg-ivory shadow-wg">
      <div className={`wg-character-card__inner ${flipped ? "is-flipped" : ""}`}>
        <div
          className="wg-character-card__face overflow-hidden"
          aria-hidden={flipped}
          inert={flipped}
        >
          {media ? (
            <MediaPlayer media={media} compact />
          ) : (
            <div className="flex aspect-[4/3] items-center justify-center bg-wg-espresso/10 font-headline text-5xl text-wg-espresso/35">
              {name.slice(0, 1).toUpperCase()}
            </div>
          )}
          <div className="space-y-3 p-5">
            <div>
              <p className="font-headline text-xs font-bold uppercase tracking-[0.18em] text-wg-sepia">
                {roles.join(" • ") || "Character"}
              </p>
              <h2 className="mt-1 text-2xl text-wg-espresso">
                <Link
                  href={href}
                  tabIndex={flipped ? -1 : undefined}
                  className="no-underline hover:text-wg-blueprint"
                  dangerouslySetInnerHTML={{ __html: titleHtml }}
                />
              </h2>
            </div>
            {age && <p className="text-sm text-wg-charcoal/70">Age: {age}</p>}
            {biographyHtml && (
              <div
                className="line-clamp-4 text-sm leading-relaxed text-wg-charcoal/80"
                dangerouslySetInnerHTML={{ __html: biographyHtml }}
              />
            )}
          </div>
        </div>

        <div
          className="wg-character-card__face wg-character-card__back space-y-4 bg-wg-espresso p-6 text-wg-ivory"
          aria-hidden={!flipped}
          inert={!flipped}
        >
          <p className="font-headline text-xs font-bold uppercase tracking-[0.18em] text-wg-sepia">
            Character profile
          </p>
          <h2 className="text-2xl text-wg-ivory" dangerouslySetInnerHTML={{ __html: titleHtml }} />
          {backHtml ? (
            <div
              className="line-clamp-6 text-sm leading-relaxed text-wg-ivory/85"
              dangerouslySetInnerHTML={{ __html: backHtml }}
            />
          ) : (
            <p className="text-sm text-wg-muted">No additional profile notes have been published yet.</p>
          )}
          <Link
            href={href}
            tabIndex={flipped ? undefined : -1}
            className="inline-block font-headline text-sm font-bold uppercase tracking-wider text-wg-ivory underline decoration-wg-sepia"
          >
            Full profile
          </Link>
        </div>
      </div>

      <div className="border-t border-wg-sepia/35 p-3 text-center">
        <button
          type="button"
          aria-pressed={flipped}
          onClick={() => setFlipped((value) => !value)}
          className="rounded-wg border border-wg-espresso px-3 py-2 font-headline text-xs font-bold uppercase tracking-wider text-wg-espresso hover:bg-wg-espresso hover:text-wg-ivory"
        >
          {flipped ? "Show portrait" : "Flip profile card"}
        </button>
      </div>
    </article>
  );
}
