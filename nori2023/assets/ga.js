// Google アナリティクス（GA4）の設定。測定IDは script タグの data-ga-id から受け取る
(function () {
  var s = document.currentScript;
  var id = s && s.getAttribute("data-ga-id");
  if (!id) return;
  window.dataLayer = window.dataLayer || [];
  window.gtag = function () { window.dataLayer.push(arguments); };
  window.gtag("js", new Date());
  window.gtag("config", id);
})();
