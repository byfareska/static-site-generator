/*
 * Forms of a static site sent to the endpoints of the application (#[AsSiteEndpoint]).
 * Added to the build by the generator when a page calls site_endpoint_script().
 *
 *   <form method="post" action="{{ site_endpoint('contact') }}" data-site-endpoint="contact">
 *     <input name="email"> <span data-error-for="email"></span>
 *     <textarea name="message"></textarea> <span data-error-for="message"></span>
 *     <p data-site-message hidden></p>
 *     <button>Wyślij</button>
 *   </form>
 *   {{ site_endpoint_script() }}  (prints the <script> tag with data-token-url)
 *
 * Without the script the form is sent as usual and the page comes back with ?wyslano=contact or ?blad=contact.
 * Events on the form: "site-endpoint:done" and "site-endpoint:failed" (detail: the answer).
 */
(function () {
  "use strict";
  var script = document.currentScript;
  var tokenUrl = script && script.getAttribute("data-token-url");
  var HONEYPOT = "_hp";
  var TOKEN = "_token";

  function ensureHoneypot(form) {
    if (form.querySelector('[name="' + HONEYPOT + '"]')) return;
    var trap = document.createElement("input");
    trap.type = "text";
    trap.name = HONEYPOT;
    trap.tabIndex = -1;
    trap.autocomplete = "off";
    trap.setAttribute("aria-hidden", "true");
    trap.style.cssText = "position:absolute;left:-10000px;width:1px;height:1px;opacity:0";
    form.appendChild(trap);
  }

  function fetchToken(form) {
    if (!tokenUrl || form.__siteToken) return;
    form.__siteToken = fetch(tokenUrl, { credentials: "same-origin", headers: { "X-Site-Endpoint": "1" } })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        var field = form.querySelector('[name="' + TOKEN + '"]');
        if (!field) {
          field = document.createElement("input");
          field.type = "hidden";
          field.name = TOKEN;
          form.appendChild(field);
        }
        field.value = data.token || "";
      })
      .catch(function () { form.__siteToken = null; });
  }

  function clearErrors(form) {
    form.querySelectorAll("[data-error-for]").forEach(function (el) { el.textContent = ""; el.hidden = true; });
    form.querySelectorAll("[aria-invalid]").forEach(function (el) { el.removeAttribute("aria-invalid"); });
  }

  function show(form, answer) {
    var errors = answer.errors || {};
    Object.keys(errors).forEach(function (field) {
      var slot = form.querySelector('[data-error-for="' + field + '"]');
      var input = form.querySelector('[name="' + field + '"]');
      if (slot) { slot.textContent = errors[field]; slot.hidden = false; }
      if (input) input.setAttribute("aria-invalid", "true");
    });
    var message = form.querySelector("[data-site-message]");
    if (message && answer.message) {
      message.textContent = answer.message;
      message.hidden = false;
      message.setAttribute("data-ok", answer.ok ? "1" : "0");
    }
  }

  function submit(form, event) {
    event.preventDefault();
    clearErrors(form);
    var button = form.querySelector('[type="submit"], button:not([type])');
    if (button) button.disabled = true;
    fetchToken(form);
    Promise.resolve(form.__siteToken)
      .then(function () {
        return fetch(form.action, {
          method: (form.method || "post").toUpperCase(),
          body: new FormData(form),
          credentials: "same-origin",
          headers: { "X-Site-Endpoint": "1", Accept: "application/json" }
        });
      })
      .then(function (r) {
        return r.json().catch(function () { return { ok: false, message: "Nie udało się wysłać." }; });
      })
      .catch(function () { return { ok: false, message: "Brak połączenia. Spróbuj ponownie." }; })
      .then(function (answer) {
        if (button) button.disabled = false;
        show(form, answer);
        if (answer.ok) form.reset();
        form.__siteToken = null;
        fetchToken(form);
        form.dispatchEvent(new CustomEvent(answer.ok ? "site-endpoint:done" : "site-endpoint:failed", { detail: answer, bubbles: true }));
      });
  }

  document.querySelectorAll("form[data-site-endpoint]").forEach(function (form) {
    ensureHoneypot(form);
    fetchToken(form);
    form.addEventListener("submit", function (e) { submit(form, e); });
  });
})();
