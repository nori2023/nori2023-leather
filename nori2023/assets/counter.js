/* トップページの訪問者カウンター（数取器）
 * ページを開いたら /visit に知らせて数えてもらい、カウンターが画面に入ったときに「ガチャッ」と1つ進める。
 * 24時間以内の再訪問では動かさず、今の数字だけ出す。 */
(function () {
  "use strict";
  function start() {
    var box = document.querySelector("[data-counter]");
    if (!box) return;
    var win = box.querySelector("[data-counter-window]");
    var tally = box.querySelector(".tally");
    var foot = box.querySelector("[data-counter-num]");
    var DIGITS = parseInt(box.getAttribute("data-digits"), 10) || 6;
    var calm = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    var wheels = [];
    var H = 40;

    function pad(v, n) { var t = String(v); while (t.length < n) t = "0" + t; return t; }
    function build(n) {
      win.textContent = "";
      wheels = [];
      for (var i = 0; i < n; i++) {
        var w = document.createElement("div");
        w.className = "wheel";
        var s = document.createElement("div");
        s.className = "wheel__strip";
        for (var d = 0; d <= 10; d++) { var sp = document.createElement("span"); sp.textContent = d % 10; s.appendChild(sp); }
        w.appendChild(s);
        win.appendChild(w);
        wheels.push({ el: w, strip: s, digit: 0 });
      }
    }
    function place(wheel, idx, animate, delay) {
      wheel.el.classList.toggle("is-rolling", !!animate);
      wheel.strip.style.transitionDelay = animate ? (delay || 0) + "ms" : "0ms";
      wheel.strip.style.transform = "translateY(" + (-H * idx) + "px)";
    }
    function show(v, animate) {
      var text = pad(v, DIGITS);
      if (text.length !== wheels.length) build(text.length);
      foot.textContent = text;
      var changed = 0;
      for (var i = wheels.length - 1; i >= 0; i--) {
        var w = wheels[i], nd = +text[i];
        if (nd === w.digit) continue;
        var delay = changed * 60;
        if (animate && !calm && nd === 0 && w.digit === 9) {
          place(w, 10, true, delay);
          (function (ww, dl) { setTimeout(function () { place(ww, 0, false); }, 480 + dl); })(w, delay);
        } else {
          place(w, nd, animate && !calm, delay);
        }
        w.digit = nd;
        changed++;
      }
    }
    function clack() {
      if (calm) return;
      tally.classList.add("is-pressed");
      setTimeout(function () { tally.classList.remove("is-pressed"); tally.classList.add("is-clack"); }, 90);
      setTimeout(function () { tally.classList.remove("is-clack"); }, 420);
    }
    function whenVisible(fn) {
      if (!("IntersectionObserver" in window)) { setTimeout(fn, 700); return; }
      var io = new IntersectionObserver(function (es) {
        for (var i = 0; i < es.length; i++) {
          if (es[i].isIntersecting) { io.disconnect(); setTimeout(fn, 350); return; }
        }
      }, { threshold: 0.6 });
      io.observe(tally);
    }

    build(DIGITS);
    show(parseInt(box.getAttribute("data-n"), 10) || 0, false);

    fetch(box.getAttribute("data-endpoint"), {
      method: "POST",
      credentials: "same-origin",
      headers: { "X-Nori-Visit": "1" }
    }).then(function (r) { return r.ok ? r.json() : null; }).then(function (res) {
      if (!res || !res.ok || typeof res.n !== "number") return;
      if (!res.counted) { show(res.n, false); return; }
      show(Math.max(0, res.n - 1), false);
      whenVisible(function () { clack(); setTimeout(function () { show(res.n, true); }, calm ? 0 : 80); });
    }).catch(function () {});
  }
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", start);
  else start();
})();
