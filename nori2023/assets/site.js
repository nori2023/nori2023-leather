// 公開サイト用の小さなスクリプト（メニュー・写真ギャラリー）
(function () {
  "use strict";

  // ---- スマホメニュー
  var toggle = document.querySelector("[data-menu-toggle]");
  var nav = document.getElementById("mobile-nav");
  if (toggle && nav) {
    var setOpen = function (open) {
      nav.hidden = !open;
      toggle.setAttribute("aria-expanded", String(open));
      toggle.setAttribute("aria-label", open ? "メニューを閉じる" : "メニューを開く");
      document.body.style.overflow = open ? "hidden" : "";
    };
    toggle.addEventListener("click", function () { setOpen(nav.hidden); });
    nav.addEventListener("click", function (e) { if (e.target.closest("a")) setOpen(false); });
    document.addEventListener("keydown", function (e) { if (e.key === "Escape" && !nav.hidden) setOpen(false); });
  }

  // ---- 写真をタップ・クリックで大きく表示（data-zoom の付いた画像）
  var zoomImgs = document.querySelectorAll("img[data-zoom]");
  if (zoomImgs.length && window.HTMLDialogElement) {
    var dlg = document.createElement("dialog");
    dlg.className = "zoom-dialog";
    dlg.setAttribute("aria-label", "写真の拡大表示");
    dlg.innerHTML = '<button type="button" class="zoom-close" aria-label="閉じる">×</button><img alt=""><p class="zoom-caption"></p>';
    document.body.appendChild(dlg);
    var big = dlg.querySelector("img");
    var cap = dlg.querySelector(".zoom-caption");
    var openZoom = function (img) {
      big.src = img.dataset.zoomSrc || img.currentSrc || img.src;
      big.alt = img.alt || "";
      cap.textContent = img.alt || "";
      dlg.showModal();
    };
    dlg.addEventListener("click", function (e) { if (e.target !== big) dlg.close(); });
    dlg.addEventListener("close", function () { big.removeAttribute("src"); });
    zoomImgs.forEach(function (img) {
      if (img.closest("a, button")) return;
      img.classList.add("cursor-zoom-in");
      img.setAttribute("tabindex", "0");
      img.setAttribute("role", "button");
      img.setAttribute("aria-label", (img.alt ? img.alt + "（" : "（") + "タップで拡大）");
      img.addEventListener("click", function () { openZoom(img); });
      img.addEventListener("keydown", function (e) {
        if (e.key === "Enter" || e.key === " ") { e.preventDefault(); openZoom(img); }
      });
    });
  }

  // ---- 「これまでに作ったもん」の自動スライド（PCの幅のとき）
  document.querySelectorAll("[data-autoslide]").forEach(function (box) {
    var track = box.querySelector("[data-autoslide-track]");
    var controls = box.querySelector("[data-autoslide-controls]");
    var toggle = box.querySelector("[data-autoslide-toggle]");
    if (!track || !controls) return;
    var wide = window.matchMedia("(min-width: 768px)");
    var calm = window.matchMedia("(prefers-reduced-motion: reduce)");
    var timer = null, hovering = false, stopped = calm.matches;
    var step = function () {
      var li = track.querySelector("li");
      var gap = parseFloat(getComputedStyle(track.querySelector("ul")).columnGap) || 16;
      return li ? li.getBoundingClientRect().width + gap : 280;
    };
    var go = function (dir) {
      var max = track.scrollWidth - track.clientWidth;
      if (max <= 4) return;
      var to = track.scrollLeft + dir * step();
      if (dir > 0 && track.scrollLeft >= max - 4) to = 0;          // 最後まで行ったら先頭へ
      if (dir < 0 && track.scrollLeft <= 4) to = max;              // 先頭で戻したら最後へ
      track.scrollTo({ left: to, behavior: calm.matches ? "auto" : "smooth" });
    };
    var run = function () {
      clearInterval(timer);
      timer = null;
      if (wide.matches && !stopped && !hovering && !document.hidden && track.scrollWidth > track.clientWidth + 4) {
        timer = setInterval(function () { go(1); }, 3500);
      }
    };
    var setStopped = function (v) {
      stopped = v;
      toggle.textContent = v ? "▶" : "❚❚";
      toggle.setAttribute("aria-label", v ? "自動スライドを再開する" : "自動スライドを止める");
      run();
    };
    controls.hidden = false;
    setStopped(stopped);
    box.querySelector("[data-autoslide-prev]").addEventListener("click", function () { go(-1); });
    box.querySelector("[data-autoslide-next]").addEventListener("click", function () { go(1); });
    toggle.addEventListener("click", function () { setStopped(!stopped); });
    track.addEventListener("mouseenter", function () { hovering = true; run(); });
    track.addEventListener("mouseleave", function () { hovering = false; run(); });
    track.addEventListener("focusin", function () { hovering = true; run(); });
    track.addEventListener("focusout", function () { hovering = false; run(); });
    document.addEventListener("visibilitychange", run);
    if (wide.addEventListener) wide.addEventListener("change", run);
  });

  // ---- 商品写真ギャラリー
  document.querySelectorAll("[data-gallery]").forEach(function (root) {
    var track = root.querySelector("[data-gallery-track]");
    var thumbs = root.querySelectorAll("[data-gallery-thumb]");
    var counter = root.querySelector("[data-gallery-index]");
    var dialog = root.querySelector("[data-gallery-dialog]");
    var full = root.querySelector("[data-gallery-full]");
    var data = [];
    try { data = JSON.parse(root.querySelector("[data-gallery-data]").textContent || "[]"); } catch (e) {}
    var index = 0;

    var mark = function (i) {
      index = i;
      thumbs.forEach(function (t, n) { t.setAttribute("aria-current", n === i ? "true" : "false"); });
      if (counter) counter.textContent = String(i + 1);
      if (full && data[i]) { full.src = data[i].src; full.alt = data[i].alt; }
    };
    var goTo = function (i) {
      track.scrollTo({ left: track.clientWidth * i, behavior: "smooth" });
      mark(i);
    };
    track.addEventListener("scroll", function () {
      var i = Math.round(track.scrollLeft / track.clientWidth);
      if (i !== index) mark(i);
    }, { passive: true });
    thumbs.forEach(function (t) { t.addEventListener("click", function () { goTo(Number(t.dataset.galleryThumb)); }); });

    if (dialog && dialog.showModal) {
      root.querySelectorAll("[data-gallery-zoom]").forEach(function (b) {
        b.addEventListener("click", function () { mark(Number(b.dataset.galleryZoom)); dialog.showModal(); });
      });
      var close = function () { dialog.close(); };
      root.querySelector("[data-gallery-close]").addEventListener("click", close);
      dialog.addEventListener("click", function (e) { if (e.target === dialog || e.target === full.parentElement) close(); });
      var prev = root.querySelector("[data-gallery-prev]");
      var next = root.querySelector("[data-gallery-next]");
      if (prev) prev.addEventListener("click", function () { goTo((index - 1 + data.length) % data.length); });
      if (next) next.addEventListener("click", function () { goTo((index + 1) % data.length); });
    }
  });
})();

