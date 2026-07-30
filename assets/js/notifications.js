import { api } from "./api.js";

const FEED_LIMIT = 40;
const POLL_INTERVAL_MS = 60000;
const CADENCE_OPTIONS = [
  { value: "instant", label: "Instant" },
  { value: "daily_digest", label: "Daily Digest" },
  { value: "weekly", label: "Weekly" },
];

function escapeHtml(value) {
  return String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

function iconSvg(name) {
  const icons = {
    bell: `<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4.5a4 4 0 0 0-4 4v2.2c0 1.2-.4 2.4-1.2 3.3L5.5 15.5h13l-1.3-1.5a4.9 4.9 0 0 1-1.2-3.3V8.5a4 4 0 0 0-4-4Z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M10 18a2 2 0 0 0 4 0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>`,
    chat: `<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 7.5a2.5 2.5 0 0 1 2.5-2.5h9A2.5 2.5 0 0 1 19 7.5v6A2.5 2.5 0 0 1 16.5 16H11l-4 3v-3H7.5A2.5 2.5 0 0 1 5 13.5Z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>`,
    trend: `<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 18h16M6 15l4-4 3 3 5-6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>`,
    file: `<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 3.5h6l4 4V20a1 1 0 0 1-1 1H8a2 2 0 0 1-2-2V5.5a2 2 0 0 1 2-2z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M14 3.5V8h4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>`,
    site: `<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20s5-4.6 5-9a5 5 0 1 0-10 0c0 4.4 5 9 5 9Z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><circle cx="12" cy="11" r="1.8" fill="none" stroke="currentColor" stroke-width="1.6"/></svg>`,
    pulse: `<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12h4l2.3-4 3.4 8 2.3-4H21" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>`,
    shield: `<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3.5 5 7v5.5c0 4.2 2.9 6.9 7 8 4.1-1.1 7-3.8 7-8V7z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M9.5 12 11 13.5l3.5-3.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>`,
    success: `<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="m8.8 12.1 2.2 2.2 4.3-4.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>`,
  };

  return icons[name] || icons.bell;
}

function toneLabel(tone) {
  return {
    success: "Success",
    info: "Info",
    trend: "Trend",
    system: "System",
  }[String(tone || "").toLowerCase()] || "System";
}

function categoryLabel(category) {
  return {
    transactional: "Transactional",
    intelligence: "Intelligence",
    operational: "Operational",
  }[String(category || "").toLowerCase()] || "Operational";
}

function cadenceLabel(cadence) {
  return {
    instant: "Instant",
    daily_digest: "Daily Digest",
    weekly: "Weekly",
  }[String(cadence || "").toLowerCase()] || "Instant";
}

function relativeTime(value) {
  if (!value) return "Just now";
  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) return "Just now";

  const diffMs = parsed.getTime() - Date.now();
  const diffSeconds = Math.round(diffMs / 1000);
  const absSeconds = Math.abs(diffSeconds);
  const formatter = new Intl.RelativeTimeFormat(undefined, { numeric: "auto" });

  if (absSeconds < 60) return formatter.format(Math.round(diffSeconds), "second");
  if (absSeconds < 3600) return formatter.format(Math.round(diffSeconds / 60), "minute");
  if (absSeconds < 86400) return formatter.format(Math.round(diffSeconds / 3600), "hour");
  return formatter.format(Math.round(diffSeconds / 86400), "day");
}

function calendarBucket(value) {
  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) return "Earlier";

  const now = new Date();
  const todayStart = new Date(now.getFullYear(), now.getMonth(), now.getDate());
  const yesterdayStart = new Date(todayStart);
  yesterdayStart.setDate(todayStart.getDate() - 1);

  if (parsed >= todayStart) return "Today";
  if (parsed >= yesterdayStart) return "Yesterday";
  return "Earlier";
}

