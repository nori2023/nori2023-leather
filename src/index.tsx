import { Hono } from "hono";
import { HTTPException } from "hono/http-exception";
import { secureHeaders } from "hono/secure-headers";
import type { AppEnv, Env } from "./env";
import { syncInstagram } from "./lib/instagram";
import { adminRoutes } from "./routes/admin";
import { notFoundPage, publicRoutes } from "./routes/public";

const app = new Hono<AppEnv>();

app.use(
  "*",
  secureHeaders({
    xFrameOptions: "SAMEORIGIN",
    referrerPolicy: "strict-origin-when-cross-origin",
    permissionsPolicy: { camera: [], microphone: [], geolocation: [] },
    crossOriginEmbedderPolicy: false,
    crossOriginResourcePolicy: false,
  }),
);

app.route("/admin", adminRoutes);
app.route("/", publicRoutes);

app.notFound((c) => c.html(notFoundPage(c.env.SITE_URL), 404));
app.onError((err, c) => {
  if (err instanceof HTTPException) return err.getResponse();
  console.error(err);
  return c.text("いま表示できんのよ。ちいと時間をおいて、もう一回試してみてつかぁさい。", 500);
});

export default {
  fetch: app.fetch,
  // 6時間ごと：Instagram 最新投稿の取得
  async scheduled(_event: ScheduledController, env: Env, ctx: ExecutionContext) {
    ctx.waitUntil(syncInstagram(env).then((r) => console.log("instagram sync", r.message)));
  },
} satisfies ExportedHandler<Env>;
