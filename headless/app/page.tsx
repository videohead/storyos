import { getRecentPosts } from "@/lib/wordpress";
import { PostCard } from "@/components/posts/post-card";
import { Button } from "@/components/ui/button";
import { siteConfig } from "@/site.config";

export default async function HomePage() {
  const posts = await getRecentPosts();

  return (
    <div className="space-y-10">
      <div className="space-y-4">
        <h1 className="text-4xl font-semibold text-wg-espresso">
          {siteConfig.site_name}
        </h1>
        <p className="max-w-xl text-wg-charcoal/80">{siteConfig.site_description}</p>
        <Button href="/posts">Browse all posts</Button>
      </div>
      <ul className="grid gap-5 sm:grid-cols-2">
        {posts.map((post) => (
          <PostCard key={post.id} post={post} />
        ))}
      </ul>
    </div>
  );
}
