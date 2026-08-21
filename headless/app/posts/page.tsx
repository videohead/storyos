import { getPostsPaginated } from "@/lib/wordpress";
import { PostCard } from "@/components/posts/post-card";
import { Button } from "@/components/ui/button";

export default async function PostsPage({
  searchParams,
}: {
  searchParams: Promise<{ page?: string }>;
}) {
  const { page } = await searchParams;
  const currentPage = Number(page) || 1;
  const { data: posts, headers } = await getPostsPaginated(currentPage, 9);

  return (
    <div className="space-y-8">
      <h1 className="text-3xl font-semibold text-wg-espresso">Posts</h1>
      <ul className="grid gap-5 sm:grid-cols-2">
        {posts.map((post) => (
          <PostCard key={post.id} post={post} />
        ))}
      </ul>
      <div className="flex justify-between">
        {currentPage > 1 ? (
          <Button href={`/posts?page=${currentPage - 1}`} variant="outline">
            Previous
          </Button>
        ) : (
          <span />
        )}
        {currentPage < headers.totalPages && (
          <Button href={`/posts?page=${currentPage + 1}`} variant="outline">
            Next
          </Button>
        )}
      </div>
    </div>
  );
}
