// 購入手続き：カード入力（ゼウスのトークン）→ 3-Dセキュア本人認証
// ゼウスのスクリプト（zeus_token_cvv2.js）より先に読み込むこと（IPコードを先に渡すため）
(function () {
  var me = document.currentScript;
  window.zeusTokenIpcode = me ? me.getAttribute("data-ipcode") : "";
  var csrf = me ? me.getAttribute("data-csrf") : "";
  var form, btn, statusEl, wait, label;

  function say(msg) {
    if (statusEl) statusEl.textContent = msg || "";
  }
  function busy(on, text) {
    if (!btn) return;
    btn.disabled = !!on;
    btn.textContent = on ? (text || "処理しています…") : label;
  }
  function parse(data) {
    if (typeof data === "string") {
      try { return JSON.parse(data); } catch (e) { return { ok: false, message: "" }; }
    }
    return data || {};
  }
  function done(res) {
    if (res && res.ok && res.redirect) {
      location.href = res.redirect;
      return;
    }
    if (wait) wait.hidden = true;
    var box = document.getElementById("3dscontainer");
    if (box) box.innerHTML = "";
    busy(false);
    say((res && res.message) || "決済の処理ができませんでした。時間をおいてもう一度お試しください。");
  }

  // ---- ゼウスのスクリプトが呼ぶ関数（名前は仕様で決まっている）
  window._onPaResSuccess = function (data) { done(parse(data)); };
  window._onError = function (error) {
    var m = error && error.message ? String(error.message) : "";
    done({ ok: false, message: "カード会社の本人認証ができませんでした。もう一度お試しください。" + (m ? "（" + m + "）" : "") });
  };
  window.loadedChallenge = function () {
    if (wait) wait.hidden = true;
  };

  function enroll() {
    var tokenEl = form.querySelector("[name=zeus_token_value]");
    var nameEl = form.querySelector("[name=zeus_token_return_card_name]");
    var body = JSON.stringify({ token_key: tokenEl ? tokenEl.value : "", card_name: nameEl ? nameEl.value : "" });
    fetch(form.getAttribute("data-enroll"), {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/json", "X-CSRF-Token": csrf, "Accept": "application/json" },
      body: body
    })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (res.ok && res.next === "3ds") {
          busy(true, "本人認証をしています…");
          if (wait) wait.hidden = false;
          // 3-Dセキュア：ゼウスのスクリプトがカード会社とやりとりし、結果を termUrl に送る
          window.setPareqParams(res.md, "PaReq", res.termUrl, "2", res.iframeUrl);
          return;
        }
        done(res);
      })
      .catch(function () { done({ ok: false, message: "通信に失敗しました。電波の良いところで、もう一度お試しください。" }); });
  }

  function pay() {
    say("");
    if (!window.zeusToken || typeof window.zeusToken.getToken !== "function") {
      say("決済の画面を読み込めませんでした。ページを開き直してください。");
      return;
    }
    busy(true, "カード情報を確認しています…");
    window.zeusToken.getToken(function (r) {
      if (!r || !r.result) {
        busy(false);
        say(window.zeusToken.getErrorMessage ? window.zeusToken.getErrorMessage(r && r.error_code) : "カード情報を確認できませんでした。");
        return;
      }
      busy(true, "決済しています…");
      enroll();
    });
  }

  function countdown() {
    var el = document.querySelector("[data-countdown]");
    if (!el) return;
    var left = parseInt(el.getAttribute("data-countdown"), 10) || 0;
    var end = Date.now() + left * 1000;
    var tick = function () {
      var s = Math.max(0, Math.round((end - Date.now()) / 1000));
      el.textContent = Math.floor(s / 60) + "分" + ("0" + (s % 60)).slice(-2) + "秒";
      if (s <= 0) { say("取り置きの時間が過ぎました。ページを開き直してください。"); if (btn) btn.disabled = true; return; }
      setTimeout(tick, 1000);
    };
    tick();
  }

  document.addEventListener("DOMContentLoaded", function () {
    form = document.querySelector("form[data-checkout]");
    if (!form) return;
    btn = document.getElementById("btn_go_to_confirm_page");
    statusEl = form.querySelector("[data-pay-status]");
    wait = document.getElementById("challenge_wait");
    label = btn ? btn.textContent : "";
    form.addEventListener("submit", function (e) { e.preventDefault(); pay(); });
    if (btn) btn.addEventListener("click", pay);
    countdown();
  });
})();
