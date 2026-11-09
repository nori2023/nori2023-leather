export type ProductStatus = "draft" | "on_sale" | "reserved" | "sold_out" | "suspended";
export const PUBLIC_STATUSES: ProductStatus[] = ["on_sale", "reserved", "sold_out"];
export const STATUS_LABEL: Record<ProductStatus, string> = {
  draft: "非公開",
  on_sale: "販売中",
  reserved: "購入手続き中",
  sold_out: "SOLD OUT",
  suspended: "販売停止",
};

export type Category = { id: string; slug: string; name: string; sort_order: number };
export type ShippingProfile = { id: string; name: string; fee: number | null; note: string | null; is_active: number };

export type ProductImage = {
  id: string;
  key_large: string;
  key_small: string;
  alt: string;
  width: number | null;
  height: number | null;
  sort_order: number;
  is_main: number;
};

export type Product = {
  id: string;
  code: string;
  slug: string;
  name: string;
  category_id: string | null;
  category_name: string | null;
  category_slug: string | null;
  description: string;
  material: string | null;
  size: string | null;
  color: string | null;
  price: number;
  shipping_profile_id: string | null;
  shipping_name: string | null;
  shipping_fee: number | null;
  status: ProductStatus;
  sold_at: string | null;
  /** SOLD OUT 後のコメント（作り手からのひとこと） */
  sold_note: string | null;
  published_at: string | null;
  updated_at: string;
  images: ProductImage[];
};

export type InstagramPost = {
  id: string;
  permalink: string;
  caption: string | null;
  media_type: string;
  posted_at: string;
  image_key: string | null;
};

/** 販売中にできる写真の枚数ルール：1枚以上（枚数の偶数・奇数は問わない） */
export const photoCountOk = (n: number) => n >= 1;
export const PHOTO_RULE_TEXT = "販売中にするには、写真が1枚以上必要です";
