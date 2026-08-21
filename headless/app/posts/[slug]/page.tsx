import { notFound } from "next/navigation";
import { getAllPostSlugs, getPostBySlug } from "@/lib/wordpress";

export async function generateStaticParams() {
  const slugs = await getAllPostSlugs();
  return slugs.map((slug) => ({ slug }));
}

export default async function PostPage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const { slug } = await params;
  const post = await getPostBySlug(slug);

  if (!post) {
    notFound();
  }

  return (
    <article className="prose prose-headings:font-headline prose-headings:text-wg-espresso prose-a:text-wg-blueprint max-w-none rounded-wg border-2 border-wg-espresso bg-wg-ivory p-8 shadow-wg">
      <h1 dangerouslySetInnerHTML={{ __html: post.title.rendered }} />
      <div dangerouslySetInnerHTML={{ __html: post.content.rendered }} />
    </article>
  );
}
