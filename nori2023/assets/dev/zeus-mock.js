// 開発用：ゼウスのスクリプトのまね（手元の環境だけで使う）
(function () {
  document.addEventListener("DOMContentLoaded", function () {
    var area = document.getElementById("zeus_token_card_info_area");
    if (area) area.innerHTML = '<label>カード番号<input id="mock_card" value="4100000000000100"></label><label>カード名義<input id="mock_name" value="TARO NORI"></label>'
      + '<input type="hidden" name="zeus_token_value"><input type="hidden" name="zeus_token_return_card_name">';
  });
  window.zeusToken = {
    getToken: function (cb) {
      var n = document.getElementById("mock_card").value;
      document.querySelector("[name=zeus_token_value]").value = (n.indexOf("0000") === 0 ? "FAIL" : "TOK") + "MOCKTOKENVALUE1234567890";
      document.querySelector("[name=zeus_token_return_card_name]").value = document.getElementById("mock_name").value;
      setTimeout(function () { cb({ result: n.length >= 14 }); }, 50);
    },
    getErrorMessage: function () { return "カード番号を正しく入力してください"; }
  };
  window.setPareqParams = function (md, paReq, termUrl) {
    fetch(termUrl, { method: "POST", headers: { "Content-Type": "application/json" }, body: JSON.stringify({ MD: md, PaRes: "Y", status: "success" }) })
      .then(function (r) { return r.text(); }).then(function (t) { window._onPaResSuccess(t); });
  };
})();
