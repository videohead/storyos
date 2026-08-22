export interface RenderedField {
  rendered: string;
  protected?: boolean;
}

export interface WPEntity {
  id: number;
  date: string;
  date_gmt: string;
  modified: string;
  modified_gmt: string;
  slug: string;
  status: string;
  type: string;
  link: string;
}

export interface Post extends WPEntity {
  title: RenderedField;
  content: RenderedField;
  excerpt: RenderedField;
  author: number;
  featured_media: number;
  categories: number[];
  tags: number[];
}

export interface Page extends WPEntity {
  title: RenderedField;
  content: RenderedField;
  excerpt: RenderedField;
  parent: number;
}

export interface Category {
  id: number;
  count: number;
  name: string;
  slug: string;
  taxonomy: "category";
}

export interface Tag {
  id: number;
  count: number;
  name: string;
  slug: string;
  taxonomy: "post_tag";
}

export interface Author {
  id: number;
  name: string;
  slug: string;
  description: string;
  avatar_urls: Record<string, string>;
}

export interface Media {
  id: number;
  source_url: string;
  alt_text: string;
  media_details?: {
    width: number;
    height: number;
  };
}

export interface PaginatedResponse<T> {
  data: T[];
  headers: {
    total: number;
    totalPages: number;
  };
}
