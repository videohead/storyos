import type { Metadata } from "next";
import Link from "next/link";
import { storyResourceConfig, type StoryType } from "@/lib/worldgraph";

export const metadata: Metadata = {
  title: "Story | World Graph Studio",
  description: "Explore published projects, worlds, characters, scenes, props, sounds, and songs.",
};

const presentationNotes: Record<StoryType, string> = {
  projects: "Production status and published graph analysis at a glance.",
  worlds: "Large-format guides to the rules, geography, themes, and history of each world.",
  characters: "Portrait cards that flip to reveal the inner character profile.",
  scenes: "Scene summaries followed by their published sequence of shots.",
  props: "Multi-frame visual studies with purpose, description, and design notes.",
  sounds: "Playable music, narration, ambience, Foley, effects, and other soundtrack cues.",
};

export default function StoryPage() {
  return (
    <div className="space-y-10">
      <header className="max-w-4xl space-y-4">
        <p className="font-headline text-xs font-bold uppercase tracking-[0.24em] text-wg-sepia">
          Published Story Graph
        </p>
        <h1 className="text-5xl font-semibold text-wg-espresso md:text-6xl">
          Enter the story
        </h1>
        <p className="max-w-3xl text-xl leading-relaxed text-wg-charcoal/75">
          Browse the people, places, scenes, objects, and sounds that shape each production.
          Every view is assembled from the public WordPress story record.
        </p>
      </header>

      <nav aria-label="Story collections">
        <ul className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
          {(Object.entries(storyResourceConfig) as Array<
            [StoryType, (typeof storyResourceConfig)[StoryType]]
          >).map(([storyType, config], index) => (
            <li key={storyType}>
              <Link
                href={`/story/${storyType}`}
                className="group flex h-full min-h-64 flex-col justify-between rounded-wg border-2 border-wg-espresso bg-wg-ivory p-6 no-underline shadow-wg transition hover:-translate-y-1 hover:bg-white"
              >
                <span className="font-headline text-6xl font-semibold text-wg-sepia/35" aria-hidden="true">
                  {String(index + 1).padStart(2, "0")}
                </span>
                <span className="space-y-3">
                  <span className="block font-headline text-3xl font-semibold text-wg-espresso group-hover:text-wg-blueprint">
                    {config.plural}
                  </span>
                  <span className="block text-sm leading-relaxed text-wg-charcoal/70">
                    {presentationNotes[storyType]}
                  </span>
                </span>
              </Link>
            </li>
          ))}
        </ul>
      </nav>
    </div>
  );
}
