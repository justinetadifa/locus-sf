const API_BASE = window.SFC_APP_CONFIG?.apiBase || "api";

async function request(path, options = {}) {
  const isFormData = options.body instanceof FormData;
  const headers = {
    Accept: "application/json",
    ...(options.headers || {}),
  };
  const method = String(options.method || "GET").toUpperCase();
  if (!["GET", "HEAD", "OPTIONS"].includes(method)) {
    const csrfToken = String(window.SFC_APP_CONFIG?.csrfToken || "");
    if (csrfToken) headers["X-CSRF-Token"] = csrfToken;
  }

  if (!isFormData && options.body !== undefined) {
    headers["Content-Type"] = "application/json";
  }

  const response = await fetch(`${API_BASE}/${path}`, {
    headers,
    credentials: "same-origin",
    ...options,
  });

  const raw = await response.text();
  let payload = {};
  try {
    payload = raw ? JSON.parse(raw) : {};
  } catch {
    payload = {
      error: raw ? raw.slice(0, 240) : `Request failed with status ${response.status}.`,
      raw,
    };
  }

  if (!response.ok) {
    throw new Error(payload.error || `Request failed with status ${response.status}.`);
  }

  return payload;
}

export const api = {
  googleEarthViewUrl(target = {}) {
    const lat = Number(target.lat ?? target.latitude ?? 0);
    const lng = Number(target.lng ?? target.longitude ?? 0);
    if (!Number.isFinite(lat) || !Number.isFinite(lng) || (lat === 0 && lng === 0)) {
      return "";
    }

    const label = String(target.label ?? target.name ?? "").trim();
    const coordinateLabel = `${lat.toFixed(6)},${lng.toFixed(6)}`;
    const query = label ? `${label} ${coordinateLabel}` : coordinateLabel;
    return `https://earth.google.com/web/search/${encodeURIComponent(query)}`;
  },
  googleEarthExportUrl(options = {}) {
    const ids = Array.from(
      new Set(
        [options.propertyId, ...(Array.isArray(options.propertyIds) ? options.propertyIds : [])]
          .map((value) => Number(value))
          .filter((value) => Number.isFinite(value) && value > 0)
      )
    );
    const params = new URLSearchParams();
    if (ids.length === 1) {
      params.set("propertyId", String(ids[0]));
    } else if (ids.length > 1) {
      params.set("ids", ids.join(","));
    }
    params.set("format", String(options.format || "kml").toLowerCase());
    params.set("scope", String(options.scope || "properties"));
    if (options.includeOverlays === false) {
      params.set("includeOverlays", "0");
    }
    const overlayTypes = Array.isArray(options.overlayTypes)
      ? options.overlayTypes.map((value) => String(value || "").trim()).filter(Boolean)
      : [];
    if (overlayTypes.length) {
      params.set("overlayTypes", overlayTypes.join(","));
    }

    const base = String(API_BASE || "api").replace(/\/+$/, "");
    return `${base}/google-earth.php?${params.toString()}`;
  },
  bootstrap() {
    return request("bootstrap.php");
  },
  properties() {
    return request("properties.php");
  },
  property(propertyId) {
    return request(`property.php?id=${propertyId}`);
  },
  clupCompliance(propertyId, investmentType) {
    return request("clup.php", {
      method: "POST",
      body: JSON.stringify({ propertyId, investmentType }),
    });
  },
  propertyCommandCenter(propertyId) {
    return request(`property-command-center.php?id=${propertyId}`);
  },
  createProperty(payload) {
    return request("properties.php", {
      method: "POST",
      body: payload instanceof FormData ? payload : JSON.stringify(payload),
    });
  },
  updateProperty(propertyId, payload) {
    if (payload instanceof FormData) {
      payload.append("_method", "PUT");
      return request(`property.php?id=${propertyId}`, {
        method: "POST",
        body: payload,
      });
    }

    return request(`property.php?id=${propertyId}`, {
      method: "PUT",
      body: JSON.stringify(payload),
    });
  },
  deleteProperty(propertyId) {
    return request(`property.php?id=${propertyId}`, {
      method: "DELETE",
    });
  },
  updateBarangay(propertyId, barangay) {
    return request("barangay.php", {
      method: "POST",
      body: JSON.stringify({ propertyId, barangay }),
    });
  },
  getDueDiligence(propertyId) {
    return request(`due-diligence.php?propertyId=${propertyId}`);
  },
  saveDueDiligence(propertyId, state) {
    return request("due-diligence.php", {
      method: "POST",
      body: JSON.stringify({ propertyId, state }),
    });
  },
  getVotes(propertyId) {
    return request(`votes.php?propertyId=${propertyId}`);
  },
  getVoteTallies(propertyIds = []) {
    const ids = Array.from(
      new Set(
        (Array.isArray(propertyIds) ? propertyIds : [propertyIds])
          .map((value) => Number(value))
          .filter((value) => Number.isFinite(value) && value > 0)
      )
    );
    if (!ids.length) {
      return Promise.resolve({ tallies: {} });
    }
    return request(`votes.php?ids=${ids.join(",")}`);
  },
  castVote(propertyId, labelOrOptionId) {
    const payload = Number.isFinite(Number(labelOrOptionId)) && String(labelOrOptionId).trim() !== ""
      ? { propertyId, voteOptionId: Number(labelOrOptionId) }
      : { propertyId, label: labelOrOptionId };
    return request("votes.php", {
      method: "POST",
      body: JSON.stringify(payload),
    });
  },
  voteOptions() {
    return request("vote-options.php");
  },
  showcase(featureType = "") {
    const params = new URLSearchParams();
    if (featureType) {
      params.set("featureType", String(featureType));
    }
    const suffix = params.toString() ? `?${params.toString()}` : "";
    return request(`showcase.php${suffix}`);
  },
  showcaseItem(itemId) {
    return request(`showcase-item.php?id=${itemId}`);
  },
  createShowcaseItem(payload) {
    return request("showcase.php", {
      method: "POST",
      body: payload instanceof FormData ? payload : JSON.stringify(payload),
    });
  },
  updateShowcaseItem(itemId, payload) {
    if (payload instanceof FormData) {
      payload.append("_method", "PUT");
      payload.append("id", String(itemId));
      return request(`showcase-item.php?id=${itemId}`, {
        method: "POST",
        body: payload,
      });
    }

    return request(`showcase-item.php?id=${itemId}`, {
      method: "PUT",
      body: JSON.stringify(payload),
    });
  },
  deleteShowcaseItem(itemId) {
    return request(`showcase-item.php?id=${itemId}`, {
      method: "DELETE",
    });
  },
  createVoteOption(payload) {
    return request("vote-options.php", {
      method: "POST",
      body: payload instanceof FormData ? payload : JSON.stringify(payload),
    });
  },
  updateVoteOption(voteOptionId, payload) {
    if (payload instanceof FormData) {
      payload.append("_method", "PUT");
      payload.append("id", String(voteOptionId));
      return request(`vote-options.php?id=${voteOptionId}`, {
        method: "POST",
        body: payload,
      });
    }

    return request(`vote-options.php?id=${voteOptionId}`, {
      method: "PUT",
      body: JSON.stringify(payload),
    });
  },
  deleteVoteOption(voteOptionId) {
    return request(`vote-options.php?id=${voteOptionId}`, {
      method: "DELETE",
    });
  },
  getMessages(propertyId) {
    return request(`messages.php?propertyId=${propertyId}`);
  },
  getDocumentRequests(propertyId) {
    return request(`document-requests.php?propertyId=${propertyId}`);
  },
  getDocumentRequestInbox() {
    return request("document-requests.php?scope=inbox");
  },
  getVisitLogByProperty(propertyId) {
    return request(`visit-logs.php?propertyId=${propertyId}`);
  },
  getVisitLogByThread(threadId) {
    return request(`visit-logs.php?threadId=${threadId}`);
  },
  createVisitProposal(payload) {
    return request("visit-logs.php", {
      method: "POST",
      body: JSON.stringify(payload),
    });
  },
  updateVisit(payload) {
    return request("visit-logs.php", {
      method: "PATCH",
      body: JSON.stringify(payload),
    });
  },
  createDocumentRequest(payload) {
    return request("document-requests.php", {
      method: "POST",
      body: JSON.stringify(payload),
    });
  },
  updateDocumentRequest(payload) {
    return request("document-requests.php", {
      method: "PATCH",
      body: JSON.stringify(payload),
    });
  },
  getMessageInbox() {
    return request("messages.php?scope=inbox");
  },
  auditLogs(options = {}) {
    const params = new URLSearchParams();
    params.set("limit", String(Math.max(1, Math.min(120, Number(options.limit || 60)))));
    if (options.scope) {
      params.set("scope", String(options.scope));
    }
    if (Number(options.afterId || 0) > 0) {
      params.set("afterId", String(Number(options.afterId)));
    }
    return request(`audit-logs.php?${params.toString()}`);
  },
  notifications(limit = 40) {
    return request(`notifications.php?limit=${Math.max(1, Number(limit || 40))}`);
  },
  sellerProfile() {
    return request("seller-profiles.php");
  },
  sellerReviewQueue(status = "") {
    const params = new URLSearchParams();
    params.set("scope", "queue");
    if (status) {
      params.set("status", String(status));
    }
    return request(`seller-profiles.php?${params.toString()}`);
  },
  saveSellerProfile(payload) {
    return request("seller-profiles.php", {
      method: "POST",
      body: JSON.stringify(payload),
    });
  },
  reviewSellerProfile(payload) {
    return request("seller-profiles.php", {
      method: "PATCH",
      body: JSON.stringify(payload),
    });
  },
  markNotificationRead(notificationId) {
    return request("notifications.php", {
      method: "PATCH",
      body: JSON.stringify({
        action: "markRead",
        notificationId,
      }),
    });
  },
  markAllNotificationsRead() {
    return request("notifications.php", {
      method: "PATCH",
      body: JSON.stringify({
        action: "markAllRead",
      }),
    });
  },
  updateNotificationCadence(notificationCadence) {
    return request("notifications.php", {
      method: "PATCH",
      body: JSON.stringify({
        action: "updateCadence",
        notificationCadence,
      }),
    });
  },
  getThread(threadId) {
    return request(`messages.php?threadId=${threadId}`);
  },
  sendMessage(payloadOrPropertyId, senderName, role, text) {
    if (typeof payloadOrPropertyId === "object" && payloadOrPropertyId !== null) {
      return request("messages.php", {
        method: "POST",
        body: JSON.stringify(payloadOrPropertyId),
      });
    }

    return request("messages.php", {
      method: "POST",
      body: JSON.stringify({ propertyId: payloadOrPropertyId, senderName, role, text }),
    });
  },
  clearMessages(threadId) {
    return request("messages.php", {
      method: "DELETE",
      body: JSON.stringify({ threadId }),
    });
  },
  shortlist() {
    return request("cart.php");
  },
  addToShortlist(propertyId) {
    return request("cart.php", {
      method: "POST",
      body: JSON.stringify({ propertyId }),
    });
  },
  removeFromShortlist(propertyId) {
    return request("cart.php", {
      method: "DELETE",
      body: JSON.stringify({ propertyId }),
    });
  },
  getScenarios(propertyId) {
    return request(`scenarios.php?propertyId=${propertyId}`);
  },
  saveScenario(payload) {
    return request("scenarios.php", {
      method: "POST",
      body: JSON.stringify(payload),
    });
  },
  locationSearch(query) {
    return request(`location-search.php?q=${encodeURIComponent(query)}`);
  },
  marketSnapshot() {
    return request("external-market.php");
  },
  newsDigest(limit = 4) {
    return request(`external-news.php?limit=${limit}`);
  },
  weatherByProperty(propertyId) {
    return request(`external-weather.php?propertyId=${propertyId}`);
  },
  aiSummary(propertyId) {
    return request(`external-ai-summary.php?propertyId=${propertyId}`);
  },
  health() {
    return request("health.php");
  },
};