// ---- 写真の動き：画面に入ったら、下から幕が上がるように現れる（トップの最初の写真・拡大表示・商品ページの写真は除く）
(function () {
  "use strict";
  if (!("IntersectionObserver" in window)) return;
  if (window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;
  var root = document.getElementById("main");
  if (!root) return;
  var skip = ".hero, [data-gallery], dialog, [data-no-motion], header, footer";
  var targets = [];
  // 写真ごとに動きを変える：場所で決まるもの以外は、順番に入れ替えていく
  var rotation = ["iris", "diag", "left", "zoom", "down"];
  var turn = 0;
  function pickType(img, box) {
    var fixed = img.closest("[data-motion]");
    if (fixed) return fixed.getAttribute("data-motion");
    if (!box) return img.classList.contains("rounded-full") ? "spin" : "rise";
    if (box.querySelector(".so-stamp")) return "blur";          // SOLD OUT：思い出のようにぼかしから
    if (box.classList.contains("pcard-ph")) return "up";       // 販売中の作品：幕が上がる
    if (img.closest("[data-autoslide]")) return "left";        // 作品の帯：左から右へ開く
    if (img.closest('a[href*="instagram.com"]')) return "pop";  // Instagram：ポンと出る
    return rotation[turn++ % rotation.length];
  }
  root.querySelectorAll("img").forEach(function (img) {
    if (img.closest(skip)) return;
    // 写真を包んでいる「はみ出しを隠す枠」を探す（なければ写真そのもの）
    var box = img.parentElement, n = 0;
    while (box && box !== root && n < 3 && getComputedStyle(box).overflow.indexOf("hidden") === -1) { box = box.parentElement; n++; }
    if (!box || box === root || n >= 3) { box = null; }
    var el = box || img;
    if (el.classList.contains("mo-reveal") || el.classList.contains("mo-fade")) return;
    // すでに画面の上側にある（読み込み直後に見えている）ものは動かさない
    var r = el.getBoundingClientRect();
    if (r.bottom < 0) return;
    el.classList.add(box ? "mo-reveal" : "mo-fade", "mo-t-" + pickType(img, box));
    if (box) img.classList.add("mo-zoom");
    // 同じ並びの中で少しずつずらす
    var sib = el.closest("li, a, figure") || el;
    var list = sib.parentElement ? Array.prototype.indexOf.call(sib.parentElement.children, sib) : 0;
    el.style.setProperty("--mo-d", ((list % 3) * 0.12).toFixed(2) + "s");
    targets.push(el);
  });
  if (!targets.length) return;
  document.documentElement.classList.add("motion");
  // 隠れている写真そのものは見張れないので、外側の要素を見張る
  var watch = new Map();
  var io = new IntersectionObserver(function (es) {
    es.forEach(function (e) {
      if (!e.isIntersecting) return;
      (watch.get(e.target) || []).forEach(function (el) { el.classList.add("is-in"); });
      io.unobserve(e.target);
    });
  }, { threshold: 0.1, rootMargin: "0px 0px -6% 0px" });
  targets.forEach(function (el) {
    var w = el.parentElement || el;
    if (!watch.has(w)) { watch.set(w, []); io.observe(w); }
    watch.get(w).push(el);
  });
})();