function resolveActionUrl(path) {
  const value = String(path || "").trim();
  if (!value) return "";
  if (/^https?:\/\//i.test(value)) return value;
  const basePath = String(window.SFC_APP_CONFIG?.basePath || "").replace(/\/+$/, "");
  if (value.startsWith("/")) {
    return `${basePath}${value}`;
  }
  return `${basePath}/${value.replace(/^\/+/, "")}`;
}

function unreadSummary(count) {
  if (count <= 0) return "All caught up";
  if (count === 1) return "1 unread signal";
  return `${count} unread signals`;
}

function pulseClass(tone) {
  return {
    success: "success",
    trend: "trend",
    system: "system",
    info: "blue",
  }[String(tone || "").toLowerCase()] || "blue";
}

function emptyStateMarkup() {
  return `
    <div class="notification-empty-state">
      <svg viewBox="0 0 240 180" aria-hidden="true">
        <defs>
          <linearGradient id="line-empty" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="#6366f1" stop-opacity="0.95"></stop>
            <stop offset="100%" stop-color="#22c55e" stop-opacity="0.9"></stop>
          </linearGradient>
        </defs>
        <rect x="28" y="34" width="184" height="110" rx="26" fill="rgba(255,255,255,0.03)" stroke="rgba(255,255,255,0.08)"></rect>
        <path d="M64 94h112" stroke="url(#line-empty)" stroke-width="8" stroke-linecap="round"></path>
        <path d="M82 72h76" stroke="rgba(255,255,255,0.16)" stroke-width="8" stroke-linecap="round"></path>
        <circle cx="120" cy="94" r="38" fill="rgba(99,102,241,0.12)" stroke="rgba(99,102,241,0.3)"></circle>
        <path d="m105 95 10 10 20-22" fill="none" stroke="#f8fafc" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"></path>
      </svg>
      <h3>All caught up. No new investment signals.</h3>
      <p>The drawer stays ready for approvals, market heat updates, replies, and due diligence movement.</p>
    </div>
  `;
}

function notificationCardMarkup(notification) {
  const tone = String(notification.tone || "system").toLowerCase();
  const isRead = Boolean(notification.isRead);
  const href = resolveActionUrl(notification.actionUrl);
  const actionLabel = notification.actionLabel || "View";

  return `
    <article
      class="notif-item ${isRead ? "" : "unread"}"
      data-tone="${escapeHtml(tone)}"
      data-notification-id="${Number(notification.id || 0)}"
      data-notification-read="${isRead ? "true" : "false"}"
      data-notification-url="${escapeHtml(href)}"
      tabindex="0"
      role="button"
      aria-label="${escapeHtml(notification.title || "Notification")}"
    >
      <div class="notif-icon-wrapper">
        ${isRead ? "" : `<span class="icon-pulse ${pulseClass(tone)}"></span>`}
        <div class="icon-bg">${iconSvg(notification.icon)}</div>
      </div>
      <div class="notif-content">
        <p><strong>${escapeHtml(notification.title)}:</strong> ${escapeHtml(notification.body)}</p>
        <div class="notif-meta-row">
          <span class="notif-tag">${escapeHtml(categoryLabel(notification.category))}</span>
          <span class="notif-tag notif-tag-muted">${escapeHtml(toneLabel(tone))}</span>
          <span class="notif-time">${escapeHtml(relativeTime(notification.createdAt))}</span>
        </div>
      </div>
      <div class="notif-actions">
        ${href ? `<a href="${escapeHtml(href)}" class="btn-text" data-notification-action="open">${escapeHtml(actionLabel)}</a>` : `<button type="button" class="btn-text" disabled>Seen</button>`}
      </div>
    </article>
  `;
}

function toastMarkup(notification) {
  const tone = String(notification.tone || "system").toLowerCase();

  return `
    <article class="notification-toast" data-tone="${escapeHtml(tone)}">
      <div class="notification-toast-icon">${iconSvg(notification.icon)}</div>
      <div class="notification-toast-copy">
        <strong>${escapeHtml(notification.title)}</strong>
        <p>${escapeHtml(notification.body)}</p>
      </div>
      <button type="button" class="notification-toast-close" aria-label="Dismiss">Close</button>
    </article>
  `;
}

function createShell() {
  const backdrop = document.createElement("div");
  backdrop.className = "notification-backdrop";
  backdrop.id = "notificationBackdrop";

  const drawer = document.createElement("aside");
  drawer.className = "notification-drawer";
  drawer.id = "notificationDrawer";
  drawer.setAttribute("aria-hidden", "true");
  drawer.innerHTML = `
    <div class="notification-drawer-head">
      <div>
        <div class="notification-kicker">LINE</div>
        <h2>Notification Center</h2>
        <p>Real-time trust signals, seller replies, and market heat in one drawer.</p>
      </div>
      <div class="notification-head-actions">
        <button type="button" class="notification-head-btn" data-notification-refresh>Refresh</button>
        <button type="button" class="notification-head-btn" data-notification-close>Close</button>
      </div>
    </div>
    <div class="notification-toolbar">
      <label class="notification-cadence-field">
        <span>Cadence</span>
        <select class="notification-cadence-select" data-notification-cadence>
          ${CADENCE_OPTIONS.map((option) => `<option value="${escapeHtml(option.value)}">${escapeHtml(option.label)}</option>`).join("")}
        </select>
      </label>
      <button type="button" class="notification-secondary-btn" data-notification-mark-all>Mark all read</button>
    </div>
    <div class="notification-status-row">
      <span data-notification-status>Loading notification feed...</span>
      <strong data-notification-unread>All caught up</strong>
    </div>
    <div class="notification-feed" data-notification-feed></div>
  `;

  const toastStack = document.createElement("div");
  toastStack.className = "notification-toast-stack";
  toastStack.setAttribute("aria-live", "polite");

  document.body.append(backdrop, drawer, toastStack);

  return {
    backdrop,
    drawer,
    toastStack,
    refreshButton: drawer.querySelector("[data-notification-refresh]"),
    closeButton: drawer.querySelector("[data-notification-close]"),
    cadenceSelect: drawer.querySelector("[data-notification-cadence]"),
    markAllButton: drawer.querySelector("[data-notification-mark-all]"),
    status: drawer.querySelector("[data-notification-status]"),
    unread: drawer.querySelector("[data-notification-unread]"),
    feed: drawer.querySelector("[data-notification-feed]"),
  };
}

function renderFeed(state) {
  if (!state.notifications.length) {
    state.shell.feed.innerHTML = emptyStateMarkup();
    return;
  }

  const groups = new Map([
    ["Today", []],
    ["Yesterday", []],
    ["Earlier", []],
  ]);

  state.notifications.forEach((notification) => {
    groups.get(calendarBucket(notification.createdAt))?.push(notification);
  });

  state.shell.feed.innerHTML = Array.from(groups.entries())
    .filter(([, items]) => items.length)
    .map(([label, items]) => `
      <section class="notif-group">
        <div class="notif-group-label">${escapeHtml(label)}</div>
        <div class="notif-group-list">
          ${items.map(notificationCardMarkup).join("")}
        </div>
      </section>
    `)
    .join("");
}

function renderHeader(state) {
  const badge = state.trigger.querySelector("[data-notification-badge]");
  const unreadCount = Number(state.unreadCount || 0);
  if (badge) {
    badge.textContent = unreadCount > 99 ? "99+" : String(unreadCount);
    badge.hidden = unreadCount < 1;
  }
  state.trigger.classList.toggle("has-unread", unreadCount > 0);
  state.trigger.setAttribute("aria-expanded", state.isOpen ? "true" : "false");
}

function renderStatus(state) {
  const cadence = String(state.preferences.notificationCadence || "instant");
  let message = `Cadence: ${cadenceLabel(cadence)}`;
  if (state.errorMessage) {
    message = state.errorMessage;
  } else if (!navigator.onLine) {
    message = "Offline. Use Refresh to retry once the network is back.";
  } else if (cadence === "instant") {
    message = "Live polling every 60 seconds";
  } else {
    message = `${cadenceLabel(cadence)} mode active. Use Refresh when you want the latest feed.`;
  }

  state.shell.status.textContent = message;
  state.shell.unread.textContent = unreadSummary(state.unreadCount);
  state.shell.cadenceSelect.value = cadence;
}

function render(state) {
  renderHeader(state);
  renderStatus(state);
  renderFeed(state);
}

function setOpen(state, nextOpen) {
  state.isOpen = Boolean(nextOpen);
  state.shell.drawer.classList.toggle("is-open", state.isOpen);
  state.shell.backdrop.classList.toggle("is-open", state.isOpen);
  state.shell.drawer.setAttribute("aria-hidden", state.isOpen ? "false" : "true");
  document.body.classList.toggle("notification-drawer-open", state.isOpen);
  renderHeader(state);
}

function syncPolling(state) {
  if (state.pollTimer) {
    window.clearInterval(state.pollTimer);
    state.pollTimer = null;
  }

  if (!navigator.onLine) {
    return;
  }

  if (String(state.preferences.notificationCadence || "instant") !== "instant") {
    return;
  }

  state.pollTimer = window.setInterval(() => {
    fetchFeed(state).catch(() => {});
  }, POLL_INTERVAL_MS);
}

function showToasts(state, notifications) {
  if (!notifications.length || document.visibilityState !== "visible") {
    return;
  }

  notifications.slice(0, 3).reverse().forEach((notification) => {
    const toast = document.createElement("div");
    toast.innerHTML = toastMarkup(notification);
    const node = toast.firstElementChild;
    if (!node) return;

    const dismiss = () => {
      node.classList.add("is-leaving");
      window.setTimeout(() => node.remove(), 180);
    };

    node.querySelector(".notification-toast-close")?.addEventListener("click", dismiss);
    node.addEventListener("click", (event) => {
      if (event.target.closest(".notification-toast-close")) {
        return;
      }
      if (notification.actionUrl) {
        window.location.href = resolveActionUrl(notification.actionUrl);
      }
    });

    state.shell.toastStack.prepend(node);
    window.setTimeout(() => dismiss(), 6200);
  });
}

async function markRead(state, notificationId) {
  if (!notificationId) return;

  const target = state.notifications.find((item) => Number(item.id) === Number(notificationId));
  if (!target || target.isRead) {
    return;
  }

  target.isRead = true;
  state.unreadCount = Math.max(0, Number(state.unreadCount || 0) - 1);
  render(state);

  try {
    const response = await api.markNotificationRead(notificationId);
    state.unreadCount = Number(response.unreadCount || state.unreadCount || 0);
  } catch {
    target.isRead = false;
    state.unreadCount += 1;
  }

  render(state);
}

async function fetchFeed(state, options = {}) {
  state.errorMessage = "";
  if (options.loadingMessage) {
    state.shell.status.textContent = options.loadingMessage;
  }

  const previousIds = new Set(state.notifications.map((item) => Number(item.id || 0)));
  const response = await api.notifications(FEED_LIMIT);

  state.notifications = Array.isArray(response.notifications) ? response.notifications : [];
  state.unreadCount = Number(response.unreadCount || 0);
  state.preferences = response.preferences || state.preferences;

  const nextIds = new Set(state.notifications.map((item) => Number(item.id || 0)));
  const fresh = state.hasLoaded
    ? state.notifications.filter((item) => !previousIds.has(Number(item.id || 0)))
    : [];

  state.hasLoaded = true;
  state.knownIds = nextIds;
  syncPolling(state);
  render(state);

  if (fresh.length && String(state.preferences.notificationCadence || "instant") === "instant") {
    showToasts(state, fresh.filter((item) => !item.isRead));
  }
}

export function initNotificationCenter() {
  const trigger = document.querySelector("[data-notification-trigger]");
  if (!trigger || document.getElementById("notificationDrawer")) {
    return;
  }

  const shell = createShell();
  const state = {
    trigger,
    shell,
    notifications: [],
    unreadCount: 0,
    preferences: {
      notificationCadence: "instant",
    },
    isOpen: false,
    hasLoaded: false,
    knownIds: new Set(),
    pollTimer: null,
    errorMessage: "",
  };

  const refresh = async (loadingMessage = "Refreshing feed...") => {
    try {
      await fetchFeed(state, { loadingMessage });
    } catch (error) {
      state.errorMessage = error?.message || "Unable to refresh notifications right now.";
      render(state);
    }
  };

  trigger.addEventListener("click", () => {
    const willOpen = !state.isOpen;
    setOpen(state, willOpen);
    if (willOpen) {
      refresh("Refreshing feed...");
    }
  });

  shell.closeButton?.addEventListener("click", () => setOpen(state, false));
  shell.backdrop.addEventListener("click", () => setOpen(state, false));
  shell.refreshButton?.addEventListener("click", () => refresh("Refreshing feed..."));

  shell.markAllButton?.addEventListener("click", async () => {
    try {
      const response = await api.markAllNotificationsRead();
      state.unreadCount = Number(response.unreadCount || 0);
      state.notifications = state.notifications.map((notification) => ({
        ...notification,
        isRead: true,
      }));
      render(state);
    } catch (error) {
      state.errorMessage = error?.message || "Unable to update notification state.";
      render(state);
    }
  });

  shell.cadenceSelect?.addEventListener("change", async (event) => {
    const value = String(event.target.value || "instant");
    try {
      const response = await api.updateNotificationCadence(value);
      state.preferences = response.preferences || state.preferences;
      state.unreadCount = Number(response.unreadCount || state.unreadCount || 0);
      state.errorMessage = "";
      syncPolling(state);
      render(state);
    } catch (error) {
      state.errorMessage = error?.message || "Unable to update cadence.";
      render(state);
    }
  });

  shell.feed.addEventListener("click", (event) => {
    const card = event.target.closest("[data-notification-id]");
    if (!card) return;

    const notificationId = Number(card.dataset.notificationId || 0);
    const href = String(card.dataset.notificationUrl || "");
    void markRead(state, notificationId);

    if (event.target.closest("[data-notification-action]")) {
      return;
    }

    if (href) {
      window.location.href = href;
    }
  });

  shell.feed.addEventListener("keydown", (event) => {
    if (event.key !== "Enter" && event.key !== " ") {
      return;
    }

    const card = event.target.closest("[data-notification-id]");
    if (!card) return;

    event.preventDefault();
    card.click();
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      setOpen(state, false);
    }
  });

  window.addEventListener("online", () => {
    state.errorMessage = "";
    syncPolling(state);
    render(state);
    refresh("Refreshing feed...");
  });

  window.addEventListener("offline", () => {
    syncPolling(state);
    render(state);
  });

  render(state);
  refresh("Loading notification feed...");
}
