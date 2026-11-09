const yen = new Intl.NumberFormat("ja-JP");
export const formatPrice = (v: number) => `¥${yen.format(v)}`;

export function formatDate(iso: string | null | undefined): string {
  if (!iso) return "";
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return "";
  const jst = new Date(d.getTime() + 9 * 3600 * 1000);
  return `${jst.getUTCFullYear()}.${String(jst.getUTCMonth() + 1).padStart(2, "0")}.${String(jst.getUTCDate()).padStart(2, "0")}`;
}

export function formatDateTime(iso: string | null | undefined): string {
  if (!iso) return "";
  const d = new Date(new Date(iso).getTime() + 9 * 3600 * 1000);
  return `${formatDate(iso)} ${String(d.getUTCHours()).padStart(2, "0")}:${String(d.getUTCMinutes()).padStart(2, "0")}`;
}

export const nowIso = () => new Date().toISOString();
export const newId = () => crypto.randomUUID();

/** 画像URL（R2 MEDIA を /media/ 経由で配信） */
export const mediaUrl = (key: string) => `/media/${key.split("/").map(encodeURIComponent).join("/")}`;
