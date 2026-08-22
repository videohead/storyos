import { Button } from "@/components/ui/button";
import { StoryCard } from "@/components/story/story-card";
import type { StoryCollectionResult, StoryType } from "@/lib/worldgraph";
import { storyResourceConfig } from "@/lib/worldgraph";

function collectionClass(storyType: StoryType): string {
  if (storyType === "worlds" || storyType === "scenes") {
    return "grid gap-6";
  }
  return "grid gap-6 md:grid-cols-2 xl:grid-cols-3";
}

export function StoryCollection({
  storyType,
  collection,
}: {
  storyType: StoryType;
  collection: StoryCollectionResult;
}) {
  const config = storyResourceConfig[storyType];

  return (
    <div className="space-y-8">
      <header className="max-w-3xl space-y-3">
        <p className="font-headline text-xs font-bold uppercase tracking-[0.22em] text-wg-sepia">
          Story Graph collection
        </p>
        <h1 className="text-4xl font-semibold text-wg-espresso">{config.plural}</h1>
        <p className="text-lg leading-relaxed text-wg-charcoal/75">{config.description}</p>
        <p className="text-sm text-wg-charcoal/60">
          {collection.total} published {collection.total === 1 ? config.singular.toLowerCase() : config.plural.toLowerCase()}
        </p>
      </header>

      {collection.items.length ? (
        <ul className={collectionClass(storyType)}>
          {collection.items.map((item) => (
            <li key={item.id} className="min-w-0">
              <StoryCard item={item} />
            </li>
          ))}
        </ul>
      ) : (
        <div className="rounded-wg border-2 border-dashed border-wg-sepia/55 bg-white/35 px-6 py-12 text-center">
          <h2 className="text-2xl text-wg-espresso">No published {config.plural.toLowerCase()}</h2>
          <p className="mt-2 text-wg-charcoal/70">
            Published {config.plural.toLowerCase()} will appear here when WordPress makes them available.
          </p>
        </div>
      )}

      {collection.totalPages > 1 && (
        <nav aria-label={`${config.plural} pages`} className="flex items-center justify-between">
          {collection.page > 1 ? (
            <Button href={`/story/${storyType}?page=${collection.page - 1}`} variant="outline">
              Previous
            </Button>
          ) : (
            <span />
          )}
          <span className="text-sm text-wg-charcoal/65">
            Page {collection.page} of {collection.totalPages}
          </span>
          {collection.page < collection.totalPages ? (
            <Button href={`/story/${storyType}?page=${collection.page + 1}`} variant="outline">
              Next
            </Button>
          ) : (
            <span />
          )}
        </nav>
      )}
    </div>
  );
}

