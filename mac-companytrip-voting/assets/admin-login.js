(() => {
  const root = document.getElementById("ma-admin-login");
  if (!root) return;
  const form = root.querySelector("#ma-login-form");
  const error = root.querySelector("#ma-login-error");
  const button = form.querySelector("button[type='submit']");
  const userInput = form.querySelector("#ma-login-user");
  const domainSelect = form.querySelector("#ma-login-domain");
  const domains = ["macusaone.com", "yesoffice.vn", "macmarketing.vn"];
  userInput.addEventListener("input", () => {
    const value = userInput.value.trim().toLowerCase();
    domains.forEach((domain) => {
      const suffix = "@" + domain;
      if (value.endsWith(suffix)) {
        userInput.value = value.slice(0, -suffix.length);
        domainSelect.value = domain;
      }
    });
  });
  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    error.hidden = true;
    button.disabled = true;
    button.textContent = "Đang đăng nhập…";
    try {
      const response = await fetch(root.dataset.loginUrl, {
        method: "POST",
        credentials: "same-origin",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          username: userInput.value.trim(),
          domain: domainSelect ? domainSelect.value : "macusaone.com",
          password: form.password.value,
          redirect: root.dataset.redirect || "",
        }),
      });
      const result = await response.json();
      if (!response.ok) {
        throw new Error(result.message || result.data?.message || "Đăng nhập thất bại.");
      }
      const payload = result.data || result;
      window.location.href = payload.redirect || "/company-trip-admin/";
    } catch (err) {
      error.textContent = err.message;
      error.hidden = false;
      button.disabled = false;
      button.textContent = "Đăng nhập";
    }
  });
})();
