// 管理画面用スクリプト：写真のアップロード（ブラウザで縮小）・並び替え・メイン設定・削除
(function () {
  "use strict";

  // 設置フォルダ（例：/nori2023）と、改ざん防止用のトークン
  var BASE = (document.querySelector('meta[name="base-path"]') || {}).content || "";
  var CSRF = (document.querySelector('meta[name="csrf-token"]') || {}).content || "";

  // スマホの「連絡先を自動入力」などを出さない（ログイン・パスワード欄は除く）
  document.querySelectorAll("form:not([data-autofill]) input, form:not([data-autofill]) textarea").forEach(function (el) {
    var t = (el.getAttribute("type") || "text").toLowerCase();
    if (t === "password" || t === "email" || t === "hidden" || t === "file" || t === "checkbox" || t === "radio") return;
    el.setAttribute("autocomplete", "off");
    if (t === "url" || t === "number" || /code|sku|slug|url|_id$/.test(el.name)) {
      el.setAttribute("autocorrect", "off");
      el.setAttribute("autocapitalize", "off");
      el.setAttribute("spellcheck", "false");
    }
  });

  // 削除などの確認
  document.querySelectorAll("form[data-confirm]").forEach(function (f) {
    f.addEventListener("submit", function (e) { if (!window.confirm(f.dataset.confirm)) e.preventDefault(); });
  });

  // 画像を指定サイズに縮小して JPEG 化（スマホ写真の向きも補正。位置情報などは含まれない）
  function resize(file, maxSide, quality) {
    var load = window.createImageBitmap
      ? createImageBitmap(file, { imageOrientation: "from-image" })
      : new Promise(function (res, rej) {
          var img = new Image();
          img.onload = function () { res(img); };
          img.onerror = rej;
          img.src = URL.createObjectURL(file);
        });
    return load.then(function (src) {
      var w = src.width, h = src.height;
      var scale = Math.min(1, maxSide / Math.max(w, h));
      var cw = Math.round(w * scale), ch = Math.round(h * scale);
      var canvas = document.createElement("canvas");
      canvas.width = cw; canvas.height = ch;
      var ctx = canvas.getContext("2d");
      ctx.drawImage(src, 0, 0, cw, ch);
      return new Promise(function (res, rej) {
        canvas.toBlob(function (b) { b ? res({ blob: b, width: cw, height: ch }) : rej(new Error("画像の変換に失敗しました")); }, "image/jpeg", quality);
      });
    });
  }

  function post(url, body) {
    var opts = { method: "POST", credentials: "same-origin", headers: { "X-CSRF-Token": CSRF, "Accept": "application/json" } };
    if (body instanceof FormData) opts.body = body;
    else if (body) { opts.body = JSON.stringify(body); opts.headers["Content-Type"] = "application/json"; }
    return fetch(BASE + url, opts).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (j) {
        if (!r.ok) throw new Error(j.error || "エラーが発生しました（" + r.status + "）");
        return j;
      });
    });
  }

  // ---- 商品写真
  var mgr = document.querySelector("[data-image-manager]");
  if (mgr) {
    var pid = mgr.dataset.productId;
    var input = mgr.querySelector("[data-image-input]");
    var drop = mgr.querySelector("[data-dropzone]");
    var status = mgr.querySelector("[data-upload-status]");

    var upload = function (files) {
      files = Array.prototype.filter.call(files, function (f) { return /^image\//.test(f.type) || /\.(heic|heif)$/i.test(f.name); });
      if (!files.length) return;
      var done = 0;
      var chain = Promise.resolve();
      files.forEach(function (file) {
        chain = chain.then(function () {
          status.textContent = "アップロード中… " + (done + 1) + " / " + files.length;
          return Promise.all([resize(file, 1600, 0.85), resize(file, 640, 0.8)]).then(function (r) {
            var fd = new FormData();
            fd.append("large", r[0].blob, "large.jpg");
            fd.append("small", r[1].blob, "small.jpg");
            fd.append("width", String(r[0].width));
            fd.append("height", String(r[0].height));
            return post("/admin/products/" + pid + "/images", fd);
          }).then(function () { done++; });
        });
      });
      chain.then(function () {
        status.textContent = done + "枚を追加しました。";
        location.reload();
      }).catch(function (e) {
        status.textContent = "失敗しました：" + e.message + "（iPhoneのHEIC形式で失敗する場合は、写真アプリで「互換性優先」に設定してください）";
      });
    };

    input.addEventListener("change", function () { upload(input.files); input.value = ""; });
    ["dragenter", "dragover"].forEach(function (t) { drop.addEventListener(t, function (e) { e.preventDefault(); drop.classList.add("border-camel"); }); });
    ["dragleave", "drop"].forEach(function (t) { drop.addEventListener(t, function () { drop.classList.remove("border-camel"); }); });
    drop.addEventListener("drop", function (e) { e.preventDefault(); upload(e.dataTransfer.files); });

    var list = mgr.querySelector("[data-image-list]");
    if (list) {
      var ids = function () { return Array.prototype.map.call(list.querySelectorAll("[data-image-id]"), function (li) { return li.dataset.imageId; }); };
      list.addEventListener("click", function (e) {
        var btn = e.target.closest("button");
        if (!btn) return;
        var li = btn.closest("[data-image-id]");
        var id = li.dataset.imageId;
        if (btn.hasAttribute("data-image-move")) {
          var order = ids();
          var i = order.indexOf(id), j = i + Number(btn.dataset.imageMove);
          if (j < 0 || j >= order.length) return;
          order.splice(j, 0, order.splice(i, 1)[0]);
          post("/admin/products/" + pid + "/images/order", { ids: order }).then(function () { location.reload(); }).catch(function (er) { alert(er.message); });
        } else if (btn.hasAttribute("data-image-main")) {
          post("/admin/images/" + id + "/main").then(function () { location.reload(); }).catch(function (er) { alert(er.message); });
        } else if (btn.hasAttribute("data-image-delete")) {
          if (!confirm("この写真を削除しますか？")) return;
          post("/admin/images/" + id + "/delete").then(function () { location.reload(); }).catch(function (er) { alert(er.message); });
        }
      });
      list.addEventListener("change", function (e) {
        var el = e.target;
        if (!el.hasAttribute("data-image-alt")) return;
        var id = el.closest("[data-image-id]").dataset.imageId;
        post("/admin/images/" + id + "/alt", { alt: el.value }).then(function () {
          el.classList.add("border-green-600");
          setTimeout(function () { el.classList.remove("border-green-600"); }, 1200);
        });
      });
    }
  }

  // ---- 写真つきフォーム（ワークショップ・経年変化）：写真をブラウザで縮小してから送信
  document.querySelectorAll("form[data-resize-form]").forEach(function (form) {
    var st = form.querySelector("[data-form-status]");
    var busy = false;
    form.addEventListener("submit", function (e) {
      e.preventDefault();
      if (busy) return;
      busy = true;
      var btn = form.querySelector("button");
      if (btn) btn.disabled = true;
      if (st) st.textContent = "保存しよります…";
      var fd = new FormData(form);
      var jobs = [];
      form.querySelectorAll("input[type=file][data-resize]").forEach(function (inp) {
        fd.delete(inp.name);
        if (inp.multiple) {
          // 複数枚：photos_0, photos_1 … の名前で送る
          Array.prototype.forEach.call(inp.files, function (f, n) {
            jobs.push(resize(f, 1600, 0.85).then(function (r) { fd.append(inp.name + "_" + n, r.blob, inp.name + n + ".jpg"); }));
          });
          return;
        }
        var f = inp.files[0];
        if (!f) return;
        jobs.push(resize(f, 1600, 0.85).then(function (r) { fd.append(inp.name, r.blob, inp.name + ".jpg"); }));
      });
      Promise.all(jobs).then(function () { return post(form.getAttribute("action").slice(BASE.length), fd); })
        .then(function (j) { location.href = j.redirect || location.href; })
        .catch(function (err) {
          busy = false;
          if (btn) btn.disabled = false;
          if (st) { st.textContent = "保存できませんでした：" + err.message; st.className = "text-sm text-red-700"; }
        });
    });
  });

  // ---- トップのメイン画像
  var hero = document.querySelector("[data-hero-input]");
  if (hero) {
    var hs = document.querySelector("[data-hero-status]");
    hero.addEventListener("change", function () {
      var f = hero.files[0];
      if (!f) return;
      hs.textContent = "アップロード中…";
      resize(f, 2000, 0.85).then(function (r) {
        var fd = new FormData();
        fd.append("large", r.blob, "hero.jpg");
        return post("/admin/settings/hero-image", fd);
      }).then(function () { location.href = BASE + "/admin/settings?notice=saved"; })
        .catch(function (e) { hs.textContent = "失敗しました：" + e.message; });
    });
  }
})();
