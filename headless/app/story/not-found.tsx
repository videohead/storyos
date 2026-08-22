import Link from "next/link";

export default function StoryNotFound() {
  return (
    <div className="rounded-wg border-2 border-wg-espresso bg-wg-ivory p-8 shadow-wg">
      <p className="font-headline text-xs font-bold uppercase tracking-[0.2em] text-wg-sepia">
        Story Graph
      </p>
      <h1 className="mt-2 text-4xl text-wg-espresso">Story item not found</h1>
      <p className="mt-3 text-wg-charcoal/75">
        This item may be unpublished, renamed, or outside the supported public collections.
      </p>
      <Link href="/story" className="mt-6 inline-block font-semibold text-wg-blueprint">
        Browse all story collections
      </Link>
    </div>
  );
}
