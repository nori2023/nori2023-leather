export type Env = {
  DB: D1Database;
  /** 任意：R2 を有効化した場合のみ（未設定なら D1 に保存） */
  MEDIA?: R2Bucket;
  PRIVATE?: R2Bucket;
  ASSETS: Fetcher;
  SITE_URL: string;
  ACCESS_TEAM_DOMAIN: string;
  ACCESS_AUD: string;
  ADMIN_EMAILS: string;
  DEV_ADMIN_BYPASS?: string;
};

export type AppVars = { adminEmail: string };
export type AppEnv = { Bindings: Env; Variables: AppVars };
