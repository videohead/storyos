import type { Metadata } from "next";
import { notFound } from "next/navigation";
import { StoryCollection } from "@/components/story/story-collection";
import {
  getStoryItems,
  isStoryType,
  storyResourceConfig,
} from "@/lib/worldgraph";

type CollectionPageProps = {
  params: Promise<{ type: string }>;
  searchParams: Promise<{ page?: string }>;
};

function pageNumber(value: string | undefined): number {
  const parsed = Number(value);
  return Number.isSafeInteger(parsed) && parsed > 0 ? parsed : 1;
}

export async function generateMetadata({ params }: CollectionPageProps): Promise<Metadata> {
  const { type } = await params;
  if (!isStoryType(type)) {
    return {};
  }

  const config = storyResourceConfig[type];
  return {
    title: `${config.plural} | World Graph Studio`,
    description: config.description,
  };
}

export default async function StoryCollectionPage({
  params,
  searchParams,
}: CollectionPageProps) {
  const [{ type }, { page }] = await Promise.all([params, searchParams]);
  if (!isStoryType(type)) {
    notFound();
  }

  const collection = await getStoryItems(type, pageNumber(page), 12);
  return <StoryCollection storyType={type} collection={collection} />;
}
