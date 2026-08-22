import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { StoryDetail } from "@/components/story/story-detail";
import {
  getStoryItemBySlug,
  isStoryType,
  storyResourceConfig,
} from "@/lib/worldgraph";

type DetailPageProps = {
  params: Promise<{ type: string; slug: string }>;
};

export async function generateMetadata({ params }: DetailPageProps): Promise<Metadata> {
  const { type, slug } = await params;
  if (!isStoryType(type)) {
    return {};
  }

  const item = await getStoryItemBySlug(type, slug);
  if (!item) {
    return { title: `Not found | ${storyResourceConfig[type].plural}` };
  }

  return {
    title: `${item.titleText} | World Graph Studio`,
    description: item.excerptHtml
      ? item.excerptHtml.replace(/<[^>]*>/g, " ").replace(/\s+/g, " ").trim()
      : storyResourceConfig[type].description,
  };
}

export default async function StoryDetailPage({ params }: DetailPageProps) {
  const { type, slug } = await params;
  if (!isStoryType(type)) {
    notFound();
  }

  const item = await getStoryItemBySlug(type, slug);
  if (!item) {
    notFound();
  }

  return <StoryDetail item={item} />;
}
