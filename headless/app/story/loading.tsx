export default function StoryLoading() {
  return (
    <div role="status" aria-live="polite" className="space-y-7">
      <span className="sr-only">Loading story content</span>
      <div className="h-4 w-40 animate-pulse rounded bg-wg-sepia/25" />
      <div className="h-14 max-w-2xl animate-pulse rounded bg-wg-espresso/15" />
      <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3" aria-hidden="true">
        {[0, 1, 2].map((item) => (
          <div
            key={item}
            className="h-80 animate-pulse rounded-wg border-2 border-wg-sepia/25 bg-white/35"
          />
        ))}
      </div>
    </div>
  );
}
