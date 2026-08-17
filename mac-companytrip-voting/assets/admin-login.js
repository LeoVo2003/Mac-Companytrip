(() => {
  const root = document.getElementById("ma-admin-login");
  if (!root) return;
  const form = root.querySelector("#ma-login-form");
  const error = root.querySelector("#ma-login-error");
  const button = form.querySelector("button[type='submit']");
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
          username: form.username.value.trim(),
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
