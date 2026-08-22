"use client";

import { useEffect } from "react";

export default function StoryError({
  error,
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  useEffect(() => {
    console.error(error);
  }, [error]);

  return (
    <div
      role="alert"
      className="rounded-wg border-2 border-wg-espresso bg-wg-ivory p-8 shadow-wg"
    >
      <h1 className="text-3xl text-wg-espresso">The story could not be loaded</h1>
      <p className="mt-3 max-w-2xl text-wg-charcoal/75">
        The public WordPress story feed did not respond as expected. You can try the request
        again without losing your place.
      </p>
      <button
        type="button"
        onClick={reset}
        className="mt-6 rounded-wg border-2 border-wg-espresso bg-wg-espresso px-5 py-3 font-headline text-sm font-bold uppercase tracking-wider text-wg-ivory shadow-wg-button hover:bg-wg-sepia hover:text-wg-ink"
      >
        Try again
      </button>
    </div>
  );
}
