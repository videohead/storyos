import Link from "next/link";
import type { Post } from "@/lib/wordpress.d";

export function PostCard({ post }: { post: Post }) {
  return (
    <li className="rounded-wg border-2 border-wg-espresso bg-wg-ivory p-5 shadow-wg transition-transform hover:-translate-y-0.5">
      <Link
        href={`/posts/${post.slug}`}
        className="font-headline text-xl font-semibold text-wg-espresso no-underline"
      >
        <span dangerouslySetInnerHTML={{ __html: post.title.rendered }} />
      </Link>
      <div
        className="mt-2 text-sm leading-relaxed text-wg-charcoal/80"
        dangerouslySetInnerHTML={{ __html: post.excerpt.rendered }}
      />
    </li>
  );
}
