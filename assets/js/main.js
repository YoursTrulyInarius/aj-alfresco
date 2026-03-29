document.addEventListener("DOMContentLoaded", () => {
  // 1) Confirm logout (works for any link that ends with logout.php)
  document.querySelectorAll('a[href$="logout.php"]').forEach((a) => {
    a.addEventListener("click", (e) => {
      const ok = confirm("Are you sure you want to logout?");
      if (!ok) e.preventDefault();
    });
  });

  // 2) Toast pop-up reminders (Tenant Dashboard uses window.__TOASTS__)
  const wrap = document.getElementById("toastWrap");
  const items = window.__TOASTS__;

  if (wrap && Array.isArray(items) && items.length > 0) {
    items.slice(0, 3).forEach((n, idx) => {
      setTimeout(() => createToast(wrap, n), idx * 700); // small stagger
    });
  }
});

function createToast(wrap, notif) {
  const toast = document.createElement("div");
  toast.className = "toast";

  const title = document.createElement("div");
  title.className = "toast-title";
  title.textContent = notif.title || "Notification";

  const msg = document.createElement("div");
  msg.className = "toast-msg";
  msg.textContent = notif.message || "";

  const close = document.createElement("button");
  close.className = "toast-close";
  close.type = "button";
  close.textContent = "×";
  close.addEventListener("click", () => toast.remove());

  toast.appendChild(close);
  toast.appendChild(title);
  toast.appendChild(msg);

  wrap.appendChild(toast);

  // auto hide
  setTimeout(() => {
    toast.classList.add("hide");
    setTimeout(() => toast.remove(), 250);
  }, 4500);
}