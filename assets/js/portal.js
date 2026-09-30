import { api } from "./api.js";
import { initNotificationCenter } from "./notifications.js";
import {
  DEFAULT_INVESTMENT_LENS,
  DEFAULT_WEIGHTS,
  INVESTMENT_LENSES,
  calculateWeightedScore,
  calculateInvestmentReadiness,
  calculateInvestmentLensResult,
  calcDueDiligencePct,
  getInvestmentLensConfig,
  loadJSON,
  saveJSON,
} from "./utils.js";

const page = document.body.dataset.page || "landing";
const role = document.body.dataset.role || "guest";
const currentUser = window.SFC_APP_CONFIG?.user || null;

const STORAGE_KEYS = {
  compare: "sfc.portal.compare",
  favorites: "sfc.portal.favorites",
  investmentLens: "sfc.portal.investmentLens",
  decisionPersona: "sfc.portal.decisionPersona",
};

const FALLBACK_DECISION_PERSONAS = [
  {
    key: "balanced",
    label: "Balanced Desk",
    shortLabel: "Balanced",
    description: "General opportunity posture balancing price, readiness, and proof.",
  },
  {
    key: "conservative_income",
    label: "Conservative Income",
    shortLabel: "Income",
    description: "Prefers cleaner proof, stronger readiness, and lower execution risk.",
  },
  {
    key: "growth_focused",
    label: "Growth Focused",
    shortLabel: "Growth",
    description: "Accepts moderate risk when momentum and upside are visible.",
  },
  {
    key: "small_business_operator",
    label: "Owner Operator",
    shortLabel: "Operator",
    description: "Prioritizes operating practicality, utilities, and direct location fit.",
  },
  {
    key: "speculative_early_mover",
    label: "Speculative Early Mover",
    shortLabel: "Speculative",
    description: "Will move earlier when location and upside look exceptional.",
  },
];

const shortlistState = {
  ids: getStoredIdsFromStorage(STORAGE_KEYS.favorites),
  loaded: role !== "investor" || !currentUser?.id,
  loadingPromise: null,
};

const APPROVAL_LABELS = {
  draft: "Draft",
  pending_review: "Pending Review",
  approved: "Approved",
  rejected: "Rejected",
  archived: "Archived",
};

const DOCUMENT_FIELDS = [
  { key: "title_copy", label: "Title Copy" },
  { key: "tax_declaration", label: "Tax Declaration" },
  { key: "survey_plan", label: "Survey Plan" },
  { key: "zoning_clearance", label: "Zoning Clearance" },
  { key: "site_photos", label: "Site Photos" },
  { key: "hazard_report", label: "Hazard / Environmental Report" },
];

const DOCUMENT_STATUS_LABELS = {
  missing: "Missing",
  requested: "Requested",
  submitted: "Submitted",
  reviewed: "Reviewed",
};

const VERIFICATION_LABELS = {
  verified: "Verified",
  partially_verified: "Partially Verified",
  unverified: "Unverified",
  pending: "Pending Review",
  draft: "Draft",
  pending_review: "Pending Review",
  rejected: "Rejected",
  suspended: "Suspended",
  archived: "Archived",
};

const REQUEST_STATUS_LABELS = {
  requested: "Requested",
  in_review: "In Review",
  fulfilled: "Fulfilled",
  declined: "Declined",
};

const VISIT_STATUS_LABELS = {
  proposed: "Proposed",
  counter_offered: "Counter Offered",
  confirmed: "Confirmed",
  in_progress: "In Progress",
  visited: "Visited",
};

function escapeHtml(value) {
  return String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

function titleCase(value) {
  return String(value ?? "")
    .replace(/[_-]+/g, " ")
    .toLowerCase()
    .replace(/\b\w/g, (char) => char.toUpperCase());
}

function voteLabel(value) {
  const raw = String(value || "").trim();
  if (!raw) return "No demand yet";
  if (raw.includes("7/11")) return "7/11";
  return titleCase(raw);
}

function truncate(value, max = 120) {
  const text = String(value ?? "").trim();
  if (text.length <= max) return text;
  return `${text.slice(0, Math.max(0, max - 1)).trimEnd()}...`;
}

function money(value) {
  const numeric = Number(value || 0);
  if (!Number.isFinite(numeric)) return "PHP 0";
  return `PHP ${Math.round(numeric).toLocaleString()}`;
}

function moneyShort(value) {
  const numeric = Number(value || 0);
  if (!Number.isFinite(numeric)) return "PHP 0";
  if (numeric >= 1000000) return `PHP ${(numeric / 1000000).toFixed(1)}M`;
  return money(numeric);
}

function absoluteAssetPath(value) {
  const source = String(value || "").trim();
  if (!source) return "";
  if (/^(?:https?:)?\/\//i.test(source) || source.startsWith("data:") || source.startsWith("/")) {
    return source;
  }

  const basePath = String(window.SFC_APP_CONFIG?.basePath || "").replace(/\/$/, "");
  return `${basePath}/${source.replace(/^\/+/, "")}`;
}

function propertyHref(propertyId) {
  return `${window.SFC_APP_CONFIG?.basePath || ""}/property-details.php?id=${propertyId}`;
}

function votingHref(propertyId) {
  return `${window.SFC_APP_CONFIG?.basePath || ""}/voting-dashboard.php?property=${propertyId}`;
}

function compareHref() {
  return `${window.SFC_APP_CONFIG?.basePath || ""}/compare-decision.php`;
}

function adminPropertyHref(propertyId = "") {
  return propertyId
    ? `${window.SFC_APP_CONFIG?.basePath || ""}/admin-properties.php?edit=${propertyId}`
    : `${window.SFC_APP_CONFIG?.basePath || ""}/admin-properties.php`;
}

function googleEarthPropertyIds(properties = []) {
  return Array.from(
    new Set(
      (Array.isArray(properties) ? properties : [])
        .map((property) => Number(property?.id || 0))
        .filter((id) => Number.isFinite(id) && id > 0)
    )
  );
}

function googleEarthViewHref(property) {
  return api.googleEarthViewUrl({
    lat: property?.lat,
    lng: property?.lng,
    name: property?.name,
  });
}

function googleEarthExportHref(options = {}) {
  return api.googleEarthExportUrl(options);
}

function googleEarthActionsMarkup({
  property = null,
  properties = [],
  scope = "properties",
  note = "",
  showView = false,
} = {}) {
  const ids = googleEarthPropertyIds(properties.length ? properties : (property ? [property] : []));
  const actions = [];
  const viewHref = property ? googleEarthViewHref(property) : "";

  if (showView && viewHref) {
    actions.push(
      `<a href="${escapeHtml(viewHref)}" target="_blank" rel="noreferrer" class="btn-shell btn-shell-secondary">${icon("earth")}View in Google Earth</a>`
    );
  }

  if (ids.length) {
    actions.push(
      `<a href="${escapeHtml(googleEarthExportHref({ propertyIds: ids, format: "kml", scope }))}" class="btn-shell btn-shell-secondary">${icon("download")}Export KML</a>`
    );
    actions.push(
      `<a href="${escapeHtml(googleEarthExportHref({ propertyIds: ids, format: "kmz", scope }))}" class="btn-shell btn-shell-ghost">${icon("download")}Export KMZ</a>`
    );
  }

  if (!actions.length) {
    return "";
  }

  return `
    <div class="earth-action-block">
      <div class="earth-action-row">
        ${actions.join("")}
      </div>
      ${note ? `<div class="earth-action-note">${escapeHtml(note)}</div>` : ""}
    </div>
  `;
}

function corridorLabel(value) {
  return {
    highway: "Highway Corridor",
    downtown: "Downtown Core",
    coastal: "Coastal Belt",
  }[String(value || "").toLowerCase()] || "Strategic Corridor";
}

function typeLabel(value) {
  return {
    logistics: "Logistics",
    hotel: "Resort / Tourism",
    commercial: "Commercial",
    bpo: "Office / BPO",
    manufacturing: "Manufacturing",
  }[String(value || "").toLowerCase()] || titleCase(value || "Property");
}

function statusTone(status) {
  const normalized = String(status || "").toLowerCase();
  if (normalized === "available") return "status-available";
  if (normalized === "reserved") return "status-reserved";
  if (normalized === "negotiating") return "status-negotiating";
  return "status-under-review";
}

function approvalTone(state) {
  const normalized = String(state || "").toLowerCase();
  if (normalized === "approved") return "approval-approved";
  if (normalized === "pending_review") return "approval-pending";
  if (normalized === "draft") return "approval-draft";
  if (normalized === "rejected") return "approval-rejected";
  if (normalized === "archived") return "approval-archived";
  return "approval-draft";
}

function verificationTone(status) {
  const normalized = String(status || "").toLowerCase();
  if (normalized === "verified") return "verification-verified";
  if (normalized === "partially_verified") return "verification-partial";
  if (normalized === "pending") return "verification-pending";
  if (normalized === "draft") return "verification-draft";
  if (normalized === "pending_review") return "verification-pending";
  if (normalized === "rejected") return "verification-rejected";
  if (normalized === "suspended") return "verification-archived";
  if (normalized === "archived") return "verification-archived";
  return "verification-unverified";
}

function requestTone(status) {
  const normalized = String(status || "").toLowerCase();
  if (normalized === "fulfilled") return "request-fulfilled";
  if (normalized === "declined") return "request-declined";
  if (normalized === "in_review") return "request-in-review";
  return "request-requested";
}

function documentTone(status) {
  const normalized = String(status || "").toLowerCase();
  if (normalized === "reviewed") return "document-reviewed";
  if (normalized === "submitted") return "document-submitted";
  if (normalized === "requested") return "document-requested";
  return "document-missing";
}

function scoreTone(score) {
  if (score >= 88) return "tone-elite";
  if (score >= 78) return "tone-strong";
  return "tone-watch";
}

function getStoredIdsFromStorage(key) {
  const stored = loadJSON(key, []);
  if (!Array.isArray(stored)) return [];
  return stored.map(Number).filter((value) => Number.isFinite(value));
}

function getCompareIds() {
  return getStoredIdsFromStorage(STORAGE_KEYS.compare);
}

function saveCompareIds(ids) {
  saveJSON(STORAGE_KEYS.compare, ids);
}

function getFavoriteIds() {
  return shortlistState.ids;
}

function saveFavoriteIds(ids) {
  shortlistState.ids = ids.map(Number).filter((value) => Number.isFinite(value));
  shortlistState.loaded = true;
  if (role !== "investor" || !currentUser?.id) {
    saveJSON(STORAGE_KEYS.favorites, shortlistState.ids);
  }
}

async function ensureFavoriteIdsLoaded() {
  if (shortlistState.loaded) {
    return shortlistState.ids;
  }

  if (!shortlistState.loadingPromise) {
    shortlistState.loadingPromise = api.shortlist()
      .then((response) => {
        saveFavoriteIds(response.propertyIds || []);
        return shortlistState.ids;
      })
      .catch(() => {
        saveFavoriteIds(getStoredIdsFromStorage(STORAGE_KEYS.favorites));
        return shortlistState.ids;
      })
      .finally(() => {
        shortlistState.loadingPromise = null;
      });
  }

  return shortlistState.loadingPromise;
}

function favoriteActionLabel(isSaved) {
  if (role === "investor") {
    return isSaved ? "In Cart" : "Add to Cart";
  }
  return isSaved ? "Saved" : "Save";
}

function getActiveInvestmentLensKey() {
  const stored = loadJSON(STORAGE_KEYS.investmentLens, DEFAULT_INVESTMENT_LENS);
  return getInvestmentLensConfig(typeof stored === "string" ? stored : DEFAULT_INVESTMENT_LENS).key;
}

function saveActiveInvestmentLensKey(lensKey) {
  saveJSON(STORAGE_KEYS.investmentLens, getInvestmentLensConfig(lensKey).key);
}

function normalizeDecisionPersonas(personas = []) {
  if (!Array.isArray(personas) || !personas.length) {
    return FALLBACK_DECISION_PERSONAS;
  }

  return personas.map((persona) => ({
    key: String(persona?.key || ""),
    label: String(persona?.label || persona?.shortLabel || "Persona"),
    shortLabel: String(persona?.shortLabel || persona?.label || "Persona"),
    description: String(persona?.description || ""),
  })).filter((persona) => persona.key);
}

function getStoredDecisionPersonaKey(personas = FALLBACK_DECISION_PERSONAS) {
  const available = normalizeDecisionPersonas(personas);
  const fallbackKey = available[0]?.key || "balanced";
  const stored = loadJSON(STORAGE_KEYS.decisionPersona, fallbackKey);
  return available.some((persona) => persona.key === stored) ? stored : fallbackKey;
}

function saveDecisionPersonaKey(personaKey) {
  saveJSON(STORAGE_KEYS.decisionPersona, String(personaKey || "balanced"));
}

function resolveDecisionVariant(decision, personaKey) {
  if (!decision || typeof decision !== "object") return null;
  const variants = decision.personas || {};
  return variants[personaKey] || decision;
}

async function toggleFavoriteId(propertyId) {
  const numericId = Number(propertyId);
  if (role === "investor" && currentUser?.id) {
    const exists = getFavoriteIds().includes(numericId);
    const response = exists ? await api.removeFromShortlist(numericId) : await api.addToShortlist(numericId);
    saveFavoriteIds(response.propertyIds || []);
    return shortlistState.ids;
  }

  const nextIds = toggleId(getFavoriteIds(), numericId);
  saveFavoriteIds(nextIds);
  return nextIds;
}

function toggleId(list, id, max = null) {
  const numericId = Number(id);
  if (list.includes(numericId)) {
    return list.filter((entry) => entry !== numericId);
  }
  if (max !== null && list.length >= max) {
    return [...list.slice(1), numericId];
  }
  return [...list, numericId];
}

function totalVotes(votes) {
  return Object.values(votes || {}).reduce((sum, value) => sum + Number(value || 0), 0);
}

function sortedVoteEntries(votes) {
  return Object.entries(votes || {}).sort((left, right) => Number(right[1]) - Number(left[1]));
}

function topVoteEntry(votes) {
  return sortedVoteEntries(votes)[0] || ["No demand yet", 0];
}

function aggregateVoteLabels(votesMap) {
  const aggregate = {};
  Object.values(votesMap || {}).forEach((votes) => {
    Object.entries(votes || {}).forEach(([label, count]) => {
      aggregate[label] = (aggregate[label] || 0) + Number(count || 0);
    });
  });
  return sortedVoteEntries(aggregate);
}

function propertyStory(property) {
  const corridor = corridorLabel(property.corridor).toLowerCase();
  return `${typeLabel(property.type)} positioning in ${property.barangay || "San Fernando"} with ${property.area} ha, ${property.roadAccess}% road access, and visibility within the ${corridor}.`;
}

function investmentLensSelectorMarkup(activeLensKey, options = {}) {
  const activeLens = getInvestmentLensConfig(activeLensKey);
  const title = options.title || "Investment Lens";
  const description = options.description || "Switch the purpose and let the rankings recalculate live.";

  return `
    <article class="panel-card investment-intent">
      <div class="investment-intent-head">
        <div>
          <div class="panel-kicker">${escapeHtml(options.kicker || "Investment Lens")}</div>
          <h3>${escapeHtml(title)}</h3>
          <p>${escapeHtml(description)}</p>
        </div>
        <div class="service-chip-row">
          ${serviceChip(`${activeLens.label} active`, "live")}
          ${serviceChip("Offline weighting", "neutral")}
        </div>
      </div>
      <div class="intent-grid" role="tablist" aria-label="Investment lens">
        ${INVESTMENT_LENSES.map((lens) => `
          <button
            type="button"
            class="intent-card ${lens.key === activeLens.key ? "active" : ""}"
            data-investment-lens="${escapeHtml(lens.key)}"
            aria-pressed="${lens.key === activeLens.key ? "true" : "false"}"
          >
            <span class="icon" aria-hidden="true">${escapeHtml(lens.icon || "•")}</span>
            <div class="intent-meta">
              <strong>${escapeHtml(lens.label)}</strong>
              <small>${escapeHtml(lens.subtitle || "Reweight the property score")}</small>
            </div>
          </button>
        `).join("")}
      </div>
    </article>
  `;
}

function investmentLensScorePill(lensResult) {
  if (!lensResult) return scorePill(0, "Lens");
  return `
    <span class="score-pill lens-score-pill ${scoreTone(lensResult.score)}">
      <span class="intent-icon" aria-hidden="true">${escapeHtml(lensResult.icon || "•")}</span>
      ${escapeHtml(lensResult.shortLabel || lensResult.label || "Lens")} ${Math.round(Number(lensResult.score || 0))}
    </span>
  `;
}

function investmentLensThesisMarkup(property, lensResult, options = {}) {
  if (!property || !lensResult) {
    return "";
  }

  const heading = options.heading || `Why ${property.name} fits ${lensResult.label}`;
  const kicker = options.kicker || "Why This Ranks Here";
  const metricLimit = Math.max(3, Number(options.metricLimit || 4));
  const emphasis = (lensResult.emphasizedPillars || []).slice(0, 3);

  return `
    <article class="panel-card thesis-card" data-lens-thesis>
      <div class="thesis-head">
        <div>
          <div class="panel-kicker">${escapeHtml(kicker)}</div>
          <h3>${escapeHtml(heading)}</h3>
        </div>
        <div class="service-chip-row">
          ${serviceChip(`${lensResult.label} lens`, "live")}
          ${serviceChip(`${lensResult.missingMetricCount || 0} missing`, Number(lensResult.missingMetricCount || 0) ? "fallback" : "neutral")}
        </div>
      </div>
      <p class="thesis-copy">${escapeHtml(lensResult.thesis || `${property.name} is being evaluated through the ${lensResult.label} lens.`)}</p>
      <div class="thesis-emphasis-row">
        ${emphasis.map((pillar) => `<span class="thesis-emphasis-pill">${escapeHtml(pillar.label)} ${Math.round(Number(pillar.share || 0))}%</span>`).join("")}
      </div>
      <div class="thesis-metric-list">
        ${(lensResult.metrics || []).slice(0, metricLimit).map((metric) => `
          <article class="thesis-metric-row">
            <div class="thesis-metric-meta">
              <strong>${escapeHtml(metric.label)}</strong>
              <span>${Math.round(Number(metric.score || 0))}% score | ${Math.round(Number(metric.weight || 0) * 100)}% weight</span>
            </div>
            <div class="thesis-metric-bar">
              <span class="thesis-metric-fill ${metric.missing ? "is-missing" : ""}" data-metric-fill="${Math.round(Number(metric.score || 0))}"></span>
            </div>
            <div class="thesis-metric-copy">
              <span>${escapeHtml(metric.displayValue || "Awaiting data")}</span>
              <small>${escapeHtml(metric.summary || "Weighted into the current lens.")}</small>
            </div>
          </article>
        `).join("")}
      </div>
    </article>
  `;
}

function animateLensMetricBars(root) {
  root.querySelectorAll("[data-metric-fill]").forEach((node) => {
    const value = Math.max(0, Math.min(100, Number(node.dataset.metricFill || 0)));
    node.style.width = "0%";
    window.requestAnimationFrame(() => {
      window.requestAnimationFrame(() => {
        node.style.width = `${value}%`;
      });
    });
  });
}

function bindInvestmentLensSelector(root, onSelect) {
  root.querySelectorAll("[data-investment-lens]").forEach((button) => {
    button.addEventListener("click", () => {
      const nextLensKey = String(button.dataset.investmentLens || "");
      if (!nextLensKey) return;
      onSelect?.(nextLensKey);
    });
  });
}

function opportunityScore(property, allProperties, votes = {}, intent = null) {
  const market = Number(property.marketScore || property.score || 0);
  const fit = calculateWeightedScore(property, allProperties, DEFAULT_WEIGHTS, intent);
  const demand = Math.min(100, totalVotes(votes) * 12);
  const corridorLift = {
    highway: 86,
    downtown: 82,
    coastal: 88,
  }[property.corridor] || 80;
  const baseScore = Math.round((market * 0.5) + (fit * 0.3) + (demand * 0.12) + (corridorLift * 0.08));
  const groundTruthMultiplier = Math.max(0.7, Number(property.groundTruthMultiplier || 1));

  return Math.round(baseScore * groundTruthMultiplier);
}

const CLUP_STATUS_RANK = Object.freeze({ PASS: 3, CONDITIONAL: 2, UNVERIFIED: 1, FAIL: 0 });
const CLUP_GATE_BY_STATUS = Object.freeze({
  PASS: "ELIGIBLE",
  CONDITIONAL: "REVIEW_REQUIRED",
  UNVERIFIED: "HOLD",
  FAIL: "BLOCKED",
});
const CLUP_LENS_USE_MAP = Object.freeze({
  commercial_center: "commercial",
  resort: "hotel",
  university: "mixed_use",
  hospital: "mixed_use",
});

function clupUseKey(value) {
  return String(value || "")
    .trim()
    .toLowerCase()
    .replace(/[\s-]+/g, "_");
}

function clupUseForLens(value) {
  const key = clupUseKey(value);
  return CLUP_LENS_USE_MAP[key] || key;
}

function clupStatus(value) {
  const normalized = String(value || "UNVERIFIED").trim().toUpperCase();
  return Object.hasOwn(CLUP_STATUS_RANK, normalized) ? normalized : "UNVERIFIED";
}

function clupGate(compliance) {
  const serverGate = String(compliance?.priorityGate || compliance?.gate || compliance?.decisionGate || "")
    .trim()
    .toUpperCase();
  if (["ELIGIBLE", "REVIEW_REQUIRED", "HOLD", "BLOCKED"].includes(serverGate)) return serverGate;
  return CLUP_GATE_BY_STATUS[clupStatus(compliance?.status)];
}

function clupCanRecommend(compliance) {
  return ["PASS", "CONDITIONAL"].includes(clupStatus(compliance?.status))
    && !["HOLD", "BLOCKED"].includes(clupGate(compliance));
}

function clupRank(compliance) {
  return CLUP_STATUS_RANK[clupStatus(compliance?.status)] ?? CLUP_STATUS_RANK.UNVERIFIED;
}

function clupScoreValue(compliance) {
  const raw = compliance?.suitabilityScore;
  if (raw === null || raw === undefined || raw === "") return null;
  const numeric = Number(raw);
  return Number.isFinite(numeric) ? Math.max(0, Math.min(100, Math.round(numeric))) : null;
}

function clupScoreLabel(compliance, suffix = true) {
  const score = clupScoreValue(compliance);
  return score === null ? "Pending" : `${score}${suffix ? "/100" : ""}`;
}

function unverifiedClup(property, proposedType = null, reason = "No authoritative CLUP evaluation is available for this site and proposed use.") {
  const type = clupUseKey(proposedType || property?.type || "");
  return {
    version: null,
    status: "UNVERIFIED",
    statusKey: "unverified",
    priorityGate: "HOLD",
    suitabilityScore: null,
    candidateSite: String(property?.name || "Candidate site"),
    proposedInvestmentType: type || null,
    proposedInvestmentLabel: type ? typeLabel(type === "mixed_use" ? "Mixed-use" : type) : "Proposed use pending",
    existingLandUse: "Verification pending",
    zoningClassification: "Verification pending",
    allowedUses: [],
    conditionalUses: [],
    restrictedUses: [],
    strategicGrowthCorridor: null,
    strategicGrowthCorridorLabel: "Verification pending",
    explanation: reason,
    recommendedLguAction: "Hold prioritization and request an authoritative zoning and ordinance evaluation for the proposed use.",
    screeningBasis: "Authoritative evaluation unavailable",
    evidenceLevel: "UNVERIFIED",
    sourceReference: null,
    verifiedAt: null,
    isPreliminary: true,
    disclaimer: "No compliance conclusion should be drawn until the CLUP evaluation is available.",
  };
}

function normalizeClupEvaluation(evaluation, property, requestedType = null) {
  if (!evaluation || typeof evaluation !== "object") {
    return unverifiedClup(property, requestedType);
  }
  const requestedKey = clupUseKey(requestedType || evaluation.proposedInvestmentType || property?.type || "");
  const resultKey = clupUseKey(evaluation.proposedInvestmentType || requestedKey);
  if (requestedKey && resultKey && requestedKey !== resultKey) {
    return unverifiedClup(
      property,
      requestedKey,
      `The server returned a ${typeLabel(resultKey)} evaluation instead of the requested ${typeLabel(requestedKey)} use. Prioritization is on hold.`
    );
  }
  const status = clupStatus(evaluation.status);
  const normalized = {
    ...unverifiedClup(property, requestedKey),
    ...evaluation,
    status,
    statusKey: status.toLowerCase(),
    proposedInvestmentType: resultKey || requestedKey || null,
    proposedInvestmentLabel: evaluation.proposedInvestmentLabel || (resultKey ? typeLabel(resultKey === "mixed_use" ? "Mixed-use" : resultKey) : "Proposed use pending"),
    allowedUses: Array.isArray(evaluation.allowedUses) ? evaluation.allowedUses : [],
    conditionalUses: Array.isArray(evaluation.conditionalUses) ? evaluation.conditionalUses : [],
    restrictedUses: Array.isArray(evaluation.restrictedUses) ? evaluation.restrictedUses : [],
    evidenceLevel: evaluation.evidenceLevel || (status === "UNVERIFIED" ? "UNVERIFIED" : "INFERRED"),
  };
  normalized.priorityGate = clupGate(normalized);
  normalized.suitabilityScore = clupScoreValue(normalized);
  return normalized;
}

function clupEvaluationCandidates(property, requestedType) {
  const requestedKey = clupUseKey(requestedType);
  const matrix = property?.clupEvaluations;
  const candidates = [];
  if (Array.isArray(matrix)) {
    candidates.push(...matrix);
  } else if (matrix && typeof matrix === "object") {
    if (matrix[requestedKey]) candidates.push(matrix[requestedKey]);
    if (matrix.evaluations && typeof matrix.evaluations === "object") {
      if (Array.isArray(matrix.evaluations)) candidates.push(...matrix.evaluations);
      else if (matrix.evaluations[requestedKey]) candidates.push(matrix.evaluations[requestedKey]);
    }
    candidates.push(...Object.values(matrix).filter((entry) => entry && typeof entry === "object" && !Array.isArray(entry)));
  }
  if (property?.clupCompliance && typeof property.clupCompliance === "object") {
    candidates.push(property.clupCompliance);
  }
  return candidates;
}

function evaluateClup(property, proposedType = null) {
  const requestedType = clupUseKey(proposedType || property?.type || "");
  const candidates = clupEvaluationCandidates(property, requestedType);
  const exact = candidates.find((evaluation) => clupUseKey(evaluation?.proposedInvestmentType) === requestedType);
  if (exact) return normalizeClupEvaluation(exact, property, requestedType);

  const legacy = candidates.find((evaluation) => !evaluation?.proposedInvestmentType)
    || ((!proposedType || requestedType === clupUseKey(property?.type)) ? candidates[0] : null);
  return legacy
    ? normalizeClupEvaluation(legacy, property, requestedType)
    : unverifiedClup(property, requestedType);
}

function clupStatusPill(compliance, label = true) {
  if (!compliance) return "";
  const status = clupStatus(compliance.status);
  const gate = clupGate(compliance);
  return `<span class="clup-status clup-status-${escapeHtml(status.toLowerCase())}" role="status" aria-label="CLUP ${escapeHtml(status)}; decision gate ${escapeHtml(titleCase(gate))}">${label ? "CLUP " : ""}${escapeHtml(status)}</span>`;
}

function clupDecisionCardMarkup(compliance, options = {}) {
  if (!compliance) return "";
  const compact = Boolean(options.compact);
  const status = clupStatus(compliance.status);
  const statusKey = status.toLowerCase();
  const gate = clupGate(compliance);
  const evidenceLevel = String(compliance.evidenceLevel || (status === "UNVERIFIED" ? "UNVERIFIED" : "INFERRED")).toUpperCase();
  const score = clupScoreValue(compliance);
  const scoreCopy = score === null ? "—" : String(score);
  const sourceMeta = [
    compliance.sourceReference ? `Source: ${compliance.sourceReference}` : null,
    compliance.version ? `Engine: ${compliance.version}` : null,
  ].filter(Boolean);
  const limitation = status === "UNVERIFIED"
    ? (compliance.disclaimer || "Compliance is on hold until an authoritative CLUP evaluation is available.")
    : compliance.isPreliminary
      ? (compliance.disclaimer || "Preliminary screen — confirm against the adopted CLUP, official zoning map, and Zoning Ordinance.")
      : `LGU-verified profile${compliance.verifiedAt ? ` · ${formatDate(compliance.verifiedAt)}` : ""}. Formal locational clearance may still be required.`;
  return `
    <article class="panel-card clup-decision-card clup-${escapeHtml(statusKey)} ${compact ? "is-compact" : ""}" data-clup-gate="${escapeHtml(gate.toLowerCase())}">
      <div class="clup-card-head">
        <div><div class="panel-kicker">CLUP Compliance &amp; Suitability</div><h3>${escapeHtml(compliance.proposedInvestmentLabel || "Proposed use pending")}</h3></div>
        <div class="clup-score-lockup">${clupStatusPill(compliance)}<strong>${escapeHtml(scoreCopy)}</strong><span>${score === null ? "Suitability pending" : "Suitability"}</span></div>
      </div>
      <div class="clup-evidence-row"><span class="clup-evidence clup-evidence-${escapeHtml(evidenceLevel.toLowerCase())}">${escapeHtml(evidenceLevel)} EVIDENCE</span><span class="clup-gate clup-gate-${escapeHtml(gate.toLowerCase())}">${escapeHtml(titleCase(gate))}</span>${sourceMeta.map((item) => `<span>${escapeHtml(item)}</span>`).join("")}</div>
      <p>${escapeHtml(compliance.explanation || "Authoritative CLUP evaluation pending.")}</p>
      ${compact ? "" : `<div class="clup-fact-grid">
        <div><span>Existing Land Use</span><strong>${escapeHtml(compliance.existingLandUse || "Verification pending")}</strong></div>
        <div><span>Zoning Classification</span><strong>${escapeHtml(compliance.zoningClassification || "Verification pending")}</strong></div>
        <div><span>Strategic Corridor</span><strong>${escapeHtml(compliance.strategicGrowthCorridorLabel || "Verification pending")}</strong></div>
        <div><span>Recommended LGU Action</span><strong>${escapeHtml(compliance.recommendedLguAction || "Request authoritative evaluation")}</strong></div>
      </div>
      <div class="clup-use-row"><span><b>Allowed</b> ${escapeHtml((compliance.allowedUses || []).join(", ") || "None listed")}</span><span><b>Conditional</b> ${escapeHtml((compliance.conditionalUses || []).join(", ") || "None listed")}</span><span><b>Restricted</b> ${escapeHtml((compliance.restrictedUses || []).join(", ") || "None listed")}</span></div>`}
      ${compact ? `<div class="clup-compact-action"><strong>LGU action</strong><span>${escapeHtml(compliance.recommendedLguAction || "Request authoritative evaluation")}</span></div>` : ""}
      <small>${escapeHtml(limitation)}</small>
    </article>`;
}

function enrichProperties(properties, allProperties, votesMap, intent = null, lensKey = null, options = {}) {
  const normalizedLensKey = lensKey ? getInvestmentLensConfig(lensKey).key : null;
  const readinessById = options.readinessById || {};
  return properties
    .map((property) => {
      const votes = votesMap[property.id] || {};
      const fitScore = calculateWeightedScore(property, allProperties, DEFAULT_WEIGHTS, intent);
      const score = opportunityScore(property, allProperties, votes, intent);
      const [topNeed] = topVoteEntry(votes);
      const readiness = readinessById[property.id] || property.investmentReadiness || null;
      const rawLensResult = normalizedLensKey
        ? calculateInvestmentLensResult(property, allProperties, normalizedLensKey, { readiness })
        : null;
      const groundTruthMultiplier = Math.max(0.7, Number(property.groundTruthMultiplier || 1));
      const lensResult = rawLensResult
        ? {
          ...rawLensResult,
          score: Math.round(Number(rawLensResult.score || 0) * groundTruthMultiplier),
        }
        : null;
      const proposedClupUse = intent
        ? clupUseKey(intent)
        : (normalizedLensKey ? clupUseForLens(normalizedLensKey) : clupUseKey(property.type));
      const clupCompliance = evaluateClup(property, proposedClupUse);
      return {
        ...property,
        marketScore: Number(property.marketScore || property.score || 0),
        fitScore,
        opportunityScore: score,
        lensScore: lensResult?.score ?? score,
        lensResult,
        clupCompliance,
        voteTotal: totalVotes(votes),
        topNeed,
        pricePerHectare: property.area ? Number(property.price || 0) / Number(property.area || 1) : 0,
      };
    })
    .sort((left, right) => {
      const complianceDelta = clupRank(right.clupCompliance) - clupRank(left.clupCompliance);
      if (complianceDelta) return complianceDelta;
      if (normalizedLensKey) {
        return (right.lensScore - left.lensScore)
          || (right.opportunityScore - left.opportunityScore)
          || (right.marketScore - left.marketScore);
      }
      return right.opportunityScore - left.opportunityScore;
    });
}

async function loadVoteTallies(properties) {
  const propertyIds = (Array.isArray(properties) ? properties : [])
    .map((property) => Number(property?.id || 0))
    .filter((id) => Number.isFinite(id) && id > 0);

  if (!propertyIds.length) {
    return {};
  }

  try {
    const response = await api.getVoteTallies(propertyIds);
    const tallies = response?.tallies || {};
    return Object.fromEntries(
      propertyIds.map((propertyId) => [
        propertyId,
        tallies[propertyId]?.votes || tallies[String(propertyId)]?.votes || {},
      ])
    );
  } catch (bulkError) {
    console.warn("Unable to load bulk vote tallies", bulkError);
  }

  const entries = await Promise.all(
    propertyIds.map(async (propertyId) => {
      try {
        const response = await api.getVotes(propertyId);
        return [propertyId, response.votes || {}];
      } catch (error) {
        console.warn("Unable to load votes", propertyId, error);
        return [propertyId, {}];
      }
    })
  );

  return Object.fromEntries(entries);
}

async function loadInquiryCounts(properties) {
  const entries = await Promise.all(
    properties.map(async (property) => {
      try {
        const response = await api.getMessages(property.id);
        return [property.id, Number(response.summary?.messageCount || (Array.isArray(response.messages) ? response.messages.length : 0))];
      } catch (error) {
        console.warn("Unable to load inquiries", property.id, error);
        return [property.id, 0];
      }
    })
  );

  return Object.fromEntries(entries);
}

function icon(name) {
  const icons = {
    map: `<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3.5 6.5 5-2 7 2.5 5-2V18l-5 2-7-2.5-5 2z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M8.5 4.5v13M15.5 7v13" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>`,
    search: `<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6.2" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="m16 16 3.5 3.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>`,
    earth: `<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M4.8 9.5h14.4M4.8 14.5h14.4M12 4a13.8 13.8 0 0 1 0 16M12 4a13.8 13.8 0 0 0 0 16" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>`,
    vote: `<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4.5" y="6" width="15" height="12" rx="2.5" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="m9 11 2.5 2.5L16 9" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>`,
    compare: `<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 5v14M17 5v14" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><path d="M10 8h4M10 12h6M10 16h3" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>`,
    save: `<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 5.5h12a1 1 0 0 1 1 1V20l-7-3-7 3V6.5a1 1 0 0 1 1-1Z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>`,
    download: `<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4.5v10M8.5 11 12 14.5 15.5 11M5 18.5h14" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>`,
    arrow: `<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>`,
    money: `<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="6" width="17" height="12" rx="2.5" fill="none" stroke="currentColor" stroke-width="1.6"/><circle cx="12" cy="12" r="2.6" fill="none" stroke="currentColor" stroke-width="1.6"/></svg>`,
    area: `<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 5h5M5 5v5M19 5h-5M19 5v5M5 19h5M5 19v-5M19 19h-5M19 19v-5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>`,
    pulse: `<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12h4l2.3-4 3.4 8 2.3-4H21" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>`,
    inbox: `<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16v10H4z" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M4 14h4l2 3h4l2-3h4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>`,
    user: `<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="3" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M5 19c1.6-2.7 4.1-4 7-4s5.4 1.3 7 4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>`,
    ranking: `<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 19V11M12 19V7M17 19V4M4 19h16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>`,
    shield: `<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3.5 5 7v5.5c0 4.2 2.9 6.9 7 8 4.1-1.1 7-3.8 7-8V7z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M9.5 12 11 13.5l3.5-3.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>`,
    clock: `<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M12 8v4l2.8 1.8" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>`,
    file: `<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 3.5h6l4 4V20a1 1 0 0 1-1 1H8a2 2 0 0 1-2-2V5.5a2 2 0 0 1 2-2z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M14 3.5V8h4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>`,
    spark: `<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>`,
    pipeline: `<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 18V9.5h4V18M10 18V6h4v12M15 18v-8.5h4V18" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M4 18h16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>`,
    university: `<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m4 9 8-4 8 4-8 4z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M7 11.5V16c1.3 1 3 1.5 5 1.5s3.7-.5 5-1.5v-4.5M20 10v5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>`,
    hospital: `<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="6" y="4.5" width="12" height="15" rx="2.5" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M12 8v8M8 12h8" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>`,
    logistics: `<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4.5 8.5h11v7h-11zM15.5 10.5h2.7l1.3 2v2.5h-4z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><circle cx="8" cy="18" r="1.7" fill="none" stroke="currentColor" stroke-width="1.6"/><circle cx="17.5" cy="18" r="1.7" fill="none" stroke="currentColor" stroke-width="1.6"/></svg>`,
    commercial: `<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 8.5h14M7 8.5V6.2A1.7 1.7 0 0 1 8.7 4.5h6.6A1.7 1.7 0 0 1 17 6.2v2.3M6 8.5h12v10H6z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>`,
    resort: `<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M8.5 18.5h7M10 11h5l-5-5z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M5 13.5c2.4-1.4 4.5-1.4 7 0" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>`,
    bpo: `<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="6" y="4.5" width="12" height="15" rx="2.2" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="M9 8.5h6M9 12h6M9 15.5h3.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>`,
    manufacturing: `<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4.5 18.5V9l5 3V9l5 3V5.5h5v13z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>`,
  };

  return `<span class="ui-icon">${icons[name] || icons.arrow}</span>`;
}

function investmentLensIconMarkup(lensKey) {
  const iconName = {
    university: "university",
    hospital: "hospital",
    logistics: "logistics",
    commercial_center: "commercial",
    resort: "resort",
    bpo: "bpo",
    manufacturing: "manufacturing",
  }[String(lensKey || "").toLowerCase()] || "spark";

  return icon(iconName);
}

function tagRow(tags = [], fallback = "Investor-ready") {
  const items = tags.length ? tags.slice(0, 3) : [fallback];
  return items.map((tag) => `<span class="tag">${escapeHtml(tag)}</span>`).join("");
}

function scorePill(score, label = "Opportunity") {
  return `<span class="score-pill ${scoreTone(score)}">${escapeHtml(label)} ${Math.round(Number(score || 0))}</span>`;
}

function statusPill(status) {
  return `<span class="status-pill ${statusTone(status)}">${escapeHtml(titleCase(status || "Available"))}</span>`;
}

function approvalStatePill(state) {
  const normalized = String(state || "approved").toLowerCase();
  return `<span class="status-pill approval-pill ${approvalTone(normalized)}">${escapeHtml(APPROVAL_LABELS[normalized] || titleCase(normalized))}</span>`;
}

function verificationPill(status) {
  const normalized = String(status || "unverified").toLowerCase();
  return `<span class="status-pill verification-pill ${verificationTone(normalized)}">${escapeHtml(VERIFICATION_LABELS[normalized] || titleCase(normalized))}</span>`;
}

function documentStatusPill(status) {
  const normalized = String(status || "missing").toLowerCase();
  return `<span class="tag document-status-pill ${documentTone(normalized)}">${escapeHtml(DOCUMENT_STATUS_LABELS[normalized] || titleCase(normalized))}</span>`;
}

function requestStatusPill(status) {
  const normalized = String(status || "requested").toLowerCase();
  return `<span class="status-pill request-status-pill ${requestTone(normalized)}">${escapeHtml(REQUEST_STATUS_LABELS[normalized] || titleCase(normalized))}</span>`;
}

function decisionStatusTone(statusKey) {
  return {
    buy_now: "decision-status-buy",
    strong_watch: "decision-status-watch",
    needs_verification: "decision-status-verify",
    speculative: "decision-status-speculative",
    low_priority: "decision-status-low",
  }[String(statusKey || "").toLowerCase()] || "decision-status-verify";
}

function decisionSignalPill(decision) {
  return `<span class="score-pill ${scoreTone(decision?.score || 0)}">Decision ${Math.round(Number(decision?.score || 0))}</span>`;
}

function decisionStatusPill(decision) {
  return `<span class="status-pill decision-status-pill ${decisionStatusTone(decision?.statusKey)}">${escapeHtml(decision?.statusLabel || "Needs Verification")}</span>`;
}

function decisionConfidencePill(decision) {
  return `<span class="tag decision-confidence-pill">${escapeHtml(decision?.confidenceLabel || "Confidence")} ${Math.round(Number(decision?.confidence || 0))}%</span>`;
}

function decisionPersonaSelectorMarkup(personas, activeKey, options = {}) {
  const available = normalizeDecisionPersonas(personas);
  if (!available.length) return "";

  return `
    <article class="panel-card decision-persona-panel">
      <div class="investment-intent-head">
        <div>
          <div class="panel-kicker">${escapeHtml(options.kicker || "Decision Persona")}</div>
          <h3>${escapeHtml(options.title || "Select Investor Persona")}</h3>
          <p>${escapeHtml(options.description || "See how the recommendation changes once the buyer profile becomes explicit.")}</p>
        </div>
        <div class="service-chip-row">
          ${serviceChip(`${available.length} personas`, "live")}
          ${serviceChip("Decision Engine", "neutral")}
        </div>
      </div>
      <div class="intent-grid" role="tablist" aria-label="Decision persona">
        ${available.map((persona) => `
          <button
            type="button"
            class="intent-card ${persona.key === activeKey ? "active" : ""}"
            data-decision-persona="${escapeHtml(persona.key)}"
            aria-pressed="${persona.key === activeKey ? "true" : "false"}"
          >
            <span class="icon" aria-hidden="true">${escapeHtml((persona.shortLabel || persona.label || "P").slice(0, 1))}</span>
            <div class="intent-meta">
              <strong>${escapeHtml(persona.label)}</strong>
              <small>${escapeHtml(persona.description || "Persona weighting")}</small>
            </div>
          </button>
        `).join("")}
      </div>
    </article>
  `;
}

function decisionSignalPanelMarkup(decision, options = {}) {
  if (!decision) return "";
  const reasons = Array.isArray(decision.reasons) ? decision.reasons.slice(0, Number(options.reasonLimit || 3)) : [];
  const nextAction = decision.nextAction?.label || "Review opportunity";
  const compact = Boolean(options.compact);

  return `
    <article class="panel-card decision-engine-panel ${compact ? "is-compact" : ""}">
      <div class="command-panel-head">
        <div>
          <div class="panel-kicker">${escapeHtml(options.kicker || "Decision Engine")}</div>
          <h3>${escapeHtml(options.title || `${decision.toneLabel || "Decision Signal"} for this opportunity`)}</h3>
        </div>
        ${serviceChip(decision.confidenceLabel || "Confidence", Number(decision.confidence || 0) >= 70 ? "live" : "fallback")}
      </div>
      <div class="decision-stats">
        ${decisionSignalPill(decision)}
        ${decisionStatusPill(decision)}
        ${decisionConfidencePill(decision)}
      </div>
      <p>${escapeHtml(decision.summary || "Decision signal available.")}</p>
      <div class="mini-list">
        <div class="mini-row"><span>Action lane</span><strong>${escapeHtml(nextAction)}</strong></div>
        <div class="mini-row"><span>Persona</span><strong>${escapeHtml(decision.label || decision.shortLabel || "Balanced Desk")}</strong></div>
      </div>
      ${reasons.length ? `<div class="insight-strip">${reasons.map((reason) => `<span class="insight-pill">${escapeHtml(reason)}</span>`).join("")}</div>` : ""}
    </article>
  `;
}

function metaChip(label) {
  return `<span class="meta-chip">${escapeHtml(label)}</span>`;
}

function formatDate(value) {
  if (!value) return "Recent";
  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) return "Recent";
  return parsed.toLocaleDateString(undefined, {
    month: "short",
    day: "numeric",
  });
}

function formatDateTime(value) {
  if (!value) return "Recent";
  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) return "Recent";
  return parsed.toLocaleString(undefined, {
    month: "short",
    day: "numeric",
    hour: "numeric",
    minute: "2-digit",
  });
}

function sellerApplicationStatus(profile = {}, user = currentUser) {
  const applicationStatus = String(profile?.applicationStatus || "").toLowerCase();
  if (applicationStatus) return applicationStatus;
  const identityStatus = String(user?.identityVerificationStatus || "unverified").toLowerCase();
  if (identityStatus === "pending") return "pending_review";
  if (["verified", "rejected", "suspended"].includes(identityStatus)) return identityStatus;
  return "draft";
}

function sellerCanPublish(profile = {}, user = currentUser) {
  return sellerApplicationStatus(profile, user) === "verified";
}

function sellerApplicationHeadline(status) {
  return {
    verified: "Seller account verified",
    pending_review: "Seller verification pending",
    rejected: "Seller application needs revision",
    suspended: "Seller access is suspended",
    draft: "Finish your seller profile",
  }[String(status || "").toLowerCase()] || "Seller verification";
}

function sellerApplicationCopy(status) {
  return {
    verified: "Your seller identity is approved. You can now publish listings and respond with a verified trust signal.",
    pending_review: "Your seller profile is in the admin approval queue. Listings stay locked until verification is approved.",
    rejected: "Update the profile details below, fix the review notes, and resubmit for admin approval.",
    suspended: "Seller publishing is paused on this account. Contact the platform admin before submitting more listings.",
    draft: "Complete the verification details below so the admin team can review your seller account before it goes live.",
  }[String(status || "").toLowerCase()] || "Complete your seller verification details before publishing.";
}

function sellerReviewSubmitLabel(status) {
  return {
    pending_review: "Review Submitted",
    rejected: "Resubmit For Review",
    verified: "Seller Approved",
    suspended: "Seller Suspended",
    draft: "Submit For Review",
  }[String(status || "").toLowerCase()] || "Submit For Review";
}

function sellerReviewStatusNote(status, audience = "seller") {
  const normalized = String(status || "").toLowerCase();

  if (audience === "seller") {
    return {
      pending_review: "Your seller verification details are already waiting in the admin approval queue.",
      verified: "You can now publish listings and respond with a verified trust signal.",
      rejected: "Admin requested revisions. Update the details below, then resubmit for review.",
      suspended: "Seller access is suspended for now. Contact the platform admin before submitting again.",
    }[normalized] || "";
  }

  return {
    verified: "This account can now publish listings in the seller workspace.",
    pending_review: "Seller remains in the review queue and is still waiting for approval.",
    rejected: "Revision requested. The seller needs to update the verification details before approval.",
    suspended: "Seller access is currently suspended until the profile is reviewed again.",
  }[normalized] || "";
}

function adminSellerReviewButtonLabel(status) {
  return {
    verified: "Seller Approved",
    pending_review: "Keep Pending",
    rejected: "Revision Requested",
    suspended: "Seller Suspended",
  }[String(status || "").toLowerCase()] || "Update Seller Status";
}

function syncAdminSellerReviewFormState(form) {
  if (!(form instanceof HTMLElement)) return;
  const statusSelect = form.querySelector('[name="status"]');
  const submitButton = form.querySelector("[data-seller-review-submit-button]");
  const statusNote = form.querySelector("[data-seller-review-status-note]");
  const selectedStatus = String(statusSelect?.value || "draft").toLowerCase();
  const buttonLabel = adminSellerReviewButtonLabel(selectedStatus);
  const noteText = sellerReviewStatusNote(selectedStatus, "admin");

  if (submitButton) {
    submitButton.textContent = buttonLabel;
  }

  if (statusNote) {
    if (noteText) {
      statusNote.innerHTML = `<strong>${escapeHtml(buttonLabel)}.</strong> ${escapeHtml(noteText)}`;
      statusNote.hidden = false;
    } else {
      statusNote.innerHTML = "";
      statusNote.hidden = true;
    }
  }
}

function formatFreshness(value, fallback = "Not recently confirmed") {
  if (!value) return fallback;
  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) return fallback;
  return formatDateTime(value);
}

function clampNumber(value, min, max) {
  return Math.min(max, Math.max(min, Number(value || 0)));
}

function formatProspectusDate(value) {
  if (!value) return "Current cycle";
  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) return "Current cycle";
  return parsed.toLocaleDateString(undefined, {
    month: "long",
    day: "numeric",
    year: "numeric",
  });
}

function formatProspectusTimestamp(value) {
  if (!value) return "Current cycle";
  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) return "Current cycle";
  return parsed.toLocaleString(undefined, {
    month: "long",
    day: "numeric",
    year: "numeric",
    hour: "numeric",
    minute: "2-digit",
  });
}

function formatProspectusCurrency(value) {
  const numeric = Number(value || 0);
  if (!Number.isFinite(numeric) || numeric <= 0) return "Pending";
  return `PHP ${Math.round(numeric).toLocaleString()}`;
}

function formatProspectusPercent(value, fallback = "Pending") {
  if (value == null || value === "") return fallback;
  const numeric = Number(value);
  if (!Number.isFinite(numeric)) return fallback;
  return `${Math.round(numeric)}%`;
}

function showcaseFeatureLabel(featureType) {
  return featureType === "city_pipeline" ? "City Pipeline" : "Offer Board";
}

function showcaseStateLabel(status) {
  const normalized = String(status || "").toLowerCase();
  return {
    open: "Open",
    closing_soon: "Closing Soon",
    awarded: "Awarded",
    paused: "Paused",
    planned: "Planned",
    approved: "Approved",
    groundbreaking: "Groundbreaking",
    under_construction: "Under Construction",
    opening_soon: "Opening Soon",
    priority: "Priority",
    validation: "Needs Validation",
    watchlist: "Watchlist",
  }[normalized] || titleCase(normalized || "active");
}

function showcaseStateTone(featureType, status) {
  const normalized = String(status || "").toLowerCase();
  if (featureType === "city_pipeline") {
    if (["priority", "under_construction", "groundbreaking"].includes(normalized)) return "showcase-state-live";
    if (["approved", "opening_soon", "validation"].includes(normalized)) return "showcase-state-warn";
    if (["watchlist"].includes(normalized)) return "showcase-state-muted";
    return "showcase-state-muted";
  }

  if (["open"].includes(normalized)) return "showcase-state-live";
  if (["closing_soon", "paused"].includes(normalized)) return "showcase-state-warn";
  return "showcase-state-muted";
}

function showcaseStatePill(item) {
  return `<span class="status-pill showcase-state-pill ${showcaseStateTone(item.featureType, item.status)}">${escapeHtml(showcaseStateLabel(item.status))}</span>`;
}

function showcaseFallbackHref(featureType) {
  return featureType === "city_pipeline"
    ? `${window.SFC_APP_CONFIG?.basePath || ""}/property-explorer.php`
    : `${window.SFC_APP_CONFIG?.basePath || ""}/property-ranking.php`;
}

function showcaseActionHref(item) {
  return item?.relatedPropertyId ? propertyHref(item.relatedPropertyId) : showcaseFallbackHref(item?.featureType);
}

function showcaseActionLabel(item) {
  if (item?.relatedPropertyId) return "Open Property";
  if (item?.featureType === "city_pipeline" && showcasePipelineMode(item) === "investment_gap") return "Find Matching Sites";
  return item?.featureType === "city_pipeline" ? "Explore Context" : "View Ranking";
}

function showcasePipelineMode(item) {
  if (item?.featureType !== "city_pipeline") return "standard";
  return String(item?.pipelineMode || "future_project").toLowerCase();
}

function showcasePipelineModeLabel(mode) {
  return {
    future_project: "Future Project",
    investment_gap: "Investor Gap",
    standard: "Standard",
  }[String(mode || "").toLowerCase()] || titleCase(mode || "future_project");
}

function showcaseSupplySignalLabel(signal) {
  return {
    not_present: "Not In Tracked Supply",
    under_supplied: "Under-Supplied",
    balanced: "Balanced Supply",
    crowded: "Already Crowded",
  }[String(signal || "").toLowerCase()] || titleCase(signal || "under_supplied");
}

function showcaseSupplySignalTone(signal) {
  return {
    not_present: "is-strong",
    under_supplied: "is-live",
    balanced: "is-neutral",
    crowded: "is-risk",
  }[String(signal || "").toLowerCase()] || "is-neutral";
}

function showcasePipelineModeChip(item) {
  if (item?.featureType !== "city_pipeline") return "";
  const mode = showcasePipelineMode(item);
  return `<span class="meta-chip showcase-need-chip ${mode === "investment_gap" ? "is-gap" : "is-project"}">${escapeHtml(showcasePipelineModeLabel(mode))}</span>`;
}

function showcaseSupplySignalChip(item) {
  if (item?.featureType !== "city_pipeline" || showcasePipelineMode(item) !== "investment_gap" || !item?.supplySignal) return "";
  return `<span class="meta-chip showcase-signal-chip ${showcaseSupplySignalTone(item.supplySignal)}">${escapeHtml(showcaseSupplySignalLabel(item.supplySignal))}</span>`;
}

function showcaseOpportunityBriefMarkup(item, variant = "card") {
  if (item?.featureType !== "city_pipeline" || showcasePipelineMode(item) !== "investment_gap") return "";

  const thesis = item?.investorThesis || item?.summary || "";
  const idealOperator = item?.idealOperator || "Best-fit operator not set yet";
  const caution = item?.avoidanceNote || "";

  if (variant === "spotlight") {
    return `
      <div class="showcase-gap-panel">
        <div class="showcase-gap-panel-row">
          <span>${icon("spark")}Supply signal</span>
          <strong>${escapeHtml(showcaseSupplySignalLabel(item?.supplySignal || "under_supplied"))}</strong>
        </div>
        <div class="showcase-gap-panel-row">
          <span>${icon("user")}Best-fit operator</span>
          <strong>${escapeHtml(idealOperator)}</strong>
        </div>
        <p>${escapeHtml(thesis)}</p>
        ${caution ? `<div class="showcase-gap-warning">${icon("shield")}Avoid duplicate build: ${escapeHtml(caution)}</div>` : ""}
      </div>
    `;
  }

  return `
    <div class="showcase-gap-panel compact">
      <div class="showcase-gap-panel-row">
        <span>Supply signal</span>
        <strong>${escapeHtml(showcaseSupplySignalLabel(item?.supplySignal || "under_supplied"))}</strong>
      </div>
      <div class="showcase-gap-panel-row">
        <span>Best fit</span>
        <strong>${escapeHtml(idealOperator)}</strong>
      </div>
      <p>${escapeHtml(truncate(thesis, 132))}</p>
      ${caution ? `<div class="showcase-gap-warning">${icon("shield")}Avoid duplicate build: ${escapeHtml(caution)}</div>` : ""}
    </div>
  `;
}

function showcasePipelineCounts(items = []) {
  const cityItems = items.filter((item) => item?.featureType === "city_pipeline");
  return {
    gaps: cityItems.filter((item) => showcasePipelineMode(item) === "investment_gap").length,
    projects: cityItems.filter((item) => showcasePipelineMode(item) !== "investment_gap").length,
    notPresent: cityItems.filter((item) => String(item?.supplySignal || "").toLowerCase() === "not_present").length,
    undersupplied: cityItems.filter((item) => String(item?.supplySignal || "").toLowerCase() === "under_supplied").length,
    caution: cityItems.filter((item) => String(item?.supplySignal || "").toLowerCase() === "crowded").length,
  };
}

function showcaseSearchHaystack(item) {
  return [
    item?.title,
    item?.partnerLabel,
    item?.category,
    item?.locationLabel,
    item?.barangay,
    item?.summary,
    item?.description,
    item?.status,
    item?.pipelineMode,
    item?.supplySignal,
    item?.investorThesis,
    item?.idealOperator,
    item?.avoidanceNote,
  ].filter(Boolean).join(" ").toLowerCase();
}

function formatCountdownDistance(value) {
  if (!value) return "Schedule pending";
  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) return "Schedule pending";

  const diff = parsed.getTime() - Date.now();
  if (diff <= 0) {
    return "Closed";
  }

  const totalMinutes = Math.floor(diff / 60000);
  const days = Math.floor(totalMinutes / (60 * 24));
  const hours = Math.floor((totalMinutes % (60 * 24)) / 60);
  const minutes = totalMinutes % 60;

  if (days > 0) return `${days}d ${hours}h ${minutes}m`;
  if (hours > 0) return `${hours}h ${minutes}m`;
  return `${minutes}m`;
}

function updateShowcaseCountdownNodes(root) {
  root.querySelectorAll("[data-showcase-countdown]").forEach((node) => {
    node.textContent = formatCountdownDistance(node.dataset.showcaseCountdown || "");
  });
}

function splitInHalf(items = []) {
  const midpoint = Math.ceil(items.length / 2);
  return [items.slice(0, midpoint), items.slice(midpoint)];
}

function haversineKm(from, to) {
  const lat1 = Number(from?.lat || 0);
  const lng1 = Number(from?.lng || 0);
  const lat2 = Number(to?.lat || 0);
  const lng2 = Number(to?.lng || 0);
  if (![lat1, lng1, lat2, lng2].every(Number.isFinite)) return Number.POSITIVE_INFINITY;
  const toRadians = (value) => (value * Math.PI) / 180;
  const deltaLat = toRadians(lat2 - lat1);
  const deltaLng = toRadians(lng2 - lng1);
  const originLat = toRadians(lat1);
  const targetLat = toRadians(lat2);
  const a = (Math.sin(deltaLat / 2) ** 2)
    + (Math.cos(originLat) * Math.cos(targetLat) * (Math.sin(deltaLng / 2) ** 2));
  return 6371 * (2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a)));
}

function messagingQrUrl(propertyId) {
  const numericId = Number(propertyId || 0);
  if (!numericId) return "";
  const basePath = String(window.SFC_APP_CONFIG?.basePath || "");
  return `${window.location.origin}${basePath}/m.php?p=${numericId}`;
}

function flattenReadinessIndicators(readiness) {
  if (!readiness?.pillars) return [];
  return Object.values(readiness.pillars).flatMap((pillar) => (
    Array.isArray(pillar?.indicators)
      ? pillar.indicators.map((indicator) => ({
        pillarLabel: String(pillar.label || ""),
        label: String(indicator.label || ""),
        rawValue: String(indicator.displayValue || "Missing"),
        normalizedScore: indicator.missing ? null : Number(indicator.normalizedScore || 0),
        missing: Boolean(indicator.missing),
      }))
      : []
  ));
}

function prospectusAuditTablesMarkup(readiness) {
  const rows = flattenReadinessIndicators(readiness);
  const [leftRows, rightRows] = splitInHalf(rows);
  const renderTable = (tableRows) => `
    <div class="prospectus-audit-table-shell">
      <table class="prospectus-audit-table">
        <thead>
          <tr>
            <th>Indicator</th>
            <th>Raw Value</th>
            <th>Normalized</th>
          </tr>
        </thead>
        <tbody>
          ${tableRows.map((row) => `
            <tr class="${row.missing ? "is-missing" : ""}">
              <td>
                <strong>${escapeHtml(row.label)}</strong>
                <span>${escapeHtml(row.pillarLabel)}</span>
              </td>
              <td>${escapeHtml(row.rawValue)}</td>
              <td>${row.missing ? "Missing" : `${Math.round(Number(row.normalizedScore || 0))}%`}</td>
            </tr>
          `).join("")}
        </tbody>
      </table>
    </div>
  `;

  return `
    <div class="prospectus-audit-grid">
      ${renderTable(leftRows)}
      ${rightRows.length ? renderTable(rightRows) : ""}
    </div>
  `;
}

function demandSnapshotSvgMarkup({ property, properties = [], lensKey = DEFAULT_INVESTMENT_LENS, votes = {} }) {
  const active = {
    lat: Number(property?.lat || 0),
    lng: Number(property?.lng || 0),
  };
  if (!Number.isFinite(active.lat) || !Number.isFinite(active.lng) || (active.lat === 0 && active.lng === 0)) {
    return `
      <svg viewBox="0 0 720 420" class="prospectus-map-svg" role="img" aria-label="Demand heatmap unavailable">
        <rect width="720" height="420" rx="24" fill="#f4f4f5"></rect>
        <text x="360" y="205" text-anchor="middle" font-size="22" fill="#3f3f46">Map coordinates are still being validated.</text>
      </svg>
    `;
  }

  const validPoints = (Array.isArray(properties) ? properties : [])
    .filter((entry) => Number.isFinite(Number(entry?.lat)) && Number.isFinite(Number(entry?.lng)))
    .map((entry) => {
      const distanceKm = haversineKm(active, entry);
      const lensScore = Number(entry?.lensResult?.score ?? entry?.lensScore ?? entry?.marketScore ?? 0);
      const scoreBase = clampNumber((lensScore * 0.72) + (Number(entry?.marketScore || 0) * 0.28), 24, 100);
      const voteBoost = Number(entry?.id) === Number(property?.id)
        ? Math.min(18, totalVotes(votes) * 3)
        : 0;
      return {
        ...entry,
        distanceKm,
        heatScore: clampNumber(scoreBase + voteBoost - Math.min(distanceKm * 4, 16), 18, 100),
      };
    });

  const focusPoints = validPoints
    .filter((entry) => entry.distanceKm <= 4.5)
    .sort((left, right) => left.distanceKm - right.distanceKm);
  const drawPoints = focusPoints.length ? focusPoints : validPoints.slice(0, 6);
  const allPoints = drawPoints.some((entry) => Number(entry.id) === Number(property?.id))
    ? drawPoints
    : [{ ...property, distanceKm: 0, heatScore: clampNumber(Number(property?.lensResult?.score ?? property?.marketScore ?? 82), 20, 100) }, ...drawPoints];

  const latitudes = allPoints.map((entry) => Number(entry.lat));
  const longitudes = allPoints.map((entry) => Number(entry.lng));
  const minLat = Math.min(...latitudes);
  const maxLat = Math.max(...latitudes);
  const minLng = Math.min(...longitudes);
  const maxLng = Math.max(...longitudes);
  const latPad = Math.max(0.004, (maxLat - minLat) * 0.18 || 0.004);
  const lngPad = Math.max(0.004, (maxLng - minLng) * 0.18 || 0.004);
  const bounds = {
    minLat: minLat - latPad,
    maxLat: maxLat + latPad,
    minLng: minLng - lngPad,
    maxLng: maxLng + lngPad,
  };

  const width = 720;
  const height = 420;
  const margin = 46;
  const projectX = (lng) => (
    margin + (((lng - bounds.minLng) / Math.max(bounds.maxLng - bounds.minLng, 0.0001)) * (width - (margin * 2)))
  );
  const projectY = (lat) => (
    height - margin - (((lat - bounds.minLat) / Math.max(bounds.maxLat - bounds.minLat, 0.0001)) * (height - (margin * 2)))
  );
  const activePoint = allPoints.find((entry) => Number(entry.id) === Number(property?.id)) || property;
  const activeX = projectX(Number(activePoint.lng || active.lng));
  const activeY = projectY(Number(activePoint.lat || active.lat));
  const kmPerLat = 111;
  const kmPerLng = 111 * Math.cos((Number(active.lat || 0) * Math.PI) / 180);
  const xRadius = (2 / Math.max(kmPerLng, 0.01)) * ((width - (margin * 2)) / Math.max(bounds.maxLng - bounds.minLng, 0.0001));
  const yRadius = (2 / kmPerLat) * ((height - (margin * 2)) / Math.max(bounds.maxLat - bounds.minLat, 0.0001));
  const topNearby = allPoints
    .filter((entry) => Number(entry.id) !== Number(property?.id) && entry.distanceKm <= 2.6)
    .sort((left, right) => right.heatScore - left.heatScore)
    .slice(0, 3);

  return `
    <svg viewBox="0 0 ${width} ${height}" class="prospectus-map-svg" role="img" aria-label="Static demand heatmap around ${escapeHtml(property?.name || "property")}">
      <defs>
        <linearGradient id="prospectusMapGrid" x1="0%" x2="100%" y1="0%" y2="100%">
          <stop offset="0%" stop-color="#eef2ff"></stop>
          <stop offset="100%" stop-color="#ffffff"></stop>
        </linearGradient>
        ${allPoints.map((entry, index) => `
          <radialGradient id="heat-${index}" cx="50%" cy="50%" r="50%">
            <stop offset="0%" stop-color="#6366f1" stop-opacity="${Math.max(0.28, entry.heatScore / 180).toFixed(2)}"></stop>
            <stop offset="65%" stop-color="#6366f1" stop-opacity="${Math.max(0.10, entry.heatScore / 520).toFixed(2)}"></stop>
            <stop offset="100%" stop-color="#6366f1" stop-opacity="0"></stop>
          </radialGradient>
        `).join("")}
      </defs>
      <rect width="${width}" height="${height}" rx="28" fill="url(#prospectusMapGrid)"></rect>
      <g opacity="0.55">
        ${[1, 2, 3, 4, 5].map((line) => `
          <path d="M${margin} ${(height / 6) * line}H${width - margin}" stroke="#e4e4e7" stroke-width="1"></path>
          <path d="M${(width / 6) * line} ${margin}V${height - margin}" stroke="#e4e4e7" stroke-width="1"></path>
        `).join("")}
      </g>
      <path d="M88 84C176 62 268 114 354 104C464 92 526 154 618 128" fill="none" stroke="#c7d2fe" stroke-width="14" stroke-linecap="round" opacity="0.45"></path>
      <path d="M96 300C184 262 280 318 374 286C460 256 534 298 612 274" fill="none" stroke="#e4e4e7" stroke-width="16" stroke-linecap="round" opacity="0.68"></path>
      ${allPoints.map((entry, index) => {
        const x = projectX(Number(entry.lng));
        const y = projectY(Number(entry.lat));
        const radius = Math.max(40, 54 + (entry.heatScore * 0.7));
        return `<circle cx="${x.toFixed(2)}" cy="${y.toFixed(2)}" r="${radius.toFixed(2)}" fill="url(#heat-${index})"></circle>`;
      }).join("")}
      <ellipse cx="${activeX.toFixed(2)}" cy="${activeY.toFixed(2)}" rx="${Math.max(24, xRadius).toFixed(2)}" ry="${Math.max(24, yRadius).toFixed(2)}" fill="none" stroke="#6366f1" stroke-width="2.5" stroke-dasharray="8 8"></ellipse>
      ${allPoints.map((entry) => {
        const x = projectX(Number(entry.lng));
        const y = projectY(Number(entry.lat));
        const isActive = Number(entry.id) === Number(property?.id);
        return `<circle cx="${x.toFixed(2)}" cy="${y.toFixed(2)}" r="${isActive ? 7 : 4.5}" fill="${isActive ? "#18181b" : "#6366f1"}" stroke="#ffffff" stroke-width="2"></circle>`;
      }).join("")}
      <g>
        <text x="54" y="52" fill="#18181b" font-size="20" font-weight="700">San Fernando, La Union</text>
        <text x="54" y="76" fill="#52525b" font-size="14">2km demand field calibrated to the active ${escapeHtml(getInvestmentLensConfig(lensKey).label)} lens</text>
      </g>
      <g>
        <rect x="494" y="40" width="178" height="104" rx="18" fill="#ffffff" stroke="#e4e4e7"></rect>
        <text x="514" y="66" fill="#18181b" font-size="13" font-weight="700">Signal Overlay</text>
        <text x="514" y="88" fill="#52525b" font-size="12">Heat intensity blends lens score,</text>
        <text x="514" y="106" fill="#52525b" font-size="12">market score, and local vote weight.</text>
        <text x="514" y="128" fill="#6366f1" font-size="12" font-weight="700">Active ring: 2km capital walk radius</text>
      </g>
      <g>
        <text x="${(activeX + 12).toFixed(2)}" y="${(activeY - 12).toFixed(2)}" fill="#18181b" font-size="13" font-weight="700">${escapeHtml(truncate(property?.name || "Active site", 28))}</text>
        ${topNearby.map((entry, index) => {
          const x = projectX(Number(entry.lng));
          const y = projectY(Number(entry.lat));
          return `<text x="${(x + 10).toFixed(2)}" y="${(y + (index * 14) + 18).toFixed(2)}" fill="#52525b" font-size="12">${escapeHtml(truncate(entry.name || "Demand node", 22))}</text>`;
        }).join("")}
      </g>
    </svg>
  `;
}

let qrFieldTables = null;
const qrGeneratorCache = new Map();

function qrTables() {
  if (qrFieldTables) return qrFieldTables;
  const exp = new Array(512).fill(0);
  const log = new Array(256).fill(0);
  let value = 1;
  for (let index = 0; index < 255; index += 1) {
    exp[index] = value;
    log[value] = index;
    value <<= 1;
    if (value & 0x100) {
      value ^= 0x11d;
    }
  }
  for (let index = 255; index < 512; index += 1) {
    exp[index] = exp[index - 255];
  }
  qrFieldTables = { exp, log };
  return qrFieldTables;
}

function qrMul(left, right) {
  if (left === 0 || right === 0) return 0;
  const { exp, log } = qrTables();
  return exp[log[left] + log[right]];
}

function qrPolyMultiply(left = [], right = []) {
  const product = new Array(left.length + right.length - 1).fill(0);
  left.forEach((leftValue, leftIndex) => {
    right.forEach((rightValue, rightIndex) => {
      product[leftIndex + rightIndex] ^= qrMul(leftValue, rightValue);
    });
  });
  return product;
}

function qrGeneratorPolynomial(degree) {
  const cacheKey = String(degree);
  if (qrGeneratorCache.has(cacheKey)) {
    return qrGeneratorCache.get(cacheKey);
  }

  let polynomial = [1];
  const { exp } = qrTables();
  for (let index = 0; index < degree; index += 1) {
    polynomial = qrPolyMultiply(polynomial, [1, exp[index]]);
  }
  qrGeneratorCache.set(cacheKey, polynomial);
  return polynomial;
}

function qrEncodeReedSolomon(dataCodewords, ecCodewords) {
  const generator = qrGeneratorPolynomial(ecCodewords);
  const remainder = new Array(ecCodewords).fill(0);

  dataCodewords.forEach((entry) => {
    const factor = entry ^ remainder[0];
    remainder.shift();
    remainder.push(0);
    for (let index = 0; index < ecCodewords; index += 1) {
      remainder[index] ^= qrMul(generator[index + 1], factor);
    }
  });

  return remainder;
}

function qrUtf8Bytes(value) {
  if (typeof TextEncoder !== "undefined") {
    return Array.from(new TextEncoder().encode(String(value ?? "")));
  }
  return Array.from(unescape(encodeURIComponent(String(value ?? "")))).map((character) => character.charCodeAt(0));
}

function qrEncodeByteMode(payload) {
  const bytes = qrUtf8Bytes(payload);
  const dataCapacity = 80;
  if (bytes.length > 78) {
    return null;
  }

  const bits = [];
  const appendBits = (value, length) => {
    for (let bit = length - 1; bit >= 0; bit -= 1) {
      bits.push((value >>> bit) & 1);
    }
  };

  appendBits(0b0100, 4);
  appendBits(bytes.length, 8);
  bytes.forEach((entry) => appendBits(entry, 8));
  appendBits(0, Math.min(4, (dataCapacity * 8) - bits.length));
  while (bits.length % 8 !== 0) {
    bits.push(0);
  }

  const codewords = [];
  for (let index = 0; index < bits.length; index += 8) {
    let value = 0;
    for (let offset = 0; offset < 8; offset += 1) {
      value = (value << 1) | bits[index + offset];
    }
    codewords.push(value);
  }

  const padBytes = [0xec, 0x11];
  while (codewords.length < dataCapacity) {
    codewords.push(padBytes[codewords.length % 2]);
  }

  return codewords;
}

function qrFormatBits(mask) {
  const data = (1 << 3) | mask;
  let remainder = data;
  for (let index = 0; index < 10; index += 1) {
    remainder = (remainder << 1) ^ (((remainder >>> 9) & 1) * 0x537);
  }
  return ((data << 10) | remainder) ^ 0x5412;
}

function qrBuildMatrix(payload) {
  const version = 4;
  const size = 33;
  const dataCodewords = qrEncodeByteMode(payload);
  if (!dataCodewords) {
    return null;
  }

  const ecCodewords = qrEncodeReedSolomon(dataCodewords, 20);
  const allCodewords = [...dataCodewords, ...ecCodewords];
  const bits = allCodewords.flatMap((entry) => (
    Array.from({ length: 8 }, (_, index) => (entry >>> (7 - index)) & 1)
  ));
  const modules = Array.from({ length: size }, () => Array(size).fill(false));
  const reserved = Array.from({ length: size }, () => Array(size).fill(false));

  const setModule = (row, column, dark) => {
    if (row < 0 || row >= size || column < 0 || column >= size) return;
    modules[row][column] = Boolean(dark);
    reserved[row][column] = true;
  };

  const drawFinder = (row, column) => {
    for (let deltaRow = -1; deltaRow <= 7; deltaRow += 1) {
      for (let deltaColumn = -1; deltaColumn <= 7; deltaColumn += 1) {
        const currentRow = row + deltaRow;
        const currentColumn = column + deltaColumn;
        const isSeparator = deltaRow === -1 || deltaRow === 7 || deltaColumn === -1 || deltaColumn === 7;
        const isOuter = deltaRow === 0 || deltaRow === 6 || deltaColumn === 0 || deltaColumn === 6;
        const isInner = deltaRow >= 2 && deltaRow <= 4 && deltaColumn >= 2 && deltaColumn <= 4;
        setModule(currentRow, currentColumn, !isSeparator && (isOuter || isInner));
      }
    }
  };

  const drawAlignment = (centerRow, centerColumn) => {
    for (let deltaRow = -2; deltaRow <= 2; deltaRow += 1) {
      for (let deltaColumn = -2; deltaColumn <= 2; deltaColumn += 1) {
        const ring = Math.max(Math.abs(deltaRow), Math.abs(deltaColumn));
        setModule(centerRow + deltaRow, centerColumn + deltaColumn, ring !== 1);
      }
    }
  };

  drawFinder(0, 0);
  drawFinder(0, size - 7);
  drawFinder(size - 7, 0);
  for (let index = 8; index < size - 8; index += 1) {
    setModule(6, index, index % 2 === 0);
    setModule(index, 6, index % 2 === 0);
  }
  drawAlignment(26, 26);
  setModule((version * 4) + 9, 8, true);

  for (let index = 0; index < 8; index += 1) {
    setModule(size - 1 - index, 8, false);
    if (index < 6) {
      setModule(8, index, false);
      setModule(index, 8, false);
    }
  }
  setModule(8, 7, false);
  setModule(8, 8, false);
  setModule(7, 8, false);
  for (let index = 0; index < 8; index += 1) {
    setModule(8, size - 1 - index, false);
  }
  for (let index = 0; index < 7; index += 1) {
    setModule(size - 7 + index, 8, false);
  }

  let bitIndex = 0;
  let upward = true;
  for (let right = size - 1; right >= 1; right -= 2) {
    if (right === 6) right -= 1;
    for (let vertical = 0; vertical < size; vertical += 1) {
      const row = upward ? size - 1 - vertical : vertical;
      for (let offset = 0; offset < 2; offset += 1) {
        const column = right - offset;
        if (reserved[row][column]) continue;
        const bit = bits[bitIndex] || 0;
        bitIndex += 1;
        const masked = ((row + column) % 2 === 0) ? bit ^ 1 : bit;
        modules[row][column] = Boolean(masked);
      }
    }
    upward = !upward;
  }

  const formatBits = qrFormatBits(0);
  const getBit = (value, index) => ((value >>> index) & 1) !== 0;
  for (let index = 0; index <= 5; index += 1) setModule(8, index, getBit(formatBits, index));
  setModule(8, 7, getBit(formatBits, 6));
  setModule(8, 8, getBit(formatBits, 7));
  setModule(7, 8, getBit(formatBits, 8));
  for (let index = 9; index <= 14; index += 1) setModule(14 - index, 8, getBit(formatBits, index));
  for (let index = 0; index < 8; index += 1) setModule(size - 1 - index, 8, getBit(formatBits, index));
  for (let index = 8; index < 15; index += 1) setModule(8, size - 15 + index, getBit(formatBits, index));
  setModule(8, size - 8, true);

  return modules;
}

function prospectusQrSvgMarkup(url) {
  const matrix = qrBuildMatrix(url);
  if (!matrix) {
    return `
      <svg viewBox="0 0 192 192" class="prospectus-qr-svg" role="img" aria-label="Messaging link">
        <rect width="192" height="192" rx="24" fill="#ffffff"></rect>
        <rect x="22" y="22" width="148" height="148" rx="20" fill="#eef2ff" stroke="#6366f1" stroke-width="4"></rect>
        <text x="96" y="86" text-anchor="middle" fill="#18181b" font-size="18" font-weight="700">Messaging Link</text>
        <text x="96" y="116" text-anchor="middle" fill="#52525b" font-size="13">Open manually if the link is too long</text>
      </svg>
    `;
  }

  const quietZone = 4;
  const scale = 4;
  const size = matrix.length + (quietZone * 2);
  const svgSize = size * scale;
  const path = [];
  matrix.forEach((row, rowIndex) => {
    row.forEach((cell, columnIndex) => {
      if (!cell) return;
      const x = (columnIndex + quietZone) * scale;
      const y = (rowIndex + quietZone) * scale;
      path.push(`M${x} ${y}h${scale}v${scale}h-${scale}z`);
    });
  });

  return `
    <svg viewBox="0 0 ${svgSize} ${svgSize}" class="prospectus-qr-svg" role="img" aria-label="QR code for threaded messaging">
      <rect width="${svgSize}" height="${svgSize}" rx="28" fill="#ffffff"></rect>
      <path d="${path.join("")}" fill="#111827"></path>
    </svg>
  `;
}

function toDatetimeLocalValue(value) {
  if (!value) return "";
  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) return "";
  const year = parsed.getFullYear();
  const month = String(parsed.getMonth() + 1).padStart(2, "0");
  const day = String(parsed.getDate()).padStart(2, "0");
  const hours = String(parsed.getHours()).padStart(2, "0");
  const minutes = String(parsed.getMinutes()).padStart(2, "0");
  return `${year}-${month}-${day}T${hours}:${minutes}`;
}

function trustBadgeRow(badges = [], options = {}) {
  if (!Array.isArray(badges) || !badges.length) {
    if (options.compact) {
      return `<span class="trust-note">Badges pending</span>`;
    }
    return `<span class="trust-note">Trust badges unlock as seller identity, documents, and site checks are completed.</span>`;
  }

  return badges.map((badge) => `
    <span class="trust-badge" data-badge-key="${escapeHtml(badge.key || "")}">
      ${icon("shield")}
      ${escapeHtml(badge.label || "Trust badge")}
    </span>
  `).join("");
}

function documentMeter(label, pct, note = "") {
  const value = Math.max(0, Math.min(100, Number(pct || 0)));
  return `
    <div class="document-meter">
      <div class="document-meter-top">
        <strong>${escapeHtml(label)}</strong>
        <span>${Math.round(value)}%</span>
      </div>
      <div class="document-meter-track"><span style="width:${Math.round(value)}%"></span></div>
      ${note ? `<div class="trust-note">${escapeHtml(note)}</div>` : ""}
    </div>
  `;
}

function documentChecklistMarkup(property, limit = DOCUMENT_FIELDS.length) {
  const checklist = Array.isArray(property?.documentChecklist) && property.documentChecklist.length
    ? property.documentChecklist
    : DOCUMENT_FIELDS;
  const statuses = property?.documentStatuses || {};

  return checklist.slice(0, limit).map((item) => `
    <div class="document-row">
      <div>
        <strong>${escapeHtml(item.label || titleCase(item.key || "Document"))}</strong>
        <span>${escapeHtml(item.key ? titleCase(String(item.key).replace(/_/g, " ")) : "Document")}</span>
      </div>
      ${documentStatusPill(statuses[item.key] || "missing")}
    </div>
  `).join("");
}

function requestTimelineMarkup(requests = [], options = {}) {
  if (!requests.length) {
    return `<div class="loading-panel">${escapeHtml(options.emptyCopy || "No document requests yet.")}</div>`;
  }

  return requests.map((request) => `
    <article class="request-card">
      <div class="request-card-top">
        <div>
          <strong>${escapeHtml(request.documentName || "Requested document")}</strong>
          <span>${escapeHtml(request.requesterName || "Platform user")} · ${escapeHtml(titleCase(request.requesterRole || "user"))}</span>
        </div>
        ${requestStatusPill(request.status)}
      </div>
      ${request.note ? `<p>${escapeHtml(request.note)}</p>` : `<p>No note attached to this request.</p>`}
      <div class="request-card-meta">
        <span>${icon("clock")}Requested ${escapeHtml(formatDateTime(request.createdAt))}</span>
        <span>${icon("file")}Updated ${escapeHtml(formatDateTime(request.updatedAt || request.createdAt))}</span>
      </div>
      ${request.responseNote ? `<div class="request-response">${escapeHtml(request.responseNote)}</div>` : ""}
      ${options.manage ? `
        <form class="request-manage-form" data-request-manage="${request.id}">
          <label class="form-shell">
            <span>Status</span>
            <select class="input-shell" name="status">
              <option value="requested" ${String(request.status) === "requested" ? "selected" : ""}>Requested</option>
              <option value="in_review" ${String(request.status) === "in_review" ? "selected" : ""}>In Review</option>
              <option value="fulfilled" ${String(request.status) === "fulfilled" ? "selected" : ""}>Fulfilled</option>
              <option value="declined" ${String(request.status) === "declined" ? "selected" : ""}>Declined</option>
            </select>
          </label>
          <label class="form-shell form-span-2">
            <span>Response note</span>
            <textarea class="input-shell input-textarea" name="responseNote" placeholder="Share the next step, upload timing, or the reason for decline.">${escapeHtml(request.responseNote || "")}</textarea>
          </label>
          <button type="submit" class="btn-shell btn-shell-secondary">Update Request</button>
        </form>
      ` : ""}
    </article>
  `).join("");
}

function readinessToneClass(status) {
  const normalized = String(status || "").toLowerCase();
  if (normalized === "strong") return "readiness-strong";
  if (normalized === "neutral") return "readiness-neutral";
  if (normalized === "incomplete") return "readiness-incomplete";
  return "readiness-warning";
}

function progressRingMarkup(score, status) {
  const value = Math.max(0, Math.min(100, Number(score || 0)));
  const radius = 26;
  const circumference = 2 * Math.PI * radius;
  const offset = circumference - ((value / 100) * circumference);
  return `
    <svg class="progress-ring ${readinessToneClass(status)}" viewBox="0 0 64 64" aria-hidden="true">
      <circle class="progress-ring-track" cx="32" cy="32" r="${radius}"></circle>
      <circle class="progress-ring-value" cx="32" cy="32" r="${radius}" style="stroke-dasharray:${circumference.toFixed(2)};stroke-dashoffset:${offset.toFixed(2)};"></circle>
    </svg>
  `;
}

function readinessIndicatorPill(indicator) {
  return `
    <span class="indicator-pill ${indicator.missing ? "" : indicator.status === "strong" ? "verified" : ""}">
      ${indicator.missing ? icon("clock") : icon(indicator.status === "strong" ? "shield" : "file")}
      ${escapeHtml(indicator.label)}
    </span>
  `;
}

function readinessMatrixMarkup(readiness, activePillarKey, canEditInline) {
  if (!readiness) {
    return emptyState("Readiness matrix unavailable", "This property does not yet have enough structured data for the IRIE view.");
  }

  const pillars = Object.values(readiness.pillars || {});
  return `
    <article class="panel-card readiness-bento">
      <div class="readiness-bento-head">
        <div>
          <div class="panel-kicker">Investment Readiness Matrix</div>
          <h3>${escapeHtml(readiness.label || "Readiness overview")}</h3>
        </div>
        <div class="service-chip-row">
          ${serviceChip(`${readiness.missingDataCount || 0} missing data point${Number(readiness.missingDataCount || 0) === 1 ? "" : "s"}`, Number(readiness.missingDataCount || 0) ? "fallback" : "live")}
          ${serviceChip("IRIE", "neutral")}
        </div>
      </div>
      <div class="readiness-score-panel ${readinessToneClass(readiness.status)}">
        <div>
          <div class="panel-kicker">Total investment readiness</div>
          <div class="readiness-score-hero">${Math.round(Number(readiness.totalScore || 0))}</div>
          <div class="trust-note">Last computed ${escapeHtml(formatDateTime(readiness.lastComputedAt))}</div>
        </div>
        <div class="readiness-summary-stack">
          <div class="readiness-summary-copy">${escapeHtml(readiness.label || "Needs More Validation")}</div>
          <div class="trust-note">${escapeHtml(readiness.missingDataCount ? `There ${Number(readiness.missingDataCount) === 1 ? "is" : "are"} ${readiness.missingDataCount} missing input${Number(readiness.missingDataCount) === 1 ? "" : "s"} affecting certainty.` : "All core IRIE inputs are currently available.")}</div>
          ${canEditInline && readiness.notes ? `<div class="request-response">${escapeHtml(readiness.notes)}</div>` : ""}
        </div>
      </div>
      <div class="readiness-pill-row">
        ${pillars.flatMap((pillar) => pillar.indicators.slice(0, 1)).map((indicator) => readinessIndicatorPill(indicator)).join("")}
      </div>
      <div class="readiness-pillars-grid">
        ${pillars.map((pillar) => `
          <button type="button" class="readiness-pillar-card ${readinessToneClass(pillar.status)} ${String(activePillarKey) === String(pillar.key) ? "is-active" : ""}" data-readiness-pillar="${escapeHtml(pillar.key)}">
            <div class="readiness-pillar-top">
              ${progressRingMarkup(pillar.score, pillar.status)}
              <div>
                <div class="readiness-pillar-score">${Math.round(Number(pillar.score || 0))}%</div>
                <strong>${escapeHtml(pillar.label)}</strong>
              </div>
            </div>
            <p>${escapeHtml(pillar.summary || `${pillar.label} summary`)}</p>
            <div class="readiness-pillar-meta">
              <span>${pillar.weight}% weight</span>
              <span>${pillar.missingFields.length ? `${pillar.missingFields.length} missing` : "Complete"}</span>
            </div>
          </button>
        `).join("")}
      </div>
      ${pillars.map((pillar) => `
        <div class="readiness-drawer ${String(activePillarKey) === String(pillar.key) ? "is-open" : ""}" data-readiness-drawer="${escapeHtml(pillar.key)}">
          <div class="readiness-drawer-head">
            <div>
              <div class="panel-kicker">${escapeHtml(pillar.label)} Drill-down</div>
              <h4>${Math.round(Number(pillar.score || 0))}% readiness</h4>
            </div>
            ${serviceChip(pillar.status === "strong" ? "High readiness" : pillar.status === "neutral" ? "Moderate readiness" : "Needs input", pillar.status === "strong" ? "live" : "fallback")}
          </div>
          <div class="indicator-list">
            ${pillar.indicators.map((indicator) => `
              <article class="indicator-row ${indicator.missing ? "is-missing" : ""}">
                <div>
                  <strong>${escapeHtml(indicator.label)}</strong>
                  <span>${escapeHtml(indicator.displayValue || "Missing")}</span>
                </div>
                <div class="indicator-row-side">
                  ${indicator.missing ? `<span class="indicator-score warning">Missing</span>` : `<span class="indicator-score ${readinessToneClass(indicator.status)}">${Math.round(Number(indicator.normalizedScore || 0))}</span>`}
                  ${readinessIndicatorPill(indicator)}
                </div>
              </article>
            `).join("")}
          </div>
          ${pillar.missingFields.length ? `<div class="auth-form-note">Missing inputs: ${escapeHtml(pillar.missingFields.join(", "))}</div>` : ""}
          ${canEditInline ? `<div class="trust-note">Admin edits below update this pillar preview instantly before save.</div>` : ""}
        </div>
      `).join("")}
    </article>
  `;
}

function inlineReadinessEditorMarkup(property) {
  const utilityStatus = String(property?.utilityStatus || "");
  const clupProfile = property?.clupProfile || {};
  return `
    <article class="panel-card readiness-editor-card">
      <div class="panel-kicker">Admin Inline Editing</div>
      <h3>IRIE input controls</h3>
      <form class="readiness-inline-form" id="readinessInlineForm">
        <label class="form-shell">
          <span>Distance to road (km)</span>
          <input class="input-shell" type="number" step="0.01" min="0" name="distToRoadKm" value="${escapeHtml(property?.distToRoadKm ?? "")}">
        </label>
        <label class="form-shell">
          <span>Utility status</span>
          <select class="input-shell" name="utilityStatus">
            <option value="" ${utilityStatus === "" ? "selected" : ""}>Select utility status</option>
            <option value="full_ready" ${utilityStatus === "full_ready" ? "selected" : ""}>Full Fiber / Power / Water</option>
            <option value="power_water" ${utilityStatus === "power_water" ? "selected" : ""}>Power / Water Ready</option>
            <option value="partial" ${utilityStatus === "partial" ? "selected" : ""}>Partial Utility Service</option>
            <option value="limited" ${utilityStatus === "limited" ? "selected" : ""}>Limited Utility Service</option>
            <option value="off_grid" ${utilityStatus === "off_grid" ? "selected" : ""}>Off Grid</option>
          </select>
        </label>
        <label class="form-shell">
          <span>Zoning score</span>
          <input class="input-shell" type="number" min="0" max="100" name="zoningScore" value="${escapeHtml(property?.zoningScore ?? "")}">
        </label>
        <label class="form-shell">
          <span>Assessed value / sqm</span>
          <input class="input-shell" type="number" min="0" name="assessedValueSqm" value="${escapeHtml(property?.assessedValueSqm ?? "")}">
        </label>
        <label class="form-shell form-span-2">
          <span>Readiness notes</span>
          <textarea class="input-shell input-textarea" name="readinessNotes" placeholder="Internal readiness commentary for admin use.">${escapeHtml(property?.readinessNotes || "")}</textarea>
        </label>
        <div class="form-span-2 clup-editor-divider"><div class="panel-kicker">Parcel CLUP Evidence</div><p>Use canonical keys: commercial, logistics, hotel, bpo, manufacturing, mixed_use.</p></div>
        <label class="form-shell"><span>Existing land use</span><input class="input-shell" name="existingLandUse" value="${escapeHtml(clupProfile.existingLandUse || "")}" placeholder="e.g. Urban commercial use"></label>
        <label class="form-shell"><span>Zoning classification</span><input class="input-shell" name="zoningClassification" value="${escapeHtml(clupProfile.zoningClassification || "")}" placeholder="e.g. C-2 Commercial Zone"></label>
        <label class="form-shell"><span>Allowed uses</span><input class="input-shell" name="clupAllowedUses" value="${escapeHtml((clupProfile.allowedUses || []).join(", "))}" placeholder="commercial, bpo"></label>
        <label class="form-shell"><span>Conditional uses</span><input class="input-shell" name="clupConditionalUses" value="${escapeHtml((clupProfile.conditionalUses || []).join(", "))}" placeholder="hotel, mixed_use"></label>
        <label class="form-shell"><span>Restricted uses</span><input class="input-shell" name="clupRestrictedUses" value="${escapeHtml((clupProfile.restrictedUses || []).join(", "))}" placeholder="manufacturing, logistics"></label>
        <label class="form-shell"><span>Source reference</span><input class="input-shell" name="clupSourceReference" value="${escapeHtml(clupProfile.sourceReference || "")}" placeholder="CLUP map sheet / ordinance section"></label>
        <label class="diligence-item form-span-2"><span>Mark parcel CLUP profile as LGU verified</span><input type="checkbox" name="clupVerified" ${clupProfile.isVerified ? "checked" : ""}></label>
        <div class="crud-actions form-span-2">
          <button type="button" class="btn-shell btn-shell-ghost" data-readiness-reset>Reset Preview</button>
          <button type="submit" class="btn-shell btn-shell-primary">Save IRIE Inputs</button>
        </div>
      </form>
    </article>
  `;
}

function dueDiligenceDrawerMarkup(items, state, options = {}) {
  const isOpen = Boolean(options.open);
  const editable = Boolean(options.editable);
  const pct = calcDueDiligencePct(items, state);
  return `
    <button type="button" class="due-diligence-fab" id="dueDiligenceFab">${icon("file")}Due Diligence</button>
    <aside class="due-diligence-drawer ${isOpen ? "is-open" : ""}" id="dueDiligenceDrawer">
      <div class="due-diligence-drawer-head">
        <div>
          <div class="panel-kicker">Legal Pillar Support</div>
          <h3>Due diligence checklist</h3>
        </div>
        <button type="button" class="modal-close" id="dueDiligenceClose">Close</button>
      </div>
      ${documentMeter("Due diligence completion", pct, editable ? "Seller or admin can update the checklist here." : "Read-only diligence view for this role.")}
      <form class="due-diligence-checklist" id="dueDiligenceForm">
        ${items.map((item) => `
          <label class="diligence-item ${state[item.key] ? "is-complete" : ""}">
            <span>${escapeHtml(item.label || item.key)}</span>
            ${editable ? `<input type="checkbox" name="${escapeHtml(item.key)}" ${state[item.key] ? "checked" : ""}>` : `<strong>${state[item.key] ? "Done" : "Pending"}</strong>`}
          </label>
        `).join("")}
        ${editable ? `
          <div class="crud-actions">
            <button type="submit" class="btn-shell btn-shell-primary">Save Checklist</button>
          </div>
        ` : ""}
      </form>
    </aside>
    <div class="drawer-backdrop ${isOpen ? "is-open" : ""}" id="dueDiligenceBackdrop"></div>
  `;
}

function serviceChip(label, tone = "neutral") {
  return `<span class="service-chip service-chip-${escapeHtml(tone)}">${escapeHtml(label)}</span>`;
}

const mapRegistry = new Map();

function destroyMap(containerId) {
  const entry = mapRegistry.get(containerId);
  if (!entry) return;
  entry.map.remove();
  mapRegistry.delete(containerId);
}

function mapPopup(property) {
  const compliance = evaluateClup(property, property.clupCompliance?.proposedInvestmentType || property.type);
  return `
    <div class="map-popup-card map-popup-card-rich">
      <div class="map-popup-card-top">
        <div class="map-popup-kicker">${escapeHtml(property.barangay || "San Fernando")}</div>
        ${clupStatusPill(compliance)}
      </div>
      <strong>${escapeHtml(property.name)}</strong>
      <div class="map-popup-meta">
        <span>${escapeHtml(corridorLabel(property.corridor))}</span>
        <span>${escapeHtml(property.area || "--")} ha</span>
      </div>
      <div class="map-popup-price">${escapeHtml(moneyShort(property.price))}</div>
    </div>
  `;
}

function mountPropertyMap({
  containerId,
  properties,
  activeId = null,
  onSelect = null,
  searchResult = null,
}) {
  const container = document.getElementById(containerId);
  if (!container || !window.maplibregl) return;

  destroyMap(containerId);

  const tileUrl = window.SFC_APP_CONFIG?.mapTileUrl || "https://basemaps.cartocdn.com/gl/positron-gl-style/style.json";
  const tileAttribution = window.SFC_APP_CONFIG?.mapAttribution || '&copy; CARTO &copy; OpenStreetMap contributors';

  const map = new window.maplibregl.Map({
    container: container,
    style: tileUrl,
    center: [120.3218, 16.6208], // [lng, lat]
    zoom: 13,
    attributionControl: false
  });

  map.addControl(new window.maplibregl.NavigationControl({ showCompass: false }), "top-right");
  map.addControl(new window.maplibregl.AttributionControl({ compact: true, customAttribution: tileAttribution }));

  const bounds = new window.maplibregl.LngLatBounds();
  const markers = new Map();

  properties.forEach((property) => {
    const lat = Number(property.lat);
    const lng = Number(property.lng);
    if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;

    const el = document.createElement("div");
    el.className = "sfc-map-pin-shell";
    el.innerHTML = `<span class="sfc-map-pin clup-pin-${escapeHtml(String(property.clupCompliance?.status || '').toLowerCase())} ${property.id === activeId ? "is-active" : ""}"></span>`;

    const popupHtml = mapPopup(property);
    const popup = new window.maplibregl.Popup({
      offset: [0, -14],
      closeButton: false,
      className: "sfc-map-popup"
    }).setHTML(popupHtml);

    const marker = new window.maplibregl.Marker({ element: el })
      .setLngLat([lng, lat])
      .setPopup(popup)
      .addTo(map);

    el.addEventListener("click", (e) => {
      e.stopPropagation();
      onSelect?.(property.id);
    });

    bounds.extend([lng, lat]);
    markers.set(property.id, marker);
  });

  let searchMarker = null;
  if (searchResult?.lat && searchResult?.lng) {
    const sLat = Number(searchResult.lat);
    const sLng = Number(searchResult.lng);

    const el = document.createElement("div");
    el.className = "sfc-map-pin-shell";
    el.innerHTML = `<span class="sfc-map-pin is-search-result"></span>`;

    const popupHtml = `
      <div class="map-popup-card map-popup-card-rich is-search-result">
        <div class="map-popup-card-top">
          <div class="map-popup-kicker">Search result</div>
          <span class="map-popup-chip">Location</span>
        </div>
        <strong>${escapeHtml(searchResult.label || "Location result")}</strong>
        <div class="map-popup-meta">
          <span>${escapeHtml(searchResult.subtitle || "LocationIQ result")}</span>
        </div>
      </div>
    `;

    const popup = new window.maplibregl.Popup({
      offset: [0, -14],
      closeButton: false,
      className: "sfc-map-popup"
    }).setHTML(popupHtml);

    searchMarker = new window.maplibregl.Marker({ element: el })
      .setLngLat([sLng, sLat])
      .setPopup(popup)
      .addTo(map);

    bounds.extend([sLng, sLat]);
  }

  if (!bounds.isEmpty()) {
    const activeMarker = markers.get(activeId);
    const useStandaloneSearchTarget = searchMarker && !Number(searchResult?.propertyId || 0);

    let targetCoords = null;
    if (useStandaloneSearchTarget && searchMarker) {
      targetCoords = searchMarker.getLngLat();
    } else if (activeMarker) {
      targetCoords = activeMarker.getLngLat();
    } else if (searchMarker) {
      targetCoords = searchMarker.getLngLat();
    }

    if (targetCoords) {
      map.easeTo({
        center: targetCoords,
        zoom: 15,
        duration: 800
      });
      if (activeMarker) {
        activeMarker.togglePopup();
      } else if (searchMarker) {
        searchMarker.togglePopup();
      }
    } else {
      map.fitBounds(bounds, { padding: 40, maxZoom: 15 });
    }
  }

  setTimeout(() => map.resize(), 0);
  mapRegistry.set(containerId, { map, markers });
}

function uniqueCardLabels(items = []) {
  const seen = new Set();
  return items
    .map((item) => String(item || "").trim())
    .filter(Boolean)
    .filter((item) => {
      const key = item.toLowerCase();
      if (seen.has(key)) return false;
      seen.add(key);
      return true;
    });
}

function propertyPrimaryLabels(property = {}) {
  return uniqueCardLabels([
    typeLabel(property.type),
    corridorLabel(property.corridor),
  ]).slice(0, 2);
}

function propertySecondaryLabels(property = {}) {
  const blocked = new Set(propertyPrimaryLabels(property).map((label) => label.toLowerCase()));
  return uniqueCardLabels([
    voteLabel(property.topNeed || ""),
    ...(Array.isArray(property.tags) ? property.tags : []),
  ]).filter((label) => {
    const normalized = label.toLowerCase();
    return normalized !== "no demand yet" && !blocked.has(normalized);
  });
}

function propertyTrustStrip(property = {}) {
  const trustBadges = Array.isArray(property.trustBadges) ? property.trustBadges : [];
  const normalizedVerification = String(property.listingVerificationStatus || "unverified").toLowerCase();
  const docsPct = Math.round(Number(property.documentCompletenessPct || 0));
  const sellerBadge = trustBadges.find((badge) => /seller/i.test(`${badge?.key || ""} ${badge?.label || ""}`));
  const freshnessBadge = trustBadges.find((badge) => /updated/i.test(`${badge?.key || ""} ${badge?.label || ""}`));
  const visitCount = Number(property.groundTruthVisitCount || 0);
  const adjustment = Math.round(Number(property.groundTruthAdjustmentPct || 0));
  const adjustmentPrefix = adjustment > 0 ? "+" : "";
  const signalLabel = visitCount > 0 ? "Field" : (sellerBadge ? "Seller" : "Signal");
  const signalValue = visitCount > 0
    ? `Ground Truth ${adjustmentPrefix}${adjustment}%`
    : sellerBadge?.label || trustBadges[0]?.label || "Signal pending";
  const freshnessValue = property.lastConfirmedAvailableAt
    ? `Confirmed ${formatDate(property.lastConfirmedAvailableAt)}`
    : freshnessBadge?.label || "Awaiting refresh";

  const cells = [
    {
      label: "Listing",
      value: VERIFICATION_LABELS[normalizedVerification] || titleCase(normalizedVerification),
      tone: normalizedVerification === "verified"
        ? "is-verified"
        : (normalizedVerification.includes("partial") || normalizedVerification.includes("pending") ? "is-watch" : ""),
    },
    {
      label: "Docs",
      value: `${docsPct}% complete`,
      tone: docsPct >= 80 ? "is-verified" : (docsPct >= 40 ? "is-watch" : ""),
    },
    {
      label: "Freshness",
      value: freshnessValue,
      tone: property.lastConfirmedAvailableAt || freshnessBadge ? "is-intel" : "",
    },
    {
      label: signalLabel,
      value: signalValue,
      tone: visitCount > 0
        ? (adjustment >= 0 ? "is-verified" : "is-watch")
        : (sellerBadge ? "is-verified" : ""),
    },
  ];

  return cells.map((cell) => `
    <div class="property-trust-cell ${cell.tone}">
      <span>${escapeHtml(cell.label)}</span>
      <strong>${escapeHtml(cell.value)}</strong>
    </div>
  `).join("");
}

function propertyCard(property, options = {}) {
  if (options.variant === "ranking") {
    return rankingPropertyCardMarkup(property, options);
  }

  if (options.variant === "compact") {
    return compactPropertyCardMarkup(property, options);
  }

  const compareIds = options.compareIds || [];
  const favoriteIds = options.favoriteIds || [];
  const showManage = options.showManage || false;
  const manageHref = options.manageHref || "";
  const showApproval = options.showApproval ?? (role === "admin" || role === "seller" || String(property.approvalState || "").toLowerCase() !== "approved");
  const lensKey = options.lensKey || null;
  const activeLens = lensKey ? getInvestmentLensConfig(lensKey) : null;
  const leadScorePill = activeLens && property.lensResult
    ? investmentLensScorePill(property.lensResult)
    : scorePill(property.opportunityScore);
  const thesis = activeLens && property.lensResult
    ? property.lensResult.thesisShort || property.lensResult.thesis || `${property.name} is being read through the ${activeLens.label} lens.`
    : property.description || propertyStory(property);
  const primaryLabels = propertyPrimaryLabels(property);
  const signalLabels = propertySecondaryLabels(property);
  const visibleSignals = signalLabels.slice(0, 2);
  const hiddenSignalCount = Math.max(0, signalLabels.length - visibleSignals.length);
  const docsPct = Math.round(Number(property.documentCompletenessPct || 0));
  const verifiedLabel = VERIFICATION_LABELS[String(property.listingVerificationStatus || "unverified").toLowerCase()]
    || titleCase(property.listingVerificationStatus || "Unverified");
  const metricItems = [
    {
      label: "Land Area",
      value: `${escapeHtml(property.area || "--")} ha`,
      note: "Parcel size",
    },
    {
      label: "Market Pulse",
      value: `${Number(property.voteTotal || 0)} votes`,
      note: voteLabel(property.topNeed || "No dominant demand"),
    },
    {
      label: "Ask Price",
      value: moneyShort(property.price),
      note: "Guide valuation",
    },
    {
      label: "Readiness",
      value: `${docsPct}% docs`,
      note: verifiedLabel,
    },
  ];
  const secondarySignalMarkup = visibleSignals.length
    ? visibleSignals.map((label) => `<span class="property-secondary-chip">${escapeHtml(label)}</span>`).join("")
    : `<span class="property-secondary-chip">Investor-ready brief</span>`;

  return `
    <article class="property-card property-card-intelligence">
      <div class="property-media">
        <img src="${escapeHtml(property.imageUrl)}" alt="${escapeHtml(property.name)}">
        <div class="property-media-top">
          ${leadScorePill}
          <div class="property-pill-stack">
            ${statusPill(property.status)}
            ${showApproval ? approvalStatePill(property.approvalState) : ""}
          </div>
        </div>
        <div class="property-media-bottom">
          <div class="property-geo-block">
            <span>${escapeHtml(property.city || "San Fernando, La Union")}</span>
            <strong>${icon("map")}${escapeHtml(property.barangay || "Unassigned")}</strong>
          </div>
          <div class="property-price-block">
            <span>Guide Price</span>
            <strong>${escapeHtml(moneyShort(property.price))}</strong>
          </div>
        </div>
      </div>
      <div class="property-body">
        <div class="property-identity-block">
          <div class="property-chip-row">
            ${primaryLabels.map((label, index) => `<span class="property-primary-chip ${index === 0 ? "is-strong" : ""}">${escapeHtml(label)}</span>`).join("")}
          </div>
          <div class="property-title-row">
            <div>
              <h3 class="property-title">${escapeHtml(property.name)}</h3>
              <div class="property-subline">${escapeHtml(property.city || "San Fernando, La Union")} | ${escapeHtml(property.barangay || "Unassigned")}</div>
            </div>
          </div>
          <p class="property-thesis">${escapeHtml(truncate(thesis, 114))}</p>
        </div>
        <div class="property-metric-grid">
          ${metricItems.map((item) => `
            <div class="property-metric">
              <span class="property-metric-label">${escapeHtml(item.label)}</span>
              <strong>${escapeHtml(item.value)}</strong>
              <small>${escapeHtml(item.note)}</small>
            </div>
          `).join("")}
        </div>
        <div class="property-trust-strip">
          ${propertyTrustStrip(property)}
        </div>
        <div class="property-signal-row">
          ${secondarySignalMarkup}
          ${hiddenSignalCount ? `<span class="property-secondary-chip property-secondary-count">+${hiddenSignalCount} signals</span>` : ""}
        </div>
        <div class="property-actions property-actions-card">
          <a href="${propertyHref(property.id)}" class="btn-shell btn-shell-primary">${icon("arrow")}View Details</a>
          <div class="property-secondary-actions">
            <button type="button" class="btn-shell btn-shell-secondary" data-compare-toggle="${property.id}">${icon("compare")}${compareIds.includes(property.id) ? "Compared" : "Compare"}</button>
            <button type="button" class="btn-shell btn-shell-ghost" data-favorite-toggle="${property.id}">${icon("save")}${favoriteActionLabel(favoriteIds.includes(property.id))}</button>
            ${showManage ? `<a href="${escapeHtml(manageHref)}" class="btn-shell btn-shell-ghost">${icon("arrow")}Manage</a>` : ""}
          </div>
        </div>
      </div>
    </article>
  `;
}

function rankingPropertyCardMarkup(property, options = {}) {
  const compareIds = options.compareIds || [];
  const favoriteIds = options.favoriteIds || [];
  const showManage = options.showManage || false;
  const manageHref = options.manageHref || "";
  const lensKey = options.lensKey || null;
  const activeLens = lensKey ? getInvestmentLensConfig(lensKey) : null;
  const isSaved = favoriteIds.includes(property.id);
  const isCompared = compareIds.includes(property.id);
  const leadScorePill = activeLens && property.lensResult
    ? investmentLensScorePill(property.lensResult)
    : scorePill(property.opportunityScore);
  const thesis = activeLens && property.lensResult
    ? property.lensResult.thesisShort || property.lensResult.thesis || `${property.name} is being read through the ${activeLens.label} lens.`
    : property.description || propertyStory(property);
  const docsPct = Math.round(Number(property.documentCompletenessPct || 0));
  const voteTotal = Number(property.voteTotal || 0);
  const verifiedLabel = VERIFICATION_LABELS[String(property.listingVerificationStatus || "unverified").toLowerCase()]
    || titleCase(property.listingVerificationStatus || "Unverified");
  const headerChips = [
    typeLabel(property.type),
    corridorLabel(property.corridor),
    voteLabel(property.topNeed || "No dominant demand"),
  ].filter(Boolean).slice(0, 3);
  const footerChips = [
    verifiedLabel,
    property.area ? `${property.area} ha` : null,
    property.lastConfirmedAvailableAt ? `Confirmed ${formatDate(property.lastConfirmedAvailableAt)}` : null,
  ].filter(Boolean).slice(0, 3);
  const compliance = evaluateClup(property, lensKey ? clupUseForLens(lensKey) : property.type);

  return `
    <article class="property-card property-card-ranking">
      <div class="property-ranking-media">
        <img src="${escapeHtml(property.imageUrl)}" alt="${escapeHtml(property.name)}">
        <div class="property-ranking-media-top">
          ${leadScorePill}
          <div class="property-pill-stack">
            ${clupStatusPill(compliance)}
            ${statusPill(property.status)}
          </div>
        </div>
        <div class="property-ranking-media-bottom">
          <span class="property-ranking-location">${icon("map")}${escapeHtml(property.city || "San Fernando, La Union")} / ${escapeHtml(property.barangay || "Unassigned")}</span>
        </div>
      </div>
      <div class="property-ranking-body">
        <div class="property-ranking-head">
          <div class="property-ranking-chip-row">
            ${headerChips.map((label, index) => `<span class="property-ranking-chip ${index === 0 ? "is-strong" : ""}">${escapeHtml(label)}</span>`).join("")}
          </div>
          <div class="property-ranking-title-row">
            <h3 class="property-ranking-title">${escapeHtml(property.name)}</h3>
            <strong class="property-ranking-price">${escapeHtml(moneyShort(property.price))}</strong>
          </div>
          <p class="property-ranking-thesis">${escapeHtml(truncate(thesis, 110))}</p>
        </div>

        <div class="property-ranking-stat-rail">
          <article class="property-ranking-stat">
            <span>Land Area</span>
            <strong>${escapeHtml(property.area || "--")} ha</strong>
          </article>
          <article class="property-ranking-stat">
            <span>Readiness</span>
            <strong>${docsPct}% docs</strong>
          </article>
          <article class="property-ranking-stat">
            <span>CLUP Suitability</span>
            <strong>${escapeHtml(clupScoreLabel(compliance))}</strong>
          </article>
        </div>

        <div class="property-ranking-footer">
          <div class="property-ranking-signal-row">
            ${footerChips.map((label) => `<span class="property-ranking-signal-chip">${escapeHtml(label)}</span>`).join("")}
          </div>
          <div class="property-ranking-actions">
            <a href="${propertyHref(property.id)}" class="btn-shell btn-shell-primary">${icon("arrow")}View Details</a>
            <button
              type="button"
              class="btn-shell btn-shell-secondary property-ranking-icon-action ${isCompared ? "is-active" : ""}"
              data-compare-toggle="${property.id}"
              aria-label="${escapeHtml(isCompared ? "Remove from compare" : "Add to compare")} ${escapeHtml(property.name)}"
              title="${escapeHtml(isCompared ? "Compared" : "Compare")}"
            >
              ${icon("compare")}
            </button>
            <button
              type="button"
              class="btn-shell btn-shell-ghost property-ranking-icon-action ${isSaved ? "is-active" : ""}"
              data-favorite-toggle="${property.id}"
              aria-label="${escapeHtml(favoriteActionLabel(isSaved))} ${escapeHtml(property.name)}"
              title="${escapeHtml(favoriteActionLabel(isSaved))}"
            >
              ${icon("save")}
            </button>
            ${showManage ? `<a href="${escapeHtml(manageHref)}" class="btn-shell btn-shell-ghost property-ranking-manage">${icon("arrow")}Manage</a>` : ""}
          </div>
        </div>
      </div>
    </article>
  `;
}

function compactPropertyCardMarkup(property, options = {}) {
  const compareIds = options.compareIds || [];
  const favoriteIds = options.favoriteIds || [];
  const showManage = options.showManage || false;
  const manageHref = options.manageHref || "";
  const lensKey = options.lensKey || null;
  const activeLens = lensKey ? getInvestmentLensConfig(lensKey) : null;
  const isActive = Boolean(options.isActive);
  const isSaved = favoriteIds.includes(property.id);
  const isCompared = compareIds.includes(property.id);
  const thesis = activeLens && property.lensResult
    ? property.lensResult.thesisShort || property.lensResult.thesis || `${property.name} is being read through the ${activeLens.label} lens.`
    : property.description || propertyStory(property);
  const leadScoreValue = activeLens && property.lensResult
    ? Math.round(Number(property.lensResult.score || property.lensScore || 0))
    : Math.round(Number(property.opportunityScore || 0));
  const verificationLabel = VERIFICATION_LABELS[String(property.listingVerificationStatus || "unverified").toLowerCase()] || titleCase(property.listingVerificationStatus || "Unverified");
  const compliance = evaluateClup(property, lensKey ? clupUseForLens(lensKey) : property.type);

  return `
    <article
      class="property-card property-card-compact ${isActive ? "is-active" : ""}"
      data-explorer-select="${property.id}"
      tabindex="0"
      role="button"
      aria-pressed="${isActive ? "true" : "false"}"
      aria-label="Select ${escapeHtml(property.name)} on the map"
    >
      <div class="property-card-compact-media">
        <img src="${escapeHtml(property.imageUrl)}" alt="${escapeHtml(property.name)}">
        <div class="property-card-compact-topline">
          <span class="property-card-compact-hero-chip">${escapeHtml(typeLabel(property.type))}</span>
          ${clupStatusPill(compliance)}
        </div>
        <button
          type="button"
          class="property-card-compact-bookmark ${isSaved ? "is-active" : ""}"
          data-favorite-toggle="${property.id}"
          aria-label="${escapeHtml(favoriteActionLabel(isSaved))} ${escapeHtml(property.name)}"
          title="${escapeHtml(favoriteActionLabel(isSaved))}"
        >
          ${icon("save")}
        </button>
        <div class="property-card-compact-overlay">
          <div class="property-card-compact-summary">
            <div class="property-card-compact-heading">
              <h3>${escapeHtml(property.name)}</h3>
              <p>${escapeHtml(property.city || "San Fernando, La Union")} / ${escapeHtml(property.barangay || "Unassigned")}</p>
            </div>
            <div class="property-card-compact-price">
              <span class="property-card-compact-scoreline">Score ${leadScoreValue}</span>
              <strong>${escapeHtml(moneyShort(property.price))}</strong>
              <span>asking price</span>
            </div>
          </div>
        </div>
      </div>
      <div class="property-card-compact-body">
        <div class="property-card-compact-meta">
          <span>${escapeHtml(corridorLabel(property.corridor))}</span>
          <span>${escapeHtml(property.area || "--")} ha</span>
          <span>${escapeHtml(verificationLabel)}</span>
        </div>
        <p class="property-card-compact-support">${escapeHtml(truncate(thesis, 66))}</p>
        <div class="property-card-compact-actions">
          <button type="button" class="property-card-compact-link is-secondary ${isCompared ? "is-active" : ""}" data-compare-toggle="${property.id}">${isCompared ? "Compared" : "Compare"}</button>
          <a href="${propertyHref(property.id)}" class="property-card-compact-link is-primary">View Details</a>
          ${showManage ? `<a href="${escapeHtml(manageHref)}" class="property-card-compact-link is-secondary">Manage</a>` : ""}
        </div>
      </div>
    </article>
  `;
}

function explorerLensRibbonMarkup(activeLensKey, options = {}) {
  const activeLens = getInvestmentLensConfig(activeLensKey);
  const visibleCount = Math.max(0, Number(options.visibleCount || 0));
  const mapLive = Boolean(options.mapLive);
  const locationLive = Boolean(options.locationLive);
  const queueCount = Math.max(0, Number(options.queueCount || 0));
  const basePath = window.SFC_APP_CONFIG?.basePath || "";

  return `
    <header class="explorer-terminal-ribbon explorer-market-ribbon explorer-market-ribbon-inline">
      <div class="explorer-market-ribbon-copy">
        <div class="panel-kicker">Property Explorer</div>
        <h1>Find the right property faster.</h1>
        <p>${escapeHtml(activeLens.shortLabel || activeLens.label)} lens active.</p>
      </div>
      <div class="explorer-market-ribbon-strip">
        <div class="explorer-market-inline-meta">
          <span class="explorer-market-summary-pill is-icon" data-tooltip="${visibleCount} properties in view" title="${visibleCount} properties in view">
            ${icon("ranking")}
            <strong>${visibleCount}</strong>
          </span>
          <span class="explorer-market-summary-pill is-icon" data-tooltip="${escapeHtml(locationLive ? "Place search enabled" : "Local search fallback")}" title="${escapeHtml(locationLive ? "Place search enabled" : "Local search fallback")}">
            ${icon("search")}
          </span>
          <span class="explorer-market-summary-pill is-icon" data-tooltip="${escapeHtml(mapLive ? "Live map" : "Fallback map")}" title="${escapeHtml(mapLive ? "Live map" : "Fallback map")}">
            ${icon("map")}
          </span>
          <span class="explorer-market-status-chip is-icon" data-tooltip="${queueCount} saved or compared" title="${queueCount} saved or compared">
            ${icon("compare")}
            <strong>${queueCount}</strong>
          </span>
        </div>
        <div class="explorer-market-chip-row" role="group" aria-label="Investment lenses">
          ${INVESTMENT_LENSES.map((lens) => `
            <button
              type="button"
              class="explorer-lens-chip explorer-lens-chip-icononly ${lens.key === activeLens.key ? "is-active" : ""}"
              data-explorer-lens="${escapeHtml(lens.key)}"
              data-tooltip="${escapeHtml(lens.label)}"
              title="${escapeHtml(lens.label)}"
              aria-label="${escapeHtml(lens.label)}"
              aria-pressed="${lens.key === activeLens.key ? "true" : "false"}"
            >
              ${investmentLensIconMarkup(lens.key)}
            </button>
          `).join("")}
        </div>
        <div class="explorer-ribbon-actions">
          <a href="${basePath}/property-ranking.php" class="btn-shell btn-shell-secondary explorer-ribbon-link">Open Rankings</a>
          <a href="${basePath}/compare-decision.php" class="btn-shell btn-shell-primary explorer-ribbon-link">Open Compare</a>
        </div>
      </div>
    </header>
  `;
}

function explorerMapHudMarkup(active, activeLens, visibleCount) {
  return `
    <div class="explorer-map-hud explorer-map-hud-clean">
      <div class="explorer-map-hud-copy">
        <span>${visibleCount} properties mapped</span>
        <strong>${escapeHtml(active ? active.name : `${activeLens.shortLabel || activeLens.label} view`)}</strong>
      </div>
    </div>
  `;
}

function explorerMapPreviewMarkup(active, activeLens, searchResult = null) {
  const useSearchTarget = searchResult && !Number(searchResult.propertyId || 0);
  if (useSearchTarget) {
    return `
      <aside class="explorer-map-preview is-search-target" aria-hidden="true">
        <div class="explorer-map-preview-top">
          <span class="explorer-map-preview-eyebrow">Search target</span>
          <span class="explorer-map-preview-badge">${escapeHtml(activeLens.shortLabel || activeLens.label)}</span>
        </div>
        <div class="explorer-map-preview-main">
          <div class="explorer-map-preview-copy">
            <strong>${escapeHtml(searchResult.label || "Map result")}</strong>
            <p>${escapeHtml(searchResult.subtitle || "Live place preview")}</p>
          </div>
        </div>
      </aside>
    `;
  }

  if (!active) return "";
  const compliance = evaluateClup(active, clupUseForLens(activeLens.key));

  return `
    <aside class="explorer-map-preview" aria-hidden="true">
      <div class="explorer-map-preview-top">
        <span class="explorer-map-preview-eyebrow">${escapeHtml(active.barangay || "San Fernando")}</span>
        ${clupStatusPill(compliance)}
      </div>
      <div class="explorer-map-preview-main">
        <div class="explorer-map-preview-copy">
          <strong>${escapeHtml(active.name)}</strong>
          <p>${escapeHtml(typeLabel(active.type))} | ${escapeHtml(corridorLabel(active.corridor))}</p>
        </div>
        <div class="explorer-map-preview-price">
          <strong>${escapeHtml(moneyShort(active.price))}</strong>
          <span>CLUP ${escapeHtml(clupScoreLabel(compliance, false))} · ${escapeHtml(active.area || "--")} ha</span>
        </div>
      </div>
    </aside>
  `;
}

function explorerSelectionCardMarkup(property, options = {}) {
  if (!property) {
    return `
      <article class="explorer-selection-card is-empty">
        <div class="panel-kicker">Selected Property</div>
        <h2>Pick a property to preview it here.</h2>
        <p>Use the list to load a cleaner summary before opening full details.</p>
      </article>
    `;
  }

  const activeLens = options.activeLens || getInvestmentLensConfig(getActiveInvestmentLensKey());
  const compareIds = options.compareIds || [];
  const favoriteIds = options.favoriteIds || [];
  const primaryLabels = propertyPrimaryLabels(property);
  const thesis = property.lensResult?.thesisShort || property.lensResult?.thesis || property.description || propertyStory(property);

  return `
    <article class="explorer-selection-card">
      <div class="explorer-selection-media">
        <img src="${escapeHtml(property.imageUrl)}" alt="${escapeHtml(property.name)}">
      </div>
      <div class="explorer-selection-body">
        <div class="explorer-selection-head">
          <div>
            <div class="panel-kicker">Selected Property</div>
            <h2>${escapeHtml(property.name)}</h2>
            <p>${escapeHtml(property.city || "San Fernando, La Union")} / ${escapeHtml(property.barangay || "Unassigned")}</p>
          </div>
          <div class="explorer-selection-score">
            ${property.lensResult ? investmentLensScorePill(property.lensResult) : scorePill(property.opportunityScore)}
          </div>
        </div>

        <div class="explorer-selection-meta">
          <strong>${escapeHtml(moneyShort(property.price))}</strong>
          <span>${escapeHtml(corridorLabel(property.corridor))}</span>
          <span>${escapeHtml(typeLabel(property.type))}</span>
          <span>${escapeHtml(property.area || "--")} ha</span>
        </div>

        <div class="explorer-selection-tags">
          ${primaryLabels.slice(0, 2).map((label) => `<span class="explorer-selection-tag">${escapeHtml(label)}</span>`).join("")}
          ${statusPill(property.status)}
          ${verificationPill(property.listingVerificationStatus)}
        </div>

        <p class="explorer-selection-story">${escapeHtml(truncate(thesis, 132))}</p>

        <div class="explorer-selection-actions">
          <a href="${propertyHref(property.id)}" class="explorer-selection-button is-primary">View Details</a>
          <button type="button" class="explorer-selection-button" data-compare-toggle="${property.id}">${compareIds.includes(property.id) ? "Compared" : "Compare"}</button>
          <button type="button" class="explorer-selection-button" data-favorite-toggle="${property.id}">${favoriteActionLabel(favoriteIds.includes(property.id))}</button>
        </div>

        <a href="${votingHref(property.id)}" class="explorer-selection-link">Open the community demand context</a>
      </div>
    </article>
  `;
}

function explorerIntelDrawerMarkup(options = {}) {
  const property = options.property || null;
  const open = Boolean(options.open);
  const activeLens = options.activeLens || getInvestmentLensConfig(getActiveInvestmentLensKey());
  const scoreModel = options.scoreModel || null;
  const compareIds = options.compareIds || [];
  const favoriteIds = options.favoriteIds || [];

  if (!property) {
    return `
      <aside class="explorer-intel-drawer ${open ? "drawer-open" : ""}" id="explorerIntelDrawer" aria-hidden="${open ? "false" : "true"}">
        <div class="explorer-intel-drawer-scroll" data-explorer-drawer-scroll>
          <div class="explorer-intel-drawer-head">
            <div>
              <div class="panel-kicker">Intel Drawer</div>
              <h2>No property selected</h2>
            </div>
            <button type="button" class="explorer-drawer-close" data-explorer-drawer-close aria-label="Close intelligence panel">X</button>
          </div>
          <div class="explorer-intel-empty">
            Pick a property card or map node to open the current property intelligence stack.
          </div>
        </div>
      </aside>
    `;
  }

  const readiness = property.investmentReadiness || null;
  const pillars = Object.values(readiness?.pillars || {}).slice(0, 5);
  const thesis = property.lensResult?.thesis || property.description || propertyStory(property);
  const lensMetrics = (property.lensResult?.metrics || []).slice(0, 4);
  const fieldAudit = property.latestFieldAudit && typeof property.latestFieldAudit === "object"
    ? Object.entries(property.latestFieldAudit)
      .filter(([key, value]) => key !== "notes" && value !== null && value !== "")
      .slice(0, 4)
    : [];
  const fieldNote = String(property.latestFieldAudit?.notes || "").trim();
  const lastConfirmed = property.lastConfirmedAvailableAt || property.updatedAt;

  return `
    <aside class="explorer-intel-drawer ${open ? "drawer-open" : ""}" id="explorerIntelDrawer" aria-hidden="${open ? "false" : "true"}">
      <div class="explorer-intel-drawer-scroll" data-explorer-drawer-scroll>
        <div class="explorer-intel-drawer-head">
          <div>
            <div class="panel-kicker">Selected Property</div>
            <h2>${escapeHtml(property.name)}</h2>
            <p>${escapeHtml(property.city || "San Fernando, La Union")} / ${escapeHtml(property.barangay || "Unassigned")}</p>
          </div>
          <button type="button" class="explorer-drawer-close" data-explorer-drawer-close aria-label="Close intelligence panel">X</button>
        </div>

        <div class="explorer-intel-media">
          <img src="${escapeHtml(property.imageUrl)}" alt="${escapeHtml(property.name)}">
        </div>

        <div class="explorer-intel-chip-row">
          ${property.lensResult ? investmentLensScorePill(property.lensResult) : scorePill(property.opportunityScore)}
          ${statusPill(property.status)}
          ${approvalStatePill(property.approvalState)}
          ${verificationPill(property.listingVerificationStatus)}
          ${groundTruthPill(property)}
        </div>

        <div class="explorer-intel-facts">
          <div>
            <span>Guide Price</span>
            <strong>${escapeHtml(moneyShort(property.price))}</strong>
          </div>
          <div>
            <span>Land Area</span>
            <strong>${escapeHtml(property.area || "--")} ha</strong>
          </div>
          <div>
            <span>Corridor</span>
            <strong>${escapeHtml(corridorLabel(property.corridor))}</strong>
          </div>
          <div>
            <span>IRIE</span>
            <strong>${Math.round(Number(readiness?.totalScore || 0)) || "--"}</strong>
          </div>
        </div>

        <section class="explorer-intel-section">
          <div class="explorer-intel-section-head">
            <span>Selected Property</span>
            <strong>${escapeHtml(activeLens.label)}</strong>
          </div>
          <p class="explorer-intel-copy">${escapeHtml(truncate(thesis, 220))}</p>
        </section>

        ${scoreModel ? `
          <section class="explorer-intel-section">
            <div class="explorer-intel-section-head">
              <span>IAI Breakdown</span>
              <strong>${Math.round(Number(scoreModel.finalScore || 0))}</strong>
            </div>
            <p class="explorer-intel-copy">${escapeHtml(scoreModel.summary || `${activeLens.label} score model active.`)}</p>
            <div class="command-score-breakdown explorer-score-breakdown">
              ${(scoreModel.components || []).map((component) => `
                <article class="command-score-card ${commandToneClass(component.tone)}">
                  <span>${escapeHtml(component.label)}</span>
                  <strong>${signedMetric(Math.round(Number(component.value || 0)))}</strong>
                  <small>${escapeHtml(component.note || "")}</small>
                </article>
              `).join("")}
            </div>
          </section>
        ` : ""}

        ${lensMetrics.length ? `
          <section class="explorer-intel-section">
            <div class="explorer-intel-section-head">
              <span>Lens Signals</span>
              <strong>${Math.round(Number(property.lensResult?.score || 0))}</strong>
            </div>
            <div class="explorer-lens-metric-list">
              ${lensMetrics.map((metric) => `
                <article class="explorer-lens-metric-row">
                  <div class="explorer-lens-metric-head">
                    <strong>${escapeHtml(metric.label)}</strong>
                    <span>${Math.round(Number(metric.score || 0))}%</span>
                  </div>
                  <div class="explorer-lens-metric-bar">
                    <span style="width:${Math.max(0, Math.min(100, Math.round(Number(metric.score || 0))))}%"></span>
                  </div>
                  <small>${escapeHtml(metric.summary || metric.displayValue || "Weighted into the active lens.")}</small>
                </article>
              `).join("")}
            </div>
          </section>
        ` : ""}

        <section class="explorer-intel-section">
          <div class="explorer-intel-section-head">
            <span>Readiness Stack</span>
            <strong>${Math.round(Number(readiness?.missingDataCount || 0))} missing</strong>
          </div>
          <div class="explorer-pillar-list">
            ${pillars.length ? pillars.map((pillar) => `
              <article class="explorer-pillar-row ${readinessToneClass(pillar.status)}">
                <div>
                  <strong>${escapeHtml(pillar.label)}</strong>
                  <small>${escapeHtml(pillar.summary || `${pillar.label} signal ready.`)}</small>
                </div>
                <span>${Math.round(Number(pillar.score || 0))}%</span>
              </article>
            `).join("") : `<div class="explorer-intel-empty">Readiness scoring is not available for this property yet.</div>`}
          </div>
        </section>

        <section class="explorer-intel-section">
          <div class="explorer-intel-section-head">
            <span>Field + Diligence</span>
            <strong>${Math.round(Number(property.dueDiligencePct || 0))}% DD</strong>
          </div>
          <div class="explorer-intel-meta-grid">
            <div>
              <span>Documents</span>
              <strong>${Math.round(Number(property.documentCompletenessPct || 0))}% complete</strong>
            </div>
            <div>
              <span>Open Requests</span>
              <strong>${Number(property.openDocumentRequestCount || 0)}</strong>
            </div>
            <div>
              <span>Ground Truth</span>
              <strong>${Number(property.groundTruthVisitCount || 0)} visit${Number(property.groundTruthVisitCount || 0) === 1 ? "" : "s"}</strong>
            </div>
            <div>
              <span>Freshness</span>
              <strong>${escapeHtml(formatFreshness(lastConfirmed, "Awaiting confirmation"))}</strong>
            </div>
          </div>
          ${fieldAudit.length ? `
            <div class="explorer-field-audit-list">
              ${fieldAudit.map(([key, value]) => `
                <article>
                  <span>${escapeHtml(titleCase(key))}</span>
                  <strong>${escapeHtml(String(value))}</strong>
                </article>
              `).join("")}
            </div>
          ` : ""}
          <p class="explorer-intel-copy ${fieldNote ? "" : "is-muted"}">${escapeHtml(fieldNote || "No field audit notes recorded yet.")}</p>
        </section>

        <div class="explorer-intel-actions">
          <a href="${propertyHref(property.id)}" class="btn-shell btn-shell-primary">View Details</a>
          <button type="button" class="btn-shell btn-shell-secondary" data-compare-toggle="${property.id}">${compareIds.includes(property.id) ? "Compared" : "Compare"}</button>
          <button type="button" class="btn-shell btn-shell-ghost" data-favorite-toggle="${property.id}">${favoriteActionLabel(favoriteIds.includes(property.id))}</button>
        </div>
      </div>
    </aside>
  `;
}

function leaderboardRows(properties, limit = 5, options = {}) {
  const lensKey = options.lensKey || null;
  return properties.slice(0, limit).map((property, index) => `
    <article class="leader-row">
      <div class="rank-badge">#${index + 1}</div>
      <div class="rank-copy">
        <h3>${escapeHtml(property.name)}</h3>
        <div class="rank-meta">
          <span>${escapeHtml(typeLabel(property.type))}</span>
          <span>${escapeHtml(corridorLabel(property.corridor))}</span>
          <span>${property.voteTotal} votes</span>
          ${property.groundTruthVisitCount ? `<span>${property.groundTruthAdjustmentPct > 0 ? "+" : ""}${Math.round(Number(property.groundTruthAdjustmentPct || 0))}% ground truth</span>` : ""}
        </div>
      </div>
      ${lensKey && property.lensResult ? investmentLensScorePill(property.lensResult) : scorePill(property.opportunityScore)}
    </article>
  `).join("");
}

function bindCollectionActions(root, rerender) {
  root._collectionRerender = rerender;
  if (root.dataset.collectionBound === "true") return;
  root.dataset.collectionBound = "true";

  root.addEventListener("click", (event) => {
    const compareButton = event.target.closest("[data-compare-toggle]");
    if (compareButton) {
      saveCompareIds(toggleId(getCompareIds(), compareButton.dataset.compareToggle, 3));
      root._collectionRerender?.();
      return;
    }

    const favoriteButton = event.target.closest("[data-favorite-toggle]");
    if (favoriteButton) {
      toggleFavoriteId(favoriteButton.dataset.favoriteToggle).then(() => {
        root._collectionRerender?.();
      });
    }
  });
}

function emptyState(title, description, actionLabel = "", actionHref = "") {
  return `
    <article class="empty-state">
      <div class="eyebrow">Nothing here yet</div>
      <h2>${escapeHtml(title)}</h2>
      <p>${escapeHtml(description)}</p>
      ${actionLabel && actionHref ? `<a href="${escapeHtml(actionHref)}" class="btn-shell btn-shell-primary" style="margin-top:18px;">${escapeHtml(actionLabel)}</a>` : ""}
    </article>
  `;
}

function initCityBrief() {
  const modal = document.getElementById("cityBriefModal");
  const triggers = Array.from(document.querySelectorAll("[data-city-brief-trigger]"));
  if (!modal || !triggers.length) return;

  const dismissButtons = Array.from(modal.querySelectorAll("[data-city-brief-dismiss]"));
  const motionQuery = window.matchMedia("(prefers-reduced-motion: reduce)");
  let lastFocused = null;
  let closeTimer = 0;
  let mediaHydrated = false;

  const hydrateMedia = () => {
    if (mediaHydrated) return;
    mediaHydrated = true;

    modal.querySelectorAll("[data-city-image-src]").forEach((node) => {
      if (!(node instanceof HTMLImageElement)) return;
      const src = node.dataset.cityImageSrc?.trim();
      if (!src || node.getAttribute("src")) return;
      node.setAttribute("src", src);
    });
  };

  const finishClose = () => {
    closeTimer = 0;
    modal.hidden = true;
    lastFocused?.focus?.();
  };

  const openModal = () => {
    if (closeTimer) {
      window.clearTimeout(closeTimer);
      closeTimer = 0;
    }

    hydrateMedia();
    lastFocused = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    modal.hidden = false;
    document.body.classList.add("has-city-brief-open");
    window.requestAnimationFrame(() => {
      modal.classList.add("is-open");
      dismissButtons[0]?.focus();
    });
  };

  const closeModal = () => {
    modal.classList.remove("is-open");
    document.body.classList.remove("has-city-brief-open");

    if (motionQuery.matches) {
      finishClose();
      return;
    }

    closeTimer = window.setTimeout(finishClose, 320);
  };

  triggers.forEach((trigger) => {
    trigger.addEventListener("click", (event) => {
      event.preventDefault();
      openModal();
    });
  });

  dismissButtons.forEach((button) => {
    button.addEventListener("click", closeModal);
  });

  modal.addEventListener("click", (event) => {
    if (event.target === modal) {
      closeModal();
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && !modal.hidden) {
      closeModal();
    }
  });
}

function parsePropertyParam() {
  const params = new URLSearchParams(window.location.search);
  const propertyId = Number(params.get("property") || 0);
  return Number.isFinite(propertyId) && propertyId > 0 ? propertyId : null;
}

function initPortalMenu() {
  const menus = Array.from(document.querySelectorAll("[data-sfc-menu]"));
  if (!menus.length) return;

  const closeMenu = (menu) => {
    menu.classList.remove("is-open");
    menu.querySelector("[data-sfc-menu-toggle]")?.setAttribute("aria-expanded", "false");
  };

  const closeAll = (exceptMenu = null) => {
    menus.forEach((menu) => {
      if (menu !== exceptMenu) {
        closeMenu(menu);
      }
    });
  };

  menus.forEach((menu) => {
    const trigger = menu.querySelector("[data-sfc-menu-toggle]");
    if (!trigger) return;

    trigger.addEventListener("click", (event) => {
      event.stopPropagation();
      const willOpen = !menu.classList.contains("is-open");
      closeAll(menu);
      menu.classList.toggle("is-open", willOpen);
      trigger.setAttribute("aria-expanded", willOpen ? "true" : "false");
    });
  });

  document.addEventListener("click", (event) => {
    menus.forEach((menu) => {
      if (!menu.contains(event.target)) {
        closeMenu(menu);
      }
    });
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      closeAll();
    }
  });
}

const CITY_GRID = {
  "poro-point": {
    key: "poro-point",
    label: "Poro Point",
    descriptor: "Port + logistics corridor",
    xPercent: 82.2,
    yPercent: 29.7,
    radius: 24,
  },
  "city-center": {
    key: "city-center",
    label: "City Center",
    descriptor: "Retail + civic gravity",
    xPercent: 62,
    yPercent: 42.8,
    radius: 21,
  },
  "civic-belt": {
    key: "civic-belt",
    label: "Civic Belt",
    descriptor: "Campus + health support",
    xPercent: 48,
    yPercent: 55.3,
    radius: 20,
  },
};

const HERO_FOCUS_DEMAND_KEYWORDS = {
  logistics: ["warehouse", "logistics", "hardware", "construction", "office", "bpo"],
  university: ["printing", "cafe", "restaurant", "grocery", "mini mart", "7/11", "office", "bpo"],
  hospital: ["pharmacy", "clinic", "diagnostics"],
  commercial_center: ["7/11", "cafe", "restaurant", "grocery", "mini mart"],
};

const LANDING_HERO_FOCUSES = {
  logistics: {
    key: "logistics",
    railLabel: "Logistics",
    accent: "#6366f1",
    accentRgb: "99, 102, 241",
    summary: "Throughput, corridor fit, and land scale rise to the front of the city frame.",
    tickerMeta: "Freight",
    defaultNode: "poro-point",
    nodes: ["poro-point"],
  },
  university: {
    key: "university",
    railLabel: "University",
    accent: "#10b981",
    accentRgb: "16, 185, 129",
    summary: "Campus-scale parcels, housing support demand, and expansion bands become the lead signal.",
    tickerMeta: "Campus",
    defaultNode: "civic-belt",
    nodes: ["city-center", "civic-belt"],
  },
  hospital: {
    key: "hospital",
    railLabel: "Hospital",
    accent: "#ef4444",
    accentRgb: "239, 68, 68",
    summary: "Utilities, compliance, and civic access sharpen the search for health-support infrastructure.",
    tickerMeta: "Health",
    defaultNode: "civic-belt",
    nodes: ["civic-belt", "city-center"],
  },
  commercial_center: {
    key: "commercial_center",
    railLabel: "Retail",
    accent: "#f59e0b",
    accentRgb: "245, 158, 11",
    summary: "Foot traffic, service demand, and downtown spillover take over the intelligence layer.",
    tickerMeta: "Retail",
    defaultNode: "city-center",
    nodes: ["city-center"],
  },
};

function landingHeroFocusConfig(focusKey) {
  return LANDING_HERO_FOCUSES[String(focusKey || "").toLowerCase()] || LANDING_HERO_FOCUSES.logistics;
}

function cityGridNodeConfig(nodeKey, fallbackKey = "city-center") {
  return CITY_GRID[String(nodeKey || "").toLowerCase()]
    || CITY_GRID[String(fallbackKey || "").toLowerCase()]
    || CITY_GRID["city-center"];
}

function setHeroGlowPercent(hero, xPercent, yPercent) {
  if (!hero) return;
  const boundedX = Math.max(8, Math.min(92, Number(xPercent || 0)));
  const boundedY = Math.max(8, Math.min(88, Number(yPercent || 0)));
  const canvas = hero.querySelector("#hero-canvas");
  const rect = canvas?.getBoundingClientRect?.() || hero.getBoundingClientRect();
  const width = Math.max(rect.width || 1, 1);
  const height = Math.max(Math.min(rect.height || 720, 720), 1);
  hero.style.setProperty("--glow-x", `${boundedX}%`);
  hero.style.setProperty("--glow-y", `${boundedY}%`);
  hero.style.setProperty("--mouse-glow-x", `${((boundedX / 100) * width).toFixed(2)}px`);
  hero.style.setProperty("--mouse-glow-y", `${((boundedY / 100) * height).toFixed(2)}px`);
}

function wakeHeroStage(hero, position = null) {
  if (!hero) return;
  if (position && Number.isFinite(Number(position.xPercent)) && Number.isFinite(Number(position.yPercent))) {
    setHeroGlowPercent(hero, Number(position.xPercent), Number(position.yPercent));
  }
  hero.classList.add("is-awake");
  hero.classList.add("is-interacting");
  window.clearTimeout(hero._heroGlowTimer);
  hero._heroGlowTimer = window.setTimeout(() => {
    hero.classList.remove("is-interacting");
  }, 1200);
}

function animateNumericValue(node, target, options = {}) {
  if (!node) return;
  const duration = Math.max(300, Number(options.duration || 1100));
  const decimals = Math.max(0, Number(options.decimals || 0));
  const suffix = options.suffix || "";
  const previous = Number(node.dataset.currentValue || 0);
  const goal = Number(target || 0);
  const start = Number.isFinite(previous) ? previous : 0;
  const startAt = performance.now();

  if (node._counterFrame) {
    cancelAnimationFrame(node._counterFrame);
  }

  const render = (value) => {
    node.dataset.currentValue = String(value);
    node.textContent = `${value.toFixed(decimals)}${suffix}`;
  };

  const step = (timestamp) => {
    const progress = Math.min(1, (timestamp - startAt) / duration);
    const eased = 1 - ((1 - progress) ** 3);
    const value = start + ((goal - start) * eased);
    render(value);
    if (progress < 1) {
      node._counterFrame = requestAnimationFrame(step);
    }
  };

  requestAnimationFrame(step);
}

function cityGridBounds(properties) {
  const latitudes = properties.map((property) => Number(property?.lat || 0)).filter((value) => Number.isFinite(value));
  const longitudes = properties.map((property) => Number(property?.lng || 0)).filter((value) => Number.isFinite(value));
  if (!latitudes.length || !longitudes.length) {
    return {
      minLat: 0,
      maxLat: 1,
      minLng: 0,
      maxLng: 1,
      latRange: 1,
      lngRange: 1,
    };
  }

  const minLat = Math.min(...latitudes);
  const maxLat = Math.max(...latitudes);
  const minLng = Math.min(...longitudes);
  const maxLng = Math.max(...longitudes);

  return {
    minLat,
    maxLat,
    minLng,
    maxLng,
    latRange: Math.max(0.0001, maxLat - minLat),
    lngRange: Math.max(0.0001, maxLng - minLng),
  };
}

function propertyCityPoint(property, bounds) {
  const lat = Number(property?.lat ?? bounds.minLat);
  const lng = Number(property?.lng ?? bounds.minLng);
  return {
    xPercent: 12 + (((lng - bounds.minLng) / bounds.lngRange) * 76),
    yPercent: 16 + (((bounds.maxLat - lat) / bounds.latRange) * 68),
  };
}

function cityNodeDistance(point, node) {
  return Math.hypot(Number(point?.xPercent || 0) - Number(node?.xPercent || 0), Number(point?.yPercent || 0) - Number(node?.yPercent || 0));
}

function demandMatchesFocus(label, focusKey) {
  const normalizedLabel = String(label || "").toLowerCase();
  const keywords = HERO_FOCUS_DEMAND_KEYWORDS[focusKey] || [];
  return keywords.some((keyword) => normalizedLabel.includes(keyword));
}

function filteredVoteEntriesForFocus(votes, focusKey) {
  return sortedVoteEntries(votes).filter(([label]) => demandMatchesFocus(label, focusKey));
}

function aggregateFilteredVotes(properties, focusKey) {
  const aggregate = {};
  properties.forEach((property) => {
    filteredVoteEntriesForFocus(property?.votes || {}, focusKey).forEach(([label, count]) => {
      aggregate[label] = (aggregate[label] || 0) + Number(count || 0);
    });
  });
  return sortedVoteEntries(aggregate);
}

function landingSentimentItems(focus, node, rankedProperties) {
  const labelPrefix = {
    university: "Campus pulse",
    hospital: "Clinical pulse",
    commercial_center: "Retail pulse",
    logistics: "Freight pulse",
  }[focus.key] || "Market pulse";

  const hotspotItems = rankedProperties
    .map((property) => {
      const [label, count] = filteredVoteEntriesForFocus(property?.votes || {}, focus.key)[0] || [];
      if (!label || Number(count || 0) < 1) return null;
      return `${labelPrefix}: ${voteLabel(label)} around ${property.barangay || node.label} | ${count} vote${Number(count) === 1 ? "" : "s"}`;
    })
    .filter(Boolean)
    .slice(0, 5);

  const aggregateItems = aggregateFilteredVotes(rankedProperties, focus.key)
    .slice(0, 4)
    .map(([label, count]) => `${node.label}: ${voteLabel(label)} signal at ${count} vote${Number(count) === 1 ? "" : "s"}`);

  const items = [...hotspotItems, ...aggregateItems];
  return items.length ? items : [`${labelPrefix}: ${focus.railLabel} lens is live, but ${node.label} has no matching vote pulse yet.`];
}

function heroSvgPoint(point) {
  return {
    x: (Number(point?.xPercent || 0) / 100) * 1000,
    y: (Number(point?.yPercent || 0) / 100) * 720,
  };
}

function landingOpportunityBlocker(property) {
  const docsPct = Math.round(Number(property?.documentCompletenessPct || 0));
  const verification = String(property?.listingVerificationStatus || "unverified").toLowerCase();

  if (docsPct < 40) {
    return `Dossier depth is only ${docsPct}% complete.`;
  }
  if (verification !== "verified") {
    return `${VERIFICATION_LABELS[verification] || titleCase(verification)} listing still needs stronger validation.`;
  }
  if (Number(property?.groundTruthVisitCount || 0) < 1) {
    return "Ground truth visit has not been logged yet.";
  }
  if (Number(property?.voteTotal || 0) < 1) {
    return "Demand signal is still emerging around this parcel.";
  }

  return "No critical blocker is visible in the current corridor scan.";
}

function landingOpportunityTrustSignal(property) {
  const verification = String(property?.listingVerificationStatus || "unverified").toLowerCase();
  const docsPct = Math.round(Number(property?.documentCompletenessPct || 0));
  const groundTruthVisits = Number(property?.groundTruthVisitCount || 0);

  if (verification === "verified" && groundTruthVisits > 0) {
    return "Verified listing with field-audit evidence already on record.";
  }
  if (verification === "verified") {
    return "Verified listing with admin-ready documentation posture.";
  }
  if (docsPct >= 60) {
    return `Document readiness is already at ${docsPct}% and climbing.`;
  }

  return "Trust posture is active, but the dossier still needs reinforcement.";
}

function landingHeroStory(state) {
  if (!state?.leader) {
    return `${state?.node?.label || "The city"} is quiet for now and waiting for a stronger live lead.`;
  }

  const leader = state.leader;
  const topNeed = voteLabel(leader.topNeed || "");
  const hasMeaningfulNeed = topNeed && String(topNeed).trim().toLowerCase() !== "no demand yet";
  const needClause = hasMeaningfulNeed
    ? `${topNeed} demand is surfacing around ${state.node.label}`
    : `${state.focus.railLabel.toLowerCase()} demand is the strongest fit right now`;

  return `${leader.name} leads because ${needClause}.`;
}

function landingHeroProofItems(state) {
  return [
    { label: "Listings", value: String(state.activeListings || 0) },
    { label: "Verified", value: String(state.verifiedListings || 0) },
    { label: "Audits", value: String(state.fieldAuditCount || 0) },
    { label: "Ready", value: String(state.dossierReadyCount || 0) },
  ];
}

function landingFeaturedOpportunityMarkup(state) {
  if (!state?.leader) {
    return `<div class="hero-opportunity-loading">No leader is available for this node yet.</div>`;
  }

  const leader = state.leader;
  const leadScore = Math.round(Number(leader.lensScore || leader.opportunityScore || 0));
  const thesis = leader?.lensResult?.thesisShort || leader?.lensResult?.thesis || leader.description || propertyStory(leader);
  const verification = VERIFICATION_LABELS[String(leader.listingVerificationStatus || "unverified").toLowerCase()]
    || titleCase(leader.listingVerificationStatus || "Unverified");
  const locationLine = [leader.city || "San Fernando, La Union", state.node.label].filter(Boolean).join(" | ");
  const whyLead = leader?.lensResult?.thesisLead || thesis;
  const trustSignal = landingOpportunityTrustSignal(leader);

  return `
    <article class="hero-brief-card">
      <div class="hero-brief-topline">
        <span class="hero-brief-chip">${escapeHtml(state.focus.railLabel)} lead</span>
        <strong class="hero-brief-price">${escapeHtml(moneyShort(leader.price))}</strong>
      </div>
      <div class="hero-brief-copy">
        <span>${escapeHtml(locationLine)}</span>
        <strong>${escapeHtml(leader.name)}</strong>
        <p>${escapeHtml(truncate(thesis, 148))}</p>
      </div>
      <div class="hero-brief-meta">
        <span>${escapeHtml(leader.area || "--")} ha</span>
        <span>${leadScore} IAI</span>
        <span>${Number(leader.voteTotal || 0)} votes</span>
        <span>${escapeHtml(verification)}</span>
      </div>
      <div class="hero-brief-summary">
        <span>Why now</span>
        <strong>${escapeHtml(truncate(whyLead, 104))}</strong>
        <p>${escapeHtml(trustSignal)}</p>
      </div>
      <a href="${propertyHref(leader.id)}" class="hero-brief-link">Open Opportunity Brief</a>
    </article>
  `;
}

function landingHeroHeatMeshMarkup(state) {
  if (!state?.nodeUniverse?.length) return "";

  const nodePoint = heroSvgPoint(state.node);
  const leaderId = Number(state.leader?.id || 0);
  const meshItems = state.nodeUniverse.slice(0, 6).map((property, index) => {
    const point = heroSvgPoint(property.cityPoint);
    const voteFactor = Math.min(1, Number(property.voteTotal || 0) / 10);
    const scoreFactor = Math.min(1, Number(property.lensScore || property.opportunityScore || 0) / 100);
    const haloRadius = 12 + (scoreFactor * 28) + (voteFactor * 12);
    const coreRadius = Number(property.id) === leaderId ? 6.5 : 4.3;
    const curveLift = 26 + (index * 8);
    const midpointX = ((nodePoint.x + point.x) / 2).toFixed(2);
    const midpointY = (((nodePoint.y + point.y) / 2) - curveLift).toFixed(2);
    const flowPath = `M${nodePoint.x.toFixed(2)} ${nodePoint.y.toFixed(2)} Q${midpointX} ${midpointY} ${point.x.toFixed(2)} ${point.y.toFixed(2)}`;

    return `
      <g class="hero-heat-link ${Number(property.id) === leaderId ? "is-leader" : ""}" style="--mesh-delay:${(index * 0.18).toFixed(2)}s">
        <path class="hero-heat-flow" d="${flowPath}"></path>
        <circle class="hero-heat-halo" cx="${point.x.toFixed(2)}" cy="${point.y.toFixed(2)}" r="${haloRadius.toFixed(2)}"></circle>
        <circle class="hero-heat-core" cx="${point.x.toFixed(2)}" cy="${point.y.toFixed(2)}" r="${coreRadius.toFixed(2)}"></circle>
      </g>
    `;
  }).join("");

  return `
    <g class="hero-node-cloud">
      <circle class="hero-node-aura" cx="${nodePoint.x.toFixed(2)}" cy="${nodePoint.y.toFixed(2)}" r="${(44 + Math.min(26, Number(state.activeListings || 0) * 3)).toFixed(2)}"></circle>
    </g>
    ${meshItems}
  `;
}

function landingShortDate(value) {
  const parsed = new Date(value || "");
  if (Number.isNaN(parsed.getTime())) return "TBA";
  return parsed.toLocaleDateString(undefined, {
    month: "short",
    day: "numeric",
    year: "numeric",
  });
}

function landingRankingLeadMarkup(property, lensKey) {
  if (!property) return "";

  const activeLens = lensKey ? getInvestmentLensConfig(lensKey) : null;
  const leadScoreMarkup = activeLens && property.lensResult
    ? investmentLensScorePill(property.lensResult)
    : scorePill(property.opportunityScore);
  const thesis = activeLens && property.lensResult
    ? property.lensResult.thesisShort || property.lensResult.thesis || property.description || propertyStory(property)
    : property.description || propertyStory(property);
  const primaryLabels = propertyPrimaryLabels(property).slice(0, 3);
  const docsPct = Math.round(Number(property.documentCompletenessPct || 0));

  return `
    <article class="landing-ranking-lead">
      <div class="landing-ranking-lead-media">
        <img src="${escapeHtml(property.imageUrl)}" alt="${escapeHtml(property.name)}">
        <div class="landing-ranking-lead-top">
          ${leadScoreMarkup}
          ${statusPill(property.status)}
        </div>
        <div class="landing-ranking-lead-bottom">
          <span>Rank #1 opportunity</span>
          <strong>${escapeHtml(activeLens?.label || "Investment")} lens</strong>
        </div>
      </div>
      <div class="landing-ranking-lead-body">
        <div class="landing-ranking-head">
          <div class="landing-ranking-kicker">Current front-runner</div>
          <h3>${escapeHtml(property.name)}</h3>
          <p>${escapeHtml(truncate(thesis, 170))}</p>
        </div>
        <div class="landing-ranking-facts">
          <div>
            <span>Guide Price</span>
            <strong>${escapeHtml(moneyShort(property.price))}</strong>
          </div>
          <div>
            <span>Top Need</span>
            <strong>${escapeHtml(voteLabel(property.topNeed || "No demand yet"))}</strong>
          </div>
          <div>
            <span>Docs Ready</span>
            <strong>${docsPct}%</strong>
          </div>
          <div>
            <span>Land Area</span>
            <strong>${escapeHtml(property.area || "--")} ha</strong>
          </div>
        </div>
        <div class="landing-ranking-tags">
          ${primaryLabels.map((label) => `<span>${escapeHtml(label)}</span>`).join("")}
        </div>
        <div class="landing-ranking-actions">
          <a href="${propertyHref(property.id)}" class="btn-shell btn-shell-primary">${icon("arrow")}View Opportunity</a>
          <a href="${(window.SFC_APP_CONFIG?.basePath || "")}/property-explorer.php" class="btn-shell btn-shell-secondary">${icon("map")}Read the District</a>
        </div>
      </div>
    </article>
  `;
}

function landingRankingMiniMarkup(property, lensKey, rank = 2) {
  if (!property) return "";

  const activeLens = lensKey ? getInvestmentLensConfig(lensKey) : null;
  const leadScoreMarkup = activeLens && property.lensResult
    ? investmentLensScorePill(property.lensResult)
    : scorePill(property.opportunityScore);
  const thesis = activeLens && property.lensResult
    ? property.lensResult.thesisShort || property.lensResult.thesis || property.description || propertyStory(property)
    : property.description || propertyStory(property);

  return `
    <article class="landing-ranking-mini">
      <div class="landing-ranking-mini-top">
        <span class="landing-ranking-mini-rank">#${rank}</span>
        ${leadScoreMarkup}
      </div>
      <h3>${escapeHtml(property.name)}</h3>
      <p>${escapeHtml(truncate(thesis, 60))}</p>
      <div class="landing-ranking-mini-meta">
        <span class="landing-ranking-mini-location">${escapeHtml(property.barangay || "Unassigned")}</span>
        <strong class="landing-ranking-mini-price">${escapeHtml(moneyShort(property.price))}</strong>
      </div>
      <a href="${propertyHref(property.id)}" class="landing-inline-link">Open Brief</a>
    </article>
  `;
}

function landingRankingPreviewMarkup(properties, lensKey) {
  if (!properties.length) {
    return emptyState("No ranked properties yet", "Approved listings will surface here once the city board has enough live inventory.");
  }

  const lead = properties[0];
  const followers = properties.slice(1, 4);

  return `
    ${landingRankingLeadMarkup(lead, lensKey)}
    <div class="landing-ranking-rail">
      ${followers.map((property, index) => landingRankingMiniMarkup(property, lensKey, index + 2)).join("")}
    </div>
  `;
}

function landingDemandCardMarkup(property, index = 0) {
  const voteCount = Number(property.voteTotal || 0);
  const docsPct = Math.round(Number(property.documentCompletenessPct || 0));
  const kicker = index === 0 ? "Strongest signal" : "Demand pulse";

  return `
    <article class="landing-demand-card ${index === 0 ? "is-lead" : ""}">
      <div class="landing-demand-card-top">
        <span>${escapeHtml(kicker)}</span>
        ${scorePill(property.opportunityScore)}
      </div>
      <h3>${escapeHtml(property.name)}</h3>
      <p>${escapeHtml(truncate(property.description || propertyStory(property), 116))}</p>
      <div class="landing-demand-stats">
        <div>
          <span>Top Need</span>
          <strong>${escapeHtml(voteLabel(property.topNeed || "No demand yet"))}</strong>
        </div>
        <div>
          <span>Votes</span>
          <strong>${voteCount}</strong>
        </div>
        <div>
          <span>Docs</span>
          <strong>${docsPct}%</strong>
        </div>
      </div>
      <a href="${propertyHref(property.id)}" class="landing-inline-link">Open Brief</a>
    </article>
  `;
}

function landingDemandPreviewMarkup(properties) {
  const demandLeaders = properties
    .filter((property) => Number(property.voteTotal || 0) > 0)
    .slice(0, 3);

  if (!demandLeaders.length) {
    return `
      <article class="landing-demand-empty">
        <div class="panel-kicker">Demand snapshot</div>
        <h3>No voting signal has fully surfaced yet.</h3>
        <p>The board will become more opinionated here as investors and residents begin voting on local needs.</p>
      </article>
    `;
  }

  return demandLeaders.map((property, index) => landingDemandCardMarkup(property, index)).join("");
}

function showcaseImageSrc(item) {
  const fallback = "assets/images/Property10.png";
  const base = String(item?.coverImageUrl || fallback || "").trim() || fallback;
  if (/^(blob:|data:)/i.test(base)) return base;

  const version = String(item?._imageVersion || item?.updatedAt || item?.createdAt || "").trim();
  if (!version) return base;

  return `${base}${base.includes("?") ? "&" : "?"}v=${encodeURIComponent(version)}`;
}

function landingShowcaseMetricValueMarkup(item) {
  if (item?.featureType === "offer_board" && item?.countdownAt) {
    return `<strong data-showcase-countdown="${escapeHtml(item.countdownAt)}">${escapeHtml(formatCountdownDistance(item.countdownAt))}</strong>`;
  }

  if (item?.featureType === "city_pipeline" && item?.completionTarget) {
    return `<strong>${escapeHtml(landingShortDate(item.completionTarget))}</strong>`;
  }

  return `<strong>${escapeHtml(item?.primaryMetricValue || showcaseStateLabel(item?.status))}</strong>`;
}

function landingShowcasePreviewCardMarkup(item, featureType = "") {
  const entry = {
    ...item,
    featureType: item?.featureType || featureType,
  };
  const primaryLabel = entry.primaryMetricLabel || (entry.featureType === "city_pipeline" ? "Expected launch" : "Offer window");
  const secondaryLabel = entry.secondaryMetricLabel || (entry.featureType === "city_pipeline" ? "Stage" : "Current offer");
  const secondaryValue = entry.secondaryMetricValue || showcaseStateLabel(entry.status);

  return `
    <article class="landing-showcase-card ${entry.featureType === "city_pipeline" ? "is-pipeline" : "is-offer"}">
      <div class="landing-showcase-media">
        <img src="${escapeHtml(showcaseImageSrc(entry))}" alt="${escapeHtml(entry.title || "Showcase item")}">
        <div class="landing-showcase-media-top">
          <span class="landing-showcase-badge">${escapeHtml(entry.partnerLabel || showcaseFeatureLabel(entry.featureType))}</span>
          ${showcaseStatePill(entry)}
        </div>
      </div>
      <div class="landing-showcase-body">
        <div class="landing-showcase-head">
          <span>${escapeHtml(entry.locationLabel || "San Fernando, La Union")}</span>
          <h3>${escapeHtml(entry.title || showcaseFeatureLabel(entry.featureType))}</h3>
          <p>${escapeHtml(truncate(entry.summary || entry.description || "Admin-curated city signal.", 116))}</p>
        </div>
        <div class="landing-showcase-metrics">
          <div>
            <span>${escapeHtml(primaryLabel)}</span>
            ${landingShowcaseMetricValueMarkup(entry)}
          </div>
          <div>
            <span>${escapeHtml(secondaryLabel)}</span>
            <strong>${escapeHtml(secondaryValue)}</strong>
          </div>
        </div>
        <div class="landing-showcase-actions">
          ${entry.isFeatured ? `<span class="meta-chip showcase-featured-chip">${icon("spark")}Featured</span>` : `<span class="landing-showcase-badge-subtle">${escapeHtml(entry.category || showcaseFeatureLabel(entry.featureType))}</span>`}
          <a href="${escapeHtml(showcaseActionHref(entry))}" class="landing-inline-link">${escapeHtml(showcaseActionLabel(entry))}</a>
        </div>
      </div>
    </article>
  `;
}

function landingShowcasePreviewMarkup(items, featureType) {
  if (!items.length) {
    return `
      <article class="landing-showcase-empty">
        <div class="panel-kicker">${escapeHtml(showcaseFeatureLabel(featureType))}</div>
        <h3>${featureType === "city_pipeline" ? "No future-facing entries are published yet." : "No curated offers are visible yet."}</h3>
        <p>${featureType === "city_pipeline"
          ? "Published pipeline projects will appear here once the city board is ready to surface them."
          : "Published Offer Board entries will appear here once admin uploads the first collection."}</p>
      </article>
    `;
  }

  return items
    .slice(0, 2)
    .map((item) => landingShowcasePreviewCardMarkup(item, featureType))
    .join("");
}

function landingHeroState(properties, votesMap, focusKey, nodeKey = null) {
  const focus = landingHeroFocusConfig(focusKey);
  const node = cityGridNodeConfig(nodeKey, focus.defaultNode);
  const rankedProperties = enrichProperties(properties, properties, votesMap, null, focus.key);
  const bounds = cityGridBounds(properties);
  const mappedProperties = rankedProperties.map((property) => ({
    ...property,
    votes: votesMap[property.id] || {},
    cityPoint: propertyCityPoint(property, bounds),
  }));
  const nodeDistances = mappedProperties.map((property) => ({
    ...property,
    nodeDistance: cityNodeDistance(property.cityPoint, node),
  }));
  const nearbyNodeProperties = nodeDistances
    .filter((property) => property.nodeDistance <= node.radius)
    .sort((left, right) => left.nodeDistance - right.nodeDistance);
  const nodeUniverse = (nearbyNodeProperties.length >= 2
    ? nearbyNodeProperties
    : [...nodeDistances]
      .sort((left, right) => left.nodeDistance - right.nodeDistance)
      .slice(0, Math.min(4, nodeDistances.length)))
    .sort((left, right) => (right.lensScore - left.lensScore) || (left.nodeDistance - right.nodeDistance) || (right.marketScore - left.marketScore));
  const topSlice = nodeUniverse.slice(0, 3);
  const iaiMetric = topSlice.length
    ? topSlice.reduce((sum, property) => sum + Number(property.lensScore || property.opportunityScore || 0), 0) / topSlice.length
    : 0;
  const leader = nodeUniverse[0] || rankedProperties[0] || null;
  const activeListings = nodeUniverse.filter((property) => String(property.status || "").toLowerCase() === "available").length || nodeUniverse.length;
  const verifiedListings = nodeUniverse.filter((property) => String(property.listingVerificationStatus || "").toLowerCase() === "verified").length;
  const fieldAuditCount = nodeUniverse.filter((property) => Number(property.groundTruthVisitCount || 0) > 0).length;
  const dossierReadyCount = nodeUniverse.filter((property) => Number(property.documentCompletenessPct || 0) >= 60).length;

  return {
    focus,
    node,
    rankedProperties,
    mappedProperties,
    nodeUniverse,
    leader,
    iaiMetric,
    activeListings,
    verifiedListings,
    fieldAuditCount,
    dossierReadyCount,
    tickerItems: landingSentimentItems(focus, node, nodeUniverse),
    focusBadge: `${focus.railLabel} lens`,
    nodeBadge: `Focus: ${node.label}`,
    metricMeta: `${focus.railLabel} / ${node.label}`,
    tickerMeta: `${focus.railLabel} lens`,
    focusSummary: `${focus.summary} Start with ${node.label}.`,
    metricSummary: leader
      ? `${leader.name} leads in ${node.label} right now.`
      : `${node.label} is waiting for live listings.`,
    opportunitySummary: `${activeListings} live listing${activeListings === 1 ? "" : "s"} around ${node.label}.`,
    nodeMeta: `${node.label} focus`,
  };
}

function renderLandingHero(hero, state) {
  if (!hero || !state) return;

  hero.style.setProperty("--hero-focus-accent", state.focus.accent);
  hero.style.setProperty("--hero-focus-accent-rgb", state.focus.accentRgb);
  setHeroGlowPercent(hero, state.node.xPercent, state.node.yPercent);
  hero.dataset.activeFocus = state.focus.key;
  hero.dataset.activeNode = state.node.key;

  const focusSummary = document.getElementById("heroFocusSummary");
  const focusBadge = document.getElementById("heroFocusBadge");
  const nodeBadge = document.getElementById("heroNodeBadge");
  const metricMeta = document.getElementById("heroMetricMeta");
  const metricSummary = document.getElementById("heroMetricSummary");
  const tickerMeta = document.getElementById("heroTickerMeta");
  const opportunitySummary = document.getElementById("heroOpportunitySummary");
  const nodeMeta = document.getElementById("heroNodeMeta");
  const tickerTrack = document.getElementById("heroSentimentTicker");
  const scoreNode = document.getElementById("heroIaiScore");
  const countNode = document.getElementById("heroOpportunityCount");
  const proofGrid = document.getElementById("heroProofGrid");
  const storyCopy = document.getElementById("heroStoryCopy");
  const featuredOpportunity = document.getElementById("heroFeaturedOpportunity");
  const featuredMeta = document.getElementById("heroFeaturedMeta");
  const heatMesh = document.getElementById("heroHeatMesh");

  if (focusSummary) focusSummary.textContent = state.focusSummary;
  if (focusBadge) focusBadge.textContent = state.focusBadge;
  if (nodeBadge) nodeBadge.textContent = state.nodeBadge;
  if (metricMeta) metricMeta.textContent = state.metricMeta;
  if (metricSummary) metricSummary.textContent = state.metricSummary;
  if (tickerMeta) tickerMeta.textContent = state.tickerMeta;
  if (opportunitySummary) opportunitySummary.textContent = state.opportunitySummary;
  if (nodeMeta) nodeMeta.textContent = state.nodeMeta;

  if (tickerTrack) {
    const items = [...state.tickerItems, ...state.tickerItems];
    tickerTrack.innerHTML = items.map((item) => `<span class="market-ticker-item">${escapeHtml(item)}</span>`).join("");
  }
  if (proofGrid) {
    proofGrid.innerHTML = landingHeroProofItems(state).map((item) => `
      <article class="hero-proof-card">
        <span>${escapeHtml(item.label)}</span>
        <strong>${escapeHtml(item.value)}</strong>
      </article>
    `).join("");
  }
  if (storyCopy) storyCopy.textContent = landingHeroStory(state);
  if (featuredOpportunity) featuredOpportunity.innerHTML = landingFeaturedOpportunityMarkup(state);
  if (featuredMeta) featuredMeta.textContent = state.leader ? state.nodeMeta : "Standby";
  if (heatMesh) heatMesh.innerHTML = landingHeroHeatMeshMarkup(state);

  animateNumericValue(scoreNode, Number(state.iaiMetric || 0), { decimals: 1, duration: 1200 });
  animateNumericValue(countNode, Number(state.activeListings || 0), { decimals: 0, duration: 900 });

  hero.querySelectorAll("[data-hero-focus]").forEach((button) => {
    button.classList.toggle("is-active", String(button.dataset.heroFocus || "") === state.focus.key);
  });
  hero.querySelectorAll("[data-lens-indicator]").forEach((indicator) => {
    indicator.classList.toggle("lens-active-amber", String(indicator.dataset.lensIndicator || "") === state.focus.key);
  });
  hero.querySelectorAll("[data-spatial-node]").forEach((node) => {
    const nodeKey = String(node.dataset.spatialNode || "");
    node.classList.toggle("is-focus-linked", state.focus.nodes.includes(nodeKey));
    node.classList.toggle("is-active", nodeKey === state.node.key);
    node.classList.toggle("is-highlighted", nodeKey === state.node.key);
  });
  hero.querySelectorAll("[data-city-node]").forEach((control) => {
    const nodeKey = String(control.dataset.cityNode || "");
    control.classList.toggle("is-focus-linked", state.focus.nodes.includes(nodeKey));
    control.classList.toggle("is-active", nodeKey === state.node.key);
  });
  hero.querySelectorAll("[data-depth-label]").forEach((label) => {
    const nodeKey = String(label.dataset.depthLabel || "");
    label.classList.toggle("is-focus-linked", state.focus.nodes.includes(nodeKey));
    label.classList.toggle("is-active", nodeKey === state.node.key);
  });
}

function initHeroStage() {
  const hero = document.querySelector("[data-hero-stage]");
  if (!hero) return;
  const canvas = hero.querySelector("#hero-canvas");
  if (!canvas) return;

  const setHeroDepth = (clientX, clientY) => {
    const rect = hero.getBoundingClientRect();
    const relativeX = ((clientX - rect.left) / Math.max(rect.width, 1)) - 0.5;
    const relativeY = ((clientY - rect.top) / Math.max(rect.height, 1)) - 0.5;
    hero.style.setProperty("--hero-tilt-x", `${(-relativeY * 6).toFixed(2)}deg`);
    hero.style.setProperty("--hero-tilt-y", `${(relativeX * 7).toFixed(2)}deg`);
    hero.style.setProperty("--hero-shift-x", `${(relativeX * 26).toFixed(2)}px`);
    hero.style.setProperty("--hero-shift-y", `${(relativeY * 18).toFixed(2)}px`);
  };

  const resetHeroDepth = () => {
    hero.style.setProperty("--hero-tilt-x", "0deg");
    hero.style.setProperty("--hero-tilt-y", "0deg");
    hero.style.setProperty("--hero-shift-x", "0px");
    hero.style.setProperty("--hero-shift-y", "0px");
  };

  const setGlowPosition = (clientX, clientY) => {
    const rect = canvas.getBoundingClientRect();
    const x = ((clientX - rect.left) / Math.max(rect.width, 1)) * 100;
    const y = ((clientY - rect.top) / Math.max(Math.min(rect.height, 720), 1)) * 100;
    setHeroGlowPercent(hero, x, y);
  };

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;
      hero.classList.add("is-awake");
      observer.disconnect();
    });
  }, {
    threshold: 0.3,
  });
  observer.observe(hero);

  canvas.addEventListener("pointermove", (event) => {
    setGlowPosition(event.clientX, event.clientY);
    setHeroDepth(event.clientX, event.clientY);
  });

  canvas.addEventListener("pointerenter", (event) => {
    wakeHeroStage(hero, {
      xPercent: ((event.clientX - canvas.getBoundingClientRect().left) / Math.max(canvas.getBoundingClientRect().width, 1)) * 100,
      yPercent: ((event.clientY - canvas.getBoundingClientRect().top) / Math.max(Math.min(canvas.getBoundingClientRect().height, 720), 1)) * 100,
    });
    setHeroDepth(event.clientX, event.clientY);
  });

  canvas.addEventListener("pointerdown", (event) => {
    wakeHeroStage(hero, {
      xPercent: ((event.clientX - canvas.getBoundingClientRect().left) / Math.max(canvas.getBoundingClientRect().width, 1)) * 100,
      yPercent: ((event.clientY - canvas.getBoundingClientRect().top) / Math.max(Math.min(canvas.getBoundingClientRect().height, 720), 1)) * 100,
    });
    setHeroDepth(event.clientX, event.clientY);
  });

  canvas.addEventListener("pointerleave", resetHeroDepth);

  canvas.addEventListener("touchstart", (event) => {
    const touch = event.touches?.[0];
    if (!touch) return;
    wakeHeroStage(hero, {
      xPercent: ((touch.clientX - canvas.getBoundingClientRect().left) / Math.max(canvas.getBoundingClientRect().width, 1)) * 100,
      yPercent: ((touch.clientY - canvas.getBoundingClientRect().top) / Math.max(Math.min(canvas.getBoundingClientRect().height, 720), 1)) * 100,
    });
    setHeroDepth(touch.clientX, touch.clientY);
  }, { passive: true });

  hero.addEventListener("keydown", (event) => {
    if (event.key !== "Enter" && event.key !== " ") return;
    event.preventDefault();
    wakeHeroStage(hero);
  });
}

const STUDIO_REVEAL_SELECTOR = [
  ".page-intro-card",
  ".panel-card",
  ".stat-card",
  ".comparison-card",
  ".request-card",
  ".thread-card",
  ".visit-thread-card",
  ".visit-window-card",
  ".command-score-card",
  ".search-result-card",
  ".role-card",
  ".news-card",
  ".about-stat-card",
  ".explorer-card",
  ".map-panel-card",
  ".prospectus-card",
  ".readiness-pillar-card",
  ".readiness-editor-card",
  ".intent-card",
  ".cta-card",
  ".landing-panel",
  ".landing-role-link",
  ".landing-showcase-card",
  ".landing-ranking-mini",
  ".landing-demand-card",
  ".final-cta-card",
  ".property-card.property-card-intelligence",
  ".property-card.property-card-compact",
  ".vote-card",
  ".showcase-card",
  ".showcase-spotlight-card",
  ".showcase-admin-card",
  ".showcase-hero",
  ".showcase-card-section",
  ".auth-visual",
  ".auth-surface",
  ".auth-floating-card",
  ".modal-card",
  ".spotlight-card",
  ".decision-card",
  ".listing-row",
  ".contact-card",
].join(",");

const STUDIO_TILT_SELECTOR = [
  ".page-intro-card",
  ".panel-card",
  ".stat-card",
  ".comparison-card",
  ".request-card",
  ".thread-card",
  ".visit-window-card",
  ".command-score-card",
  ".search-result-card",
  ".role-card",
  ".news-card",
  ".about-stat-card",
  ".explorer-card",
  ".map-panel-card",
  ".prospectus-card",
  ".readiness-pillar-card",
  ".intent-card",
  ".landing-panel",
  ".landing-role-link",
  ".landing-showcase-card",
  ".landing-ranking-mini",
  ".landing-demand-card",
  ".property-card.property-card-intelligence",
  ".showcase-card",
  ".showcase-spotlight-card",
  ".vote-card",
  ".auth-surface",
  ".auth-floating-card",
  ".portal-entry",
].join(",");

let studioMotionInitialized = false;
let studioRevealObserver = null;
let studioMotionReduced = false;
let studioTiltEnabled = false;

function studioMotionTargets(root, selector) {
  if (!root) return [];
  const targets = [];

  if (root instanceof Element && root.matches(selector)) {
    targets.push(root);
  }

  if (typeof root.querySelectorAll === "function") {
    targets.push(...root.querySelectorAll(selector));
  }

  return targets;
}

function hydrateStudioReveal(node, index = 0) {
  if (!(node instanceof HTMLElement) || node.dataset.uiRevealReady === "1") return;

  node.dataset.uiRevealReady = "1";
  node.classList.add("ui-reveal");
  node.style.setProperty("--ui-reveal-delay", `${Math.min(index, 10) * 55}ms`);

  if (studioMotionReduced || !studioRevealObserver) {
    node.classList.add("is-visible");
    return;
  }

  studioRevealObserver.observe(node);
}

function hydrateStudioTilt(node) {
  if (!(node instanceof HTMLElement) || node.dataset.uiTiltReady === "1" || !studioTiltEnabled) return;

  node.dataset.uiTiltReady = "1";
  node.classList.add("ui-tilt-card");

  const resetTilt = () => {
    node.style.setProperty("--ui-tilt-x", "0deg");
    node.style.setProperty("--ui-tilt-y", "0deg");
    node.style.setProperty("--ui-glow-x", "50%");
    node.style.setProperty("--ui-glow-y", "50%");
    node.classList.remove("is-tilting");
  };

  const updateTilt = (clientX, clientY) => {
    const rect = node.getBoundingClientRect();
    const relativeX = ((clientX - rect.left) / Math.max(rect.width, 1)) - 0.5;
    const relativeY = ((clientY - rect.top) / Math.max(rect.height, 1)) - 0.5;
    node.style.setProperty("--ui-tilt-x", `${(-relativeY * 4.8).toFixed(2)}deg`);
    node.style.setProperty("--ui-tilt-y", `${(relativeX * 6.4).toFixed(2)}deg`);
    node.style.setProperty("--ui-glow-x", `${(((clientX - rect.left) / Math.max(rect.width, 1)) * 100).toFixed(2)}%`);
    node.style.setProperty("--ui-glow-y", `${(((clientY - rect.top) / Math.max(rect.height, 1)) * 100).toFixed(2)}%`);
  };

  resetTilt();

  node.addEventListener("pointerenter", (event) => {
    node.classList.add("is-tilting");
    updateTilt(event.clientX, event.clientY);
  });

  node.addEventListener("pointermove", (event) => {
    updateTilt(event.clientX, event.clientY);
  });

  node.addEventListener("pointerleave", resetTilt);
}

function syncStudioMotion(root = document) {
  studioMotionTargets(root, STUDIO_REVEAL_SELECTOR).forEach((node, index) => hydrateStudioReveal(node, index));
  studioMotionTargets(root, STUDIO_TILT_SELECTOR).forEach((node) => hydrateStudioTilt(node));
}

function initStudioMotion() {
  if (!document.body) return;

  if (!studioMotionInitialized) {
    studioMotionInitialized = true;
    studioMotionReduced = window.matchMedia?.("(prefers-reduced-motion: reduce)")?.matches ?? false;
    studioTiltEnabled = (window.matchMedia?.("(pointer:fine)")?.matches ?? true) && !studioMotionReduced;

    if ("IntersectionObserver" in window && !studioMotionReduced) {
      studioRevealObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) return;
          entry.target.classList.add("is-visible");
          studioRevealObserver?.unobserve(entry.target);
        });
      }, {
        threshold: 0.16,
        rootMargin: "0px 0px -8% 0px",
      });
    }

    if ("MutationObserver" in window) {
      const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
          mutation.addedNodes.forEach((node) => {
            if (!(node instanceof Element)) return;
            syncStudioMotion(node);
          });
        });
      });

      observer.observe(document.body, {
        childList: true,
        subtree: true,
      });
    }
  }

  document.body.classList.add("studio-motion-ready");
  syncStudioMotion(document);
}

function initLandingChrome() {
  if (page !== "landing") return;

  const header = document.querySelector(".site-header");
  const hero = document.querySelector("[data-hero-stage]");
  const featureShell = document.querySelector(".hero-feature-shell");
  const revealTargets = document.querySelectorAll(".landing-shell .section-block, .landing-shell .final-cta-card");
  const allowPointerDepth = window.matchMedia?.("(pointer:fine)")?.matches ?? true;

  let scrollFrame = null;
  const syncHeader = () => {
    document.body.classList.toggle("landing-header-condensed", window.scrollY > 18);
    scrollFrame = null;
  };

  syncHeader();
  window.addEventListener("scroll", () => {
    if (scrollFrame !== null) return;
    scrollFrame = window.requestAnimationFrame(syncHeader);
  }, { passive: true });

  if (hero && featureShell && allowPointerDepth) {
    const resetDepth = () => {
      featureShell.style.setProperty("--landing-card-tilt-x", "0deg");
      featureShell.style.setProperty("--landing-card-tilt-y", "0deg");
      featureShell.style.setProperty("--landing-card-shift-x", "0px");
      featureShell.style.setProperty("--landing-card-shift-y", "0px");
    };

    resetDepth();
    hero.addEventListener("pointermove", (event) => {
      const rect = hero.getBoundingClientRect();
      const relativeX = ((event.clientX - rect.left) / Math.max(rect.width, 1)) - 0.5;
      const relativeY = ((event.clientY - rect.top) / Math.max(rect.height, 1)) - 0.5;
      featureShell.style.setProperty("--landing-card-tilt-x", `${(-relativeY * 3.4).toFixed(2)}deg`);
      featureShell.style.setProperty("--landing-card-tilt-y", `${(relativeX * 4.2).toFixed(2)}deg`);
      featureShell.style.setProperty("--landing-card-shift-x", `${(relativeX * 10).toFixed(2)}px`);
      featureShell.style.setProperty("--landing-card-shift-y", `${(relativeY * -8).toFixed(2)}px`);
    });
    hero.addEventListener("pointerleave", resetDepth);
  }

  if (revealTargets.length && "IntersectionObserver" in window) {
    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        entry.target.classList.add("is-visible");
        observer.unobserve(entry.target);
      });
    }, {
      threshold: 0.18,
      rootMargin: "0px 0px -10% 0px",
    });

    revealTargets.forEach((target) => observer.observe(target));
  } else {
    revealTargets.forEach((target) => target.classList.add("is-visible"));
  }
}

async function initLanding() {
  const hero = document.querySelector("[data-hero-stage]");
  const rankingRoot = document.getElementById("homeRankingPreview");
  const votingRoot = document.getElementById("homeVotingPreview");
  const offerRoot = document.getElementById("homeOfferPreview");
  const pipelineRoot = document.getElementById("homePipelinePreview");
  if (!hero && !rankingRoot && !votingRoot && !offerRoot && !pipelineRoot) return;

  const fetchShowcaseItems = async (featureType, root) => {
    if (!root) return [];
    try {
      return (await api.showcase(featureType)).items || [];
    } catch {
      return [];
    }
  };

  const [bootstrap, offerItems, pipelineItems] = await Promise.all([
    api.bootstrap(),
    fetchShowcaseItems("offer_board", offerRoot),
    fetchShowcaseItems("city_pipeline", pipelineRoot),
  ]);
  const properties = bootstrap.properties || [];
  const votesMap = await loadVoteTallies(properties);
  let heroFocusKey = landingHeroFocusConfig(getActiveInvestmentLensKey()).key;
  let selectedNodeKey = landingHeroFocusConfig(heroFocusKey).defaultNode;
  let previewNodeKey = null;
  let lastHeroState = null;
  let orbitActive = false;
  let orbitIndex = 0;
  let orbitTimer = null;
  let showcaseTicker = null;
  const orbitToggle = document.getElementById("heroOrbitToggle");
  const orbitStatus = document.getElementById("heroOrbitStatus");
  const orbitFrames = [
    { focusKey: "logistics", nodeKey: "poro-point" },
    { focusKey: "commercial_center", nodeKey: "city-center" },
    { focusKey: "university", nodeKey: "civic-belt" },
    { focusKey: "hospital", nodeKey: "civic-belt" },
  ];

  const activeNodeKey = () => cityGridNodeConfig(previewNodeKey || selectedNodeKey, landingHeroFocusConfig(heroFocusKey).defaultNode).key;

  const matchingOrbitIndex = () => {
    const match = orbitFrames.findIndex((frame) => frame.focusKey === heroFocusKey && frame.nodeKey === selectedNodeKey);
    return match >= 0 ? match : 0;
  };

  const renderOrbitUi = () => {
    if (!hero) return;
    hero.classList.toggle("is-orbiting", orbitActive);
    if (orbitToggle) {
      orbitToggle.setAttribute("aria-pressed", orbitActive ? "true" : "false");
      orbitToggle.textContent = orbitActive ? "Sweep Active" : "Begin Sweep";
    }
    if (orbitStatus) {
      orbitStatus.textContent = orbitActive
        ? `Presentation orbit is active. ${lastHeroState?.focus?.railLabel || "City intelligence"} is sweeping ${lastHeroState?.node?.label || "the grid"}.`
        : "Manual control engaged. Begin sweep to move the city read across the board.";
    }
  };

  const renderHero = () => {
    if (!hero) return;
    const state = landingHeroState(properties, votesMap, heroFocusKey, activeNodeKey());
    renderLandingHero(hero, state);
    lastHeroState = state;
    renderOrbitUi();
    return state;
  };

  const render = () => {
    const heroState = renderHero();
    const rankedPreview = heroState?.rankedProperties || enrichProperties(properties, properties, votesMap, null, heroFocusKey);

    if (rankingRoot) {
      rankingRoot.innerHTML = landingRankingPreviewMarkup(rankedPreview, heroFocusKey);
    }

    if (votingRoot) {
      votingRoot.innerHTML = landingDemandPreviewMarkup(rankedPreview);
    }

    if (offerRoot) {
      offerRoot.innerHTML = landingShowcasePreviewMarkup(offerItems, "offer_board");
    }

    if (pipelineRoot) {
      pipelineRoot.innerHTML = landingShowcasePreviewMarkup(pipelineItems, "city_pipeline");
    }

    updateShowcaseCountdownNodes(document);
  };

  const stopOrbit = () => {
    orbitActive = false;
    if (orbitTimer) {
      window.clearTimeout(orbitTimer);
      orbitTimer = null;
    }
    renderOrbitUi();
  };

  const queueOrbit = (delay = 6400) => {
    if (!orbitActive) return;
    if (orbitTimer) {
      window.clearTimeout(orbitTimer);
    }
    orbitTimer = window.setTimeout(() => {
      orbitIndex = (orbitIndex + 1) % orbitFrames.length;
      const frame = orbitFrames[orbitIndex];
      heroFocusKey = frame.focusKey;
      selectedNodeKey = frame.nodeKey;
      previewNodeKey = null;
      render();
      wakeHeroStage(hero, cityGridNodeConfig(selectedNodeKey));
      queueOrbit();
    }, delay);
  };

  const startOrbit = (delay = 6400) => {
    orbitActive = true;
    orbitIndex = matchingOrbitIndex();
    renderOrbitUi();
    queueOrbit(delay);
  };

  render();
  if ((offerRoot || pipelineRoot) && showcaseTicker === null) {
    showcaseTicker = window.setInterval(() => updateShowcaseCountdownNodes(document), 1000);
  }
  hero?.querySelectorAll("[data-hero-focus]").forEach((button) => {
    button.addEventListener("click", () => {
      stopOrbit();
      heroFocusKey = landingHeroFocusConfig(button.dataset.heroFocus || "").key;
      saveActiveInvestmentLensKey(heroFocusKey);
      selectedNodeKey = landingHeroFocusConfig(heroFocusKey).defaultNode;
      previewNodeKey = null;
      render();
      wakeHeroStage(hero, cityGridNodeConfig(selectedNodeKey));
    });
  });
  hero?.querySelectorAll("[data-city-node]").forEach((control) => {
    const nodeKey = cityGridNodeConfig(control.dataset.cityNode || "", landingHeroFocusConfig(heroFocusKey).defaultNode).key;

    const previewNode = () => {
      previewNodeKey = nodeKey;
      renderHero();
      wakeHeroStage(hero, cityGridNodeConfig(nodeKey));
    };

    const clearPreview = () => {
      if (previewNodeKey !== nodeKey) return;
      previewNodeKey = null;
      renderHero();
    };

    control.addEventListener("mouseenter", previewNode);
    control.addEventListener("focus", previewNode);
    control.addEventListener("mouseleave", clearPreview);
    control.addEventListener("blur", clearPreview);
    control.addEventListener("click", () => {
      stopOrbit();
      selectedNodeKey = nodeKey;
      previewNodeKey = null;
      renderHero();
      wakeHeroStage(hero, cityGridNodeConfig(nodeKey));
    });
  });
  orbitToggle?.addEventListener("click", () => {
    if (orbitActive) {
      stopOrbit();
      return;
    }
    startOrbit(900);
  });
}

function locationBoard(properties, activeId, title = "Location view") {
  if (!properties.length) {
    return `<article class="location-board"><div class="loading-panel">No locations available.</div></article>`;
  }

  const latitudes = properties.map((property) => Number(property.lat || 0));
  const longitudes = properties.map((property) => Number(property.lng || 0));
  const minLat = Math.min(...latitudes);
  const maxLat = Math.max(...latitudes);
  const minLng = Math.min(...longitudes);
  const maxLng = Math.max(...longitudes);
  const latRange = Math.max(0.0001, maxLat - minLat);
  const lngRange = Math.max(0.0001, maxLng - minLng);

  const dots = properties.map((property, index) => {
    const left = 12 + (((Number(property.lng || 0) - minLng) / lngRange) * 76);
    const top = 16 + (((maxLat - Number(property.lat || 0)) / latRange) * 68);
    const label = truncate(property.name, 16);
    return `
      <div class="location-dot ${property.id === activeId ? "is-active" : ""}" style="left:${left.toFixed(2)}%;top:${top.toFixed(2)}%;">
        <button type="button" aria-label="Focus ${escapeHtml(property.name)}" data-select-property="${property.id}"></button>
        <span>${escapeHtml(index + 1)}. ${escapeHtml(label)}</span>
      </div>
    `;
  }).join("");

  return `
    <article class="location-board">
      <div class="panel-kicker">${escapeHtml(title)}</div>
      <h3>Spatial market view</h3>
      <p>See how opportunity clusters across the city.</p>
      <div class="location-stage">${dots}</div>
    </article>
  `;
}

function voteBars(votes) {
  const entries = sortedVoteEntries(votes);
  const total = Math.max(1, totalVotes(votes));
  if (!entries.length) {
    return `<div class="loading-panel">No votes have been cast for this location yet.</div>`;
  }

  return entries.map(([label, count]) => {
    const numericCount = Number(count || 0);
    const pct = Math.round((numericCount / total) * 100);
    return `
      <div class="bar-row">
        <div class="bar-top">
          <span>${escapeHtml(voteLabel(label))}</span>
          <strong>${numericCount} vote${numericCount === 1 ? "" : "s"} | ${pct}%</strong>
        </div>
        <div class="bar-track"><div class="bar-fill" style="width:${pct}%"></div></div>
      </div>
    `;
  }).join("");
}

function initials(value) {
  return String(value || "")
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part.charAt(0).toUpperCase())
    .join("") || "OP";
}

function voteOptionMedia(option) {
  if (option?.imageUrl) {
    return `<img src="${escapeHtml(option.imageUrl)}" alt="${escapeHtml(option.title || "Vote option")}">`;
  }

  return `<span>${escapeHtml(initials(option?.title || "Option"))}</span>`;
}

function voteOptionCard(option, count = 0, isSelected = false, disabled = false) {
  return `
    <button
      type="button"
      class="vote-card ${isSelected ? "is-selected" : ""}"
      data-cast-vote="${option.id}"
      ${disabled ? "disabled" : ""}
    >
      <div class="vote-card-media">${voteOptionMedia(option)}</div>
      <div class="vote-card-body">
        <div class="vote-card-title-row">
          <strong>${escapeHtml(option.title || "Vote option")}</strong>
          <span class="vote-card-count">${count} vote${count === 1 ? "" : "s"}</span>
        </div>
        <p>${escapeHtml(option.description || "Admin-managed business option for this location.")}</p>
      </div>
    </button>
  `;
}

function visitStatusTone(status) {
  const normalized = String(status || "").toLowerCase();
  if (normalized === "visited") return "visit-status-visited";
  if (normalized === "in_progress") return "visit-status-active";
  if (normalized === "confirmed") return "visit-status-confirmed";
  if (normalized === "counter_offered") return "visit-status-counter";
  return "visit-status-proposed";
}

function visitStatusPill(status) {
  const normalized = String(status || "proposed").toLowerCase();
  return `<span class="status-pill visit-status-pill ${visitStatusTone(normalized)}">${escapeHtml(VISIT_STATUS_LABELS[normalized] || titleCase(normalized))}</span>`;
}

function groundTruthPill(target = {}) {
  const visitCount = Number(target.groundTruthVisitCount || 0);
  if (visitCount < 1) return "";
  const adjustment = Number(target.groundTruthAdjustmentPct || 0);
  const tone = adjustment >= 0 ? "ground-truth-positive" : "ground-truth-negative";
  const prefix = adjustment > 0 ? "+" : "";
  return `<span class="status-pill ground-truth-pill ${tone}">Ground Truth ${prefix}${Math.round(adjustment)}%</span>`;
}

function compareTimelineDate(value) {
  const parsed = new Date(value || "");
  return Number.isNaN(parsed.getTime()) ? 0 : parsed.getTime();
}

function formatVisitWindow(window) {
  if (!window?.startAt || !window?.endAt) return "Awaiting schedule";
  const start = new Date(window.startAt);
  const end = new Date(window.endAt);
  if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) return "Awaiting schedule";
  const sameDay = start.toDateString() === end.toDateString();
  const dayLabel = start.toLocaleDateString(undefined, {
    month: "long",
    day: "numeric",
    year: "numeric",
  });
  const startTime = start.toLocaleTimeString(undefined, {
    hour: "numeric",
    minute: "2-digit",
  });
  const endTime = end.toLocaleTimeString(undefined, {
    hour: "numeric",
    minute: "2-digit",
  });

  if (sameDay) {
    return `${dayLabel} | ${startTime} - ${endTime}`;
  }

  return `${formatDateTime(window.startAt)} - ${formatDateTime(window.endAt)}`;
}

function visitPurposeOptions(selectedPurpose = "") {
  const options = Array.from(new Set([
    ...INVESTMENT_LENSES.map((lens) => lens.label),
    selectedPurpose,
  ].filter(Boolean)));

  return options.map((purpose) => `
    <option value="${escapeHtml(purpose)}" ${purpose === selectedPurpose ? "selected" : ""}>${escapeHtml(purpose)}</option>
  `).join("");
}

function visitMetricLabel(value) {
  return {
    1: "Low confidence",
    2: "Caution",
    3: "Balanced",
    4: "Strong",
    5: "Elite",
  }[Number(value || 0)] || "Pending";
}

function prospectusSignatureMarkup(property, duePct) {
  const verificationLabel = VERIFICATION_LABELS[String(property?.listingVerificationStatus || "unverified").toLowerCase()] || titleCase(property?.listingVerificationStatus || "unverified");
  const reviewDate = property?.documentsReviewedAt || property?.siteVerifiedAt || property?.lastConfirmedAvailableAt || property?.updatedAt;
  const reviewLabel = reviewDate ? formatProspectusTimestamp(reviewDate) : "Pending verification";
  return `
    <div class="prospectus-signature-block">
      <div class="prospectus-signature-label">Digital Signature Area</div>
      <div class="prospectus-signature-line"></div>
      <div class="prospectus-signature-meta">
        <span>Admin diligence status: ${escapeHtml(verificationLabel)}</span>
        <span>Due diligence completion: ${duePct}%</span>
        <span>Verification timestamp: ${escapeHtml(reviewLabel)}</span>
      </div>
    </div>
  `;
}

function prospectusMarkup({
  property,
  readiness,
  lensResult,
  lensKey,
  votes = {},
  summary = null,
  weather = null,
  visit = null,
  conversationSummary = {},
  conversationThread = null,
  allProperties = [],
  duePct = 0,
  generatedAt = null,
}) {
  if (!property || !readiness || !lensResult) {
    return "";
  }

  const activeLens = getInvestmentLensConfig(lensKey);
  const compliance = evaluateClup(property, lensKey ? clupUseForLens(lensKey) : property.type);
  const messageUrl = messagingQrUrl(property.id);
  const qrMarkup = messageUrl ? prospectusQrSvgMarkup(messageUrl) : "";
  const mapMarkup = demandSnapshotSvgMarkup({
    property,
    properties: allProperties,
    lensKey,
    votes,
  });
  const thesisHeading = activeLens.key === "university"
    ? "University Thesis Summary"
    : `${activeLens.label} Thesis Summary`;
  const topMetrics = (lensResult.topMetrics || lensResult.metrics || []).slice(0, 4);
  const fieldAudit = visit?.fieldAudit || property?.latestFieldAudit || {};
  const fieldAuditComplete = Boolean(visit?.fieldAuditComplete || Object.keys(fieldAudit).length);
  const visitWindow = visit?.confirmedWindow || visit?.counterWindow || visit?.primaryWindow || null;
  const latestVisitLabel = property.latestGroundTruthVisitAt || visit?.visitedAt || visit?.updatedAt
    ? formatProspectusTimestamp(property.latestGroundTruthVisitAt || visit?.visitedAt || visit?.updatedAt)
    : "Pending";
  const sealLabel = duePct >= 75 || String(property.listingVerificationStatus || "").toLowerCase() === "verified"
    ? "Diligence Reviewed"
    : "Diligence In Progress";

  return `
    <section class="prospectus-print-shell" aria-label="One-click prospectus">
      <article class="prospectus-page">
        <header class="prospectus-hero">
          <div class="prospectus-hero-copy">
            <div class="prospectus-kicker">LOCUS-SF | One-Click Prospectus</div>
            <h1>${escapeHtml(property.name)}</h1>
            <p>${escapeHtml(propertyStory(property))}</p>
            <div class="prospectus-chip-row">
              <span class="prospectus-chip">Investment Lens: ${escapeHtml(activeLens.label)}</span>
              <span class="prospectus-chip">IAI / Lens Score: ${Math.round(Number(lensResult.score || 0))}</span>
              <span class="prospectus-chip">CLUP: ${escapeHtml(clupStatus(compliance.status))} · ${escapeHtml(clupScoreLabel(compliance))}</span>
              <span class="prospectus-chip">Data Valid as of ${escapeHtml(formatProspectusDate(generatedAt || new Date().toISOString()))}</span>
            </div>
          </div>
          <div class="admin-seal">
            <span>${escapeHtml(sealLabel)}</span>
            <strong>${duePct}% diligence</strong>
          </div>
        </header>

        <section class="prospectus-grid bento-grid">
          <article class="bento-item prospectus-card prospectus-span-12 clup-report-card clup-${escapeHtml(clupStatus(compliance.status).toLowerCase())}">
            <div class="prospectus-section-kicker">CLUP Compliance and Suitability</div>
            <h2>${escapeHtml(clupStatus(compliance.status))} — ${escapeHtml(compliance.proposedInvestmentLabel)}</h2>
            <p class="prospectus-lead">${escapeHtml(compliance.explanation)}</p>
            <div class="prospectus-fact-grid">
              <div><span>Suitability Score</span><strong>${escapeHtml(clupScoreLabel(compliance))}</strong></div>
              <div><span>Existing Land Use</span><strong>${escapeHtml(compliance.existingLandUse)}</strong></div>
              <div><span>Zoning Classification</span><strong>${escapeHtml(compliance.zoningClassification)}</strong></div>
              <div><span>Decision Gate</span><strong>${escapeHtml(titleCase(clupGate(compliance)))}</strong></div>
              <div><span>Evidence</span><strong>${escapeHtml(compliance.evidenceLevel || "UNVERIFIED")}</strong></div>
              <div><span>Source</span><strong>${escapeHtml(compliance.sourceReference || "Authoritative source pending")}</strong></div>
            </div>
            <div class="prospectus-note"><strong>Recommended LGU action:</strong> ${escapeHtml(compliance.recommendedLguAction)}</div>
            <small>${escapeHtml(compliance.disclaimer || (compliance.isPreliminary ? "Preliminary screen. Confirm against the adopted CLUP, official zoning map, Zoning Ordinance, and locational-clearance process." : "LGU-verified profile. Formal locational clearance may still be required."))}</small>
          </article>
          <article class="bento-item prospectus-card prospectus-card-primary prospectus-span-7">
            <div class="prospectus-section-kicker">Scenario Synthesis</div>
            <h2>${escapeHtml(thesisHeading)}</h2>
            <p class="prospectus-lead">${escapeHtml(lensResult.thesis || `${property.name} is being interpreted through the ${activeLens.label} lens.`)}</p>
            <div class="prospectus-metric-strip">
              ${topMetrics.map((metric) => `
                <div class="prospectus-metric-pill">
                  <strong>${escapeHtml(metric.label)}</strong>
                  <span>${Math.round(Number(metric.score || 0))}% | ${escapeHtml(metric.displayValue || "Awaiting data")}</span>
                </div>
              `).join("")}
            </div>
            <div class="prospectus-fact-grid">
              <div><span>Guide Price</span><strong>${escapeHtml(formatProspectusCurrency(property.price))}</strong></div>
              <div><span>Land Area</span><strong>${escapeHtml(Number(property.area || 0).toFixed(1))} ha</strong></div>
              <div><span>Market Score</span><strong>${Math.round(Number(property.marketScore || 0))}</strong></div>
              <div><span>Readiness Score</span><strong>${Math.round(Number(readiness.totalScore || 0))}</strong></div>
            </div>
          </article>

          <article class="bento-item prospectus-card prospectus-span-5">
            <div class="prospectus-section-kicker">Authority Stack</div>
            <h2>Decision Snapshot</h2>
            <div class="prospectus-mini-list">
              <div><span>Corridor</span><strong>${escapeHtml(corridorLabel(property.corridor))}</strong></div>
              <div><span>Property Type</span><strong>${escapeHtml(typeLabel(property.type))}</strong></div>
              <div><span>Due Diligence</span><strong>${duePct}% complete</strong></div>
              <div><span>Document Completeness</span><strong>${formatProspectusPercent(property.documentCompletenessPct)}</strong></div>
              <div><span>Ground Truth</span><strong>${property.groundTruthVisitCount ? `${property.groundTruthAdjustmentPct > 0 ? "+" : ""}${Math.round(Number(property.groundTruthAdjustmentPct || 0))}%` : "No multiplier yet"}</strong></div>
              <div><span>Message Threads</span><strong>${Number(conversationSummary.threadCount || 0)} thread(s)</strong></div>
            </div>
            ${prospectusSignatureMarkup(property, duePct)}
          </article>

          <article class="bento-item prospectus-card prospectus-span-7">
            <div class="prospectus-section-kicker">Spatial Intelligence</div>
            <h2>2km Demand Heatmap Snapshot</h2>
            <div class="prospectus-map-shell">
              ${mapMarkup}
            </div>
          </article>

          <article class="bento-item prospectus-card prospectus-span-5">
            <div class="prospectus-section-kicker">Contact Matrix</div>
            <h2>Threaded Messaging Access</h2>
            <div class="prospectus-qr-shell">
              ${qrMarkup}
              <div class="prospectus-qr-copy">
                <strong>${escapeHtml(conversationThread?.subject || `${property.name} messaging thread`)}</strong>
                <span>Scan to open the live messaging surface for this property.</span>
                ${messageUrl ? `<small>${escapeHtml(messageUrl)}</small>` : `<small>Messaging link unavailable for this environment.</small>`}
              </div>
            </div>
            <div class="prospectus-mini-list">
              <div><span>Seller Contact</span><strong>${escapeHtml(property.ownerContact?.name || "Listing Desk")}</strong></div>
              <div><span>Email</span><strong>${escapeHtml(property.ownerContact?.email || "portfolio@sfcelerate.local")}</strong></div>
              <div><span>Phone</span><strong>${escapeHtml(property.ownerContact?.phone || "+63 917 555 0199")}</strong></div>
              <div><span>Response SLA</span><strong>${escapeHtml(property.ownerContact?.responseSla || "24 HOURS")}</strong></div>
            </div>
          </article>

          <article class="bento-item prospectus-card prospectus-span-12">
            <div class="prospectus-section-kicker">Indicator Audit</div>
            <h2>Investment Readiness Indicator Matrix</h2>
            <p class="prospectus-support-copy">Normalization and raw-value evidence for the IRIE layer, split into two print-safe audit tables for presentation and thesis defense.</p>
            ${prospectusAuditTablesMarkup(readiness)}
          </article>

          <article class="bento-item prospectus-card prospectus-span-6">
            <div class="prospectus-section-kicker">Ground Truth</div>
            <h2>Field Audit and Visit Logistics</h2>
            <div class="prospectus-mini-list">
              <div><span>Visit Status</span><strong>${escapeHtml(VISIT_STATUS_LABELS[String(visit?.status || "proposed").toLowerCase()] || "Proposed")}</strong></div>
              <div><span>Primary Window</span><strong>${escapeHtml(visitWindow ? formatVisitWindow(visitWindow) : "Awaiting scheduling")}</strong></div>
              <div><span>Latest Visit</span><strong>${escapeHtml(latestVisitLabel)}</strong></div>
              <div><span>IAI Multiplier</span><strong>${Number((visit?.groundTruthMultiplier ?? property.latestFieldAuditMultiplier ?? property.groundTruthMultiplier ?? 1)).toFixed(2)}x</strong></div>
            </div>
            <div class="prospectus-audit-metrics">
              <div><span>Neighborhood Vibe</span><strong>${escapeHtml(visitMetricLabel(fieldAudit.neighborhood_vibe))}</strong></div>
              <div><span>Utility Proximity</span><strong>${escapeHtml(visitMetricLabel(fieldAudit.utility_proximity))}</strong></div>
              <div><span>Expansion Feasibility</span><strong>${escapeHtml(visitMetricLabel(fieldAudit.expansion_feasibility))}</strong></div>
            </div>
            ${fieldAuditComplete && fieldAudit.notes ? `<div class="prospectus-note">${escapeHtml(fieldAudit.notes)}</div>` : `<div class="prospectus-note">Field audit unlocks after the on-site walkthrough and feeds back into the final ranking multiplier.</div>`}
          </article>

          <article class="bento-item prospectus-card prospectus-span-6">
            <div class="prospectus-section-kicker">Decision Context</div>
            <h2>Platform Signal Digest</h2>
            <div class="prospectus-mini-list">
              <div><span>Top Demand Signal</span><strong>${escapeHtml(voteLabel(topVoteEntry(votes)[0]))}</strong></div>
              <div><span>Vote Volume</span><strong>${totalVotes(votes)} votes</strong></div>
              <div><span>Climate Context</span><strong>${escapeHtml(weather?.summary || "Offline climate note")}</strong></div>
              <div><span>AI Brief</span><strong>${escapeHtml(summary?.headline || "Structured investment brief")}</strong></div>
            </div>
            <div class="prospectus-bullet-list">
              ${(summary?.takeaways?.length ? summary.takeaways : [
                lensResult.thesisShort || `${activeLens.label} thesis ready`,
                `${readiness.label || "Readiness"} at ${Math.round(Number(readiness.totalScore || 0))}%`,
                property.groundTruthVisitCount ? `Ground truth has been logged ${property.groundTruthVisitCount} time(s)` : "Ground truth still pending first visit",
              ]).slice(0, 4).map((item) => `<div>${escapeHtml(item)}</div>`).join("")}
            </div>
          </article>
        </section>

        <footer class="prospectus-footer">
          <span>Data Valid as of ${escapeHtml(formatProspectusTimestamp(generatedAt || new Date().toISOString()))}</span>
          <span>${escapeHtml(property.city || "San Fernando, La Union")} | ${escapeHtml(property.barangay || "Barangay pending")}</span>
          <span>LOCUS-SF Investment Brief</span>
        </footer>
      </article>
    </section>
  `;
}

function visitWindowMetaMarkup(label, window) {
  if (!window?.startAt || !window?.endAt) return "";
  return `
    <div class="visit-thread-window">
      <span>${escapeHtml(label)}</span>
      <strong>${escapeHtml(formatVisitWindow(window))}</strong>
    </div>
  `;
}

function visitActivityItems(visit) {
  if (!visit || !Array.isArray(visit.activity)) {
    return [];
  }

  return visit.activity.map((event, index) => ({
    ...event,
    timelineKind: "visit_event",
    timelineId: `visit-${visit.id || "timeline"}-${index}`,
  }));
}

function visitFieldAuditShell(visit, currentRole = role) {
  if (!visit || String(visit.status || "").toLowerCase() !== "visited") {
    return "";
  }

  const fieldAudit = visit.fieldAudit || {};
  const hasAudit = Boolean(visit.fieldAuditComplete);
  const canSubmitAudit = currentRole === "investor";

  return `
    <section class="field-audit-shell">
      <div class="field-audit-head">
        <div>
          <div class="panel-kicker">Field Audit</div>
          <h4>Ground Truth Multiplier</h4>
          <p>${hasAudit ? "The latest field observations are already feeding into the attractiveness score." : "The audit opens after the walkthrough to convert field notes into ranking signal."}</p>
        </div>
        ${hasAudit ? groundTruthPill({
          groundTruthVisitCount: 1,
          groundTruthAdjustmentPct: visit.groundTruthAdjustmentPct,
        }) : ""}
      </div>
      ${hasAudit ? `
        <div class="field-audit-summary">
          <div class="field-audit-metric"><span>Neighborhood Vibe</span><strong>${escapeHtml(visitMetricLabel(fieldAudit.neighborhood_vibe))}</strong></div>
          <div class="field-audit-metric"><span>Utility Proximity</span><strong>${escapeHtml(visitMetricLabel(fieldAudit.utility_proximity))}</strong></div>
          <div class="field-audit-metric"><span>Expansion Feasibility</span><strong>${escapeHtml(visitMetricLabel(fieldAudit.expansion_feasibility))}</strong></div>
        </div>
        ${fieldAudit.notes ? `<div class="field-audit-note">${escapeHtml(fieldAudit.notes)}</div>` : ""}
      ` : ""}
      ${canSubmitAudit ? `
        <form class="field-audit-form" data-visit-form="audit">
          <label class="field-audit-control">
            <span>Neighborhood Vibe</span>
            <input type="range" min="1" max="5" step="1" name="neighborhood_vibe" value="${Number(fieldAudit.neighborhood_vibe || 3)}" data-range-input>
            <strong data-range-output>${escapeHtml(visitMetricLabel(fieldAudit.neighborhood_vibe || 3))}</strong>
          </label>
          <label class="field-audit-control">
            <span>Utility Proximity</span>
            <input type="range" min="1" max="5" step="1" name="utility_proximity" value="${Number(fieldAudit.utility_proximity || 3)}" data-range-input>
            <strong data-range-output>${escapeHtml(visitMetricLabel(fieldAudit.utility_proximity || 3))}</strong>
          </label>
          <label class="field-audit-control">
            <span>Expansion Feasibility</span>
            <input type="range" min="1" max="5" step="1" name="expansion_feasibility" value="${Number(fieldAudit.expansion_feasibility || 3)}" data-range-input>
            <strong data-range-output>${escapeHtml(visitMetricLabel(fieldAudit.expansion_feasibility || 3))}</strong>
          </label>
          <label class="form-shell">
            <span>Field note</span>
            <textarea class="input-shell input-textarea" name="notes" placeholder="Capture what changed once boots hit the ground.">${escapeHtml(fieldAudit.notes || "")}</textarea>
          </label>
          <div class="visit-inline-actions">
            <button type="submit" class="btn-shell btn-shell-primary">${hasAudit ? "Update Field Audit" : "Submit Field Audit"}</button>
          </div>
        </form>
      ` : !hasAudit ? `<div class="auth-form-note">The investor will be prompted to submit the post-visit audit once the walkthrough is marked completed.</div>` : ""}
    </section>
  `;
}

function siteVisitStepperMarkup(visit, options = {}) {
  if (!visit) return "";

  const currentRole = String(options.currentRole || role || "guest").toLowerCase();
  const status = String(visit.status || "proposed").toLowerCase();
  const canManage = ["seller", "admin"].includes(currentRole);
  const canAcceptCounter = currentRole === "investor" && status === "counter_offered";
  const stepTwoActions = [];

  if (status === "proposed" && canManage) {
    stepTwoActions.push(`<button type="button" class="btn-shell btn-shell-primary visit-inline-button" data-visit-action="confirm" data-visit-selection="primary">Confirm Primary</button>`);
    if (visit.secondaryWindow?.startAt) {
      stepTwoActions.push(`<button type="button" class="btn-shell btn-shell-secondary visit-inline-button" data-visit-action="confirm" data-visit-selection="secondary">Confirm Secondary</button>`);
    }
  }
  if (canManage && ["proposed", "counter_offered", "confirmed"].includes(status)) {
    stepTwoActions.push(`<button type="button" class="btn-shell btn-shell-ghost visit-inline-button" data-visit-toggle="counter">Suggest New Time</button>`);
  }
  if (canAcceptCounter) {
    stepTwoActions.push(`<button type="button" class="btn-shell btn-shell-primary visit-inline-button" data-visit-action="acceptCounter">Accept Counter</button>`);
  }
  if (visit.activeWindow?.startAt) {
    stepTwoActions.push(`<button type="button" class="btn-shell btn-shell-secondary visit-inline-button" data-visit-ics>Add to iCal</button>`);
  }
  if (canManage && status === "confirmed") {
    stepTwoActions.push(`<button type="button" class="btn-shell btn-shell-primary visit-inline-button" data-visit-action="markInProgress">Mark In Progress</button>`);
  }
  if (canManage && status === "in_progress") {
    stepTwoActions.push(`<button type="button" class="btn-shell btn-shell-primary visit-inline-button" data-visit-action="markVisited">Mark Visited</button>`);
  }

  const stepOneClass = "done";
  const stepTwoClass = status === "visited"
    ? "done"
    : ["confirmed", "in_progress"].includes(status)
      ? "active"
      : "active";
  const stepThreeClass = visit.fieldAuditComplete ? "done" : status === "visited" ? "active" : "locked";

  const stepTwoCopy = status === "counter_offered"
    ? formatVisitWindow(visit.counterWindow)
    : status === "proposed"
      ? `Primary ${formatVisitWindow(visit.primaryWindow)}`
      : formatVisitWindow(visit.activeWindow);

  return `
    <div class="logistic-stepper">
      <div class="step ${stepOneClass}">
        <div class="step-marker">${stepOneClass === "done" ? "✓" : "1"}</div>
        <div class="step-text">
          <strong>Inquiry & Intent</strong>
          <span>Purpose: ${escapeHtml(visit.investmentPurpose || "Field diligence")}</span>
        </div>
      </div>

      <div class="step ${stepTwoClass}">
        <div class="step-marker">${stepTwoClass === "done" ? "✓" : "2"}</div>
        <div class="step-text">
          <strong>${status === "counter_offered" ? "Reschedule Proposed" : "Ground Truth Scheduled"}</strong>
          <span>${escapeHtml(stepTwoCopy)}</span>
          ${stepTwoActions.length ? `<div class="step-actions">${stepTwoActions.join("")}</div>` : ""}
        </div>
      </div>

      <div class="step ${stepThreeClass}">
        <div class="step-marker">${stepThreeClass === "done" ? "✓" : "3"}</div>
        <div class="step-text">
          <strong>Field Audit & IAI Adjustment</strong>
          <span>${visit.fieldAuditComplete ? `Multiplier ${Number(visit.groundTruthMultiplier || 1).toFixed(2)} applied` : status === "visited" ? "Audit now unlocked for the investor" : "Unlocks after visit completion"}</span>
        </div>
      </div>
    </div>
  `;
}

function logisticsHubMarkup({ property, visit, currentRole = role, counterMode = false, compact = false } = {}) {
  const propertyName = property?.name || visit?.propertyName || "Property";
  const currentRoleKey = String(currentRole || "guest").toLowerCase();
  const wrapperClass = compact ? "booking-orchestrator booking-orchestrator-compact" : "panel-card booking-orchestrator";
  const investorLabel = visit?.investorName && ["seller", "admin"].includes(currentRoleKey)
    ? `<div class="visit-meta-line">Investor: ${escapeHtml(visit.investorName)}</div>`
    : "";

  if (!visit) {
    if (currentRoleKey === "guest") {
      return `
        <article class="${wrapperClass}">
          <div class="panel-kicker">Logistics Hub</div>
          <h3>Ground Truth Orchestration</h3>
          <div class="auth-form-note">Investor login is required to nominate primary and backup site-visit windows.</div>
        </article>
      `;
    }

    if (currentRoleKey === "investor") {
      return `
        <article class="${wrapperClass}">
          <div class="orchestrator-header">
            <div>
              <div class="panel-kicker">Logistics Hub</div>
              <h3>Ground Truth Orchestration</h3>
              <p>Nominate a primary and secondary visit window so the seller can coordinate the field thesis around your investment purpose.</p>
            </div>
            ${visitStatusPill("proposed")}
          </div>
          <form class="visit-proposal-form" data-visit-form="proposal">
            <label class="form-shell form-span-2">
              <span>Investment Purpose</span>
              <select class="input-shell" name="investmentPurpose">
                ${visitPurposeOptions("")}
              </select>
            </label>
            <label class="form-shell">
              <span>Primary Start</span>
              <input class="input-shell" type="datetime-local" name="primaryStartAt" required>
            </label>
            <label class="form-shell">
              <span>Primary End</span>
              <input class="input-shell" type="datetime-local" name="primaryEndAt" required>
            </label>
            <label class="form-shell">
              <span>Secondary Start</span>
              <input class="input-shell" type="datetime-local" name="secondaryStartAt" required>
            </label>
            <label class="form-shell">
              <span>Secondary End</span>
              <input class="input-shell" type="datetime-local" name="secondaryEndAt" required>
            </label>
            <div class="visit-inline-actions form-span-2">
              <button type="submit" class="btn-shell btn-shell-primary">Request Site Visit</button>
            </div>
          </form>
        </article>
      `;
    }

    return `
      <article class="${wrapperClass}">
        <div class="panel-kicker">Logistics Hub</div>
        <h3>Ground Truth Orchestration</h3>
        <div class="auth-form-note">Awaiting an investor proposal with primary and backup windows.</div>
      </article>
    `;
  }

  return `
    <article class="${wrapperClass}">
      <div class="orchestrator-header">
        <div>
          <div class="panel-kicker">Logistics Hub</div>
          <h3>Site Visit: ${escapeHtml(propertyName)}</h3>
          <div class="visit-meta-line">Purpose: ${escapeHtml(visit.investmentPurpose || "Field diligence")}</div>
          ${investorLabel}
        </div>
        ${visitStatusPill(visit.status)}
      </div>
      ${siteVisitStepperMarkup(visit, { currentRole })}
      <div class="visit-window-grid">
        <div class="visit-window-card"><span>Primary Window</span><strong>${escapeHtml(formatVisitWindow(visit.primaryWindow))}</strong></div>
        <div class="visit-window-card"><span>Secondary Window</span><strong>${escapeHtml(formatVisitWindow(visit.secondaryWindow))}</strong></div>
        ${visit.counterWindow?.startAt ? `<div class="visit-window-card"><span>Counter Offer</span><strong>${escapeHtml(formatVisitWindow(visit.counterWindow))}</strong></div>` : ""}
        ${visit.confirmedWindow?.startAt ? `<div class="visit-window-card"><span>Confirmed Slot</span><strong>${escapeHtml(formatVisitWindow(visit.confirmedWindow))}</strong></div>` : ""}
      </div>
      ${counterMode && ["seller", "admin"].includes(currentRoleKey) ? `
        <form class="visit-counter-form" data-visit-form="counter">
          <label class="form-shell">
            <span>Counter Start</span>
            <input class="input-shell" type="datetime-local" name="counterStartAt" value="${escapeHtml(toDatetimeLocalValue(visit.counterWindow?.startAt || visit.confirmedWindow?.startAt || ""))}" required>
          </label>
          <label class="form-shell">
            <span>Counter End</span>
            <input class="input-shell" type="datetime-local" name="counterEndAt" value="${escapeHtml(toDatetimeLocalValue(visit.counterWindow?.endAt || visit.confirmedWindow?.endAt || ""))}" required>
          </label>
          <div class="visit-inline-actions form-span-2">
            <button type="submit" class="btn-shell btn-shell-primary">Send Counter Offer</button>
            <button type="button" class="btn-shell btn-shell-ghost" data-visit-cancel="counter">Cancel</button>
          </div>
        </form>
      ` : ""}
      ${visitFieldAuditShell(visit, currentRole)}
    </article>
  `;
}

function visitActivityCardMarkup(item, visit, currentRole) {
  const canAcceptCounter = String(currentRole || "").toLowerCase() === "investor"
    && String(visit?.status || "").toLowerCase() === "counter_offered"
    && String(item.kind || "").toLowerCase() === "counter_offered";

  return `
    <article class="chat-message visit-thread-card">
      <div class="chat-message-meta">
        <span>${escapeHtml(item.actorName || "Logistics Hub")}</span>
        <span>${escapeHtml(VISIT_STATUS_LABELS[String(item.status || "proposed").toLowerCase()] || titleCase(item.status || "proposed"))}</span>
      </div>
      <div class="visit-thread-card-title">${escapeHtml(item.title || "Visit update")}</div>
      <div class="chat-message-body">${escapeHtml(item.summary || "")}</div>
      <div class="visit-thread-window-stack">
        ${visitWindowMetaMarkup("Previous", item.previousWindow)}
        ${visitWindowMetaMarkup("Counter Offer", item.counterWindow)}
        ${visitWindowMetaMarkup("Confirmed", item.confirmedWindow)}
      </div>
      ${canAcceptCounter ? `
        <div class="visit-inline-actions">
          <button type="button" class="btn-shell btn-shell-primary visit-inline-button" data-visit-action="acceptCounter">Accept Counter</button>
        </div>
      ` : ""}
      <div class="chat-message-time">${escapeHtml(formatDateTime(item.createdAt))}</div>
    </article>
  `;
}

function conversationMessageMarkup(message, currentRole) {
  const messageRole = String(message.role || "investor").toLowerCase();
  const ownMessage = messageRole === String(currentRole || "").toLowerCase();
  return `
    <article class="chat-message ${ownMessage ? "is-own" : ""}">
      <div class="chat-message-meta">
        <span>${escapeHtml(message.senderName || "Platform User")}</span>
        <span>${escapeHtml(titleCase(message.role || "participant"))}</span>
      </div>
      <div class="chat-message-body">${escapeHtml(message.text || "")}</div>
      <div class="chat-message-time">${escapeHtml(formatDateTime(message.createdAt))}</div>
    </article>
  `;
}

function conversationBubbles(messages, currentRole = "investor", emptyCopy = "No messages yet. Start the conversation when you're ready.", visit = null) {
  const items = [
    ...(Array.isArray(messages) ? messages.map((message, index) => ({
      ...message,
      timelineKind: "message",
      timelineId: `message-${message.id || index}`,
    })) : []),
    ...visitActivityItems(visit),
  ].sort((left, right) => compareTimelineDate(left.createdAt) - compareTimelineDate(right.createdAt));

  if (!items.length) {
    return `<div class="loading-panel">${escapeHtml(emptyCopy)}</div>`;
  }

  return items.map((item) => (
    item.timelineKind === "visit_event"
      ? visitActivityCardMarkup(item, visit, currentRole)
      : conversationMessageMarkup(item, currentRole)
  )).join("");
}

function buildVisitIcsContent(visit, property = null) {
  const activeWindow = visit?.confirmedWindow || visit?.activeWindow;
  if (!activeWindow?.startAt || !activeWindow?.endAt) {
    return "";
  }

  const start = new Date(activeWindow.startAt);
  const end = new Date(activeWindow.endAt);
  if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) {
    return "";
  }

  const formatUtc = (date) => date.toISOString().replace(/[-:]/g, "").replace(/\.\d{3}Z$/, "Z");
  const propertyName = property?.name || visit?.propertyName || "Site Visit";
  const location = [
    property?.barangay || visit?.propertyBarangay || "",
    property?.city || visit?.propertyCity || "San Fernando, La Union",
  ].filter(Boolean).join(", ");
  const description = [
    `Investment Purpose: ${visit?.investmentPurpose || "Field diligence"}`,
    visit?.fieldAudit?.notes ? `Latest Notes: ${visit.fieldAudit.notes}` : "",
  ].filter(Boolean).join("\\n");

  return [
    "BEGIN:VCALENDAR",
    "VERSION:2.0",
    "PRODID:-//LOCUS-SF//Ground Truth Visit//EN",
    "BEGIN:VEVENT",
    `UID:sfc-visit-${visit?.id || "logistics"}@sfcelerate.local`,
    `DTSTAMP:${formatUtc(new Date())}`,
    `DTSTART:${formatUtc(start)}`,
    `DTEND:${formatUtc(end)}`,
    `SUMMARY:Site Visit - ${propertyName}`,
    `DESCRIPTION:${description}`,
    `LOCATION:${propertyName}${location ? `, ${location}` : ""}`,
    "END:VEVENT",
    "END:VCALENDAR",
  ].join("\r\n");
}

function downloadVisitIcs(visit, property = null) {
  const content = buildVisitIcsContent(visit, property);
  if (!content) return;

  const blob = new Blob([content], { type: "text/calendar;charset=utf-8" });
  const link = document.createElement("a");
  const slug = String((property?.name || visit?.propertyName || "site-visit"))
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "") || "site-visit";
  link.href = URL.createObjectURL(blob);
  link.download = `${slug}.ics`;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  window.setTimeout(() => URL.revokeObjectURL(link.href), 0);
}

function bindVisitInteractions(root, options = {}) {
  if (!root) return;

  const visit = options.visit || null;
  const property = options.property || null;
  const propertyId = Number(options.propertyId || property?.id || 0);
  const threadId = Number(options.threadId || visit?.threadId || 0);
  const setCounterMode = typeof options.setCounterMode === "function" ? options.setCounterMode : () => {};
  const onUpdated = typeof options.onUpdated === "function" ? options.onUpdated : async () => {};

  const applyUpdate = async (payload, create = false) => {
    try {
      const response = create ? await api.createVisitProposal(payload) : await api.updateVisit(payload);
      await onUpdated(response);
    } catch (error) {
      window.alert(error.message || "Unable to update site visit logistics.");
    }
  };

  root.querySelector("[data-visit-form='proposal']")?.addEventListener("submit", async (event) => {
    event.preventDefault();
    const formData = new FormData(event.currentTarget);
    await applyUpdate({
      propertyId,
      investmentPurpose: String(formData.get("investmentPurpose") || "").trim(),
      primaryStartAt: formData.get("primaryStartAt"),
      primaryEndAt: formData.get("primaryEndAt"),
      secondaryStartAt: formData.get("secondaryStartAt"),
      secondaryEndAt: formData.get("secondaryEndAt"),
    }, true);
  });

  root.querySelector("[data-visit-form='counter']")?.addEventListener("submit", async (event) => {
    event.preventDefault();
    if (!visit) return;
    const formData = new FormData(event.currentTarget);
    await applyUpdate({
      visitId: visit.id,
      threadId,
      action: "counterOffer",
      counterStartAt: formData.get("counterStartAt"),
      counterEndAt: formData.get("counterEndAt"),
    });
  });

  root.querySelector("[data-visit-form='audit']")?.addEventListener("submit", async (event) => {
    event.preventDefault();
    if (!visit) return;
    const formData = new FormData(event.currentTarget);
    await applyUpdate({
      visitId: visit.id,
      threadId,
      action: "submitAudit",
      neighborhood_vibe: Number(formData.get("neighborhood_vibe") || 0),
      utility_proximity: Number(formData.get("utility_proximity") || 0),
      expansion_feasibility: Number(formData.get("expansion_feasibility") || 0),
      notes: String(formData.get("notes") || "").trim(),
    });
  });

  root.querySelectorAll("[data-visit-toggle='counter']").forEach((button) => {
    button.addEventListener("click", () => {
      setCounterMode(true);
    });
  });
  root.querySelectorAll("[data-visit-cancel='counter']").forEach((button) => {
    button.addEventListener("click", () => {
      setCounterMode(false);
    });
  });

  root.querySelectorAll("[data-visit-action]").forEach((button) => {
    button.addEventListener("click", async () => {
      if (!visit) return;
      const action = String(button.dataset.visitAction || "").trim();
      if (!action) return;
      const payload = {
        visitId: visit.id,
        threadId,
        action,
      };
      if (button.dataset.visitSelection) {
        payload.selection = button.dataset.visitSelection;
      }
      await applyUpdate(payload);
    });
  });

  root.querySelectorAll("[data-visit-ics]").forEach((button) => {
    button.addEventListener("click", () => {
      downloadVisitIcs(visit, property);
    });
  });

  root.querySelectorAll("[data-range-input]").forEach((input) => {
    const output = input.parentElement?.querySelector("[data-range-output]");
    const sync = () => {
      if (output) {
        output.textContent = visitMetricLabel(input.value);
      }
    };
    sync();
    input.addEventListener("input", sync);
  });
}

function conversationThreadList(threads, activeThreadId, emptyCopy = "Conversations appear here once investors start messaging.") {
  if (!Array.isArray(threads) || !threads.length) {
    return `<div class="loading-panel">${escapeHtml(emptyCopy)}</div>`;
  }

  return threads.map((thread) => `
    <button type="button" class="thread-card ${Number(thread.id) === Number(activeThreadId) ? "is-active" : ""}" data-thread-open="${thread.id}">
      <div class="thread-card-top">
        <strong>${escapeHtml(thread.propertyName || "Property conversation")}</strong>
        <span>${escapeHtml(formatDate(thread.lastMessageAt || thread.updatedAt))}</span>
      </div>
      <div class="thread-card-meta">
        <span>${escapeHtml(thread.investorName || "Investor")}</span>
        <span>${thread.messageCount || 0} messages</span>
      </div>
      <p>${escapeHtml(truncate(thread.lastMessageText || thread.subject || "Open this thread to reply.", 82))}</p>
    </button>
  `).join("");
}

function adminThreadParticipants(thread) {
  const roster = [
    {
      role: "investor",
      id: Number(thread?.investorUserId || 0) || null,
      name: thread?.investorName || "Investor",
      email: thread?.investorEmail || "",
    },
    {
      role: "seller",
      id: Number(thread?.sellerUserId || 0) || null,
      name: thread?.sellerName || "Seller",
      email: thread?.sellerEmail || "",
    },
  ];

  const seen = new Set();
  return roster.filter((entry) => {
    const id = Number(entry.id || 0);
    if (!id || seen.has(id)) return false;
    seen.add(id);
    return true;
  });
}

function adminThreadRecipientOptions(thread, selectedUserId = null) {
  return adminThreadParticipants(thread).map((participant) => {
    const optionLabel = `${titleCase(participant.role)} - ${participant.name}${participant.email ? ` (${participant.email})` : ""}`;
    return `<option value="${participant.id}" ${Number(participant.id) === Number(selectedUserId) ? "selected" : ""}>${escapeHtml(optionLabel)}</option>`;
  }).join("");
}

function auditBadgeClass(badge) {
  return {
    CRITICAL: "badge-critical",
    VERIFIED: "badge-verified",
    MODERATED: "badge-moderated",
    TRACE: "badge-trace",
  }[String(badge || "").toUpperCase()] || "badge-trace";
}

function auditScopeLabel(scope) {
  return {
    all: "All Events",
    financials: "Financials",
    moderation: "Moderation",
  }[String(scope || "").toLowerCase()] || "All Events";
}

function auditFieldLabel(path) {
  return String(path || "payload")
    .replace(/\./g, " / ")
    .replace(/([a-z])([A-Z])/g, "$1 $2")
    .replace(/[_-]+/g, " ")
    .replace(/\b\w/g, (char) => char.toUpperCase());
}

function auditFieldKey(path) {
  return String(path || "")
    .replace(/[.\s_-]+/g, "")
    .toLowerCase();
}

function auditDiffPriority(path) {
  const normalized = auditFieldKey(path);
  if (normalized.includes("approvalstate")) return 0;
  if (normalized.includes("pricepersqm")) return 1;
  if (normalized === "price" || normalized.endsWith("price")) return 2;
  if (normalized.includes("votes")) return 3;
  if (normalized.includes("messagecount")) return 4;
  if (normalized.includes("roadaccess")) return 5;
  if (normalized.includes("zoningscore")) return 6;
  if (normalized.includes("utilitystatus")) return 7;
  if (normalized.endsWith("status")) return 8;
  return 20;
}

function auditPrimaryDiff(entry) {
  const diffs = auditEntryDiffs(entry);
  if (!diffs.length) return null;

  return [...diffs].sort((left, right) => (
    auditDiffPriority(left.path) - auditDiffPriority(right.path)
    || String(left.path).localeCompare(String(right.path))
  ))[0];
}

function auditEntrySummary(entry, fallback = "") {
  const diff = auditPrimaryDiff(entry);
  if (!diff) {
    return entry?.summary || fallback;
  }

  return `${auditFieldLabel(diff.path)} changed from ${auditValuePreview(diff.before, diff.path)} to ${auditValuePreview(diff.after, diff.path)}.`;
}

function auditEntryTitle(entry) {
  const beforeState = entry?.metadata?.before || {};
  const afterState = entry?.metadata?.after || {};

  return afterState?.name
    || beforeState?.name
    || entry?.targetLabel
    || "Select a log entry";
}

function auditValuePreview(value, path = "") {
  const normalized = auditFieldKey(path);

  if (value == null || value === "") return "Empty";

  if (normalized.includes("pricepersqm") || normalized.includes("assessedvaluesqm")) {
    return `${money(value)} / sqm`;
  }

  if (normalized === "price" || normalized.endsWith("price")) {
    return money(value);
  }

  if (normalized.includes("approvalstate")) {
    return APPROVAL_LABELS[String(value || "").toLowerCase()] || titleCase(value);
  }

  if (normalized.includes("utilitystatus") || normalized.endsWith("status")) {
    return titleCase(value);
  }

  if (normalized.includes("votes")) {
    const numeric = Number(value || 0);
    return `${numeric.toLocaleString()} vote${numeric === 1 ? "" : "s"}`;
  }

  if (normalized.includes("messagecount") || normalized.includes("messagescleared")) {
    const numeric = Number(value || 0);
    return `${numeric.toLocaleString()} message${numeric === 1 ? "" : "s"}`;
  }

  if (normalized.includes("selectedvoteoptionid")) {
    return `Option #${Number(value || 0)}`;
  }

  if (normalized.includes("disttoroadkm")) {
    const numeric = Number(value || 0);
    return `${numeric.toLocaleString(undefined, { maximumFractionDigits: 2 })} km`;
  }

  if (typeof value === "number") {
    return Number.isInteger(value)
      ? value.toLocaleString()
      : value.toLocaleString(undefined, { maximumFractionDigits: 2 });
  }

  if (typeof value === "boolean") return value ? "True" : "False";

  if (Array.isArray(value)) {
    return value.length ? truncate(value.join(", "), 72) : "Empty";
  }

  if (typeof value === "object") {
    return truncate(JSON.stringify(value), 72);
  }

  return truncate(String(value), 72);
}

function auditDiffEntries(before, after, path = "") {
  const leftIsObject = before && typeof before === "object" && !Array.isArray(before);
  const rightIsObject = after && typeof after === "object" && !Array.isArray(after);

  if (leftIsObject || rightIsObject) {
    const leftValue = leftIsObject ? before : {};
    const rightValue = rightIsObject ? after : {};
    const keys = Array.from(new Set([...Object.keys(leftValue), ...Object.keys(rightValue)]));
    return keys.flatMap((key) => auditDiffEntries(leftValue[key], rightValue[key], path ? `${path}.${key}` : key));
  }

  if (JSON.stringify(before) === JSON.stringify(after)) {
    return [];
  }

  return [{
    path: path || "payload",
    before,
    after,
  }];
}

function auditEntryDiffs(entry) {
  return auditDiffEntries(entry?.metadata?.before || {}, entry?.metadata?.after || {});
}

function auditActionMarkup(entry) {
  const diffs = auditEntryDiffs(entry);
  const diff = auditPrimaryDiff(entry);
  if (!diff) {
    return `<div class="audit-action-block"><div class="audit-action-foot">${escapeHtml(auditEntrySummary(entry, "No structured diff captured."))}</div></div>`;
  }

  const additionalChanges = Math.max(0, diffs.length - 1);

  return `
    <div class="audit-action-block">
      <div class="audit-action-line">
        <span class="audit-field-chip">${escapeHtml(auditFieldLabel(diff.path))}</span>
        <div class="audit-diff-inline">
          <span class="diff-old">${escapeHtml(auditValuePreview(diff.before, diff.path))}</span>
          <span class="audit-diff-arrow">to</span>
          <span class="diff-new">${escapeHtml(auditValuePreview(diff.after, diff.path))}</span>
        </div>
      </div>
      <div class="audit-action-foot">
        ${additionalChanges ? `<span class="audit-change-count">+${additionalChanges} more change${additionalChanges === 1 ? "" : "s"}</span>` : escapeHtml(auditEntrySummary(entry, "Structured change captured."))}
      </div>
    </div>
  `;
}

function governanceTimelineMarkup(logs) {
  const timelineLogs = logs.filter((entry) => (
    entry.entityType === "MESSAGE"
    || entry.badge === "CRITICAL"
    || entry.badge === "MODERATED"
    || entry.scope === "moderation"
  ));
  const visible = (timelineLogs.length ? timelineLogs : logs).slice(0, 6);

  if (!visible.length) {
    return `<div class="loading-panel">Message moderation and dispute events will appear here once operators intervene.</div>`;
  }

  return visible.map((entry) => `
    <article class="audit-activity-item ${String(entry.badge || "").toLowerCase()}">
      <div class="audit-activity-marker"></div>
      <div class="audit-activity-copy">
        <div class="audit-activity-topline">
          <span class="audit-feed-badge ${auditBadgeClass(entry.badge)}">${escapeHtml(entry.badge || "TRACE")}</span>
          <span class="mono">${escapeHtml(formatDateTime(entry.createdAt))}</span>
        </div>
        <strong>${escapeHtml(entry.eventType || entry.actionType || "TRACE")}</strong>
        <p>${escapeHtml(auditEntrySummary(entry, "System trace recorded."))}</p>
        <span class="mono">${escapeHtml(entry.targetLabel || `${entry.entityType}: #${entry.entityId}`)}</span>
      </div>
    </article>
  `).join("");
}

function auditDrawerMarkup(entry) {
  const diffs = entry ? auditEntryDiffs(entry) : [];
  const beforeState = entry?.metadata?.before || {};
  const afterState = entry?.metadata?.after || {};

  return `
    <div class="audit-drawer-shell ${entry ? "is-open" : ""}">
      <button type="button" class="audit-drawer-backdrop" data-audit-close aria-label="Close audit diff drawer"></button>
      <aside class="audit-drawer">
        <div class="audit-drawer-head">
          <div>
            <div class="panel-kicker">Audit Diff</div>
            <h3>${escapeHtml(auditEntryTitle(entry))}</h3>
            <p>${escapeHtml(auditEntrySummary(entry, "Open any log row to inspect its before/after state."))}</p>
          </div>
          <button type="button" class="btn-shell btn-shell-secondary" data-audit-close>Close</button>
        </div>

        ${entry ? `
          <div class="audit-drawer-meta">
            <span class="audit-feed-badge ${auditBadgeClass(entry.badge)}">${escapeHtml(entry.badge || "TRACE")}</span>
            <span class="mono">${escapeHtml(formatDateTime(entry.createdAt))}</span>
            <span class="mono">${escapeHtml(entry.actorName || "System")}</span>
            <span class="mono">${escapeHtml(entry.targetLabel || `${entry.entityType}: #${entry.entityId}`)}</span>
          </div>

          <div class="audit-diff-list">
            ${diffs.length ? diffs.slice(0, 24).map((diff) => `
              <div class="audit-diff-row">
                <span class="audit-diff-field">${escapeHtml(auditFieldLabel(diff.path))}</span>
                <div class="audit-diff-values">
                  <span class="diff-old">${escapeHtml(auditValuePreview(diff.before, diff.path))}</span>
                  <span class="audit-diff-arrow">-></span>
                  <span class="diff-new">${escapeHtml(auditValuePreview(diff.after, diff.path))}</span>
                </div>
              </div>
            `).join("") : `<div class="loading-panel">No structured before/after payload was attached to this entry.</div>`}
          </div>

          <div class="audit-json-grid">
            <article class="audit-json-card">
              <div class="panel-kicker">Before</div>
              <pre>${escapeHtml(JSON.stringify(beforeState, null, 2))}</pre>
            </article>
            <article class="audit-json-card">
              <div class="panel-kicker">After</div>
              <pre>${escapeHtml(JSON.stringify(afterState, null, 2))}</pre>
            </article>
          </div>
        ` : ""}
      </aside>
    </div>
  `;
}

async function initAdminDashboard() {
  const root = document.getElementById("adminDashboardRoot");
  if (!root) return;
  window.clearInterval(root._auditStreamTimer);

  const bootstrap = await api.bootstrap();
  const properties = bootstrap.properties || [];
  const [votesMap, inquiryMap, inboxResponse, sellerQueueResponse] = await Promise.all([
    loadVoteTallies(properties),
    loadInquiryCounts(properties),
    api.getMessageInbox().catch(() => ({ threads: [] })),
    api.sellerReviewQueue().catch(() => ({ profiles: [], summary: {} })),
  ]);
  const enriched = enrichProperties(properties, properties, votesMap);
  let inboxThreads = Array.isArray(inboxResponse?.threads) ? inboxResponse.threads : [];
  let sellerProfiles = Array.isArray(sellerQueueResponse?.profiles) ? sellerQueueResponse.profiles : [];
  let sellerSummary = {
    total: Number(sellerQueueResponse?.summary?.total || sellerProfiles.length || 0),
    pendingReview: Number(sellerQueueResponse?.summary?.pendingReview || 0),
    verified: Number(sellerQueueResponse?.summary?.verified || 0),
    rejected: Number(sellerQueueResponse?.summary?.rejected || 0),
    suspended: Number(sellerQueueResponse?.summary?.suspended || 0),
    draft: Number(sellerQueueResponse?.summary?.draft || 0),
  };

  const totalVotesCount = Object.values(votesMap).reduce((sum, votes) => sum + totalVotes(votes), 0);
  const totalInquiries = Object.values(inquiryMap).reduce((sum, count) => sum + Number(count || 0), 0);
  const topDemand = aggregateVoteLabels(votesMap)[0] || ["No demand yet", 0];
  let activeThreadId = Number(inboxThreads[0]?.id || 0) || null;
  let activeThreadMessages = [];
  let activeThreadVisit = null;
  let adminReplyRecipientUserId = null;
  let adminVisitCounterMode = false;
  let auditScope = "all";
  let auditLogs = [];
  let selectedAuditId = null;
  let auditLatestId = 0;
  let liveMode = true;

  const sortThreadsByRecent = (threads) => [...threads].sort((left, right) => (
    compareTimelineDate(right?.lastMessageAt || right?.updatedAt || right?.createdAt)
      - compareTimelineDate(left?.lastMessageAt || left?.updatedAt || left?.createdAt)
  ));
  inboxThreads = sortThreadsByRecent(inboxThreads);
  activeThreadId = Number(inboxThreads[0]?.id || 0) || null;

  const syncAdminReplyRecipient = (thread, messages = activeThreadMessages) => {
    const participants = adminThreadParticipants(thread);
    const participantIds = participants.map((participant) => Number(participant.id || 0)).filter(Boolean);
    if (!participantIds.length) {
      adminReplyRecipientUserId = null;
      return;
    }

    const currentRecipientId = Number(adminReplyRecipientUserId || 0);
    if (currentRecipientId && participantIds.includes(currentRecipientId)) {
      return;
    }

    const preferredSenderId = [...(Array.isArray(messages) ? messages : [])]
      .reverse()
      .find((message) => String(message?.role || "").toLowerCase() !== "admin"
        && participantIds.includes(Number(message?.senderUserId || 0)));

    adminReplyRecipientUserId = Number(preferredSenderId?.senderUserId || 0)
      || Number(thread?.sellerUserId || 0)
      || Number(thread?.investorUserId || 0)
      || participantIds[0];
  };

  const loadAdminThread = async (threadId) => {
    activeThreadId = Number(threadId || 0) || null;
    adminVisitCounterMode = false;
    if (!activeThreadId) {
      activeThreadMessages = [];
      activeThreadVisit = null;
      adminReplyRecipientUserId = null;
      return;
    }

    const cachedThread = inboxThreads.find((thread) => Number(thread.id) === Number(activeThreadId)) || null;
    const response = await api.getThread(activeThreadId).catch(() => ({ thread: cachedThread, messages: [], visit: null }));
    const refreshedThread = response?.thread || cachedThread;

    if (refreshedThread) {
      inboxThreads = sortThreadsByRecent(inboxThreads.map((thread) => (
        Number(thread.id) === Number(activeThreadId) ? { ...thread, ...refreshedThread } : thread
      )));
    }

    activeThreadMessages = Array.isArray(response?.messages) ? response.messages : [];
    activeThreadVisit = response?.visit || null;
    syncAdminReplyRecipient(refreshedThread, activeThreadMessages);
  };

  const syncAuditSelection = () => {
    if (selectedAuditId === null) {
      return;
    }

    if (!auditLogs.some((entry) => Number(entry.id) === Number(selectedAuditId))) {
      selectedAuditId = null;
    }
  };

  const mergeAuditLogs = (incoming) => {
    const merged = [...incoming, ...auditLogs];
    const deduped = [];
    const seen = new Set();
    merged.forEach((entry) => {
      const key = Number(entry?.id || 0);
      if (!key || seen.has(key)) return;
      seen.add(key);
      deduped.push(entry);
    });
    auditLogs = deduped.slice(0, 90);
    auditLatestId = Math.max(auditLatestId, ...auditLogs.map((entry) => Number(entry.id || 0)));
    syncAuditSelection();
  };

  const loadAuditLogs = async ({ stream = false } = {}) => {
    const response = await api.auditLogs({
      limit: stream ? 20 : 60,
      scope: auditScope,
      afterId: stream ? auditLatestId : undefined,
    }).catch(() => ({ logs: [], latestId: auditLatestId }));

    if (stream) {
      const incoming = Array.isArray(response.logs) ? response.logs : [];
      if (incoming.length) {
        mergeAuditLogs(incoming);
      }
      auditLatestId = Math.max(auditLatestId, Number(response.latestId || 0));
      return incoming.length > 0;
    }

    auditLogs = Array.isArray(response.logs) ? response.logs : [];
    auditLatestId = Math.max(Number(response.latestId || 0), ...auditLogs.map((entry) => Number(entry.id || 0)), 0);
    syncAuditSelection();
    return true;
  };

  const selectedAudit = () => auditLogs.find((entry) => Number(entry.id) === Number(selectedAuditId)) || null;

  const render = () => {
    const activeThread = inboxThreads.find((thread) => Number(thread.id) === Number(activeThreadId)) || null;
    const activeThreadProperty = properties.find((property) => Number(property.id) === Number(activeThread?.propertyId)) || null;
    const activeThreadParticipants = adminThreadParticipants(activeThread);
    const selectedRecipient = activeThreadParticipants.find((participant) => (
      Number(participant.id) === Number(adminReplyRecipientUserId)
    )) || activeThreadParticipants[0] || null;
    const activeAudit = selectedAudit();
    const pendingSellerProfiles = sellerProfiles.filter((profile) => sellerApplicationStatus(profile) === "pending_review");
    const sellerRosterPreview = (pendingSellerProfiles.length ? pendingSellerProfiles : sellerProfiles).slice(0, 4);
    root.innerHTML = `
      <div class="stat-grid">
        <article class="stat-card"><div class="panel-kicker">Live listings</div><strong>${properties.length}</strong><p>Total active property records in the platform.</p></article>
        <article class="stat-card"><div class="panel-kicker">Seller applications</div><strong>${sellerSummary.total || 0}</strong><p>${sellerSummary.pendingReview || 0} seller account(s) are currently waiting for review.</p></article>
        <article class="stat-card"><div class="panel-kicker">Votes</div><strong>${totalVotesCount}</strong><p>Total business demand signals across properties.</p></article>
        <article class="stat-card"><div class="panel-kicker">Inquiries</div><strong>${totalInquiries}</strong><p>Conversation activity tied to listings.</p></article>
      </div>

      <div class="panel-grid" style="grid-template-columns:repeat(2,minmax(0,1fr));">
        <article class="panel-card leaderboard-shell">
          <div class="panel-kicker">Top ranked listings</div>
          <h3>What deserves attention now</h3>
          <div class="leaderboard-list">${leaderboardRows(enriched, 4)}</div>
        </article>

        <article class="panel-card">
          <div class="panel-kicker">Seller review queue</div>
          <h3>${pendingSellerProfiles.length ? "Seller accounts waiting on admin approval" : "Seller roster overview"}</h3>
          <div class="mini-list">
            ${sellerRosterPreview.length ? `
              ${sellerRosterPreview.map((profile) => `
                <div class="mini-row">
                  <span>${icon("user")}${escapeHtml(profile.displayName || profile.legalName || profile.name || profile.email || "Seller")}</span>
                  <strong>${escapeHtml(VERIFICATION_LABELS[String(profile.applicationStatus || "draft").toLowerCase()] || titleCase(profile.applicationStatus || "draft"))}</strong>
                </div>
              `).join("")}
              <div class="mini-row"><span>${icon("shield")}Verified sellers</span><strong>${sellerSummary.verified || 0}</strong></div>
              <div class="mini-row"><span>${icon("clock")}Pending reviews</span><strong>${sellerSummary.pendingReview || 0}</strong></div>
              <div class="mini-row"><span>${icon("file")}Needs revision</span><strong>${sellerSummary.rejected || 0}</strong></div>
            ` : `<div class="loading-panel">Seller applications will appear here once accounts start registering through the seller workspace.</div>`}
          </div>
        </article>

        <article class="panel-card">
          <div class="panel-kicker">Investor / Resident activity</div>
          <h3>Demand and response</h3>
          <div class="mini-list">
            <div class="mini-row"><span>${icon("vote")}Leading need</span><strong>${escapeHtml(voteLabel(topDemand[0]))}</strong></div>
            <div class="mini-row"><span>${icon("pulse")}Total votes</span><strong>${totalVotesCount}</strong></div>
            <div class="mini-row"><span>${icon("inbox")}Total inquiries</span><strong>${totalInquiries}</strong></div>
          </div>
        </article>

        <article class="panel-card">
          <div class="panel-kicker">Conversation monitor</div>
          <h3>${inboxThreads.length ? "Recent direct chats" : "No direct chats yet"}</h3>
          <div class="mini-list">
            ${inboxThreads.length ? inboxThreads.slice(0, 4).map((thread) => `
              <div class="mini-row"><span>${icon("inbox")}${escapeHtml(thread.propertyName || "Property")}</span><strong>${escapeHtml(thread.investorName || "Investor")}</strong></div>
            `).join("") : `<div class="loading-panel">Direct investor-to-seller threads will appear here for admin monitoring.</div>`}
          </div>
        </article>

        <article class="panel-card">
          <div class="panel-kicker">Quick actions</div>
          <h3>Admin control surfaces</h3>
          <div class="property-actions">
            <a href="${adminPropertyHref()}" class="btn-shell btn-shell-primary">Manage Listings</a>
            <a href="${compareHref()}" class="btn-shell btn-shell-secondary">Review Compare</a>
            <a href="${votingHref(enriched[0]?.id || 1)}" class="btn-shell btn-shell-ghost">Open Voting</a>
          </div>
        </article>
      </div>

      <article class="panel-card seller-inbox-card">
        <div class="panel-kicker">Admin inbox</div>
        <h3>${inboxThreads.length ? "Moderate, guide, and reply inside active listing threads" : "No direct chats yet"}</h3>
        <div class="seller-inbox-grid">
          <div class="thread-list">
            ${conversationThreadList(inboxThreads, activeThreadId, "Investor-to-seller conversations will appear here once property threads become active.")}
          </div>
          <div class="thread-view">
            ${activeThread ? `
              <div class="thread-view-head">
                <strong>${escapeHtml(activeThread.propertyName || "Property conversation")}</strong>
                <span>${Number(activeThread.messageCount || activeThreadMessages.length || 0)} messages</span>
              </div>
              <div class="property-stat-row">
                <span>${icon("user")}Investor: ${escapeHtml(activeThread.investorName || "Investor")}</span>
                <span>${icon("shield")}Seller: ${escapeHtml(activeThread.sellerName || "Seller")}</span>
                <span>${icon("clock")}${escapeHtml(formatDate(activeThread.lastMessageAt || activeThread.updatedAt))}</span>
              </div>
              ${logisticsHubMarkup({
                property: activeThreadProperty,
                visit: activeThreadVisit,
                currentRole: "admin",
                counterMode: adminVisitCounterMode,
                compact: true,
              })}
              <div class="chat-thread-surface">
                ${conversationBubbles(activeThreadMessages, "admin", "This thread is ready for an admin reply.", activeThreadVisit)}
              </div>
              <form class="thread-compose" id="adminThreadReplyForm">
                <label class="form-shell">
                  <span>Send to</span>
                  <select class="input-shell" id="adminThreadReplyRecipient">
                    ${adminThreadRecipientOptions(activeThread, selectedRecipient?.id || adminReplyRecipientUserId)}
                  </select>
                </label>
                <textarea class="input-shell input-textarea" id="adminThreadReplyInput" placeholder="Reply as admin with guidance, moderation direction, scheduling support, or escalation notes."></textarea>
                <div class="property-actions">
                  <button type="submit" class="btn-shell btn-shell-primary">Send Admin Reply</button>
                </div>
                <div class="auth-form-note">Admin replies post into the shared property thread and can be directed to either participant on the conversation.</div>
              </form>
            ` : `<div class="loading-panel">Select a conversation to message the investor or seller and keep the listing thread moving.</div>`}
          </div>
        </div>
      </article>

      <article class="panel-card">
        <div class="panel-kicker">Seller verification</div>
        <h3>${sellerProfiles.length ? "Review and decide seller applications" : "No seller applications yet"}</h3>
        <div class="listing-stack">
          ${sellerProfiles.length ? sellerProfiles.map((profile) => `
            <article class="listing-row">
              <div class="listing-main">
                <div class="property-title">${escapeHtml(profile.displayName || profile.legalName || profile.name || profile.email || "Seller")}</div>
                <div class="property-subline">${escapeHtml(profile.email || "No email")} | ${escapeHtml(titleCase(profile.sellerType || "individual"))} | ${escapeHtml(profile.city || "San Fernando, La Union")}</div>
                <p>${escapeHtml(profile.authorizationBasis || profile.addressLine || "Seller profile is waiting for more operational notes.")}</p>
                <div class="property-stat-row">
                  <span>${icon("user")}${escapeHtml(profile.phone || "No phone yet")}</span>
                  <span>${icon("file")}${escapeHtml(profile.governmentIdNo || "No ID yet")}</span>
                  <span>${icon("ranking")}${Number(profile.listingCount || 0)} listings</span>
                  <span>${icon("clock")}${escapeHtml(formatDate(profile.submittedAt || profile.updatedAt || profile.createdAt))}</span>
                </div>
                <div class="listing-meta-row">
                  ${verificationPill(profile.applicationStatus || "draft")}
                  ${metaChip(`${Number(profile.pendingListingCount || 0)} pending listing(s)`)}
                  ${metaChip(profile.companyName || profile.legalName || "Seller profile")}
                </div>
                ${profile.reviewNotes ? `<div class="auth-form-note" style="margin-top:12px;">Latest review note: ${escapeHtml(profile.reviewNotes)}</div>` : ""}
                <form class="crud-form-grid" data-seller-review-form="${profile.userId}" style="margin-top:18px;">
                  <label class="form-shell">
                    <span>Decision</span>
                    <select class="input-shell" name="status" data-seller-review-status-select>
                      <option value="verified" ${String(profile.applicationStatus || "").toLowerCase() === "verified" ? "selected" : ""}>Approve</option>
                      <option value="pending_review" ${String(profile.applicationStatus || "").toLowerCase() === "pending_review" ? "selected" : ""}>Keep pending</option>
                      <option value="rejected" ${String(profile.applicationStatus || "").toLowerCase() === "rejected" ? "selected" : ""}>Needs revision</option>
                      <option value="suspended" ${String(profile.applicationStatus || "").toLowerCase() === "suspended" ? "selected" : ""}>Suspend</option>
                    </select>
                  </label>
                  <label class="form-shell form-span-2">
                    <span>Review note</span>
                    <input class="input-shell" name="reviewNotes" value="${escapeHtml(profile.reviewNotes || "")}" placeholder="Tell the seller what to fix or confirm.">
                  </label>
                  <div class="crud-actions form-span-2">
                    <button type="submit" class="btn-shell btn-shell-primary" data-seller-review-submit-button>${escapeHtml(adminSellerReviewButtonLabel(profile.applicationStatus || "draft"))}</button>
                    <a href="mailto:${escapeHtml(profile.email || "")}" class="btn-shell btn-shell-secondary">Email Seller</a>
                  </div>
                  <div class="auth-form-note form-span-2" data-seller-review-status-note ${sellerReviewStatusNote(profile.applicationStatus || "draft", "admin") ? "" : "hidden"}>
                    ${sellerReviewStatusNote(profile.applicationStatus || "draft", "admin")
                      ? `<strong>${escapeHtml(adminSellerReviewButtonLabel(profile.applicationStatus || "draft"))}.</strong> ${escapeHtml(sellerReviewStatusNote(profile.applicationStatus || "draft", "admin"))}`
                      : ""}
                  </div>
                </form>
              </div>
              <div class="listing-actions">
                <div class="mini-list">
                  <div class="mini-row"><span>${icon("shield")}Identity</span><strong>${escapeHtml(VERIFICATION_LABELS[String(profile.identityVerificationStatus || "unverified").toLowerCase()] || titleCase(profile.identityVerificationStatus || "unverified"))}</strong></div>
                  <div class="mini-row"><span>${icon("user")}Legal name</span><strong>${escapeHtml(profile.legalName || profile.name || "Seller")}</strong></div>
                  <div class="mini-row"><span>${icon("map")}Address</span><strong>${escapeHtml(profile.barangay || profile.city || "Not set")}</strong></div>
                  <div class="mini-row"><span>${icon("compare")}Business reg</span><strong>${escapeHtml(profile.businessRegistrationNo || "Not supplied")}</strong></div>
                </div>
              </div>
            </article>
          `).join("") : emptyState("No seller applications yet", "New seller registrations will appear here once someone creates a seller account and submits verification details.", "", "")}
        </div>
      </article>

      <section class="governance-grid">
        <section class="governance-terminal">
          <header class="terminal-header">
            <div class="terminal-title"><span class="pulse-red"></span>SYSTEM_LOG_LEVEL: TRACE</div>
            <div class="filter-bar">
              ${["all", "financials", "moderation"].map((scope) => `
                <button type="button" class="filter-pill ${auditScope === scope ? "active" : ""}" data-audit-scope="${scope}">${escapeHtml(auditScopeLabel(scope))}</button>
              `).join("")}
              <button type="button" class="filter-pill ${liveMode ? "active is-live" : ""}" data-audit-live>${liveMode ? "Live Stream On" : "Live Stream Off"}</button>
            </div>
          </header>

          <div class="ledger-table-wrapper">
            <table class="ledger-table">
              <thead>
                <tr>
                  <th>TIMESTAMP [UTC]</th>
                  <th>ACTOR</th>
                  <th>EVENT_TYPE</th>
                  <th>TARGET_ENTITY</th>
                  <th>ACTION</th>
                </tr>
              </thead>
              <tbody>
                ${auditLogs.length ? auditLogs.map((entry) => `
                  <tr class="log-entry ${entry.badge === "CRITICAL" ? "high-priority" : ""} ${Number(entry.id) === Number(selectedAuditId) ? "is-selected" : ""}" data-audit-open="${entry.id}">
                    <td class="mono">${escapeHtml(entry.createdAt || "")}</td>
                    <td class="user-cell"><span class="audit-user-avatar">${escapeHtml(String(entry.actorName || "S").slice(0, 1).toUpperCase())}</span>${escapeHtml(entry.actorName || "System")}</td>
                    <td>
                      <div class="audit-event-stack">
                        <span class="audit-feed-badge ${auditBadgeClass(entry.badge)}">${escapeHtml(entry.badge || "TRACE")}</span>
                        <small>${escapeHtml(entry.eventType || entry.actionType || "TRACE")}</small>
                      </div>
                    </td>
                    <td class="mono">${escapeHtml(entry.targetLabel || `${entry.entityType}: #${entry.entityId}`)}</td>
                    <td>${auditActionMarkup(entry)}</td>
                  </tr>
                `).join("") : `
                  <tr class="log-entry">
                    <td colspan="5">
                      <div class="loading-panel">The audit ledger is empty. New edits, approvals, votes, and messages will stream here.</div>
                    </td>
                  </tr>
                `}
              </tbody>
            </table>
          </div>
        </section>

        <aside class="audit-feed-shell">
          <header class="audit-feed-head">
            <div>
              <div class="panel-kicker">Moderation Command</div>
              <h3>Message moderation and dispute trail</h3>
            </div>
            <span class="audit-feed-badge ${liveMode ? "badge-verified" : "badge-trace"}">${liveMode ? "STREAMING" : "PAUSED"}</span>
          </header>
          <div class="audit-activity-feed">
            ${governanceTimelineMarkup(auditLogs)}
          </div>
        </aside>
      </section>

      ${auditDrawerMarkup(activeAudit)}
    `;

    root.querySelectorAll("[data-audit-scope]").forEach((button) => {
      button.addEventListener("click", async () => {
        auditScope = String(button.dataset.auditScope || "all");
        await loadAuditLogs();
        render();
      });
    });

    root.querySelector("[data-audit-live]")?.addEventListener("click", () => {
      liveMode = !liveMode;
      scheduleAuditStream();
      render();
    });

    root.querySelectorAll("[data-audit-open]").forEach((row) => {
      row.addEventListener("click", () => {
        selectedAuditId = Number(row.dataset.auditOpen || 0) || null;
        render();
      });
    });

    root.querySelectorAll("[data-audit-close]").forEach((button) => {
      button.addEventListener("click", () => {
        selectedAuditId = null;
        render();
      });
    });

    root.querySelectorAll("[data-thread-open]").forEach((button) => {
      button.addEventListener("click", async () => {
        await loadAdminThread(button.dataset.threadOpen);
        render();
      });
    });

    document.getElementById("adminThreadReplyRecipient")?.addEventListener("change", (event) => {
      adminReplyRecipientUserId = Number(event.currentTarget.value || 0) || null;
    });

    document.getElementById("adminThreadReplyForm")?.addEventListener("submit", async (event) => {
      event.preventDefault();
      if (!activeThread) return;
      const input = document.getElementById("adminThreadReplyInput");
      const text = input?.value?.trim() || "";
      const recipientUserId = Number(document.getElementById("adminThreadReplyRecipient")?.value || 0) || null;
      if (!text || !recipientUserId) return;

      try {
        const response = await api.sendMessage({ threadId: activeThread.id, recipientUserId, text });
        const updatedThread = response?.thread || activeThread;
        activeThreadMessages = Array.isArray(response?.messages) ? response.messages : activeThreadMessages;
        activeThreadVisit = response?.visit || activeThreadVisit;
        adminReplyRecipientUserId = recipientUserId;
        inboxThreads = sortThreadsByRecent(inboxThreads.map((thread) => (
          Number(thread.id) === Number(activeThread.id)
            ? {
              ...thread,
              ...updatedThread,
              lastMessageText: text,
              lastMessageAt: updatedThread?.lastMessageAt || new Date().toISOString(),
              messageCount: Number(updatedThread?.messageCount || thread.messageCount || 0),
            }
            : thread
        )));
        if (input) input.value = "";
        render();
      } catch (error) {
        window.alert(error.message || "Unable to send the admin reply right now.");
      }
    });

    bindVisitInteractions(root, {
      property: activeThreadProperty,
      visit: activeThreadVisit,
      threadId: activeThread?.id || 0,
      counterMode: adminVisitCounterMode,
      setCounterMode: (nextMode) => {
        adminVisitCounterMode = Boolean(nextMode);
        render();
      },
      onUpdated: async (response) => {
        activeThreadVisit = response.visit || activeThreadVisit;
        await loadAdminThread(activeThread?.id || activeThreadId);
        render();
      },
    });

    root.querySelectorAll("[data-seller-review-form]").forEach((form) => {
      const statusSelect = form.querySelector('[name="status"]');
      syncAdminSellerReviewFormState(form);
      statusSelect?.addEventListener("change", () => {
        syncAdminSellerReviewFormState(form);
      });

      form.addEventListener("submit", async (event) => {
        event.preventDefault();
        const userId = Number(form.dataset.sellerReviewForm || 0);
        if (!userId) return;
        const formData = new FormData(form);
        const response = await api.reviewSellerProfile({
          userId,
          status: formData.get("status"),
          reviewNotes: formData.get("reviewNotes"),
        });
        sellerProfiles = Array.isArray(response?.profiles) ? response.profiles : sellerProfiles;
        sellerSummary = {
          total: Number(response?.summary?.total || sellerProfiles.length || 0),
          pendingReview: Number(response?.summary?.pendingReview || 0),
          verified: Number(response?.summary?.verified || 0),
          rejected: Number(response?.summary?.rejected || 0),
          suspended: Number(response?.summary?.suspended || 0),
          draft: Number(response?.summary?.draft || 0),
        };
        render();
      });
    });
  };

  const scheduleAuditStream = () => {
    window.clearInterval(root._auditStreamTimer);
    if (!liveMode) return;
    root._auditStreamTimer = window.setInterval(async () => {
      const hasNewEntries = await loadAuditLogs({ stream: true });
      if (hasNewEntries) {
        render();
      }
    }, 10000);
  };

  await loadAuditLogs();
  if (activeThreadId) {
    await loadAdminThread(activeThreadId);
  }
  render();
  scheduleAuditStream();
}

async function initInvestorDashboard() {
  const root = document.getElementById("investorDashboardRoot");
  if (!root) return;

  const bootstrap = await api.bootstrap();
  const properties = bootstrap.properties || [];
  const votesMap = await loadVoteTallies(properties);
  const enriched = enrichProperties(properties, properties, votesMap);
  const topDemand = aggregateVoteLabels(votesMap)[0] || ["No demand yet", 0];
  const [marketResponse, newsResponse, inboxResponse] = await Promise.all([
    api.marketSnapshot().catch(() => null),
    api.newsDigest(3).catch(() => null),
    api.getMessageInbox().catch(() => ({ threads: [] })),
  ]);
  const market = marketResponse?.snapshot || {
    provider: "Seeded market context",
    live: false,
    summary: {
      headline: "Market context currently unavailable",
      subline: "Configure live services or try again later.",
    },
    metrics: [],
    highlights: [],
  };
  const newsFeed = newsResponse?.feed || {
    provider: "Seeded investment digest",
    live: false,
    items: [],
    note: "Configure NewsAPI to surface live business headlines.",
  };
  const inboxThreads = inboxResponse?.threads || [];

  const render = () => {
    const compareIds = getCompareIds();
    const favoriteIds = getFavoriteIds();
    const favorites = enriched.filter((property) => favoriteIds.includes(property.id));
    root.innerHTML = `
      <div class="stat-grid">
        <article class="stat-card"><div class="panel-kicker">Shortlist cart</div><strong>${favoriteIds.length}</strong><p>Properties saved under your investor account.</p></article>
        <article class="stat-card"><div class="panel-kicker">Compare</div><strong>${compareIds.length}</strong><p>Listings currently in your decision queue.</p></article>
        <article class="stat-card"><div class="panel-kicker">Chats</div><strong>${inboxThreads.length}</strong><p>Active seller conversations tied to your account.</p></article>
        <article class="stat-card"><div class="panel-kicker">Leading demand</div><strong>${escapeHtml(voteLabel(topDemand[0]))}</strong><p>Strongest current establishment signal.</p></article>
      </div>

      <div class="panel-grid" style="grid-template-columns:repeat(2,minmax(0,1fr));">
        <article class="leaderboard-hero">
          <div class="panel-kicker">Recommended now</div>
          <h2>${escapeHtml(enriched[0]?.name || "No properties available")}</h2>
          <p>${escapeHtml(enriched[0] ? propertyStory(enriched[0]) : "Live ranked opportunities will appear here.")}</p>
          <div class="property-actions" style="margin-top:18px;">
            <a href="${propertyHref(enriched[0]?.id || 1)}" class="btn-shell btn-shell-primary">View Details</a>
            <a href="${compareHref()}" class="btn-shell btn-shell-secondary">Open Compare</a>
          </div>
        </article>

        <article class="panel-card market-context-card">
          <div class="panel-kicker">Market context</div>
          <div class="service-chip-row">
            ${serviceChip(market.live ? "Live market" : "Seeded context", market.live ? "live" : "fallback")}
            ${serviceChip(market.provider || "Market data", "neutral")}
          </div>
          <h3>${escapeHtml(market.summary?.headline || "Market context")}</h3>
          <p>${escapeHtml(market.summary?.subline || "Directional context for investor conversations.")}</p>
          <div class="mini-list">
            ${(market.metrics || []).slice(0, 4).map((metric) => `
              <div class="mini-row"><span>${icon("pulse")}${escapeHtml(metric.label || "Metric")}</span><strong>${escapeHtml(metric.value || "n/a")}</strong></div>
            `).join("")}
          </div>
        </article>
      </div>

      <div class="panel-grid" style="grid-template-columns:repeat(2,minmax(0,1fr));">
        <article class="panel-card">
          <div class="panel-kicker">Business digest</div>
          <div class="service-chip-row">
            ${serviceChip(newsFeed.live ? "Live news" : "Seeded digest", newsFeed.live ? "live" : "fallback")}
            ${serviceChip(newsFeed.provider || "News", "neutral")}
          </div>
          <h3>Signals investors can scan quickly</h3>
          <div class="news-list">
            ${(newsFeed.items || []).slice(0, 3).map((item) => `
              <article class="news-card">
                <div class="news-meta">
                  <span>${escapeHtml(item.source || "Source")}</span>
                  <span>${escapeHtml(formatDate(item.publishedAt))}</span>
                </div>
                <h4>${escapeHtml(item.title || "Untitled update")}</h4>
                <p>${escapeHtml(truncate(item.description || "No summary available.", 110))}</p>
                ${item.url ? `<a href="${escapeHtml(item.url)}" target="_blank" rel="noreferrer" class="text-link">Open source</a>` : ""}
              </article>
            `).join("") || `<div class="loading-panel">${escapeHtml(newsFeed.note || "No headlines available right now.")}</div>`}
          </div>
        </article>

          <article class="panel-card">
            <div class="panel-kicker">Demand pulse</div>
            <h3>Where local need is visible</h3>
          <div class="mini-list">
            ${enriched.slice(0, 4).map((property) => `
              <div class="mini-row"><span>${icon("vote")}${escapeHtml(property.name)}</span><strong>${property.voteTotal} votes</strong></div>
            `).join("")}
          </div>
          ${(market.highlights || []).length ? `<div class="insight-strip">${market.highlights.slice(0, 2).map((item) => `<span class="insight-pill">${escapeHtml(item)}</span>`).join("")}</div>` : ""}
          </article>
        </div>

      <article class="panel-card voting-summary-panel">
        <div class="panel-kicker">Seller conversations</div>
        <h3>${inboxThreads.length ? "Recent direct chats" : "No seller chats yet"}</h3>
        <div class="mini-list">
          ${inboxThreads.length ? inboxThreads.slice(0, 4).map((thread) => `
            <div class="mini-row">
              <span>${icon("inbox")}${escapeHtml(thread.propertyName || "Property")}</span>
              <strong>${escapeHtml(formatDate(thread.lastMessageAt || thread.updatedAt))}</strong>
            </div>
          `).join("") : `<div class="loading-panel">Open any property and message the seller directly to start a thread.</div>`}
        </div>
      </article>

      <article class="panel-card">
        <div class="panel-kicker">Top opportunities</div>
        <h3>Ranked properties worth reviewing</h3>
        <div class="property-grid">
          ${enriched.slice(0, 3).map((property) => propertyCard(property, {
            compareIds,
            favoriteIds,
          })).join("")}
        </div>
      </article>

      <article class="panel-card">
        <div class="panel-kicker">Shortlist cart</div>
        <h3>${favorites.length ? "Saved properties ready for review" : "No saved properties yet"}</h3>
        ${favorites.length ? `
          ${googleEarthActionsMarkup({
            properties: favorites,
            scope: "shortlist",
            note: "Export your saved shortlist to Google Earth for site inspection, route review, and presentation-ready validation.",
          })}
          <div class="property-grid">${favorites.slice(0, 3).map((property) => propertyCard(property, { compareIds, favoriteIds })).join("")}</div>
        ` : emptyState("Add a property to your shortlist cart", "Use rankings, explorer, or the property page to keep land areas and establishment opportunities in your investor cart.")}
      </article>
    `;

    bindCollectionActions(root, render);
  };
  render();
}

async function initRankingPage() {
  const root = document.getElementById("rankingPageRoot");
  if (!root) return;

  const bootstrap = await api.bootstrap();
  const properties = bootstrap.properties || [];
  const votesMap = await loadVoteTallies(properties);
  let type = "all";
  let corridor = "all";
  let clupStatus = "all";
  let investmentLensKey = getActiveInvestmentLensKey();

  const render = () => {
    const visibleBase = properties.filter((property) => {
      if (type !== "all" && property.type !== type) return false;
      if (corridor !== "all" && property.corridor !== corridor) return false;
      return true;
    });
    const activeLens = getInvestmentLensConfig(investmentLensKey);
    const visible = enrichProperties(visibleBase, properties, votesMap, null, investmentLensKey)
      .filter((property) => clupStatus === "all" || property.clupCompliance?.statusKey === clupStatus);
    const compareIds = getCompareIds();
    const favoriteIds = getFavoriteIds();
    const lead = visible[0] || null;
    const visibleCount = visible.length;
    const strongCount = visible.filter((property) => Number(property.lensScore || 0) >= 85).length;
    const averageLensScore = visibleCount
      ? Math.round(visible.reduce((sum, property) => sum + Number(property.lensScore || 0), 0) / visibleCount)
      : 0;
    const averageDiligence = visibleCount
      ? Math.round(visible.reduce((sum, property) => sum + Number(property.dueDiligencePct || 0), 0) / visibleCount)
      : 0;
    const approvedCount = visible.filter((property) => String(property.approvalState || "").toLowerCase() === "approved").length;
    const clupPassCount = visible.filter((property) => property.clupCompliance?.status === "PASS").length;
    const leadLabels = lead ? propertyPrimaryLabels(lead) : [];
    const leadStory = lead
      ? (lead.lensResult?.thesisShort || lead.lensResult?.thesis || propertyStory(lead))
      : "Adjust the filters to surface ranked opportunities.";
    const leadReason = lead
      ? (lead.lensResult?.thesisLead || leadStory)
      : "Expand the filter range to bring qualifying properties back into the board.";
    const boardScopeLabel = corridor === "all" ? "All corridors" : corridorLabel(corridor);
    const boardTypeLabel = type === "all" ? "All property types" : typeLabel(type);

    root.innerHTML = `
      <div class="ranking-studio">
        <div class="ranking-command-grid">
          <aside class="stack ranking-command-rail">
            <article class="panel-card ranking-command-dock">
              <div class="ranking-dock-head">
                <div>
                  <div class="panel-kicker">Board Controls</div>
                  <h3>Shape the live leaderboard</h3>
                  <p>Refine the board by corridor and property type, then let the active investment lens reset the ranking logic in real time.</p>
                </div>
                <span class="ranking-dock-badge">${visibleCount} in view</span>
              </div>

              <div class="filter-grid ranking-filter-grid">
                <label class="form-shell">
                  <span>Property type</span>
                  <select class="input-shell" id="rankingType">
                    <option value="all">All types</option>
                    <option value="commercial" ${type === "commercial" ? "selected" : ""}>Commercial</option>
                    <option value="logistics" ${type === "logistics" ? "selected" : ""}>Logistics</option>
                    <option value="hotel" ${type === "hotel" ? "selected" : ""}>Resort / Tourism</option>
                    <option value="bpo" ${type === "bpo" ? "selected" : ""}>Office / BPO</option>
                    <option value="manufacturing" ${type === "manufacturing" ? "selected" : ""}>Manufacturing</option>
                  </select>
                </label>
                <label class="form-shell">
                  <span>Corridor</span>
                  <select class="input-shell" id="rankingCorridor">
                    <option value="all">All corridors</option>
                    <option value="highway" ${corridor === "highway" ? "selected" : ""}>Highway</option>
                    <option value="downtown" ${corridor === "downtown" ? "selected" : ""}>Downtown</option>
                    <option value="coastal" ${corridor === "coastal" ? "selected" : ""}>Coastal</option>
                  </select>
                </label>
                <label class="form-shell">
                  <span>CLUP status</span>
                  <select class="input-shell" id="rankingClupStatus">
                    <option value="all">All compliance results</option>
                    <option value="pass" ${clupStatus === "pass" ? "selected" : ""}>PASS only</option>
                    <option value="conditional" ${clupStatus === "conditional" ? "selected" : ""}>CONDITIONAL only</option>
                    <option value="fail" ${clupStatus === "fail" ? "selected" : ""}>FAIL only</option>
                  </select>
                </label>
              </div>

              <div class="ranking-summary-rail">
                <article class="ranking-summary-cell">
                  <span>${escapeHtml(activeLens.shortLabel)} avg</span>
                  <strong>${averageLensScore}</strong>
                </article>
                <article class="ranking-summary-cell">
                  <span>Elite fits</span>
                  <strong>${strongCount}</strong>
                </article>
                <article class="ranking-summary-cell">
                  <span>Diligence avg</span>
                  <strong>${averageDiligence}%</strong>
                </article>
                <article class="ranking-summary-cell">
                  <span>CLUP pass</span>
                  <strong>${clupPassCount}/${visibleCount}</strong>
                </article>
              </div>

              <article class="ranking-thesis-note">
                <div class="panel-kicker">Current thesis</div>
                <h3>${escapeHtml(activeLens.label)} is setting the decision standard.</h3>
                <p>Scores move with purpose, not just price. The current lens is favoring ${escapeHtml(boardScopeLabel.toLowerCase())} opportunities that fit ${escapeHtml(boardTypeLabel.toLowerCase())} demand most cleanly.</p>
                <div class="service-chip-row ranking-thesis-chip-row">
                  ${serviceChip(`${activeLens.label} active`, "live")}
                  ${serviceChip(boardScopeLabel, "neutral")}
                  ${serviceChip(boardTypeLabel, "fallback")}
                </div>
              </article>
            </article>
          </aside>

        <section class="stack ranking-command-main">
          ${investmentLensSelectorMarkup(investmentLensKey, {
            title: "Select Investment Lens",
            description: "Re-rank the board by investment purpose. Scores, front-runner logic, and supporting explanation all update in one pass.",
          })}

          <article class="leaderboard-hero ranking-hero-board ranking-stage-shell" style="--ranking-hero-image:url('${escapeHtml(absoluteAssetPath(lead?.imageUrl || ""))}')">
            <div class="ranking-stage-grid">
              <div class="ranking-stage-main">
                <div class="panel-kicker">Lead opportunity for ${escapeHtml(activeLens.label)}</div>
                <h2>${escapeHtml(lead?.name || "No properties in this filter")}</h2>
                <p>${escapeHtml(truncate(leadStory, 220))}</p>
                <div class="service-chip-row ranking-hero-chip-row">
                  ${lead ? clupStatusPill(lead.clupCompliance) : ""}
                  ${leadLabels.map((label) => `<span class="service-chip service-chip-neutral">${escapeHtml(label)}</span>`).join("")}
                  ${lead ? serviceChip(voteLabel(lead.topNeed || "No demand yet"), "fallback") : ""}
                  ${lead ? serviceChip(corridorLabel(lead.corridor), "neutral") : ""}
                </div>
                <div class="ranking-stage-note">
                  <span>Why it leads</span>
                  <strong>${escapeHtml(truncate(leadReason, 152))}</strong>
                </div>
                <div class="property-actions ranking-hero-actions">
                  ${lead ? `<a href="${propertyHref(lead.id)}" class="btn-shell btn-shell-primary">${icon("arrow")}Open Property Thesis</a>` : ""}
                  <a href="${window.SFC_APP_CONFIG.basePath || ""}/property-explorer.php" class="btn-shell btn-shell-secondary">${icon("explorer")}Open Explorer</a>
                  ${lead ? `<button type="button" class="btn-shell btn-shell-ghost" data-compare-toggle="${lead.id}">${icon("compare")}${compareIds.includes(lead.id) ? "Compared" : "Compare"}</button>` : ""}
                </div>
              </div>
              <aside class="ranking-stage-aside">
                <div class="ranking-hero-score">
                  <span class="ranking-hero-score-label">Current lens score</span>
                  <strong>${Math.round(Number(lead?.lensScore || 0))}</strong>
                  <p>${escapeHtml(lead ? `${lead.name} is currently the clearest fit under the ${activeLens.label} lens.` : "No active lead in this filter.")}</p>
                </div>
                <div class="ranking-hero-metrics ranking-stage-metrics">
                  <article><span>Guide price</span><strong>${escapeHtml(moneyShort(lead?.price || 0))}</strong></article>
                  <article><span>Land area</span><strong>${escapeHtml(lead?.area || "--")} ha</strong></article>
                  <article><span>Votes</span><strong>${Number(lead?.voteTotal || 0)}</strong></article>
                  <article><span>Due diligence</span><strong>${Math.round(Number(lead?.dueDiligencePct || 0))}%</strong></article>
                  <article><span>CLUP suitability</span><strong>${Math.round(Number(lead?.clupCompliance?.suitabilityScore || 0))}</strong></article>
                </div>
              </aside>
            </div>
            ${visible.length ? googleEarthActionsMarkup({
              properties: visible,
              scope: "ranking-visible",
              note: "Export the current ranked set to Google Earth without disturbing the platform’s scoring and recommendation flow.",
            }) : ""}
          </article>

          <div class="ranking-board-grid">
            ${lead ? clupDecisionCardMarkup(lead.clupCompliance, { compact: true }) : ""}
            ${lead ? investmentLensThesisMarkup(lead, lead.lensResult, {
              kicker: `Top Pick for ${activeLens.label}`,
              heading: `${lead.name} leads under the ${activeLens.label} lens`,
            }) : ""}

            <article class="panel-card leaderboard-shell ranking-leaderboard-shell">
              <div class="ranking-section-head">
                <div>
                  <div class="panel-kicker">Leaderboard</div>
                  <h3>Top ranked properties</h3>
                </div>
                <span class="service-chip service-chip-neutral">${visibleCount} entries</span>
              </div>
              <div class="leaderboard-list">${visible.length ? leaderboardRows(visible, 6, { lensKey: investmentLensKey }) : emptyState("No ranked properties", "Try widening the filters to see more results.")}</div>
            </article>
          </div>

          <div class="ranking-results-head">
            <div>
              <div class="panel-kicker">Full board</div>
              <h3>Scan the remaining ranked opportunities</h3>
            </div>
            <span class="service-chip service-chip-neutral">${visibleCount} propert${visibleCount === 1 ? "y" : "ies"} in view</span>
          </div>

          <div class="property-grid ranking-property-grid">
            ${visible.length
              ? visible.map((property) => propertyCard(property, { compareIds, favoriteIds, lensKey: investmentLensKey, variant: "ranking" })).join("")
              : emptyState("No ranked properties", "Try widening the filters to see more results.")}
          </div>
        </section>
        </div>
      </div>
    `;

    document.getElementById("rankingType")?.addEventListener("change", (event) => {
      type = event.target.value;
      render();
    });
    document.getElementById("rankingCorridor")?.addEventListener("change", (event) => {
      corridor = event.target.value;
      render();
    });
    document.getElementById("rankingClupStatus")?.addEventListener("change", (event) => {
      clupStatus = event.target.value;
      render();
    });
    bindInvestmentLensSelector(root, (nextLensKey) => {
      investmentLensKey = nextLensKey;
      saveActiveInvestmentLensKey(nextLensKey);
      render();
    });
    bindCollectionActions(root, render);
    animateLensMetricBars(root);
  };
  render();
}

async function initSellerDashboard() {
  const root = document.getElementById("sellerDashboardRoot");
  if (!root) return;

  const modal = document.getElementById("sellerListingModal");
  const form = document.getElementById("sellerListingForm");
  const addButton = document.getElementById("sellerAddListing");
  const userEmail = currentUser?.email || "seller@sfcelerate.local";
  const userName = currentUser?.name || "Seller Studio";
  const userId = Number(currentUser?.id || 0);
  let properties = [];
  let inquiryMap = {};
  let threads = [];
  let activeThreadId = null;
  let activeThreadMessages = [];
  let activeThreadVisit = null;
  let documentRequests = [];
  let sellerVisitCounterMode = false;
  let sellerProfile = null;

  const openModal = () => {
    if (!sellerCanPublish(sellerProfile)) {
      return;
    }
    if (modal) modal.hidden = false;
  };

  const closeModal = () => {
    if (modal) modal.hidden = true;
  };

  const fillForm = (property = null) => {
    document.getElementById("sellerModalTitle").textContent = property ? "Edit Listing" : "Submit Listing";
    document.getElementById("sellerPropertyId").value = property?.id || "";
    document.getElementById("sellerPropertyName").value = property?.name || "";
    document.getElementById("sellerCity").value = property?.city || "San Fernando, La Union";
    document.getElementById("sellerBarangay").value = property?.barangay || "";
    document.getElementById("sellerPropertyType").value = property?.type || "commercial";
    document.getElementById("sellerCorridor").value = property?.corridor || "highway";
    document.getElementById("sellerStatus").value = property?.status || "Available";
    document.getElementById("sellerPrice").value = property?.price || "";
    document.getElementById("sellerLandArea").value = property?.area || "";
    document.getElementById("sellerAccess").value = property?.roadAccess || 85;
    document.getElementById("sellerDescription").value = property?.description || "";
    document.getElementById("sellerImagePath").value = property?.imageUrl || "assets/images/Property10.png";
    document.getElementById("sellerTags").value = (property?.tags || []).join(", ");
    document.getElementById("sellerFacilities").value = (property?.facilities || []).join(", ");
    document.getElementById("sellerOwnerName").value = property?.ownerContact?.name || userName;
    document.getElementById("sellerOwnerEmail").value = property?.ownerContact?.email || userEmail;
    document.getElementById("sellerOwnerPhone").value = property?.ownerContact?.phone || "+63 917 000 0199";
    document.getElementById("sellerOwnerSla").value = property?.ownerContact?.responseSla || "24 HOURS";
    document.getElementById("sellerImage").value = "";
  };

  const render = () => {
    const profile = sellerProfile || {};
    const applicationStatus = sellerApplicationStatus(profile);
    const canPublish = sellerCanPublish(profile);
    const ownListings = properties.filter((property) => Number(property.sellerUserId || 0) === userId || String(property.ownerContact?.email || "").toLowerCase() === userEmail.toLowerCase());
    const available = ownListings.filter((property) => String(property.status || "").toLowerCase() === "available").length;
    const inquiryTotal = ownListings.reduce((sum, property) => sum + Number(inquiryMap[property.id] || 0), 0);
    const activeThread = threads.find((thread) => Number(thread.id) === Number(activeThreadId)) || threads[0] || null;
    const activeThreadProperty = properties.find((property) => Number(property.id) === Number(activeThread?.propertyId || 0)) || null;
    const pendingReview = ownListings.filter((property) => String(property.approvalState || "").toLowerCase() === "pending_review").length;
    const openDocumentRequests = documentRequests.filter((request) => ["requested", "in_review"].includes(String(request.status))).length;
    const sellerVerification = VERIFICATION_LABELS[String(applicationStatus || currentUser?.identityVerificationStatus || "unverified").toLowerCase()] || titleCase(String(applicationStatus || currentUser?.identityVerificationStatus || "unverified"));
    const sellerReviewNote = sellerReviewStatusNote(applicationStatus, "seller");

    if (addButton) {
      addButton.disabled = !canPublish;
      addButton.textContent = canPublish ? "Submit Listing" : "Verification Required";
      addButton.title = canPublish
        ? "Submit a new listing"
        : "Complete seller verification and wait for admin approval before publishing listings.";
    }

    root.innerHTML = `
      <div class="stat-grid">
        <article class="stat-card"><div class="panel-kicker">My listings</div><strong>${ownListings.length}</strong><p>Properties currently tied to your seller account.</p></article>
        <article class="stat-card"><div class="panel-kicker">Available now</div><strong>${available}</strong><p>Listings open for investor attention.</p></article>
        <article class="stat-card"><div class="panel-kicker">Messages</div><strong>${inquiryTotal}</strong><p>Messages received across your submissions.</p></article>
        <article class="stat-card"><div class="panel-kicker">Active chats</div><strong>${threads.length}</strong><p>Investor threads you can reply to directly.</p></article>
        <article class="stat-card"><div class="panel-kicker">Seller status</div><strong>${escapeHtml(sellerVerification)}</strong><p>${pendingReview} listing(s) pending review and ${openDocumentRequests} open document task(s).</p></article>
      </div>

      <div class="panel-grid" style="grid-template-columns:repeat(2,minmax(0,1fr));">
        <article class="panel-card">
          <div class="panel-kicker">Seller verification</div>
          <h3>${escapeHtml(sellerApplicationHeadline(applicationStatus))}</h3>
          <p>${escapeHtml(sellerApplicationCopy(applicationStatus))}</p>
          <div class="mini-list" style="margin-top:18px;">
            <div class="mini-row"><span>${icon("shield")}Application status</span><strong>${escapeHtml(sellerVerification)}</strong></div>
            <div class="mini-row"><span>${icon("user")}Legal name</span><strong>${escapeHtml(profile.legalName || userName)}</strong></div>
            <div class="mini-row"><span>${icon("map")}Location</span><strong>${escapeHtml(profile.city || "San Fernando, La Union")}</strong></div>
            <div class="mini-row"><span>${icon("clock")}Submitted</span><strong>${escapeHtml(profile.submittedAt ? formatDate(profile.submittedAt) : "Not submitted")}</strong></div>
          </div>
          ${profile.reviewNotes ? `<div class="auth-form-note" style="margin-top:16px;">Admin note: ${escapeHtml(profile.reviewNotes)}</div>` : ""}
        </article>

        <article class="panel-card">
          <div class="panel-kicker">Market signal</div>
          <h3>How your listings are positioned</h3>
          <p>${canPublish
            ? (ownListings.length ? `${escapeHtml(ownListings[0].name)} is currently your strongest visible listing.` : "Submit your first verified listing to start seeing market feedback.")
            : "Listing submission stays locked until your seller identity is approved by the admin team."}</p>
          <div class="property-actions" style="margin-top:18px;">
            <button type="button" class="btn-shell btn-shell-primary" id="sellerAddListingInline" ${canPublish ? "" : "disabled"}>${canPublish ? "Submit Listing" : "Verification Required"}</button>
            <a href="${window.SFC_APP_CONFIG.basePath || ""}/property-ranking.php" class="btn-shell btn-shell-secondary">View Rankings</a>
          </div>
        </article>

        <article class="panel-card" style="grid-column:1 / -1;">
          <div class="panel-kicker">Seller profile</div>
          <h3>${canPublish ? "Verification profile on file" : "Complete the seller profile before publishing"}</h3>
          ${applicationStatus === "verified" || applicationStatus === "suspended" ? `
            <div class="mini-list">
              <div class="mini-row"><span>${icon("user")}Display name</span><strong>${escapeHtml(profile.displayName || profile.legalName || userName)}</strong></div>
              <div class="mini-row"><span>${icon("user")}Seller type</span><strong>${escapeHtml(titleCase(profile.sellerType || "individual"))}</strong></div>
              <div class="mini-row"><span>${icon("inbox")}Email</span><strong>${escapeHtml(profile.email || userEmail)}</strong></div>
              <div class="mini-row"><span>${icon("map")}Address</span><strong>${escapeHtml(profile.addressLine || profile.city || "Not supplied")}</strong></div>
            </div>
          ` : `
            <form class="crud-form-grid" id="sellerProfileForm">
              <label class="form-shell">
                <span>Seller type</span>
                <select class="input-shell" name="sellerType">
                  <option value="individual" ${String(profile.sellerType || "individual").toLowerCase() === "individual" ? "selected" : ""}>Individual owner</option>
                  <option value="company" ${String(profile.sellerType || "").toLowerCase() === "company" ? "selected" : ""}>Company / Developer</option>
                  <option value="broker" ${String(profile.sellerType || "").toLowerCase() === "broker" ? "selected" : ""}>Broker / Representative</option>
                </select>
              </label>
              <label class="form-shell">
                <span>Legal or business name</span>
                <input class="input-shell" name="legalName" value="${escapeHtml(profile.legalName || userName)}" required>
              </label>
              <label class="form-shell">
                <span>Display name</span>
                <input class="input-shell" name="displayName" value="${escapeHtml(profile.displayName || "")}" placeholder="Optional public-facing label">
              </label>
              <label class="form-shell">
                <span>Phone</span>
                <input class="input-shell" name="phone" value="${escapeHtml(profile.phone || "")}" required>
              </label>
              <label class="form-shell">
                <span>Company name</span>
                <input class="input-shell" name="companyName" value="${escapeHtml(profile.companyName || "")}">
              </label>
              <label class="form-shell">
                <span>Business registration no.</span>
                <input class="input-shell" name="businessRegistrationNo" value="${escapeHtml(profile.businessRegistrationNo || "")}">
              </label>
              <label class="form-shell">
                <span>Government ID / license no.</span>
                <input class="input-shell" name="governmentIdNo" value="${escapeHtml(profile.governmentIdNo || "")}" required>
              </label>
              <label class="form-shell">
                <span>Address line</span>
                <input class="input-shell" name="addressLine" value="${escapeHtml(profile.addressLine || "")}" required>
              </label>
              <label class="form-shell">
                <span>Barangay</span>
                <input class="input-shell" name="barangay" value="${escapeHtml(profile.barangay || "")}">
              </label>
              <label class="form-shell">
                <span>City</span>
                <input class="input-shell" name="city" value="${escapeHtml(profile.city || "San Fernando, La Union")}" required>
              </label>
              <label class="form-shell form-span-2">
                <span>Authority on the property</span>
                <input class="input-shell" name="authorizationBasis" value="${escapeHtml(profile.authorizationBasis || "")}" placeholder="Owner, exclusive broker, authorized representative" required>
              </label>
              <div class="crud-actions form-span-2">
                <button type="submit" class="btn-shell btn-shell-secondary" data-profile-submit-mode="draft">Save Draft</button>
                <button type="submit" class="btn-shell btn-shell-primary" data-profile-submit-mode="submit">${escapeHtml(sellerReviewSubmitLabel(applicationStatus))}</button>
              </div>
              ${sellerReviewNote ? `<div class="auth-form-note form-span-2"><strong>${escapeHtml(sellerReviewSubmitLabel(applicationStatus))}.</strong> ${escapeHtml(sellerReviewNote)}</div>` : ""}
            </form>
          `}
        </article>

        <article class="panel-card">
          <div class="panel-kicker">Document requests</div>
          <h3>${openDocumentRequests ? "Verification tasks needing action" : "No open document requests"}</h3>
          <div class="request-stack compact-request-stack">
            ${requestTimelineMarkup(documentRequests.slice(0, 4), {
              manage: true,
              emptyCopy: "Investor and admin document requests will appear here once they need title copies, surveys, or hazard reports.",
            })}
          </div>
        </article>

        <article class="panel-card seller-inbox-card">
          <div class="panel-kicker">Seller inbox</div>
          <h3>${threads.length ? "Direct investor chats" : "No direct investor chats yet"}</h3>
          <div class="seller-inbox-grid">
            <div class="thread-list">
              ${conversationThreadList(threads, activeThreadId, "Investors will appear here once they message your listings from the property page.")}
            </div>
            <div class="thread-view">
              ${activeThread ? `
                <div class="thread-view-head">
                  <strong>${escapeHtml(activeThread.propertyName || "Property conversation")}</strong>
                  <span>${escapeHtml(activeThread.investorName || "Investor")}</span>
                </div>
                ${logisticsHubMarkup({
                  property: activeThreadProperty,
                  visit: activeThreadVisit,
                  currentRole: "seller",
                  counterMode: sellerVisitCounterMode,
                  compact: true,
                })}
                <div class="chat-thread-surface">
                  ${conversationBubbles(activeThreadMessages, "seller", "This thread is ready for your reply.", activeThreadVisit)}
                </div>
                <form class="thread-compose" id="sellerThreadReplyForm">
                  <textarea class="input-shell input-textarea" id="sellerThreadReplyInput" placeholder="Reply to the investor about documents, pricing, viewing schedule, or next steps."></textarea>
                  <button type="submit" class="btn-shell btn-shell-primary">Send Reply</button>
                </form>
              ` : `<div class="loading-panel">Select a conversation once investor threads become available.</div>`}
            </div>
          </div>
        </article>
      </div>

      <article class="panel-card">
        <div class="panel-kicker">My listings</div>
        <h3>${ownListings.length ? "Your active submissions" : "No listings submitted yet"}</h3>
        <div class="listing-stack">
          ${ownListings.length ? ownListings.map((property) => `
            <article class="listing-row">
              <div class="listing-main">
                <div class="property-title">${escapeHtml(property.name)}</div>
                <div class="property-subline">${escapeHtml(property.city || "San Fernando, La Union")} | ${escapeHtml(property.barangay || "Unassigned")}</div>
                <p>${escapeHtml(truncate(property.description || propertyStory(property), 140))}</p>
                <div class="property-stat-row">
                  <span>${icon("money")}${escapeHtml(moneyShort(property.price))}</span>
                  <span>${icon("area")}${escapeHtml(property.area)} ha</span>
                  <span>${icon("inbox")}${Number(inquiryMap[property.id] || 0)} inquiries</span>
                  <span>${icon("file")}${Math.round(Number(property.documentCompletenessPct || 0))}% docs</span>
                  <span>${icon("clock")}${escapeHtml(formatDate(property.lastConfirmedAvailableAt || property.updatedAt))}</span>
                </div>
                <div class="listing-meta-row">
                  ${approvalStatePill(property.approvalState)}
                  ${verificationPill(property.listingVerificationStatus)}
                  ${metaChip(`${Number(property.openDocumentRequestCount || 0)} open requests`)}
                </div>
                <div class="trust-badge-row">${trustBadgeRow(property.trustBadges || [], { compact: true })}</div>
              </div>
              <div class="listing-actions">
                ${statusPill(property.status)}
                <a href="${propertyHref(property.id)}" class="btn-shell btn-shell-secondary">${icon("arrow")}View</a>
                <button type="button" class="btn-shell btn-shell-secondary" data-confirm-availability="${property.id}">${icon("clock")}Confirm Available</button>
                <button type="button" class="btn-shell btn-shell-primary" data-seller-edit="${property.id}">${icon("compare")}Edit</button>
                <button type="button" class="btn-shell btn-shell-ghost" data-seller-delete="${property.id}">${icon("save")}Delete</button>
              </div>
            </article>
          `).join("") : emptyState(
            canPublish ? "Start with your first listing" : "Seller verification is still required",
            canPublish
              ? "Use the seller dashboard to submit a property and begin collecting attention."
              : "Complete your seller verification profile and wait for approval before publishing the first listing.",
            "",
            ""
          )}
        </div>
      </article>
    `;

    document.getElementById("sellerAddListingInline")?.addEventListener("click", () => {
      fillForm();
      openModal();
    });

    root.querySelectorAll("[data-seller-edit]").forEach((button) => {
      button.addEventListener("click", () => {
        const property = properties.find((entry) => entry.id === Number(button.dataset.sellerEdit));
        fillForm(property);
        openModal();
      });
    });

    root.querySelectorAll("[data-seller-delete]").forEach((button) => {
      button.addEventListener("click", async () => {
        const property = properties.find((entry) => entry.id === Number(button.dataset.sellerDelete));
        if (!property) return;
        if (!window.confirm(`Delete ${property.name}?`)) return;
        await api.deleteProperty(property.id);
        await reload();
      });
    });
    root.querySelectorAll("[data-confirm-availability]").forEach((button) => {
      button.addEventListener("click", async () => {
        const propertyId = Number(button.dataset.confirmAvailability || 0);
        if (!propertyId) return;
        await api.updateProperty(propertyId, {
          lastConfirmedAvailableAt: new Date().toISOString(),
        });
        await reload();
      });
    });

    root.querySelectorAll("[data-thread-open]").forEach((button) => {
      button.addEventListener("click", async () => {
        activeThreadId = Number(button.dataset.threadOpen);
        if (!activeThreadId) return;
        const response = await api.getThread(activeThreadId).catch(() => ({ messages: [], visit: null }));
        activeThreadMessages = response.messages || [];
        activeThreadVisit = response.visit || null;
        sellerVisitCounterMode = false;
        render();
      });
    });

    document.getElementById("sellerThreadReplyForm")?.addEventListener("submit", async (event) => {
      event.preventDefault();
      if (!activeThread) return;
      const input = document.getElementById("sellerThreadReplyInput");
      const text = input?.value?.trim() || "";
      if (!text) return;
      const response = await api.sendMessage({ threadId: activeThread.id, text });
      activeThreadMessages = response.messages || [];
      activeThreadVisit = response.visit || activeThreadVisit;
      threads = threads.map((thread) => (
        Number(thread.id) === Number(activeThread.id)
          ? { ...thread, lastMessageText: text, lastMessageAt: new Date().toISOString(), messageCount: (thread.messageCount || 0) + 1 }
          : thread
      ));
      if (input) input.value = "";
      render();
    });
    bindVisitInteractions(root, {
      property: activeThreadProperty,
      visit: activeThreadVisit,
      threadId: activeThread?.id || 0,
      counterMode: sellerVisitCounterMode,
      setCounterMode: (nextMode) => {
        sellerVisitCounterMode = Boolean(nextMode);
        render();
      },
      onUpdated: async () => {
        sellerVisitCounterMode = false;
        await reload();
      },
    });
    root.querySelectorAll("[data-request-manage]").forEach((formElement) => {
      formElement.addEventListener("submit", async (event) => {
        event.preventDefault();
        const requestId = Number(formElement.dataset.requestManage || 0);
        if (!requestId) return;
        const formData = new FormData(formElement);
        await api.updateDocumentRequest({
          requestId,
          status: formData.get("status"),
          responseNote: formData.get("responseNote"),
        });
        await reload();
      });
    });
    document.getElementById("sellerProfileForm")?.addEventListener("submit", async (event) => {
      event.preventDefault();
      const formElement = event.currentTarget;
      const formData = new FormData(formElement);
      const payload = Object.fromEntries(formData.entries());
      payload.submit = event.submitter?.dataset?.profileSubmitMode === "submit";
      const response = await api.saveSellerProfile(payload);
      sellerProfile = response?.profile || sellerProfile;
      if (currentUser && response?.user) {
        Object.assign(currentUser, response.user);
      }
      render();
    });
  };

  const reload = async () => {
    const previousThreadId = activeThreadId;
    const [propertyResponse, profileResponse, inboxResponse, documentRequestResponse] = await Promise.all([
      api.properties(),
      api.sellerProfile().catch(() => ({ profile: null })),
      api.getMessageInbox().catch(() => ({ threads: [] })),
      api.getDocumentRequestInbox().catch(() => ({ requests: [] })),
    ]);
    properties = propertyResponse.properties || [];
    sellerProfile = profileResponse.profile || null;
    inquiryMap = await loadInquiryCounts(properties);
    threads = inboxResponse.threads || [];
    documentRequests = documentRequestResponse.requests || [];
    activeThreadId = threads.some((thread) => Number(thread.id) === Number(previousThreadId))
      ? previousThreadId
      : (threads[0]?.id || null);
    if (activeThreadId) {
      const threadResponse = await api.getThread(activeThreadId).catch(() => ({ messages: [], visit: null }));
      activeThreadMessages = threadResponse.messages || [];
      activeThreadVisit = threadResponse.visit || null;
    } else {
      activeThreadMessages = [];
      activeThreadVisit = null;
    }
    render();
  };

  addButton?.addEventListener("click", () => {
    fillForm();
    openModal();
  });

  document.addEventListener("click", (event) => {
    const closeTarget = event.target.closest("[data-modal-close='sellerListingModal']");
    if (closeTarget) closeModal();
  });

  form?.addEventListener("submit", async (event) => {
    event.preventDefault();
    const propertyId = Number(document.getElementById("sellerPropertyId").value || 0);
    const payload = new FormData();
    payload.append("property_name", document.getElementById("sellerPropertyName").value);
    payload.append("city", document.getElementById("sellerCity").value);
    payload.append("barangay", document.getElementById("sellerBarangay").value);
    payload.append("property_type", document.getElementById("sellerPropertyType").value);
    payload.append("corridor", document.getElementById("sellerCorridor").value);
    payload.append("status", document.getElementById("sellerStatus").value);
    payload.append("price", document.getElementById("sellerPrice").value);
    payload.append("land_area", document.getElementById("sellerLandArea").value);
    payload.append("road_access", document.getElementById("sellerAccess").value);
    payload.append("description", document.getElementById("sellerDescription").value);
    payload.append("image_path", document.getElementById("sellerImagePath").value);
    payload.append("tags", document.getElementById("sellerTags").value);
    payload.append("facilities", document.getElementById("sellerFacilities").value);
    payload.append("owner_name", document.getElementById("sellerOwnerName").value || userName);
    payload.append("owner_email", document.getElementById("sellerOwnerEmail").value || userEmail);
    payload.append("owner_phone", document.getElementById("sellerOwnerPhone").value);
    payload.append("owner_response_sla", document.getElementById("sellerOwnerSla").value);
    const imageFile = document.getElementById("sellerImage").files?.[0];
    if (imageFile) payload.append("image_file", imageFile);

    if (propertyId > 0) {
      await api.updateProperty(propertyId, payload);
    } else {
      await api.createProperty(payload);
    }

    closeModal();
    await reload();
  });

  await reload();
}

async function initVotingDashboard() {
  const root = document.getElementById("votingDashboardRoot");
  if (!root) return;

  const bootstrap = await api.bootstrap();
  const properties = bootstrap.properties || [];
  const votesMap = await loadVoteTallies(properties);
  let voteOptions = (await api.voteOptions().catch(() => ({ voteOptions: [] }))).voteOptions || [];
  let activeId = parsePropertyParam() || properties[0]?.id || 0;
  let selectedVoteOptionId = null;
  let editingVoteOptionId = null;

  const refreshActiveVoteState = async () => {
    if (!activeId) return;
    const response = await api.getVotes(activeId).catch(() => ({ votes: {}, selectedVoteOptionId: null }));
    votesMap[activeId] = response.votes || {};
    selectedVoteOptionId = response.selectedVoteOptionId || null;
  };

  await refreshActiveVoteState();

  const render = () => {
    const enriched = enrichProperties(properties, properties, votesMap);
    const aggregate = aggregateVoteLabels(votesMap);
    const selected = enriched.find((property) => property.id === activeId) || enriched[0];
    if (!selected) {
      root.innerHTML = emptyState("No voting locations available", "Property locations will appear here once records exist.");
      return;
    }

    const selectedVotes = votesMap[selected.id] || {};
    const [topNeed, topNeedCount] = topVoteEntry(selectedVotes);
    const totalPlatformVotes = Object.values(votesMap).reduce((sum, votes) => sum + totalVotes(votes), 0);
    const activeVoteOptions = voteOptions.filter((option) => option.isActive !== false);
    const editingOption = voteOptions.find((option) => Number(option.id) === Number(editingVoteOptionId)) || null;
    const selectedLabels = propertyPrimaryLabels(selected);
    const secondaryLabels = propertySecondaryLabels(selected).slice(0, 2);
    const selectedVoteTotal = totalVotes(selectedVotes);
    const participationPct = totalPlatformVotes ? Math.round((selectedVoteTotal / totalPlatformVotes) * 100) : 0;
    const rankedZones = [...enriched].sort((left, right) => (
      Number(right.voteTotal || 0) - Number(left.voteTotal || 0)
      || Number(right.opportunityScore || 0) - Number(left.opportunityScore || 0)
    ));
    const selectedRank = Math.max(1, rankedZones.findIndex((property) => Number(property.id) === Number(selected.id)) + 1);
    const liveZoneCount = enriched.filter((property) => Number(property.voteTotal || 0) > 0).length;
    const leadingSignalLabel = voteLabel(topNeed || "No demand yet");
    const cityLeaderLabel = voteLabel(aggregate[0]?.[0] || "No demand yet");
    const cityLeaderCount = Number(aggregate[0]?.[1] || 0);
    const runnerUp = sortedVoteEntries(selectedVotes)[1] || null;
    const locationLine = [selected.city || "San Fernando, La Union", selected.barangay || "Unassigned"].filter(Boolean).join(" / ");
    const voteModeLabel = role === "investor"
      ? "Live voting unlocked"
      : role === "admin"
        ? "Preview mode"
        : "Investor access required";
    const voteModeCopy = role === "investor"
      ? "Cast one demand signal for this zone. Your latest choice replaces your previous one."
      : role === "admin"
        ? "Admins can inspect the live option set here while managing the voting inventory below."
        : "Voting is reserved for Investor / Resident access. Enter that workspace to participate.";
    const voteModeTone = role === "investor" ? "is-live" : role === "admin" ? "is-preview" : "is-locked";
    const railSummary = cityLeaderCount
      ? `${cityLeaderLabel} currently leads citywide.`
      : "The board is ready for its first citywide leader.";
    const zoneLeaderSummary = selectedVoteTotal
      ? `${leadingSignalLabel} currently leads this zone.`
      : "This zone has not received a demand signal yet.";
    const citywideSummary = cityLeaderCount
      ? `${cityLeaderLabel} is the clearest citywide demand signal right now.`
      : "Platform-level recommendations will appear here once votes are cast.";

    root.innerHTML = `
      <div class="voting-signal-studio">
        <aside class="voting-signal-rail">
          <article class="panel-card voting-rail-overview">
            <div class="panel-kicker">Signal atlas</div>
            <h3>Demand is clustering around real zones, not abstract categories.</h3>
            <p>${liveZoneCount} of ${rankedZones.length} locations already hold live vote signals. ${escapeHtml(railSummary)}</p>
            <div class="voting-rail-metric-grid">
              <article>
                <span>City votes</span>
                <strong>${totalPlatformVotes}</strong>
              </article>
              <article>
                <span>Live zones</span>
                <strong>${liveZoneCount}/${rankedZones.length}</strong>
              </article>
              <article>
                <span>City leader</span>
                <strong>${escapeHtml(cityLeaderCount ? cityLeaderLabel : "Standby")}</strong>
              </article>
            </div>
          </article>

          <article class="panel-card voting-location-panel">
            <div class="voting-location-panel-head">
              <div>
                <div class="panel-kicker">Location rail</div>
                <h3>Choose a voting zone</h3>
              </div>
              <span class="voting-location-badge">${rankedZones.length} zones</span>
            </div>
            <div class="vote-location-list">
              ${rankedZones.map((property, index) => {
                const propertyVotes = Number(property.voteTotal || 0);
                const propertyShare = totalPlatformVotes ? Math.round((propertyVotes / totalPlatformVotes) * 100) : 0;
                const isActive = Number(property.id) === Number(selected.id);
                return `
                  <button
                    type="button"
                    class="vote-location-card voting-zone-card ${isActive ? "is-active" : ""}"
                    data-vote-location="${property.id}"
                    aria-pressed="${isActive ? "true" : "false"}"
                  >
                    <div class="voting-zone-card-top">
                      <span class="voting-zone-rank">${String(index + 1).padStart(2, "0")}</span>
                      <span class="voting-zone-share">${propertyShare}% city share</span>
                    </div>
                    <div class="voting-zone-copy">
                      <div class="panel-kicker">${escapeHtml(property.barangay || "Unassigned")}</div>
                      <h3>${escapeHtml(property.name)}</h3>
                      <p>${escapeHtml(voteLabel(property.topNeed || "No demand yet"))}</p>
                    </div>
                    <div class="voting-zone-card-footer">
                      <span>${escapeHtml(typeLabel(property.type))}</span>
                      <strong>${propertyVotes} vote${propertyVotes === 1 ? "" : "s"}</strong>
                    </div>
                  </button>
                `;
              }).join("")}
            </div>
          </article>
        </aside>

        <section class="voting-signal-main">
          <article class="decision-card voting-stage-card voting-stage-shell" style="--voting-stage-image:url('${escapeHtml(absoluteAssetPath(selected.imageUrl || ""))}')">
            <div class="voting-stage-topline">
              <div class="voting-stage-topline-copy">
                <div class="panel-kicker">Selected location</div>
                <span class="voting-stage-path">${escapeHtml(locationLine)}</span>
              </div>
              <span class="voting-stage-live">${escapeHtml(selectedVoteTotal ? "Live demand signal" : "Awaiting first vote")}</span>
            </div>

            <div class="voting-stage-board">
              <div class="voting-stage-copy">
                <h2>${escapeHtml(selected.name)}</h2>
                <p>${escapeHtml(propertyStory(selected))}</p>
                <div class="decision-stats voting-stage-proof-row">
                  ${scorePill(selected.opportunityScore)}
                  ${verificationPill(selected.listingVerificationStatus)}
                  ${serviceChip(leadingSignalLabel, topNeedCount ? "live" : "neutral")}
                </div>
                <div class="service-chip-row voting-stage-chip-row">
                  ${selectedLabels.map((label) => serviceChip(label, "neutral")).join("")}
                  ${secondaryLabels.map((label, index) => serviceChip(label, index === 0 && topNeedCount ? "fallback" : "neutral")).join("")}
                </div>
              </div>

              <div class="voting-stage-signal-card">
                <span>Zone rank</span>
                <strong>#${selectedRank}</strong>
                <p>${selectedVoteTotal ? `${selectedVoteTotal} live votes place this zone among the city's clearest demand reads.` : "This zone is open and waiting for the first investor or resident signal."}</p>
                <div class="voting-stage-signal-meta">
                  <article>
                    <span>Top need</span>
                    <strong>${escapeHtml(leadingSignalLabel)}</strong>
                  </article>
                  <article>
                    <span>Share</span>
                    <strong>${participationPct}%</strong>
                  </article>
                </div>
              </div>
            </div>

            <div class="voting-stage-metrics voting-stage-metrics-rich">
              <article><span>Votes here</span><strong>${selectedVoteTotal}</strong></article>
              <article><span>Land area</span><strong>${escapeHtml(selected.area || "--")} ha</strong></article>
              <article><span>Guide price</span><strong>${escapeHtml(moneyShort(selected.price || 0))}</strong></article>
              <article><span>Corridor</span><strong>${escapeHtml(corridorLabel(selected.corridor))}</strong></article>
            </div>

            <div class="voting-stage-bottom">
              <div class="mini-list voting-stage-facts">
                <div class="mini-row"><span>${icon("vote")}Top-voted establishment</span><strong>${escapeHtml(leadingSignalLabel)}</strong></div>
                <div class="mini-row"><span>${icon("pulse")}Votes on leader</span><strong>${topNeedCount}</strong></div>
                <div class="mini-row"><span>${icon("spark")}Runner-up signal</span><strong>${escapeHtml(runnerUp ? `${voteLabel(runnerUp[0])} (${runnerUp[1]})` : "No runner-up yet")}</strong></div>
              </div>
              <div class="property-actions voting-stage-actions">
                <a href="${propertyHref(selected.id)}" class="btn-shell btn-shell-primary">${icon("arrow")}Open Property</a>
                <a href="${window.SFC_APP_CONFIG.basePath || ""}/property-ranking.php" class="btn-shell btn-shell-secondary">${icon("ranking")}Open Rankings</a>
              </div>
            </div>
          </article>

          <div class="voting-insight-grid">
            <article class="panel-card voting-breakdown-panel">
              <div class="voting-panel-head">
                <div>
                  <div class="panel-kicker">Vote breakdown</div>
                  <h3>Demand by establishment</h3>
                  <p>${escapeHtml(zoneLeaderSummary)}</p>
                </div>
                <div class="voting-panel-badge">
                  <span>Leader</span>
                  <strong>${escapeHtml(topNeedCount ? leadingSignalLabel : "Standby")}</strong>
                </div>
              </div>
              <div class="bar-list voting-breakdown-bars">${voteBars(selectedVotes)}</div>
            </article>

            <article class="panel-card voting-cast-panel">
              <div class="voting-panel-head">
                <div>
                  <div class="panel-kicker">Cast a vote</div>
                  <h3>${role === "admin" ? "Voting options preview" : "What does this location need?"}</h3>
                  <p>${escapeHtml(voteModeCopy)}</p>
                </div>
                <span class="voting-mode-pill ${voteModeTone}">${escapeHtml(voteModeLabel)}</span>
              </div>
              <div class="service-chip-row voting-cast-chip-row">
                ${selectedLabels.map((label) => serviceChip(label, "neutral")).join("")}
                ${serviceChip(`${activeVoteOptions.length} active option${activeVoteOptions.length === 1 ? "" : "s"}`, activeVoteOptions.length ? "live" : "neutral")}
              </div>
              ${role === "investor"
                ? `<div class="vote-card-grid">${activeVoteOptions.map((option) => voteOptionCard(option, Number(selectedVotes[option.title] || 0), Number(selectedVoteOptionId) === Number(option.id), false)).join("")}</div>`
                : role === "admin"
                  ? `
                    <div class="voting-preview-note">
                      <strong>Admin preview only.</strong>
                      <p>The cards below show the live voting inventory exactly as investors see it.</p>
                    </div>
                    <div class="vote-card-grid">${activeVoteOptions.map((option) => voteOptionCard(option, Number(selectedVotes[option.title] || 0), false, true)).join("")}</div>
                  `
                  : `
                    <div class="voting-locked-state">
                      <strong>Investor / Resident access unlocks voting.</strong>
                      <p>Enter the investor workspace to place one vote per location and help shape the next business need here.</p>
                      <a href="${window.SFC_APP_CONFIG.basePath || ""}/investor-login.php" class="btn-shell btn-shell-primary">${icon("user")}Investor / Resident Access</a>
                    </div>
                  `
              }
            </article>
          </div>

          <article class="panel-card voting-summary-panel">
            <div class="voting-panel-head">
              <div>
                <div class="panel-kicker">Overall recommendation summary</div>
                <h3>Most requested businesses across San Fernando</h3>
                <p>${escapeHtml(citywideSummary)}</p>
              </div>
              <div class="voting-summary-highlight">
                <span>City leader</span>
                <strong>${escapeHtml(cityLeaderCount ? cityLeaderLabel : "Standby")}</strong>
                <em>${cityLeaderCount} vote${cityLeaderCount === 1 ? "" : "s"}</em>
              </div>
            </div>
            <div class="voting-summary-grid">
              <div class="voting-summary-stats">
                <article><span>Total platform votes</span><strong>${totalPlatformVotes}</strong></article>
                <article><span>Active zones</span><strong>${liveZoneCount}</strong></article>
                <article><span>Selected zone share</span><strong>${participationPct}%</strong></article>
              </div>
              <div class="bar-list voting-summary-bars">
                ${aggregate.slice(0, 6).map(([label, count]) => {
                  const pct = totalPlatformVotes ? Math.round((Number(count || 0) / totalPlatformVotes) * 100) : 0;
                  const numericCount = Number(count || 0);
                  return `
                    <div class="bar-row">
                      <div class="bar-top"><span>${escapeHtml(voteLabel(label))}</span><strong>${numericCount} vote${numericCount === 1 ? "" : "s"} | ${pct}%</strong></div>
                      <div class="bar-track"><div class="bar-fill" style="width:${pct}%"></div></div>
                    </div>
                  `;
                }).join("") || `<div class="loading-panel">Platform-level recommendations will appear here once votes are cast.</div>`}
              </div>
            </div>
          </article>
        </section>
      </div>

      ${role === "admin" ? `
        <article class="panel-card vote-admin-panel voting-admin-panel-shell">
          <div class="panel-kicker">Admin vote options</div>
          <h3>${editingOption ? "Edit vote option" : "Add a visual vote option"}</h3>
          <form class="vote-admin-form" id="voteOptionForm">
            <input type="hidden" id="voteOptionId" value="${editingOption?.id || ""}">
            <label class="form-shell">
              <span>Title</span>
              <input class="input-shell" id="voteOptionTitle" value="${escapeHtml(editingOption?.title || "")}" placeholder="7/11" required>
            </label>
            <label class="form-shell">
              <span>Description</span>
              <input class="input-shell" id="voteOptionDescription" value="${escapeHtml(editingOption?.description || "")}" placeholder="Short voting caption">
            </label>
            <label class="form-shell">
              <span>Sort Order</span>
              <input class="input-shell" id="voteOptionSort" type="number" value="${escapeHtml(editingOption?.sortOrder || activeVoteOptions.length + 1)}">
            </label>
            <label class="form-shell">
              <span>Current Image Path</span>
              <input class="input-shell" id="voteOptionImagePath" value="${escapeHtml(editingOption?.imageUrl || "")}" placeholder="assets/images/vote-7-11.svg">
            </label>
            <label class="form-shell form-span-2">
              <span>Upload Image</span>
              <input class="input-shell" id="voteOptionImageFile" type="file" accept="image/*">
            </label>
            <div class="crud-actions form-span-2">
              <button type="button" class="btn-shell btn-shell-secondary" id="voteOptionReset">Reset</button>
              <button type="submit" class="btn-shell btn-shell-primary">${editingOption ? "Update Vote Option" : "Add Vote Option"}</button>
            </div>
          </form>

          <div class="vote-admin-list">
            ${voteOptions.map((option) => `
              <article class="vote-admin-row ${option.isActive === false ? "is-inactive" : ""}">
                <div class="vote-admin-row-media">${voteOptionMedia(option)}</div>
                <div class="vote-admin-row-copy">
                  <strong>${escapeHtml(option.title)}</strong>
                  <span>${escapeHtml(option.description || "No description")}</span>
                </div>
                <div class="vote-admin-row-actions">
                  <span class="tag">${option.isActive === false ? "Inactive" : "Active"}</span>
                  <button type="button" class="btn-shell btn-shell-secondary" data-vote-option-edit="${option.id}">Edit</button>
                  <button type="button" class="btn-shell btn-shell-danger" data-vote-option-delete="${option.id}">Delete</button>
                </div>
              </article>
            `).join("")}
          </div>
        </article>
      ` : ""}
      </div>
    `;

    root.querySelectorAll("[data-vote-location]").forEach((card) => {
      card.addEventListener("click", async () => {
        activeId = Number(card.dataset.voteLocation);
        await refreshActiveVoteState();
        render();
      });
    });

    root.querySelectorAll("[data-cast-vote]").forEach((button) => {
      button.addEventListener("click", async () => {
        const voteOptionId = Number(button.dataset.castVote);
        const response = await api.castVote(selected.id, voteOptionId);
        votesMap[selected.id] = response.votes || {};
        selectedVoteOptionId = response.selectedVoteOptionId || null;
        render();
      });
    });

    root.querySelectorAll("[data-vote-option-edit]").forEach((button) => {
      button.addEventListener("click", () => {
        editingVoteOptionId = Number(button.dataset.voteOptionEdit);
        render();
      });
    });

    root.querySelectorAll("[data-vote-option-delete]").forEach((button) => {
      button.addEventListener("click", async () => {
        const voteOptionId = Number(button.dataset.voteOptionDelete);
        if (!window.confirm("Remove this vote option from the active investor voting set?")) return;
        const response = await api.deleteVoteOption(voteOptionId);
        voteOptions = response.voteOptions || [];
        if (Number(editingVoteOptionId) === voteOptionId) {
          editingVoteOptionId = null;
        }
        render();
      });
    });

    document.getElementById("voteOptionReset")?.addEventListener("click", () => {
      editingVoteOptionId = null;
      render();
    });

    document.getElementById("voteOptionForm")?.addEventListener("submit", async (event) => {
      event.preventDefault();
      const formData = new FormData();
      const voteOptionId = Number(document.getElementById("voteOptionId")?.value || 0);
      formData.append("title", document.getElementById("voteOptionTitle")?.value || "");
      formData.append("description", document.getElementById("voteOptionDescription")?.value || "");
      formData.append("sort_order", document.getElementById("voteOptionSort")?.value || "0");
      formData.append("image_url", document.getElementById("voteOptionImagePath")?.value || "");
      const imageFile = document.getElementById("voteOptionImageFile")?.files?.[0];
      if (imageFile) {
        formData.append("image_file", imageFile);
      }

      const response = voteOptionId > 0
        ? await api.updateVoteOption(voteOptionId, formData)
        : await api.createVoteOption(formData);

      voteOptions = response.voteOptions || [];
      editingVoteOptionId = null;
      render();
    });
  };

  render();
}

function showcasePrimaryMetricMarkup(item) {
  const isGap = showcasePipelineMode(item) === "investment_gap";
  const label = item?.primaryMetricLabel || (item?.featureType === "city_pipeline" ? (isGap ? "Gap level" : "Expected launch") : "Offer window");
  if (item?.featureType === "offer_board" && item?.countdownAt) {
    return `
      <article class="showcase-metric">
        <span>${escapeHtml(label)}</span>
        <strong data-showcase-countdown="${escapeHtml(item.countdownAt)}">${escapeHtml(formatCountdownDistance(item.countdownAt))}</strong>
      </article>
    `;
  }

  const value = item?.primaryMetricValue || (item?.completionTarget ? formatProspectusDate(item.completionTarget) : (isGap ? "Investor review" : "Pending"));
  return `
    <article class="showcase-metric">
      <span>${escapeHtml(label)}</span>
      <strong>${escapeHtml(value)}</strong>
    </article>
  `;
}

function showcaseTimelineValue(item) {
  if (item?.featureType === "offer_board" && item?.countdownAt) {
    return formatCountdownDistance(item.countdownAt);
  }

  if (item?.primaryMetricValue) {
    return item.primaryMetricValue;
  }

  if (showcasePipelineMode(item) === "investment_gap") {
    return "Investor review";
  }

  if (item?.completionTarget) {
    return formatProspectusDate(item.completionTarget);
  }

  return "Pending";
}

function showcaseTimelineValueMarkup(item) {
  if (item?.featureType === "offer_board" && item?.countdownAt) {
    return `<strong data-showcase-countdown="${escapeHtml(item.countdownAt)}">${escapeHtml(formatCountdownDistance(item.countdownAt))}</strong>`;
  }

  return `<strong>${escapeHtml(showcaseTimelineValue(item))}</strong>`;
}

function showcaseSecondaryMetricMarkup(item) {
  const isGap = showcasePipelineMode(item) === "investment_gap";
  const label = item?.secondaryMetricLabel || (item?.featureType === "city_pipeline" ? (isGap ? "Tracked supply" : "Development stage") : "Current offer");
  const value = item?.secondaryMetricValue || (isGap && item?.supplySignal ? showcaseSupplySignalLabel(item.supplySignal) : showcaseStateLabel(item?.status));
  return `
    <article class="showcase-metric showcase-metric-secondary">
      <span>${escapeHtml(label)}</span>
      <strong>${escapeHtml(value)}</strong>
    </article>
  `;
}

function showcaseCardMarkup(item) {
  const isOffer = item.featureType === "offer_board";
  return `
    <article class="showcase-card ${item.featureType === "city_pipeline" ? "is-pipeline" : "is-offer"}">
      <div class="showcase-card-halo"></div>
      <div class="showcase-card-media">
        <img src="${escapeHtml(showcaseImageSrc(item))}" alt="${escapeHtml(item.title)}">
        <div class="showcase-card-media-top">
          <span class="showcase-mini-badge">${escapeHtml(item.partnerLabel || showcaseFeatureLabel(item.featureType))}</span>
          ${showcaseStatePill(item)}
        </div>
      </div>
      <div class="showcase-card-body">
        <div class="showcase-card-head">
          <div class="showcase-card-topline">
            <div class="showcase-card-location">${escapeHtml(item.locationLabel || "San Fernando, La Union")}</div>
            ${isOffer ? `<span class="showcase-card-timeline">${escapeHtml(showcaseTimelineValue(item))}</span>` : ""}
          </div>
          <h3>${escapeHtml(item.title)}</h3>
          <p>${escapeHtml(truncate(item.summary || item.description || "", isOffer ? 132 : 140))}</p>
        </div>
        <div class="showcase-card-chip-row">
          ${showcasePipelineModeChip(item)}
          ${showcaseSupplySignalChip(item)}
          ${item.category ? `<span class="meta-chip">${escapeHtml(item.category)}</span>` : ""}
          ${item.barangay ? `<span class="meta-chip">${escapeHtml(item.barangay)}</span>` : ""}
          ${item.isFeatured ? `<span class="meta-chip showcase-featured-chip">${icon("spark")}Featured</span>` : ""}
        </div>
        <div class="showcase-card-metrics">
          ${showcasePrimaryMetricMarkup(item)}
          ${showcaseSecondaryMetricMarkup(item)}
        </div>
        ${showcaseOpportunityBriefMarkup(item, "card")}
        <div class="showcase-card-actions">
          <a href="${escapeHtml(showcaseActionHref(item))}" class="btn-shell btn-shell-primary">${icon("arrow")}${escapeHtml(showcaseActionLabel(item))}</a>
        </div>
      </div>
    </article>
  `;
}

function showcaseTopFrequency(items = [], selector, fallback = "Standby") {
  const counts = new Map();
  items.forEach((item) => {
    const rawValue = typeof selector === "function" ? selector(item) : item?.[selector];
    const label = String(rawValue || "").trim();
    if (!label) return;
    counts.set(label, (counts.get(label) || 0) + 1);
  });

  let top = fallback;
  let count = 0;
  counts.forEach((valueCount, valueLabel) => {
    if (valueCount > count) {
      top = valueLabel;
      count = valueCount;
    }
  });

  return { label: top, count };
}

function showcaseCityLeadCardMarkup(item, tone = "gap") {
  if (!item) return "";

  const isGap = showcasePipelineMode(item) === "investment_gap";
  const summary = truncate(item.description || item.summary || "", 196);
  const primaryLabel = item?.primaryMetricLabel || (isGap ? "Gap level" : "Expected launch");
  const primaryValue = item?.primaryMetricValue || (isGap ? "Investor review" : showcaseTimelineValue(item));
  const secondaryLabel = item?.secondaryMetricLabel || (isGap ? "Tracked supply" : "Development stage");
  const secondaryValue = item?.secondaryMetricValue || (isGap ? showcaseSupplySignalLabel(item.supplySignal) : showcaseStateLabel(item.status));
  const tertiaryLabel = isGap ? "Best-fit operator" : "Watch zone";
  const tertiaryValue = isGap
    ? (item?.idealOperator || "Operator signal pending")
    : (item?.barangay || item?.locationLabel || "San Fernando, La Union");
  const note = isGap
    ? (item?.avoidanceNote
      ? `Avoid duplicate build: ${item.avoidanceNote}`
      : truncate(item?.investorThesis || item?.summary || "Whitespace rationale is still being refined.", 120))
    : `${item?.partnerLabel || "City Pipeline"} is tracking this as ${showcaseStateLabel(item.status).toLowerCase()} momentum.`;

  return `
    <article class="showcase-city-lead-card is-${tone}">
      <div class="showcase-city-lead-media">
        <img src="${escapeHtml(showcaseImageSrc(item))}" alt="${escapeHtml(item.title)}">
        <div class="showcase-city-lead-badges">
          <span class="showcase-city-lead-kicker">${escapeHtml(isGap ? "Investor Gap Radar" : "Pipeline Momentum")}</span>
          ${showcaseStatePill(item)}
        </div>
      </div>
      <div class="showcase-city-lead-body">
        <div class="showcase-city-lead-topline">
          <span class="showcase-city-lead-location">${escapeHtml(item.locationLabel || "San Fernando, La Union")}</span>
          <span class="showcase-city-lead-partner">${escapeHtml(item.partnerLabel || showcaseFeatureLabel(item.featureType))}</span>
        </div>
        <div class="showcase-city-lead-heading">
          <h3>${escapeHtml(item.title)}</h3>
          <p>${escapeHtml(summary)}</p>
        </div>
        <div class="showcase-card-chip-row showcase-city-lead-chip-row">
          ${showcasePipelineModeChip(item)}
          ${showcaseSupplySignalChip(item)}
          ${item.category ? `<span class="meta-chip">${escapeHtml(item.category)}</span>` : ""}
          ${item.barangay ? `<span class="meta-chip">${escapeHtml(item.barangay)}</span>` : ""}
          ${item.isFeatured ? `<span class="meta-chip showcase-featured-chip">${icon("spark")}Featured signal</span>` : ""}
        </div>
        <div class="showcase-city-lead-stats">
          <div>
            <span>${escapeHtml(primaryLabel)}</span>
            <strong>${escapeHtml(primaryValue)}</strong>
          </div>
          <div>
            <span>${escapeHtml(secondaryLabel)}</span>
            <strong>${escapeHtml(secondaryValue)}</strong>
          </div>
          <div>
            <span>${escapeHtml(tertiaryLabel)}</span>
            <strong>${escapeHtml(tertiaryValue)}</strong>
          </div>
        </div>
        <div class="showcase-city-lead-note">
          ${isGap ? icon("shield") : icon("pipeline")}
          <p>${escapeHtml(note)}</p>
        </div>
        <div class="showcase-city-lead-actions">
          <a href="${escapeHtml(showcaseActionHref(item))}" class="btn-shell btn-shell-primary">${icon("arrow")}${escapeHtml(showcaseActionLabel(item))}</a>
        </div>
      </div>
    </article>
  `;
}

function showcaseCitySignalCardMarkup(item, variant = "standard") {
  if (!item) return "";

  const isGap = showcasePipelineMode(item) === "investment_gap";
  const isCompact = variant === "compact";
  const primaryLabel = item?.primaryMetricLabel || (isGap ? "Gap level" : "Expected launch");
  const primaryValue = item?.primaryMetricValue || (isGap ? "Investor review" : showcaseTimelineValue(item));
  const secondaryLabel = item?.secondaryMetricLabel || (isGap ? "Tracked supply" : "Development stage");
  const secondaryValue = item?.secondaryMetricValue || (isGap ? showcaseSupplySignalLabel(item.supplySignal) : showcaseStateLabel(item.status));
  const note = isGap
    ? (item?.idealOperator || item?.investorThesis || "Operator signal pending")
    : `${item?.partnerLabel || "City Pipeline"} / ${showcaseTimelineValue(item)}`;

  return `
    <article class="showcase-city-signal-card ${isGap ? "is-gap" : "is-project"} ${isCompact ? "is-compact" : ""}">
      <div class="showcase-city-signal-media">
        <img src="${escapeHtml(showcaseImageSrc(item))}" alt="${escapeHtml(item.title)}">
        <div class="showcase-city-signal-media-badge">${escapeHtml(item.category || (isGap ? "Investor gap" : "Future project"))}</div>
      </div>
      <div class="showcase-city-signal-body">
        <div class="showcase-city-signal-topline">
          <span>${escapeHtml(item.locationLabel || "San Fernando, La Union")}</span>
          ${showcaseStatePill(item)}
        </div>
        <h4>${escapeHtml(item.title)}</h4>
        <p>${escapeHtml(truncate(item.summary || item.description || "", isCompact ? 96 : 118))}</p>
        <div class="showcase-city-signal-stats">
          <div>
            <span>${escapeHtml(primaryLabel)}</span>
            <strong>${escapeHtml(primaryValue)}</strong>
          </div>
          <div>
            <span>${escapeHtml(secondaryLabel)}</span>
            <strong>${escapeHtml(secondaryValue)}</strong>
          </div>
        </div>
        <div class="showcase-city-signal-note">
          ${isGap ? icon("user") : icon("clock")}
          <strong>${escapeHtml(truncate(note, isCompact ? 88 : 108))}</strong>
        </div>
        <a href="${escapeHtml(showcaseActionHref(item))}" class="showcase-city-signal-link">${icon("arrow")}${escapeHtml(showcaseActionLabel(item))}</a>
      </div>
    </article>
  `;
}

function showcaseCityLaneMarkup({
  kicker,
  title,
  copy,
  tone = "gap",
  collection = [],
  emptyTitle,
  emptyCopy,
  metaLabels = [],
}) {
  const lead = collection.find((item) => item.isFeatured) || collection[0] || null;
  const supporting = lead ? collection.filter((item) => item !== lead) : [];
  const meta = metaLabels.filter(Boolean);

  return `
    <section class="showcase-city-lane is-${tone}">
      <div class="showcase-city-lane-head">
        <div>
          <div class="panel-kicker">${escapeHtml(kicker)}</div>
          <h3>${escapeHtml(title)}</h3>
          <p>${escapeHtml(copy)}</p>
        </div>
        ${meta.length ? `
          <div class="showcase-city-lane-meta">
            ${meta.map((label) => `<span>${escapeHtml(label)}</span>`).join("")}
          </div>
        ` : ""}
      </div>
      ${lead ? `
        <div class="showcase-city-lane-grid ${supporting.length ? "" : "is-single"}">
          ${showcaseCityLeadCardMarkup(lead, tone)}
        </div>
        ${supporting.length ? `
          <div class="showcase-city-overflow-grid">
            ${supporting.map((entry) => showcaseCitySignalCardMarkup(entry, "compact")).join("")}
          </div>
        ` : ""}
      ` : emptyState(emptyTitle, emptyCopy)}
    </section>
  `;
}

function showcaseSpotlightMarkup(item) {
  if (!item) return "";

  const spotlightSummary = truncate(item.description || item.summary || "", item.featureType === "city_pipeline" ? 168 : 220);
  const partnerLabel = item.partnerLabel || showcaseFeatureLabel(item.featureType);

  return `
    <article class="showcase-spotlight-card ${item.featureType === "city_pipeline" ? "is-pipeline" : "is-offer"}">
      <div class="showcase-spotlight-media">
        <img src="${escapeHtml(showcaseImageSrc(item))}" alt="${escapeHtml(item.title)}">
      </div>
      <div class="showcase-spotlight-copy">
        <div class="showcase-spotlight-topline">
          <div>
            <div class="panel-kicker">Featured ${escapeHtml(showcaseFeatureLabel(item.featureType))}</div>
            <span class="showcase-spotlight-location">${escapeHtml(item.locationLabel || "San Fernando, La Union")}</span>
          </div>
          ${showcaseStatePill(item)}
        </div>
        <div class="showcase-spotlight-heading">
          <h3>${escapeHtml(item.title)}</h3>
          <span class="showcase-spotlight-partner">${escapeHtml(partnerLabel)}</span>
        </div>
        <p>${escapeHtml(spotlightSummary)}</p>
        <div class="showcase-spotlight-stats">
          <div><span>${escapeHtml(item.primaryMetricLabel || "Timeline")}</span>${showcaseTimelineValueMarkup(item)}</div>
          <div><span>${escapeHtml(item.secondaryMetricLabel || "Signal")}</span><strong>${escapeHtml(item.secondaryMetricValue || showcaseStateLabel(item.status))}</strong></div>
          <div><span>Location</span><strong>${escapeHtml(item.locationLabel || "San Fernando, La Union")}</strong></div>
        </div>
        <div class="showcase-card-chip-row">
          ${showcasePipelineModeChip(item)}
          ${showcaseSupplySignalChip(item)}
          ${item.category ? `<span class="meta-chip">${escapeHtml(item.category)}</span>` : ""}
          ${item.barangay ? `<span class="meta-chip">${escapeHtml(item.barangay)}</span>` : ""}
          ${item.isFeatured ? `<span class="meta-chip showcase-featured-chip">${icon("spark")}Featured</span>` : ""}
        </div>
        ${showcaseOpportunityBriefMarkup(item, "spotlight")}
        <div class="showcase-spotlight-actions">
          <a href="${escapeHtml(showcaseActionHref(item))}" class="btn-shell btn-shell-primary">${icon("arrow")}${escapeHtml(showcaseActionLabel(item))}</a>
        </div>
      </div>
    </article>
  `;
}

async function initShowcasePage(rootId, featureType) {
  const root = document.getElementById(rootId);
  if (!root) return;

  let items = (await api.showcase(featureType)).items || [];
  let search = "";
  let activeCategory = "all";
  let activeLane = "all";

  const render = () => {
    const categories = Array.from(new Set(items.map((item) => String(item.category || "").trim()).filter(Boolean)));
    const filtered = items.filter((item) => {
      if (featureType === "city_pipeline") {
        const itemMode = showcasePipelineMode(item);
        if (activeLane === "investment_gap" && itemMode !== "investment_gap") return false;
        if (activeLane === "future_project" && itemMode === "investment_gap") return false;
      }
      if (activeCategory !== "all" && String(item.category || "").toLowerCase() !== activeCategory) {
        return false;
      }
      if (search && !showcaseSearchHaystack(item).includes(search.toLowerCase())) {
        return false;
      }
      return true;
    });
    const featured = filtered.find((item) => item.isFeatured) || filtered[0] || items.find((item) => item.isFeatured) || items[0] || null;
    const filteredFeaturedCount = filtered.filter((item) => item.isFeatured).length;
    const filteredTimelineCount = filtered.filter((item) => item.countdownAt || item.completionTarget).length;
    const filteredLocations = Array.from(new Set(
      filtered
        .map((item) => String(item.locationLabel || "San Fernando, La Union").trim())
        .filter(Boolean)
    ));
    const liveStatuses = new Set(["open", "closing_soon", "paused"]);
    const liveCount = filtered.filter((item) => liveStatuses.has(String(item.status || "").toLowerCase())).length;
    const awardedCount = filtered.filter((item) => String(item.status || "").toLowerCase() === "awarded").length;
    const linkedCount = filtered.filter((item) => Number(item.relatedPropertyId || 0) > 0).length;
    const leadWindow = featured ? showcaseTimelineValue(featured) : "Pending";
    const leadSignal = featured ? (featured.secondaryMetricValue || showcaseStateLabel(featured.status)) : "Standby";
    const leadPartnerLabel = featured?.partnerLabel || "Editorial release";
    const locationLabel = filteredLocations.length
      ? (filteredLocations.length === 1 ? filteredLocations[0] : `${filteredLocations.length} locations`)
      : "San Fernando, La Union";
    const boardCategoryLabel = activeCategory === "all"
      ? (categories.length ? `${categories.length} ${categories.length === 1 ? "category" : "categories"}` : "Open board")
      : titleCase(activeCategory);
    const stageNoteTitle = featureType === "city_pipeline"
      ? "A city intelligence board for what San Fernando still needs and what is already forming."
      : "A premium release board for opportunities that deserve cleaner spotlight treatment.";
    const stageNoteCopy = featureType === "city_pipeline"
      ? "Pipeline entries stay separate from live inventory so investors can tell the difference between active projects, white-space opportunities, and areas that may already be too crowded."
      : "Offer Board lets the admin team surface curated opportunities with better timing, imagery, and visual storytelling than a standard listing grid.";
    const collectionTitle = featureType === "city_pipeline" ? "Pipeline Collection" : "Curated Offer Collection";
    const collectionCopy = featureType === "city_pipeline"
      ? "Published city pipeline entries are separated into investor gaps and real projects so the board is easier to scan and harder to misread."
      : "Published Offer Board entries appear here as a cleaner premium rail of spotlight opportunities.";
    const filteredCityCounts = featureType === "city_pipeline" ? showcasePipelineCounts(filtered) : null;
    const gapItems = featureType === "city_pipeline"
      ? filtered.filter((item) => showcasePipelineMode(item) === "investment_gap")
      : [];
    const projectItems = featureType === "city_pipeline"
      ? filtered.filter((item) => showcasePipelineMode(item) !== "investment_gap")
      : [];
    const leadGap = featureType === "city_pipeline"
      ? (gapItems.find((item) => item.isFeatured) || gapItems[0] || null)
      : null;
    const leadProject = featureType === "city_pipeline"
      ? (projectItems.find((item) => item.isFeatured) || projectItems[0] || null)
      : null;
    const dominantCategory = featureType === "city_pipeline"
      ? showcaseTopFrequency(filtered, (item) => item.category, "Mixed signals")
      : null;
    const dominantGapCategory = featureType === "city_pipeline"
      ? showcaseTopFrequency(gapItems, (item) => item.category, "No gap sector yet")
      : null;
    const dominantGapLocation = featureType === "city_pipeline"
      ? showcaseTopFrequency(gapItems, (item) => item.barangay || item.locationLabel, "Citywide")
      : null;
    const dominantProjectLocation = featureType === "city_pipeline"
      ? showcaseTopFrequency(projectItems, (item) => item.locationLabel, "Citywide")
      : null;
    const projectStagePulse = featureType === "city_pipeline"
      ? showcaseTopFrequency(projectItems, (item) => showcaseStateLabel(item.status), "No project pace yet")
      : null;
    const collectionSectionMarkup = (kicker, title, copy, collection, emptyTitle, emptyCopy) => `
      <section class="showcase-card-section">
        <div class="showcase-card-section-head">
          <div>
            <div class="panel-kicker">${escapeHtml(kicker)}</div>
            <h3>${escapeHtml(title)}</h3>
            <p>${escapeHtml(copy)}</p>
          </div>
          <div class="showcase-card-section-meta">
            <span>${collection.length} cards in view</span>
            <span>${activeCategory === "all" ? "All categories" : escapeHtml(titleCase(activeCategory))}</span>
          </div>
        </div>
        <section class="showcase-card-grid">
          ${collection.length
            ? collection.map((item) => showcaseCardMarkup(item)).join("")
            : emptyState(emptyTitle, emptyCopy)}
        </section>
      </section>
    `;

    root.innerHTML = `
      <div class="showcase-stage ${featureType === "city_pipeline" ? "is-pipeline" : "is-offer"}">
        <section class="showcase-hero">
          <div class="showcase-hero-copy">
            ${featureType === "city_pipeline" ? `
              <div class="panel-kicker">${escapeHtml(showcaseFeatureLabel(featureType))}</div>
              <h2>What should investors <em>build next</em> in San Fernando?</h2>
              <p>Use this board to separate active future projects from the businesses, services, and establishments the city still wants to attract, so investors do not duplicate what is already saturated.</p>
              <div class="showcase-search-shell">
                <input class="showcase-search-input" id="showcaseSearchInput" value="${escapeHtml(search)}" placeholder="Search missing businesses, establishments, and future projects">
              </div>
              <div class="showcase-filter-row showcase-mode-row">
                <button type="button" class="filter-chip ${activeLane === "all" ? "active" : ""}" data-showcase-lane="all">All city signals</button>
                <button type="button" class="filter-chip ${activeLane === "investment_gap" ? "active" : ""}" data-showcase-lane="investment_gap">What the city still needs</button>
                <button type="button" class="filter-chip ${activeLane === "future_project" ? "active" : ""}" data-showcase-lane="future_project">Projects already forming</button>
              </div>
              <div class="showcase-filter-row">
                <button type="button" class="filter-chip ${activeCategory === "all" ? "active" : ""}" data-showcase-category="all">All</button>
                ${categories.map((category) => `
                  <button type="button" class="filter-chip ${activeCategory === category.toLowerCase() ? "active" : ""}" data-showcase-category="${escapeHtml(category.toLowerCase())}">${escapeHtml(category)}</button>
                `).join("")}
              </div>
              <div class="showcase-summary-ribbon showcase-summary-ribbon-city">
                <span>${icon("pipeline")}${filtered.length === 1 ? "1 signal in view" : `${filtered.length} signals in view`}</span>
                <span>${icon("spark")}${escapeHtml(dominantCategory?.count ? `${dominantCategory.label} leads` : "Mixed sector read")}</span>
                <span>${icon("map")}${escapeHtml(locationLabel)}</span>
                <span>${icon("shield")}${filteredCityCounts?.caution ? `${filteredCityCounts.caution} duplicate-build watchout${filteredCityCounts.caution === 1 ? "" : "s"}` : "Open whitespace read"}</span>
              </div>
              <div class="showcase-hero-stat-grid">
                <article class="showcase-hero-stat">
                  <span>Whitespace briefs</span>
                  <strong>${filteredCityCounts?.gaps || 0}</strong>
                </article>
                <article class="showcase-hero-stat">
                  <span>Not in tracked supply</span>
                  <strong>${filteredCityCounts?.notPresent || 0}</strong>
                </article>
                <article class="showcase-hero-stat">
                  <span>Under-supplied</span>
                  <strong>${filteredCityCounts?.undersupplied || 0}</strong>
                </article>
                <article class="showcase-hero-stat">
                  <span>Projects in motion</span>
                  <strong>${filteredCityCounts?.projects || 0}</strong>
                </article>
              </div>
            ` : `
              <div class="showcase-offer-hero-topline">
                <div>
                  <div class="panel-kicker">${escapeHtml(showcaseFeatureLabel(featureType))}</div>
                  <span class="showcase-offer-overline">Editorial release board</span>
                </div>
                <span class="showcase-offer-live-pill">${filtered.length} visible now</span>
              </div>
              <div class="showcase-offer-heading">
                <h2>Which curated <em>offers</em> deserve the cleanest spotlight?</h2>
                <p>Use this board to surface admin-curated opportunities in a more editorial, image-led format than the standard listing pages.</p>
              </div>
              <div class="showcase-offer-toolbar">
                <div class="showcase-search-shell">
                  <input class="showcase-search-input" id="showcaseSearchInput" value="${escapeHtml(search)}" placeholder="Search curated offers, partners, and locations">
                </div>
                <div class="showcase-filter-panel">
                  <div class="showcase-filter-row">
                    <button type="button" class="filter-chip ${activeCategory === "all" ? "active" : ""}" data-showcase-category="all">All</button>
                    ${categories.map((category) => `
                      <button type="button" class="filter-chip ${activeCategory === category.toLowerCase() ? "active" : ""}" data-showcase-category="${escapeHtml(category.toLowerCase())}">${escapeHtml(category)}</button>
                    `).join("")}
                  </div>
                </div>
              </div>
              <div class="showcase-summary-ribbon showcase-summary-ribbon-offer">
                <span>${icon("spark")}${filtered.length === 1 ? "1 offer in view" : `${filtered.length} offers in view`}</span>
                <span>${icon("map")}${escapeHtml(locationLabel)}</span>
                <span>${icon("clock")}Timed release board</span>
                <span>${icon("arrow")}${escapeHtml(boardCategoryLabel)}</span>
              </div>
              <div class="showcase-offer-stat-grid">
                <article class="showcase-offer-stat is-accent">
                  <span>Visible now</span>
                  <strong>${filtered.length}</strong>
                  <p>Curated offer cards in the current board view.</p>
                </article>
                <article class="showcase-offer-stat">
                  <span>Live windows</span>
                  <strong>${filteredTimelineCount}</strong>
                  <p>Entries with active release timing or countdown context.</p>
                </article>
                <article class="showcase-offer-stat">
                  <span>Featured</span>
                  <strong>${filteredFeaturedCount}</strong>
                  <p>Items carrying spotlight treatment inside the current mix.</p>
                </article>
                <article class="showcase-offer-stat">
                  <span>Direct routes</span>
                  <strong>${linkedCount}</strong>
                  <p>Cards that jump straight into the deeper property thesis.</p>
                </article>
              </div>
              <div class="showcase-offer-insight-grid">
                <article class="showcase-offer-insight">
                  <span>Lead item</span>
                  <strong>${escapeHtml(featured?.title || "Standby")}</strong>
                  <p>${escapeHtml(truncate(featured?.summary || featured?.description || "Select a featured offer to anchor the editorial spotlight.", 110))}</p>
                </article>
                <article class="showcase-offer-insight">
                  <span>Release cadence</span>
                  <strong>${escapeHtml(leadWindow)}</strong>
                  <p>${escapeHtml(featured ? `${leadSignal} is the current timing signal for the lead offer.` : "Waiting for a published timing window.")}</p>
                </article>
                <article class="showcase-offer-insight">
                  <span>Board posture</span>
                  <strong>${liveCount} live / ${awardedCount} awarded</strong>
                  <p>${escapeHtml(linkedCount ? "Property-linked offers stay one click away from the full diligence view." : "When direct property routing is missing, the board falls back to the rankings surface.")}</p>
                </article>
              </div>
            `}
          </div>
          ${featureType === "city_pipeline" ? `
            <div class="showcase-hero-side showcase-hero-side-city">
              ${showcaseSpotlightMarkup(featured)}
              <div class="showcase-city-command-grid">
                <article class="showcase-city-command-card is-wide is-accent">
                  <span>${icon("pipeline")}Board pulse</span>
                  <strong>${filtered.length} live signal${filtered.length === 1 ? "" : "s"} across ${filteredLocations.length} zone${filteredLocations.length === 1 ? "" : "s"}</strong>
                  <p>${escapeHtml(activeLane === "all"
                    ? "Compare unmet investor demand against real pipeline momentum without mixing the two stories together."
                    : activeLane === "investment_gap"
                      ? "Whitespace mode is active, so every signal in view is about what the city still wants investors to build."
                      : "Momentum mode is active, so every signal in view reflects a project already forming, approved, or under way.")}</p>
                </article>
                <article class="showcase-city-command-card">
                  <span>${icon("spark")}Top whitespace</span>
                  <strong>${escapeHtml(leadGap?.title || "No investor gap in view")}</strong>
                  <p>${escapeHtml(leadGap ? `${showcaseSupplySignalLabel(leadGap.supplySignal || "under_supplied")} / ${leadGap.primaryMetricValue || "Priority review"}` : "Switch back to All city signals to compare unmet demand.")}</p>
                </article>
                <article class="showcase-city-command-card">
                  <span>${icon("clock")}Momentum lead</span>
                  <strong>${escapeHtml(leadProject?.title || "No future project in view")}</strong>
                  <p>${escapeHtml(leadProject ? `${showcaseTimelineValue(leadProject)} / ${showcaseStateLabel(leadProject.status)}` : "Project momentum returns when future-project filters are active.")}</p>
                </article>
                <article class="showcase-city-command-card">
                  <span>${icon("shield")}Duplicate-build watch</span>
                  <strong>${filteredCityCounts?.caution || 0} crowded warning${filteredCityCounts?.caution === 1 ? "" : "s"}</strong>
                  <p>${escapeHtml(filteredCityCounts?.caution
                    ? "These warnings stay visible so investors do not misread saturated lanes as whitespace."
                    : "No crowded supply signals are visible in this view, so the board is leaning toward open demand and active momentum.")}</p>
                </article>
              </div>
            </div>
          ` : `
            <div class="showcase-hero-side showcase-hero-side-offer">
              ${showcaseSpotlightMarkup(featured)}
              <article class="showcase-intel-dock showcase-intel-dock-offer">
                <div class="panel-kicker">Board Read</div>
                <h3>${escapeHtml(stageNoteTitle)}</h3>
                <p>${escapeHtml(stageNoteCopy)}</p>
                <div class="showcase-intel-list showcase-intel-list-offer">
                  <div><span>Lead item</span><strong>${escapeHtml(featured?.title || "Standby")}</strong></div>
                  <div><span>Lead partner</span><strong>${escapeHtml(leadPartnerLabel)}</strong></div>
                  <div><span>Primary timeline</span><strong>${escapeHtml(leadWindow)}</strong></div>
                  <div><span>Current signal</span><strong>${escapeHtml(leadSignal)}</strong></div>
                </div>
              </article>
            </div>
          `}
        </section>

        ${featureType === "city_pipeline" ? `
          ${activeLane !== "future_project" ? showcaseCityLaneMarkup({
            kicker: "Investor gap radar",
            title: "Where San Fernando still wants new operators to show up.",
            copy: "Whitespace briefs stay separate from project momentum so investors can act on unmet demand without copying already saturated supply.",
            tone: "gap",
            collection: gapItems,
            emptyTitle: "No investor-gap entries match this view",
            emptyCopy: "Broaden the search or switch back to All city signals.",
            metaLabels: [
              `${gapItems.length} whitespace signal${gapItems.length === 1 ? "" : "s"}`,
              dominantGapCategory?.count ? `${dominantGapCategory.label} leads` : "",
              dominantGapLocation?.count ? `${dominantGapLocation.label} focus` : "",
            ],
          }) : ""}
          ${activeLane !== "investment_gap" ? showcaseCityLaneMarkup({
            kicker: "Pipeline momentum",
            title: "What is already gaining real city traction.",
            copy: "These are future developments or advancing concepts already moving through the city story, so investors can distinguish momentum from fresh whitespace.",
            tone: "project",
            collection: projectItems,
            emptyTitle: "No future-project entries match this view",
            emptyCopy: "Broaden the search or switch back to Projects already forming.",
            metaLabels: [
              `${projectItems.length} project signal${projectItems.length === 1 ? "" : "s"}`,
              projectStagePulse?.count ? `${projectStagePulse.label} pace` : "",
              dominantProjectLocation?.count ? `${dominantProjectLocation.label} cluster` : "",
            ],
          }) : ""}
        ` : `
          <section class="showcase-card-section showcase-card-section-offer">
            <div class="showcase-card-section-head showcase-card-section-head-offer">
              <div>
                <div class="panel-kicker">${escapeHtml(collectionTitle)}</div>
                <h3>What is currently ready for a cleaner release.</h3>
                <p>${escapeHtml(collectionCopy)}</p>
              </div>
              <div class="showcase-card-section-meta">
                <span>${filtered.length} cards in view</span>
                <span>${activeCategory === "all" ? "All categories" : escapeHtml(titleCase(activeCategory))}</span>
                <span>${escapeHtml(locationLabel)}</span>
              </div>
            </div>
            <section class="showcase-card-grid showcase-card-grid-offer">
              ${filtered.length
                ? filtered.map((item) => showcaseCardMarkup(item)).join("")
                : emptyState("No offer board entries match this view", "Try a broader search or switch back to All.")}
            </section>
          </section>
        `}
      </div>
    `;

    document.getElementById("showcaseSearchInput")?.addEventListener("input", (event) => {
      search = event.target.value;
      render();
    });

    root.querySelectorAll("[data-showcase-category]").forEach((button) => {
      button.addEventListener("click", () => {
        activeCategory = String(button.dataset.showcaseCategory || "all");
        render();
      });
    });

    root.querySelectorAll("[data-showcase-lane]").forEach((button) => {
      button.addEventListener("click", () => {
        activeLane = String(button.dataset.showcaseLane || "all");
        render();
      });
    });

    updateShowcaseCountdownNodes(root);
    window.clearInterval(root._showcaseTicker);
    root._showcaseTicker = window.setInterval(() => updateShowcaseCountdownNodes(root), 1000);
  };

  render();
}

function showcaseAdminCardMarkup(item) {
  return `
    <article class="showcase-admin-card">
      <div class="showcase-admin-media">
        <img src="${escapeHtml(showcaseImageSrc(item))}" alt="${escapeHtml(item.title)}">
      </div>
      <div class="showcase-admin-copy">
        <div class="showcase-admin-topline">
          ${showcaseStatePill(item)}
          ${item.isPublished ? `<span class="meta-chip">Published</span>` : `<span class="meta-chip">Hidden</span>`}
          ${item.isFeatured ? `<span class="meta-chip showcase-featured-chip">${icon("spark")}Featured</span>` : ""}
        </div>
        <h3>${escapeHtml(item.title)}</h3>
        <p>${escapeHtml(truncate(item.summary || item.description || "", 160))}</p>
        <div class="showcase-card-chip-row">
          ${showcasePipelineModeChip(item)}
          ${showcaseSupplySignalChip(item)}
          ${item.category ? `<span class="meta-chip">${escapeHtml(item.category)}</span>` : ""}
          ${item.locationLabel ? `<span class="meta-chip">${escapeHtml(item.locationLabel)}</span>` : ""}
          ${item.relatedPropertyName ? `<span class="meta-chip">Linked: ${escapeHtml(item.relatedPropertyName)}</span>` : ""}
        </div>
        <div class="showcase-card-metrics">
          ${showcasePrimaryMetricMarkup(item)}
          ${showcaseSecondaryMetricMarkup(item)}
        </div>
        ${showcaseOpportunityBriefMarkup(item, "card")}
      </div>
      <div class="showcase-admin-actions">
        <a href="${escapeHtml(item.featureType === "city_pipeline" ? `${window.SFC_APP_CONFIG?.basePath || ""}/city-pipeline.php` : `${window.SFC_APP_CONFIG?.basePath || ""}/offer-board.php`)}" class="btn-shell btn-shell-secondary">View Public</a>
        <button type="button" class="btn-shell btn-shell-secondary" data-showcase-toggle-publish="${item.id}">${item.isPublished ? "Hide" : "Publish"}</button>
        <button type="button" class="btn-shell btn-shell-primary" data-showcase-edit="${item.id}">Edit</button>
        <button type="button" class="btn-shell btn-shell-danger" data-showcase-delete="${item.id}">Delete</button>
      </div>
    </article>
  `;
}

async function initAdminShowcase() {
  const root = document.getElementById("adminShowcaseRoot");
  if (!root) return;

  const modal = document.getElementById("showcaseCrudModal");
  const deleteModal = document.getElementById("showcaseDeleteModal");
  const form = document.getElementById("showcaseCrudForm");
  const addButton = document.getElementById("adminShowcaseAdd");
  const deleteLabel = document.getElementById("deleteShowcaseLabel");
  const confirmDeleteButton = document.getElementById("confirmDeleteShowcase");
  const wizardBackButton = document.getElementById("showcaseWizardBack");
  const wizardNextButton = document.getElementById("showcaseWizardNext");
  const wizardSubmitButton = document.getElementById("showcaseWizardSubmit");
  const feedbackNote = document.getElementById("showcaseCrudFeedback");
  let items = [];
  let properties = [];
  let activeFeature = "offer_board";
  let search = "";
  let deleteId = null;
  let currentStep = 0;
  let previewObjectUrl = "";
  let isSaving = false;

  const openModal = (target) => {
    if (target) target.hidden = false;
  };

  const closeModal = (target) => {
    if (target) target.hidden = true;
  };

  const setShowcaseFeedback = (message = "", tone = "") => {
    if (!feedbackNote) return;
    feedbackNote.hidden = !message;
    feedbackNote.textContent = message;
    feedbackNote.classList.toggle("is-success", tone === "success");
  };

  const setSavingState = (saving) => {
    isSaving = Boolean(saving);
    if (wizardNextButton) wizardNextButton.disabled = isSaving;
    if (wizardBackButton) wizardBackButton.disabled = isSaving;
    if (wizardSubmitButton) {
      wizardSubmitButton.disabled = isSaving;
      wizardSubmitButton.textContent = isSaving ? "Saving..." : (Number(document.getElementById("showcaseItemId")?.value || 0) > 0 ? "Save Changes" : "Save Showcase Item");
    }
  };

  const stampShowcaseItems = (collection = []) => {
    const stamp = Date.now();
    return collection.map((item, index) => ({
      ...item,
      _imageVersion: `${stamp}-${index}`,
    }));
  };

  const mergeShowcaseResponseItems = (response, fallbackItems = items) => {
    let nextItems = Array.isArray(response?.items) ? response.items : [...fallbackItems];
    const updatedItem = response?.item && typeof response.item === "object" ? response.item : null;

    if (updatedItem?.id) {
      let found = false;
      nextItems = nextItems.map((entry) => {
        if (Number(entry?.id) !== Number(updatedItem.id)) return entry;
        found = true;
        return {
          ...entry,
          ...updatedItem,
        };
      });

      if (!found) {
        nextItems = [updatedItem, ...nextItems];
      }
    }

    return stampShowcaseItems(nextItems);
  };

  const fillPropertyOptions = () => {
    const select = document.getElementById("showcaseRelatedProperty");
    if (!select) return;
    const existingValue = select.value;
    select.innerHTML = `
      <option value="">None</option>
      ${properties.map((property) => `<option value="${property.id}">${escapeHtml(property.name)}</option>`).join("")}
    `;
    select.value = existingValue;
  };

  const wizardSteps = () => Array.from(form?.querySelectorAll("[data-showcase-step]") || []);

  const updateWizardMeta = () => {
    const steps = wizardSteps();
    const activeStep = steps[currentStep];
    const stepCount = document.getElementById("showcaseStepCount");
    const stepTitle = document.getElementById("showcaseStepTitle");
    const stepHint = document.getElementById("showcaseStepHint");

    if (stepCount) {
      stepCount.textContent = `Step ${currentStep + 1} of ${steps.length}`;
    }

    if (stepTitle) {
      stepTitle.textContent = activeStep?.dataset.stepTitle || "Showcase Entry";
    }

    if (stepHint) {
      stepHint.textContent = activeStep?.dataset.stepHint || "Complete the current step, then continue when ready.";
    }

    wizardBackButton.hidden = currentStep === 0;
    wizardNextButton.hidden = currentStep >= steps.length - 1;
    wizardSubmitButton.hidden = currentStep !== steps.length - 1;
    wizardNextButton.textContent = currentStep === steps.length - 2 ? "Review Media & Publish" : "Next";
  };

  const goToStep = (nextStep = 0) => {
    const steps = wizardSteps();
    currentStep = Math.max(0, Math.min(Number(nextStep) || 0, Math.max(0, steps.length - 1)));

    steps.forEach((step, index) => {
      const isActive = index === currentStep;
      step.hidden = !isActive;
      step.classList.toggle("is-active", isActive);
    });

    form?.querySelectorAll("[data-showcase-step-trigger]").forEach((button, index) => {
      const isActive = index === currentStep;
      button.classList.toggle("is-active", isActive);
      button.classList.toggle("is-complete", index < currentStep);
      button.setAttribute("aria-current", isActive ? "step" : "false");
    });

    updateWizardMeta();
    setShowcaseFeedback("");
  };

  const validateStep = (index = currentStep) => {
    const step = wizardSteps()[index];
    if (!step) return true;

    const fields = Array.from(step.querySelectorAll("input, select, textarea")).filter((field) => {
      if (field.type === "hidden" || field.disabled) return false;
      if (field.closest("[hidden]")) return false;
      return true;
    });

    for (const field of fields) {
      if (!field.reportValidity()) {
        field.focus();
        return false;
      }
    }

    return true;
  };

  const validateCoreFields = () => {
    const essentials = ["showcaseTitle", "showcaseSummary"];
    for (const fieldId of essentials) {
      const field = document.getElementById(fieldId);
      if (field && !field.reportValidity()) {
        goToStep(0);
        field.focus();
        return false;
      }
    }
    return true;
  };

  const clearPreviewObjectUrl = () => {
    if (!previewObjectUrl) return;
    URL.revokeObjectURL(previewObjectUrl);
    previewObjectUrl = "";
  };

  const updateShowcaseMediaPreview = (overrideSrc = "") => {
    const previewImage = document.getElementById("showcaseMediaPreviewImage");
    const previewTitle = document.getElementById("showcaseMediaPreviewTitle");
    const previewNote = document.getElementById("showcaseMediaPreviewNote");
    const title = document.getElementById("showcaseTitle")?.value?.trim() || "Untitled showcase entry";
    const imagePath = document.getElementById("showcaseImagePath")?.value?.trim() || "assets/images/Property10.png";
    const selectedFile = document.getElementById("showcaseImage")?.files?.[0] || null;

    let source = overrideSrc || imagePath || "assets/images/Property10.png";
    let note = "Upload a new image or keep the fallback path for a first draft.";

    clearPreviewObjectUrl();

    if (selectedFile) {
      previewObjectUrl = URL.createObjectURL(selectedFile);
      source = previewObjectUrl;
      note = `${selectedFile.name} will be used when you save this entry.`;
    } else if (overrideSrc) {
      note = "This is the current saved cover image for the entry.";
    } else if (imagePath && imagePath !== "assets/images/Property10.png") {
      note = "This fallback image path will be used unless you upload a new cover.";
    }

    if (previewImage) {
      previewImage.src = source;
      previewImage.alt = title;
    }

    if (previewTitle) {
      previewTitle.textContent = title;
    }

    if (previewNote) {
      previewNote.textContent = note;
    }
  };

  const showcaseStatusCatalog = {
    offer_board: [
      ["open", "Open"],
      ["closing_soon", "Closing Soon"],
      ["awarded", "Awarded"],
      ["paused", "Paused"],
    ],
    future_project: [
      ["planned", "Planned"],
      ["approved", "Approved"],
      ["under_construction", "Under Construction"],
      ["opening_soon", "Opening Soon"],
      ["validation", "Validation"],
    ],
    investment_gap: [
      ["priority", "Priority Need"],
      ["planned", "Planned Gap"],
      ["watchlist", "Watchlist"],
      ["validation", "Needs Validation"],
    ],
  };

  const syncShowcaseFieldVisibility = (preferredStatus = "") => {
    const feature = document.getElementById("showcaseFeatureType")?.value || "offer_board";
    const pipelineMode = document.getElementById("showcasePipelineMode")?.value || "future_project";
    const isPipeline = feature === "city_pipeline";
    const isGap = isPipeline && pipelineMode === "investment_gap";
    const statusSelect = document.getElementById("showcaseStatus");
    const statusOptions = !isPipeline
      ? showcaseStatusCatalog.offer_board
      : (isGap ? showcaseStatusCatalog.investment_gap : showcaseStatusCatalog.future_project);
    const defaultStatus = !isPipeline ? "open" : (isGap ? "priority" : "planned");

    document.querySelectorAll(".showcase-pipeline-only").forEach((node) => {
      node.hidden = !isPipeline;
    });
    document.querySelectorAll(".showcase-gap-only").forEach((node) => {
      node.hidden = !isGap;
    });
    document.querySelectorAll(".showcase-offer-only").forEach((node) => {
      node.hidden = isPipeline;
    });

    if (statusSelect) {
      const currentValue = preferredStatus || statusSelect.value || defaultStatus;
      statusSelect.innerHTML = statusOptions.map(([value, label]) => `<option value="${escapeHtml(value)}">${escapeHtml(label)}</option>`).join("");
      statusSelect.value = statusOptions.some(([value]) => value === currentValue) ? currentValue : defaultStatus;
    }

    const note = document.getElementById("showcasePipelineNote");
    if (note) {
      note.textContent = isGap
        ? "Investor Gap entries are for businesses and establishments San Fernando still needs. Use the supply signal, thesis, and caution note only when they strengthen investor clarity."
        : isPipeline
          ? "Future Project entries are for approved, planned, or under-construction developments already moving through the city pipeline."
          : "Offer Board entries are for timed, curated opportunities. You can save the first draft with essentials and return later for timing or media polish.";
    }

    const featureBadge = document.getElementById("showcaseFormFeatureBadge");
    if (featureBadge) {
      featureBadge.textContent = showcaseFeatureLabel(feature);
    }

    const modeBadge = document.getElementById("showcaseFormModeBadge");
    if (modeBadge) {
      modeBadge.textContent = !isPipeline
        ? "Timed opportunity"
        : (isGap ? "Investor gap brief" : "Future project");
    }

    const modeLabel = document.getElementById("showcaseCrudModeLabel");
    if (modeLabel) {
      modeLabel.textContent = !isPipeline
        ? "Start with the offer headline and stage."
        : (isGap ? "Describe the city need before adding extra signals." : "Start with the project headline and stage.");
    }

    const guide = document.getElementById("showcaseCrudGuide");
    if (guide) {
      guide.textContent = !isPipeline
        ? "Title, stage, and summary are enough to create an Offer Board draft. Timing, metrics, and media can wait."
        : (isGap
          ? "Title, stage, summary, and supply signal are enough to publish a clear investor gap brief. The rest is optional support."
          : "Title, stage, summary, and location are enough to draft a future project card. Add dates and metrics only when they are confirmed.");
    }

    const partnerField = document.getElementById("showcasePartnerLabel");
    if (partnerField && !partnerField.value.trim() && isPipeline) {
      partnerField.value = "City Investment Desk";
    }
  };

  const fillForm = (item = null) => {
    fillPropertyOptions();
    setShowcaseFeedback("");
    setSavingState(false);
    document.getElementById("showcaseCrudTitle").textContent = item ? "Edit Showcase Item" : "Add Showcase Item";
    document.getElementById("showcaseItemId").value = item?.id || "";
    document.getElementById("showcaseFeatureType").value = item?.featureType || activeFeature;
    document.getElementById("showcasePipelineMode").value = item?.pipelineMode || "future_project";
    syncShowcaseFieldVisibility(item?.status || "");
    document.getElementById("showcaseTitle").value = item?.title || "";
    document.getElementById("showcasePartnerLabel").value = item?.partnerLabel || ((item?.featureType || activeFeature) === "city_pipeline" ? "City Investment Desk" : "");
    document.getElementById("showcaseCategory").value = item?.category || "";
    document.getElementById("showcaseLocationLabel").value = item?.locationLabel || "San Fernando, La Union";
    document.getElementById("showcaseBarangay").value = item?.barangay || "";
    document.getElementById("showcaseSupplySignal").value = item?.supplySignal || "under_supplied";
    document.getElementById("showcaseRelatedProperty").value = item?.relatedPropertyId || "";
    document.getElementById("showcasePublished").value = item?.isPublished ? "1" : "0";
    document.getElementById("showcaseFeatured").value = item?.isFeatured ? "1" : "0";
    document.getElementById("showcaseSortOrder").value = item?.sortOrder || "1";
    document.getElementById("showcaseCountdownAt").value = toDatetimeLocalValue(item?.countdownAt || "");
    document.getElementById("showcaseCompletionTarget").value = toDatetimeLocalValue(item?.completionTarget || "");
    document.getElementById("showcasePrimaryMetricLabel").value = item?.primaryMetricLabel || "";
    document.getElementById("showcasePrimaryMetricValue").value = item?.primaryMetricValue || "";
    document.getElementById("showcaseSecondaryMetricLabel").value = item?.secondaryMetricLabel || "";
    document.getElementById("showcaseSecondaryMetricValue").value = item?.secondaryMetricValue || "";
    document.getElementById("showcaseInvestorThesis").value = item?.investorThesis || "";
    document.getElementById("showcaseIdealOperator").value = item?.idealOperator || "";
    document.getElementById("showcaseAvoidanceNote").value = item?.avoidanceNote || "";
    document.getElementById("showcaseSummary").value = item?.summary || "";
    document.getElementById("showcaseDescription").value = item?.description || "";
    document.getElementById("showcaseImagePath").value = item?.coverImageUrl || "assets/images/Property10.png";
    document.getElementById("showcaseImage").value = "";

    const advancedFields = document.getElementById("showcaseAdvancedFields");
    if (advancedFields) {
      advancedFields.open = Boolean(
        item?.countdownAt
        || item?.completionTarget
        || item?.primaryMetricLabel
        || item?.primaryMetricValue
        || item?.secondaryMetricLabel
        || item?.secondaryMetricValue
        || item?.description
        || Number(item?.sortOrder || 1) > 1
        || (item?.coverImageUrl && item.coverImageUrl !== "assets/images/Property10.png")
      );
    }

    wizardSubmitButton.textContent = item ? "Save Changes" : "Save Showcase Item";
    updateShowcaseMediaPreview(item ? showcaseImageSrc(item) : "");
    goToStep(0);
  };

  const render = () => {
    const featureItems = items.filter((item) => item.featureType === activeFeature);
    const filtered = items.filter((item) => {
      if (item.featureType !== activeFeature) return false;
      if (search && !showcaseSearchHaystack(item).includes(search.toLowerCase())) return false;
      return true;
    });
    const featuredCount = items.filter((item) => item.featureType === activeFeature && item.isFeatured).length;
    const publishedCount = items.filter((item) => item.featureType === activeFeature && item.isPublished).length;
    const hiddenCount = items.filter((item) => item.featureType === activeFeature && !item.isPublished).length;
    const cityCounts = activeFeature === "city_pipeline" ? showcasePipelineCounts(featureItems) : null;

    root.innerHTML = `
      <div class="showcase-admin-layout">
        <aside class="stack">
          <article class="panel-card">
            <div class="panel-kicker">Studio status</div>
            <h3>${escapeHtml(showcaseFeatureLabel(activeFeature))}</h3>
            <div class="mini-list">
              <div class="mini-row"><span>${icon("ranking")}Total entries</span><strong>${filtered.length}</strong></div>
              <div class="mini-row"><span>${icon("spark")}Featured</span><strong>${featuredCount}</strong></div>
              <div class="mini-row"><span>${icon("shield")}Published</span><strong>${publishedCount}</strong></div>
              <div class="mini-row"><span>${icon("file")}Hidden</span><strong>${hiddenCount}</strong></div>
              ${activeFeature === "city_pipeline" ? `
                <div class="mini-row"><span>${icon("pipeline")}Investor gaps</span><strong>${cityCounts?.gaps || 0}</strong></div>
                <div class="mini-row"><span>${icon("clock")}Future projects</span><strong>${cityCounts?.projects || 0}</strong></div>
              ` : ""}
            </div>
          </article>

          <article class="panel-card">
            <div class="panel-kicker">Public routing</div>
            <h3>${activeFeature === "city_pipeline" ? "Investor guidance lives here" : "Hidden under More"}</h3>
            <p>${activeFeature === "city_pipeline"
              ? "Use City Pipeline to separate real future projects from missing-business briefs so investors can see what the city still needs before they build."
              : "The Offer Board and City Pipeline stay outside the main nav, then surface through the header overflow menu."}</p>
            <div class="showcase-card-chip-row">
              <span class="meta-chip">More menu</span>
              <span class="meta-chip">Admin only CRUD</span>
              <span class="meta-chip">${activeFeature === "city_pipeline" ? "Investor gap board" : "Image-led display"}</span>
            </div>
          </article>
        </aside>

        <section class="stack">
          <article class="panel-card showcase-admin-toolbar">
            <div class="showcase-admin-filter-row">
              <button type="button" class="filter-chip ${activeFeature === "offer_board" ? "active" : ""}" data-showcase-feature="offer_board">Offer Board</button>
              <button type="button" class="filter-chip ${activeFeature === "city_pipeline" ? "active" : ""}" data-showcase-feature="city_pipeline">City Pipeline</button>
            </div>
            <div class="property-actions" style="margin-top:18px;">
              <input class="input-shell" id="adminShowcaseSearch" value="${escapeHtml(search)}" placeholder="Search showcase entries" style="flex:1 1 280px;">
              <button type="button" class="btn-shell btn-shell-primary" id="adminShowcaseAddInline">Add Item</button>
            </div>
          </article>

          <div class="showcase-admin-grid">
            ${filtered.length
              ? filtered.map((item) => showcaseAdminCardMarkup(item)).join("")
              : emptyState("No showcase entries yet", "Use Add Item to start the first board card.")}
          </div>
        </section>
      </div>
    `;

    root.querySelectorAll("[data-showcase-feature]").forEach((button) => {
      button.addEventListener("click", () => {
        activeFeature = String(button.dataset.showcaseFeature || "offer_board");
        render();
      });
    });

    document.getElementById("adminShowcaseSearch")?.addEventListener("input", (event) => {
      search = event.target.value;
      render();
    });

    document.getElementById("adminShowcaseAddInline")?.addEventListener("click", () => {
      fillForm();
      openModal(modal);
    });

    root.querySelectorAll("[data-showcase-edit]").forEach((button) => {
      button.addEventListener("click", () => {
        const item = items.find((entry) => entry.id === Number(button.dataset.showcaseEdit));
        if (!item) return;
        fillForm(item);
        openModal(modal);
      });
    });

    root.querySelectorAll("[data-showcase-delete]").forEach((button) => {
      button.addEventListener("click", () => {
        const item = items.find((entry) => entry.id === Number(button.dataset.showcaseDelete));
        if (!item) return;
        deleteId = item.id;
        deleteLabel.textContent = `Delete ${item.title} from ${showcaseFeatureLabel(item.featureType)}.`;
        openModal(deleteModal);
      });
    });

    root.querySelectorAll("[data-showcase-toggle-publish]").forEach((button) => {
      button.addEventListener("click", async () => {
        const item = items.find((entry) => entry.id === Number(button.dataset.showcaseTogglePublish));
        if (!item) return;
        const response = await api.updateShowcaseItem(item.id, {
          is_published: item.isPublished ? 0 : 1,
        });
        items = mergeShowcaseResponseItems(response, items);
        render();
      });
    });
  };

  const reload = async () => {
    const [showcaseResponse, propertiesResponse] = await Promise.all([api.showcase(), api.properties()]);
    items = stampShowcaseItems(showcaseResponse.items || []);
    properties = propertiesResponse.properties || [];
    render();
  };

  addButton?.addEventListener("click", () => {
    fillForm();
    openModal(modal);
  });

  form?.querySelectorAll("[data-showcase-step-trigger]").forEach((button) => {
    button.addEventListener("click", () => {
      if (isSaving) return;
      const targetStep = Number(button.dataset.showcaseStepTrigger || 0);
      if (targetStep > currentStep && !validateStep(currentStep)) return;
      goToStep(targetStep);
    });
  });

  wizardBackButton?.addEventListener("click", () => {
    if (isSaving) return;
    goToStep(currentStep - 1);
  });

  wizardNextButton?.addEventListener("click", () => {
    if (isSaving) return;
    if (!validateStep(currentStep)) return;
    goToStep(currentStep + 1);
  });

  document.getElementById("showcaseFeatureType")?.addEventListener("change", () => {
    syncShowcaseFieldVisibility();
  });

  document.getElementById("showcasePipelineMode")?.addEventListener("change", () => {
    syncShowcaseFieldVisibility();
  });

  document.getElementById("showcaseTitle")?.addEventListener("input", () => {
    updateShowcaseMediaPreview();
  });

  document.getElementById("showcaseImagePath")?.addEventListener("input", () => {
    updateShowcaseMediaPreview();
  });

  document.getElementById("showcaseImage")?.addEventListener("change", () => {
    updateShowcaseMediaPreview();
  });

  document.addEventListener("click", (event) => {
    const closeTarget = event.target.closest("[data-modal-close]");
    if (!closeTarget) return;
    clearPreviewObjectUrl();
    closeModal(document.getElementById(closeTarget.dataset.modalClose));
  });

  form?.addEventListener("submit", async (event) => {
    event.preventDefault();
    if (isSaving) return;
    if (!validateCoreFields() || !validateStep(currentStep)) return;
    try {
      setShowcaseFeedback("");
      setSavingState(true);

      const itemId = Number(document.getElementById("showcaseItemId").value || 0);
      const payloadData = {
        feature_type: document.getElementById("showcaseFeatureType").value,
        title: document.getElementById("showcaseTitle").value,
        partner_label: document.getElementById("showcasePartnerLabel").value,
        category: document.getElementById("showcaseCategory").value,
        location_label: document.getElementById("showcaseLocationLabel").value,
        barangay: document.getElementById("showcaseBarangay").value,
        status: document.getElementById("showcaseStatus").value,
        pipeline_mode: document.getElementById("showcasePipelineMode").value,
        supply_signal: document.getElementById("showcaseSupplySignal").value,
        related_property_id: document.getElementById("showcaseRelatedProperty").value,
        is_published: document.getElementById("showcasePublished").value,
        is_featured: document.getElementById("showcaseFeatured").value,
        sort_order: document.getElementById("showcaseSortOrder").value,
        primary_metric_label: document.getElementById("showcasePrimaryMetricLabel").value,
        primary_metric_value: document.getElementById("showcasePrimaryMetricValue").value,
        secondary_metric_label: document.getElementById("showcaseSecondaryMetricLabel").value,
        secondary_metric_value: document.getElementById("showcaseSecondaryMetricValue").value,
        investor_thesis: document.getElementById("showcaseInvestorThesis").value,
        ideal_operator: document.getElementById("showcaseIdealOperator").value,
        avoidance_note: document.getElementById("showcaseAvoidanceNote").value,
        summary: document.getElementById("showcaseSummary").value,
        description: document.getElementById("showcaseDescription").value,
        cover_image_url: document.getElementById("showcaseImagePath").value,
      };
      const countdownAt = document.getElementById("showcaseCountdownAt").value;
      const completionTarget = document.getElementById("showcaseCompletionTarget").value;
      if (countdownAt) payloadData.countdown_at = countdownAt;
      if (completionTarget) payloadData.completion_target = completionTarget;
      const imageFile = document.getElementById("showcaseImage").files?.[0];
      const payload = imageFile
        ? (() => {
          const formData = new FormData();
          Object.entries(payloadData).forEach(([key, value]) => {
            formData.append(key, value ?? "");
          });
          formData.append("image_file", imageFile);
          return formData;
        })()
        : payloadData;

      const response = itemId > 0
        ? await api.updateShowcaseItem(itemId, payload)
        : await api.createShowcaseItem(payload);

      items = mergeShowcaseResponseItems(response, items);
      clearPreviewObjectUrl();
      closeModal(modal);
      await reload();
    } catch (error) {
      console.error("Unable to save showcase item", error);
      setShowcaseFeedback(error?.message || "Unable to save this showcase item right now.");
    } finally {
      setSavingState(false);
    }
  });

  confirmDeleteButton?.addEventListener("click", async () => {
    if (!deleteId) return;
    const response = await api.deleteShowcaseItem(deleteId);
    items = stampShowcaseItems(response.items || items);
    deleteId = null;
    closeModal(deleteModal);
    render();
  });

  goToStep(0);
  updateShowcaseMediaPreview();
  await reload();
}

async function initExplorer() {
  const root = document.getElementById("explorerAppRoot");
  if (!root) return;

  const bootstrap = await api.bootstrap();
  const properties = bootstrap.properties || [];
  if (!properties.length) {
    root.innerHTML = emptyState("Explorer unavailable", "No properties are loaded yet. Add inventory to bring the map online.");
    return;
  }
  const votesMap = await loadVoteTallies(properties);
  const mapService = bootstrap.meta?.services?.maps || {};
  const locationService = bootstrap.meta?.services?.location || {};
  let search = "";
  let type = "all";
  let corridor = "all";
  let clupStatus = "all";
  let activeId = parsePropertyParam() || properties[0]?.id || 0;
  let locationQuery = "";
  let locationResults = [];
  let selectedSearchResult = null;
  let investmentLensKey = getActiveInvestmentLensKey();
  let mobileView = "list";

  const runLocationSearch = async () => {
    const field = document.getElementById("explorerLocationSearch");
    locationQuery = field?.value || locationQuery;
    const response = await api.locationSearch(locationQuery).catch(() => ({ search: { results: [] } }));
    locationResults = response.search?.results || [];
    selectedSearchResult = locationResults[0] || null;
    if (selectedSearchResult?.propertyId) {
      activeId = Number(selectedSearchResult.propertyId);
    }
    mobileView = "map";
    render();
  };

  const render = () => {
    const visibleBase = properties.filter((property) => {
      const haystack = `${property.name} ${property.city} ${property.barangay || ""}`.toLowerCase();
      if (search && !haystack.includes(search.toLowerCase())) return false;
      if (type !== "all" && property.type !== type) return false;
      if (corridor !== "all" && property.corridor !== corridor) return false;
      return true;
    });
    const activeLens = getInvestmentLensConfig(investmentLensKey);
    const visible = enrichProperties(visibleBase, properties, votesMap, null, investmentLensKey)
      .filter((property) => clupStatus === "all" || property.clupCompliance?.statusKey === clupStatus);
    const compareIds = getCompareIds();
    const favoriteIds = getFavoriteIds();
    const active = visible.find((property) => property.id === activeId) || visible[0];
    const leadProperty = visible[0] || active;
    activeId = active?.id || 0;

    if (!visible.length) {
      destroyMap("explorerLeafletMap");
      root.innerHTML = emptyState("No properties match this view", "Try widening the search or corridor filters.", "Reset Explorer", `${window.SFC_APP_CONFIG.basePath || ""}/property-explorer.php`);
      return;
    }

    const hasLocationQuery = Boolean(String(locationQuery || "").trim());
    const locationResultMarkup = locationResults.slice(0, 5).map((result, index) => `
      <button type="button" class="explorer-inline-result ${selectedSearchResult?.label === result.label ? "is-active" : ""}" data-search-result="${index}">
        <strong>${escapeHtml(result.label || "Search result")}</strong>
        <span>${escapeHtml(result.subtitle || "Map result")}</span>
      </button>
    `).join("");
    const locationNote = !locationService.enabled
      ? "Place search currently uses your local property inventory because LocationIQ is not configured."
      : "Use place search when you want the map to jump to a barangay or landmark.";
    const shouldShowLocationPanel = hasLocationQuery || locationResults.length;

    root.innerHTML = `
      <div class="explorer-terminal explorer-marketplace mobile-view-${mobileView}">
        <div class="explorer-market-shell">
          ${explorerLensRibbonMarkup(investmentLensKey, {
            visibleCount: visible.length,
            mapLive: mapService.enabled,
            locationLive: locationService.enabled,
            queueCount: favoriteIds.length + compareIds.length,
          })}

          <div class="explorer-workspace explorer-workspace-minimal">
            <section class="explorer-results-stage">
              <div class="explorer-results-stage-top">
                <div class="explorer-results-toolbar">
                  <div>
                    <div class="panel-kicker">Filters</div>
                    <h2>Refine the list.</h2>
                    <p>Search, narrow, and preview properties without the map taking over the page.</p>
                  </div>
                  <span class="explorer-results-count">${visible.length} in view</span>
                </div>

                <div class="explorer-filter-panel">
                  <div class="explorer-filter-panel-head">
                    <div class="explorer-filter-panel-copy">
                      <div class="panel-kicker">Quick Search</div>
                      <strong>Build a cleaner shortlist.</strong>
                    </div>
                    <div class="explorer-filter-panel-accent">
                      <span>${icon("spark")}Smart filters</span>
                    </div>
                  </div>

                  <div class="explorer-filter-grid">
                    <label class="form-shell explorer-filter-span">
                      <span>Search</span>
                      <div class="explorer-input-with-icon">
                        <span class="explorer-input-icon">${icon("search")}</span>
                        <input class="input-shell" id="explorerSearch" value="${escapeHtml(search)}" placeholder="Property, barangay, corridor">
                      </div>
                    </label>

                    <label class="form-shell explorer-filter-card">
                      <span>Type</span>
                      <select class="input-shell" id="explorerType">
                        <option value="all">All types</option>
                        <option value="commercial" ${type === "commercial" ? "selected" : ""}>Commercial</option>
                        <option value="logistics" ${type === "logistics" ? "selected" : ""}>Logistics</option>
                        <option value="hotel" ${type === "hotel" ? "selected" : ""}>Resort / Tourism</option>
                        <option value="bpo" ${type === "bpo" ? "selected" : ""}>Office / BPO</option>
                        <option value="manufacturing" ${type === "manufacturing" ? "selected" : ""}>Manufacturing</option>
                      </select>
                    </label>

                    <label class="form-shell explorer-filter-card">
                      <span>Corridor</span>
                      <select class="input-shell" id="explorerCorridor">
                        <option value="all">All corridors</option>
                        <option value="highway" ${corridor === "highway" ? "selected" : ""}>Highway</option>
                        <option value="downtown" ${corridor === "downtown" ? "selected" : ""}>Downtown</option>
                        <option value="coastal" ${corridor === "coastal" ? "selected" : ""}>Coastal</option>
                      </select>
                    </label>
                    <label class="form-shell explorer-filter-card">
                      <span>CLUP status</span>
                      <select class="input-shell" id="explorerClupStatus">
                        <option value="all">All results</option>
                        <option value="pass" ${clupStatus === "pass" ? "selected" : ""}>PASS</option>
                        <option value="conditional" ${clupStatus === "conditional" ? "selected" : ""}>CONDITIONAL</option>
                        <option value="fail" ${clupStatus === "fail" ? "selected" : ""}>FAIL</option>
                      </select>
                    </label>

                    <label class="form-shell explorer-location-field explorer-filter-span">
                      <span>Place Search</span>
                      <div class="split-input">
                        <div class="explorer-input-with-icon">
                          <span class="explorer-input-icon">${icon("map")}</span>
                          <input class="input-shell" id="explorerLocationSearch" value="${escapeHtml(locationQuery)}" placeholder="Search San Fernando, barangay, or landmark">
                        </div>
                        <button type="button" class="btn-shell btn-shell-secondary" id="explorerLocationTrigger">${icon("search")}Search</button>
                      </div>
                    </label>
                  </div>

                  <div class="explorer-sidebar-summary explorer-sidebar-summary-filters">
                    <span>${escapeHtml(activeLens.label)}</span>
                    <span>${escapeHtml(corridorLabel(leadProperty?.corridor || ""))}</span>
                    <span>${favoriteIds.length + compareIds.length} saved / compared</span>
                  </div>
                  <p class="explorer-filter-note">${icon("map")}${escapeHtml(locationNote)}</p>
                </div>

                ${shouldShowLocationPanel ? `
                  <div class="explorer-inline-results">
                    <div class="explorer-inline-results-head">
                      <span>${locationResults.length ? "Place matches" : "Search status"}</span>
                      <strong>${locationResults.length ? `${Math.min(locationResults.length, 5)} shown` : "No match"}</strong>
                    </div>
                    <div class="explorer-inline-results-list">
                      ${locationResults.length ? locationResultMarkup : `
                        <div class="explorer-list-empty">
                          <strong>No map target yet</strong>
                          <span>Try a shorter barangay, landmark, or street name.</span>
                        </div>
                      `}
                    </div>
                  </div>
                ` : ""}
              </div>

              <div class="explorer-card-stack">
                <div class="explorer-card-stack-head">
                  <div class="explorer-card-stack-heading">
                    <span class="panel-kicker">Results Found</span>
                    <strong>${visible.length} results found</strong>
                  </div>
                  <span class="explorer-card-stack-sort">Sorted by ${escapeHtml(activeLens.shortLabel || activeLens.label)}</span>
                </div>
                <div class="explorer-results-grid">
                  ${visible.map((property) => propertyCard(property, {
                    variant: "compact",
                    isActive: property.id === activeId,
                    compareIds,
                    favoriteIds,
                    lensKey: investmentLensKey,
                    showManage: role === "admin",
                    manageHref: adminPropertyHref(property.id),
                  })).join("")}
                </div>
              </div>
            </section>

            <section class="explorer-support-rail">
              ${explorerSelectionCardMarkup(active, {
                activeLens,
                compareIds,
                favoriteIds,
              })}

              <div class="explorer-map-shell">
                <div class="explorer-map-shell-head">
                  <div class="explorer-map-shell-copy">
                    <div class="panel-kicker">Map Preview</div>
                    <h2>City context</h2>
                    <p>See where the shortlist sits across San Fernando without the map overpowering the page.</p>
                  </div>
                  <div class="explorer-map-shell-meta">
                    <span>${escapeHtml(activeLens.shortLabel || activeLens.label)} lens</span>
                    <span>${escapeHtml(mapService.enabled ? "Leaflet live" : "Fallback map")}</span>
                  </div>
                </div>
                <div class="clup-map-legend"><span><i class="is-pass"></i>PASS</span><span><i class="is-conditional"></i>CONDITIONAL</span><span><i class="is-fail"></i>FAIL</span></div>

                <section class="explorer-map-pane">
                  <div class="explorer-map-stage">
                    <div id="explorerLeafletMap" class="explorer-terminal-map explorer-map"></div>
                    ${explorerMapHudMarkup(active, activeLens, visible.length)}
                    ${explorerMapPreviewMarkup(active, activeLens, selectedSearchResult)}
                  </div>
                </section>
              </div>
            </section>
          </div>
        </div>

        <div class="explorer-mobile-nav">
          <button type="button" class="explorer-mobile-tab ${mobileView === "list" ? "is-active" : ""}" data-explorer-mobile-view="list">Results</button>
          <button type="button" class="explorer-mobile-tab ${mobileView === "map" ? "is-active" : ""}" data-explorer-mobile-view="map">Map</button>
        </div>
      </div>
    `;

    root.querySelector("#explorerSearch")?.addEventListener("input", (event) => {
      search = event.target.value;
      render();
    });
    root.querySelector("#explorerType")?.addEventListener("change", (event) => {
      type = event.target.value;
      render();
    });
    root.querySelector("#explorerCorridor")?.addEventListener("change", (event) => {
      corridor = event.target.value;
      render();
    });
    root.querySelector("#explorerClupStatus")?.addEventListener("change", (event) => {
      clupStatus = event.target.value;
      render();
    });
    root.querySelectorAll("[data-explorer-lens]").forEach((button) => {
      button.addEventListener("click", () => {
        const nextLensKey = button.dataset.explorerLens || investmentLensKey;
        investmentLensKey = nextLensKey;
        saveActiveInvestmentLensKey(nextLensKey);
        render();
      });
    });
    root.querySelector("#explorerLocationTrigger")?.addEventListener("click", runLocationSearch);
    root.querySelector("#explorerLocationSearch")?.addEventListener("keydown", async (event) => {
      if (event.key !== "Enter") return;
      event.preventDefault();
      await runLocationSearch();
    });
    root.querySelectorAll("[data-search-result]").forEach((button) => {
      button.addEventListener("click", () => {
        selectedSearchResult = locationResults[Number(button.dataset.searchResult)] || null;
        if (selectedSearchResult?.propertyId) {
          activeId = Number(selectedSearchResult.propertyId);
        }
        mobileView = "map";
        render();
      });
    });
    root.querySelectorAll("[data-explorer-select]").forEach((card) => {
      card.addEventListener("click", (event) => {
        if (event.target.closest("a, button")) return;
        activeId = Number(card.dataset.explorerSelect);
        selectedSearchResult = null;
        if (window.innerWidth <= 768) {
          mobileView = "map";
        }
        render();
      });
      card.addEventListener("keydown", (event) => {
        if (event.key !== "Enter" && event.key !== " ") return;
        event.preventDefault();
        activeId = Number(card.dataset.explorerSelect);
        selectedSearchResult = null;
        if (window.innerWidth <= 768) {
          mobileView = "map";
        }
        render();
      });
    });
    root.querySelectorAll("[data-explorer-mobile-view]").forEach((button) => {
      button.addEventListener("click", () => {
        mobileView = button.dataset.explorerMobileView || "map";
        render();
      });
    });
    bindCollectionActions(root, render);
    animateLensMetricBars(root);

    mountPropertyMap({
      containerId: "explorerLeafletMap",
      properties: visible,
      activeId: active?.id || visible[0]?.id || null,
      searchResult: selectedSearchResult,
      onSelect: (propertyId) => {
        activeId = Number(propertyId);
        selectedSearchResult = null;
        if (window.innerWidth <= 768) {
          mobileView = "map";
        }
        render();
      },
    });
  };

  render();
}

async function initCompare() {
  const root = document.getElementById("compareDecisionRoot");
  if (!root) return;

  const bootstrap = await api.bootstrap();
  const properties = bootstrap.properties || [];
  const decisionPersonas = normalizeDecisionPersonas(bootstrap.meta?.decisionPersonas || []);
  const votesMap = await loadVoteTallies(properties);
  let intent = "";
  let budget = "";
  let investmentLensKey = getActiveInvestmentLensKey();
  let decisionPersonaKey = getStoredDecisionPersonaKey(decisionPersonas);

  const render = () => {
    const compareIds = getCompareIds();
    const comparedBase = properties.filter((property) => compareIds.includes(property.id)).slice(0, 3);
    if (comparedBase.length < 2) {
      root.innerHTML = emptyState("Select at least two properties to compare", "Use Rankings or Explorer to add properties into your compare queue.", "Open Rankings", `${window.SFC_APP_CONFIG.basePath || ""}/property-ranking.php`);
      return;
    }

    const activeLens = getInvestmentLensConfig(investmentLensKey);
    const enriched = enrichProperties(comparedBase, properties, votesMap, intent || null, investmentLensKey)
      .filter((property) => !Number(budget || 0) || Number(property.price) <= Number(budget || 0));
    const activeComparedBase = enriched.length ? enriched : enrichProperties(comparedBase, properties, votesMap, intent || null, investmentLensKey);
    const activeCompared = activeComparedBase
      .map((property) => {
        const activeDecision = resolveDecisionVariant(property.decision, decisionPersonaKey) || {
          score: Number(property.lensScore || property.opportunityScore || 0),
          confidence: 55,
          confidenceLabel: "Medium Confidence",
          statusKey: "strong_watch",
          statusLabel: "Strong Watch",
          toneLabel: "Momentum Watch",
          summary: propertyStory(property),
          nextAction: { label: "Review property", target: "details" },
          reasons: [],
        };
        return {
          ...property,
          activeDecision,
          compareLeadScore: Math.round((Number(activeDecision.score || 0) * 0.7) + (Number(property.lensScore || 0) * 0.3)),
        };
      })
      .sort((left, right) => (
        (({ PASS: 2, CONDITIONAL: 1, FAIL: 0 })[right.clupCompliance?.status] - ({ PASS: 2, CONDITIONAL: 1, FAIL: 0 })[left.clupCompliance?.status])
        || (right.compareLeadScore - left.compareLeadScore)
        || (Number(right.activeDecision?.confidence || 0) - Number(left.activeDecision?.confidence || 0))
        || (right.lensScore - left.lensScore)
      ));
    const winner = activeCompared[0];
    const activePersona = decisionPersonas.find((persona) => persona.key === decisionPersonaKey) || decisionPersonas[0] || null;

    root.innerHTML = `
      <div class="compare-layout">
        <aside class="stack">
          <article class="panel-card">
            <div class="panel-kicker">Decision filters</div>
            <h3>Adjust the comparison</h3>
            <div class="filter-grid">
              <label class="form-shell">
                <span>Target type</span>
                <select class="input-shell" id="compareIntent">
                  <option value="">General</option>
                  <option value="commercial" ${intent === "commercial" ? "selected" : ""}>Commercial</option>
                  <option value="logistics" ${intent === "logistics" ? "selected" : ""}>Logistics</option>
                  <option value="hotel" ${intent === "hotel" ? "selected" : ""}>Resort / Tourism</option>
                  <option value="bpo" ${intent === "bpo" ? "selected" : ""}>Office / BPO</option>
                  <option value="manufacturing" ${intent === "manufacturing" ? "selected" : ""}>Manufacturing</option>
                </select>
              </label>
              <label class="form-shell">
                <span>Budget cap (PHP)</span>
                <input class="input-shell" id="compareBudget" type="number" value="${escapeHtml(budget)}" placeholder="95000000">
              </label>
            </div>
            <div class="property-actions" style="margin-top:18px;">
              <button type="button" class="btn-shell btn-shell-secondary" id="clearCompare">Clear compare</button>
              <a href="${window.SFC_APP_CONFIG.basePath || ""}/property-explorer.php" class="btn-shell btn-shell-primary">Open Explorer</a>
            </div>
          </article>

          ${decisionPersonaSelectorMarkup(decisionPersonas, decisionPersonaKey, {
            title: "Select Investor Persona",
            description: "Turn the compare board into user-specific intelligence instead of one-size-fits-all ranking.",
          })}
        </aside>

        <section class="stack">
          ${investmentLensSelectorMarkup(investmentLensKey, {
            title: "Select Investment Lens",
            description: "Watch the lead recommendation shift as you test different end-use scenarios.",
          })}

          <article class="decision-card">
            <div class="panel-kicker">Recommended lead for ${escapeHtml(activeLens.label)} | ${escapeHtml(activePersona?.label || "Balanced Desk")}</div>
            <h2>${escapeHtml(winner.name)}</h2>
            <p>${escapeHtml(winner.activeDecision?.summary || propertyStory(winner))}</p>
            <div class="decision-stats">
              ${clupStatusPill(winner.clupCompliance)}
              ${decisionSignalPill(winner.activeDecision)}
              ${decisionStatusPill(winner.activeDecision)}
              ${decisionConfidencePill(winner.activeDecision)}
              ${winner.lensResult ? investmentLensScorePill(winner.lensResult) : scorePill(winner.opportunityScore)}
              <span class="tag">${escapeHtml(moneyShort(winner.price))}</span>
            </div>
            <div class="mini-list" style="margin-top:18px;">
              <div class="mini-row"><span>${icon("spark")}Action lane</span><strong>${escapeHtml(winner.activeDecision?.nextAction?.label || "Review property")}</strong></div>
              <div class="mini-row"><span>${icon("ranking")}${escapeHtml(activeLens.shortLabel)} score</span><strong>${winner.lensScore}</strong></div>
              <div class="mini-row"><span>${icon("vote")}Top need</span><strong>${escapeHtml(voteLabel(winner.topNeed || "No demand yet"))}</strong></div>
              <div class="mini-row"><span>${icon("money")}Price per ha</span><strong>${escapeHtml(moneyShort(winner.pricePerHectare))}</strong></div>
              <div class="mini-row"><span>${icon("admin")}CLUP suitability</span><strong>${winner.clupCompliance.suitabilityScore}/100</strong></div>
            </div>
            ${winner.activeDecision?.reasons?.length ? `
              <div class="insight-strip" style="margin-top:18px;">
                ${winner.activeDecision.reasons.slice(0, 3).map((reason) => `<span class="insight-pill">${escapeHtml(reason)}</span>`).join("")}
              </div>
            ` : ""}
            ${winner ? googleEarthActionsMarkup({
              property: winner,
              properties: activeCompared,
              scope: "compare-set",
              showView: true,
              note: "Inspect the lead site in Google Earth or export the full compare set as KML or KMZ.",
            }) : ""}
          </article>

          ${clupDecisionCardMarkup(winner.clupCompliance)}

          ${winner ? investmentLensThesisMarkup(winner, winner.lensResult, {
            kicker: `Why it wins for ${activeLens.label}`,
            heading: `${winner.name} leads this comparison`,
          }) : ""}

          <div class="comparison-grid">
            ${activeCompared.map((property) => `
              <article class="comparison-card">
                <div class="property-media"><img src="${escapeHtml(property.imageUrl)}" alt="${escapeHtml(property.name)}"></div>
                <div class="property-body">
                  <div class="property-title">${escapeHtml(property.name)}</div>
                  <div class="property-subline">${escapeHtml(corridorLabel(property.corridor))}</div>
                  <div class="lens-inline-note">${escapeHtml(property.lensResult?.thesisShort || `${property.name} under the ${activeLens.label} lens.`)}</div>
                  <p>${escapeHtml(truncate(propertyStory(property), 130))}</p>
                  <div class="decision-stats" style="margin-bottom:14px;">
                    ${clupStatusPill(property.clupCompliance)}
                    ${decisionSignalPill(property.activeDecision)}
                    ${decisionStatusPill(property.activeDecision)}
                    ${decisionConfidencePill(property.activeDecision)}
                  </div>
                  <div class="mini-list">
                    <div class="mini-row"><span>${icon("ranking")}${escapeHtml(activeLens.shortLabel)}</span><strong>${property.lensScore}</strong></div>
                    <div class="mini-row"><span>${icon("money")}Price</span><strong>${escapeHtml(moneyShort(property.price))}</strong></div>
                    <div class="mini-row"><span>${icon("vote")}Demand</span><strong>${property.voteTotal}</strong></div>
                    <div class="mini-row"><span>${icon("spark")}Next move</span><strong>${escapeHtml(property.activeDecision?.nextAction?.label || "Review")}</strong></div>
                    <div class="mini-row"><span>${icon("admin")}CLUP</span><strong>${property.clupCompliance.status} · ${property.clupCompliance.suitabilityScore}</strong></div>
                  </div>
                  <div class="property-actions">
                    <a href="${propertyHref(property.id)}" class="btn-shell btn-shell-primary">View Details</a>
                    <button type="button" class="btn-shell btn-shell-ghost" data-remove-compare="${property.id}">Remove</button>
                  </div>
                </div>
              </article>
            `).join("")}
          </div>
        </section>
      </div>
    `;

    document.getElementById("compareIntent")?.addEventListener("change", (event) => {
      intent = event.target.value;
      render();
    });
    document.getElementById("compareBudget")?.addEventListener("input", (event) => {
      budget = event.target.value;
      render();
    });
    bindInvestmentLensSelector(root, (nextLensKey) => {
      investmentLensKey = nextLensKey;
      saveActiveInvestmentLensKey(nextLensKey);
      render();
    });
    root.querySelectorAll("[data-decision-persona]").forEach((button) => {
      button.addEventListener("click", () => {
        decisionPersonaKey = String(button.dataset.decisionPersona || decisionPersonaKey);
        saveDecisionPersonaKey(decisionPersonaKey);
        render();
      });
    });
    document.getElementById("clearCompare")?.addEventListener("click", () => {
      saveCompareIds([]);
      render();
    });
    root.querySelectorAll("[data-remove-compare]").forEach((button) => {
      button.addEventListener("click", () => {
        const nextIds = getCompareIds().filter((id) => id !== Number(button.dataset.removeCompare));
        saveCompareIds(nextIds);
        render();
      });
    });
    animateLensMetricBars(root);
  };

  render();
}

function commandToneClass(tone) {
  const normalized = String(tone || "system").toLowerCase();
  if (normalized === "success") return "command-tone-success";
  if (normalized === "info") return "command-tone-info";
  if (normalized === "trend") return "command-tone-trend";
  if (normalized === "danger") return "command-tone-danger";
  return "command-tone-system";
}

function commandSeverityClass(severity) {
  const normalized = String(severity || "low").toLowerCase();
  if (normalized === "critical") return "command-severity-critical";
  if (normalized === "high") return "command-severity-high";
  if (normalized === "medium") return "command-severity-medium";
  return "command-severity-low";
}

function commandUrgencyClass(urgency) {
  const normalized = String(urgency || "normal").toLowerCase();
  if (normalized === "critical" || normalized === "high") return "command-urgency-high";
  if (normalized === "medium") return "command-urgency-medium";
  return "command-urgency-normal";
}

function signedMetric(value, suffix = "") {
  const numeric = Number(value || 0);
  return `${numeric > 0 ? "+" : ""}${numeric}${suffix}`;
}

function buildCommandScoreModel({ property, enriched, readiness, votes, conversationSummary, visit, documentRequests, activeLens }) {
  const baseIAI = Math.round(Number(enriched?.lensResult?.score ?? enriched?.opportunityScore ?? property?.marketScore ?? 0));
  const readinessScore = Number(readiness?.totalScore || property?.investmentReadiness?.totalScore || 0);
  const duePct = Number(property?.dueDiligencePct || 0);
  const demandSignal = Math.round(clampNumber(totalVotes(votes) * 0.8, 0, 12));
  const readinessLift = Math.round(clampNumber(((readinessScore - 58) / 9) + ((duePct - 50) / 18), -6, 10));
  const groundTruthAdjustment = Number(property?.groundTruthVisitCount || 0) > 0
    ? Math.round(baseIAI * clampNumber((Number(property?.groundTruthMultiplier || 1) - 1) * 0.6, -0.08, 0.14))
    : 0;

  let trustAdjustment = 0;
  const listingStatus = String(property?.listingVerificationStatus || "unverified").toLowerCase();
  const sellerStatus = String(property?.sellerIdentityStatus || "unverified").toLowerCase();
  if (listingStatus === "verified") trustAdjustment += 2;
  if (listingStatus === "reviewing") trustAdjustment += 1;
  if (["unverified", "rejected", "draft", "pending_review", "archived"].includes(listingStatus)) trustAdjustment -= 1;
  if (sellerStatus === "verified") trustAdjustment += 1;
  if (sellerStatus === "unverified") trustAdjustment -= 1;
  if (property?.documentsReviewedAt) trustAdjustment += 1;
  if (property?.siteVerifiedAt) trustAdjustment += 1;
  if ((documentRequests || []).some((request) => ["requested", "in_review"].includes(String(request.status || "").toLowerCase()))) trustAdjustment -= 1;
  if (Number(conversationSummary?.messageCount || 0) > 0) trustAdjustment += 1;
  trustAdjustment = Math.round(clampNumber(trustAdjustment, -4, 6));

  const finalScore = Math.round(clampNumber(baseIAI + demandSignal + readinessLift + groundTruthAdjustment + trustAdjustment, 0, 100));
  const delta = finalScore - baseIAI;
  const activeVisitStatus = String(visit?.statusLabel || visit?.status || "No visit").replace(/_/g, " ");

  return {
    baseIAI,
    demandSignal,
    readinessLift,
    groundTruthAdjustment,
    trustAdjustment,
    finalScore,
    delta,
    summary: `${activeLens?.label || "Default"} lens with live readiness, demand, trust, and field validation.`,
    components: [
      {
        label: "Base IAI",
        value: baseIAI,
        note: `${activeLens?.label || "Market"} scoring baseline`,
        tone: "info",
      },
      {
        label: "Demand Signal",
        value: demandSignal,
        note: `${totalVotes(votes)} local pulse${totalVotes(votes) === 1 ? "" : "s"} recorded`,
        tone: "trend",
      },
      {
        label: "Readiness Lift",
        value: readinessLift,
        note: `${Math.round(readinessScore || 0)} readiness / ${Math.round(duePct || 0)}% diligence`,
        tone: "info",
      },
      {
        label: "Ground Truth",
        value: groundTruthAdjustment,
        note: Number(property?.groundTruthVisitCount || 0)
          ? `${signedMetric(Math.round(Number(property?.groundTruthAdjustmentPct || 0)), "%")} from ${property.groundTruthVisitCount} visit${property.groundTruthVisitCount === 1 ? "" : "s"}`
          : "No field audit multiplier yet",
        tone: Number(property?.groundTruthVisitCount || 0) ? "success" : "system",
      },
      {
        label: "Trust Adjustment",
        value: trustAdjustment,
        note: `${titleCase(String(property?.listingVerificationStatus || "unverified"))} / ${titleCase(String(property?.sellerIdentityStatus || "unverified"))}`,
        tone: trustAdjustment >= 0 ? "success" : "danger",
      },
    ],
    rationale: [
      `Current visit state: ${titleCase(activeVisitStatus)}`,
      `${Number(conversationSummary?.messageCount || 0)} logged message${Number(conversationSummary?.messageCount || 0) === 1 ? "" : "s"} in the investor-seller thread`,
      `${(documentRequests || []).filter((request) => ["requested", "in_review"].includes(String(request.status || "").toLowerCase())).length} open document workflow item${(documentRequests || []).filter((request) => ["requested", "in_review"].includes(String(request.status || "").toLowerCase())).length === 1 ? "" : "s"}`,
    ],
  };
}

function commandScoreSummaryMarkup(scoreModel, activeLens) {
  return `
    <div class="command-metric-label">Command Score</div>
    <div class="command-metric-value">${Math.round(Number(scoreModel?.finalScore || 0))}</div>
    <div class="command-metric-delta ${Number(scoreModel?.delta || 0) >= 0 ? "is-positive" : "is-negative"}">
      ${signedMetric(Math.round(Number(scoreModel?.delta || 0)))} vs base IAI
    </div>
    <div class="command-metric-copy">${escapeHtml(scoreModel?.summary || `${activeLens?.label || "Property"} command score ready.`)}</div>
    <div class="command-metric-strip">
      <span>${escapeHtml(activeLens?.label || "Default")} Lens</span>
      <strong>${Math.round(Number(scoreModel?.baseIAI || 0))}</strong>
    </div>
  `;
}

function commandScorePanelInnerMarkup(scoreModel, activeLens) {
  return `
    <div class="command-panel-head">
      <div>
        <div class="panel-kicker">Score explanation</div>
        <h3>Why this property ranks here right now</h3>
      </div>
      ${serviceChip(`${activeLens?.label || "Default"} lens`, "live")}
    </div>
    <div class="command-score-breakdown">
      ${(scoreModel?.components || []).map((component) => `
        <article class="command-score-card ${commandToneClass(component.tone)}">
          <span>${escapeHtml(component.label)}</span>
          <strong>${signedMetric(Math.round(Number(component.value || 0)))}</strong>
          <small>${escapeHtml(component.note || "")}</small>
        </article>
      `).join("")}
    </div>
    <div class="command-rationale-list">
      ${(scoreModel?.rationale || []).map((item) => `<div>${escapeHtml(item)}</div>`).join("")}
    </div>
  `;
}

function commandRibbonMarkup({ property, enriched, scoreModel, activeLens, lastConfirmed, compareIds, favoriteIds }) {
  return `
    <article class="command-ribbon">
      <div class="command-ribbon-media">
        <img src="${escapeHtml(enriched?.imageUrl || property?.imageUrl || "")}" alt="${escapeHtml(enriched?.name || property?.name || "Property")}">
      </div>
      <div class="command-ribbon-copy">
        <div class="panel-kicker">Property Command Center</div>
        <h2>${escapeHtml(enriched?.name || property?.name || "Property")}</h2>
        <p>${escapeHtml(propertyStory(enriched || property))}</p>
        <div class="decision-stats">
          <div id="propertyLensScoreSlot">${enriched?.lensResult ? investmentLensScorePill(enriched.lensResult) : scorePill(enriched?.opportunityScore || property?.marketScore || 0)}</div>
          ${statusPill(enriched?.status)}
          ${approvalStatePill(enriched?.approvalState)}
          ${verificationPill(enriched?.listingVerificationStatus)}
          ${groundTruthPill(enriched)}
          <span class="tag">${escapeHtml(voteLabel(enriched?.topNeed || "No demand yet"))}</span>
        </div>
        <div class="command-ribbon-facts">
          <div><span>${icon("map")}Corridor</span><strong>${escapeHtml(corridorLabel(enriched?.corridor))}</strong></div>
          <div><span>${icon("area")}Land Area</span><strong>${escapeHtml(enriched?.area)} ha</strong></div>
          <div><span>${icon("money")}Guide Price</span><strong>${escapeHtml(moneyShort(enriched?.price))}</strong></div>
          <div><span>${icon("clock")}Freshness</span><strong>${escapeHtml(formatFreshness(lastConfirmed, "Awaiting confirmation"))}</strong></div>
        </div>
        <div class="trust-badge-row">${trustBadgeRow(enriched?.trustBadges || [])}</div>
        <div class="property-actions">
          <a href="${votingHref(enriched?.id || property?.id)}" class="btn-shell btn-shell-primary">Open Voting</a>
          <button type="button" class="btn-shell btn-shell-secondary" id="propertyProspectusPrintButton">${icon("file")}Print Prospectus</button>
          <button type="button" class="btn-shell btn-shell-secondary" data-command-target="due-diligence">${icon("shield")}Open Checklist</button>
          <button type="button" class="btn-shell btn-shell-secondary" data-compare-toggle="${enriched?.id || property?.id}">${icon("compare")}${compareIds.includes(enriched?.id || property?.id) ? "Compared" : "Compare"}</button>
          <button type="button" class="btn-shell btn-shell-ghost" data-favorite-toggle="${enriched?.id || property?.id}">${icon("save")}${favoriteActionLabel(favoriteIds.includes(enriched?.id || property?.id))}</button>
        </div>
        ${googleEarthActionsMarkup({
          property: enriched || property,
          scope: "property-details",
          showView: true,
          note: "Use Google Earth as a complementary site inspection layer for parcel context, report export, and visual validation.",
        })}
      </div>
      <div class="command-ribbon-score" id="propertyCommandMetricSlot">
        ${commandScoreSummaryMarkup(scoreModel, activeLens)}
      </div>
    </article>
  `;
}

function commandBlockersMarkup(blockers = []) {
  return `
    <article class="panel-card command-blocker-panel">
      <div class="command-panel-head">
        <div>
          <div class="panel-kicker">Critical blockers</div>
          <h3>What is still suppressing investment readiness</h3>
        </div>
        ${serviceChip(`${blockers.length} blocker${blockers.length === 1 ? "" : "s"}`, blockers.length ? "fallback" : "live")}
      </div>
      <div class="command-blocker-list">
        ${blockers.length ? blockers.map((blocker) => `
          <button type="button" class="command-blocker-item ${commandSeverityClass(blocker.severity)}" data-command-target="${escapeHtml(blocker.actionTarget || "overview")}">
            <div class="command-blocker-top">
              <span>${escapeHtml(String(blocker.severity || "medium").toUpperCase())}</span>
              <strong>${escapeHtml(blocker.title || "Blocker")}</strong>
            </div>
            <p>${escapeHtml(blocker.summary || "Review the latest blocker details.")}</p>
            <div class="command-blocker-meta">
              <span>Owner: ${escapeHtml(titleCase(blocker.ownerRole || "system"))}</span>
              <span>${escapeHtml(blocker.actionLabel || "Resolve")}</span>
            </div>
          </button>
        `).join("") : `<div class="loading-panel">No critical blockers. This property is clear to move through the current operating loop.</div>`}
      </div>
    </article>
  `;
}

function commandTimelineMarkup(items = []) {
  return `
    <article class="panel-card command-timeline-panel">
      <div class="command-panel-head">
        <div>
          <div class="panel-kicker">Operational timeline</div>
          <h3>What changed recently across trust, logistics, and messaging</h3>
        </div>
        ${serviceChip("Live property trail", "neutral")}
      </div>
      <div class="command-timeline-list">
        ${items.length ? items.map((item) => `
          <button
            type="button"
            class="command-timeline-item ${commandToneClass(item.tone)}"
            data-command-target="${escapeHtml(item.target || "overview")}"
            ${item.auditId ? `data-command-audit="${Number(item.auditId)}"` : ""}
          >
            <div class="command-timeline-marker"></div>
            <div class="command-timeline-copy">
              <div class="command-timeline-topline">
                <span class="command-event-pill ${commandToneClass(item.tone)}">${escapeHtml(item.badge || titleCase(item.kind || "Update"))}</span>
                <span>${escapeHtml(formatDateTime(item.createdAt))}</span>
              </div>
              <strong>${escapeHtml(item.title || "System update")}</strong>
              <p>${escapeHtml(item.summary || "No supporting detail provided.")}</p>
              <small>${escapeHtml(item.actorName || "System")} · ${escapeHtml(titleCase(item.actorRole || "system"))}</small>
            </div>
          </button>
        `).join("") : `<div class="loading-panel">No recent events on this property yet.</div>`}
      </div>
    </article>
  `;
}

function commandTrustMarkup({ trust, property, enriched }) {
  const lastModeration = trust?.lastModeration;
  const lastApproval = trust?.lastApproval;
  return `
    <article class="panel-card command-trust-panel" id="propertyTrustSection">
      <div class="command-panel-head">
        <div>
          <div class="panel-kicker">Trust and compliance</div>
          <h3>Institutional confidence and ledger visibility</h3>
        </div>
        ${serviceChip(`${Number(trust?.auditLogCount || 0)} audit events`, Number(trust?.auditLogCount || 0) ? "live" : "neutral")}
      </div>
      <div class="mini-list">
        <div class="mini-row"><span>${icon("shield")}Seller identity</span><strong>${escapeHtml(VERIFICATION_LABELS[String(property?.sellerIdentityStatus || "unverified").toLowerCase()] || titleCase(property?.sellerIdentityStatus || "unverified"))}</strong></div>
        <div class="mini-row"><span>${icon("shield")}Listing verification</span><strong>${escapeHtml(VERIFICATION_LABELS[String(property?.listingVerificationStatus || "unverified").toLowerCase()] || titleCase(property?.listingVerificationStatus || "unverified"))}</strong></div>
        <div class="mini-row"><span>${icon("file")}Approval state</span><strong>${escapeHtml(APPROVAL_LABELS[String(property?.approvalState || "approved").toLowerCase()] || titleCase(property?.approvalState || "approved"))}</strong></div>
        <div class="mini-row"><span>${icon("pulse")}Document completeness</span><strong>${Number(trust?.documentCompletenessPct || 0)}%</strong></div>
        <div class="mini-row"><span>${icon("clock")}Due diligence</span><strong>${Number(trust?.dueDiligencePct || 0)}%</strong></div>
        <div class="mini-row"><span>${icon("ranking")}Ground truth</span><strong>${Number(trust?.groundTruthVisitCount || 0)} visit${Number(trust?.groundTruthVisitCount || 0) === 1 ? "" : "s"}</strong></div>
      </div>
      <div class="command-trust-events">
        <div>
          <span>Last approval</span>
          <strong>${escapeHtml(lastApproval?.summary || "No approval transition logged yet")}</strong>
        </div>
        <div>
          <span>Last moderation</span>
          <strong>${escapeHtml(lastModeration?.summary || "No moderation event on record")}</strong>
        </div>
      </div>
      <div class="trust-badge-row">${trustBadgeRow(enriched?.trustBadges || [])}</div>
    </article>
  `;
}

function commandNextActionsMarkup(nextActions = {}) {
  const roleGroups = [
    ["Investor", nextActions.investor || []],
    ["Seller", nextActions.seller || []],
    ["Admin", nextActions.admin || []],
  ];

  return `
    <article class="panel-card command-next-actions-panel">
      <div class="command-panel-head">
        <div>
          <div class="panel-kicker">Recommended next moves</div>
          <h3>What each stakeholder should do next</h3>
        </div>
        ${serviceChip("Role aware", "live")}
      </div>
      <div class="command-action-groups">
        ${roleGroups.map(([label, actions]) => `
          <section class="command-action-group">
            <div class="command-action-role">${escapeHtml(label)}</div>
            <div class="command-action-list">
              ${actions.length ? actions.map((action) => `
                <button type="button" class="command-action-item ${commandUrgencyClass(action.urgency)}" data-command-target="${escapeHtml(action.target || "overview")}">
                  <strong>${escapeHtml(action.label || "Review item")}</strong>
                  <p>${escapeHtml(action.reason || "No context supplied.")}</p>
                </button>
              `).join("") : `<div class="loading-panel">No queued action for ${escapeHtml(label.toLowerCase())} right now.</div>`}
            </div>
          </section>
        `).join("")}
      </div>
    </article>
  `;
}

function prioritizedCommandActions(nextActions = {}, limit = 3) {
  const roleGroups = [
    ["Investor", nextActions.investor || []],
    ["Seller", nextActions.seller || []],
    ["Admin", nextActions.admin || []],
  ];
  const seeded = roleGroups.flatMap(([roleLabel, actions]) => (
    actions.length ? [{ ...actions[0], roleLabel }] : []
  ));
  const overflow = roleGroups.flatMap(([roleLabel, actions]) => (
    actions.slice(1).map((action) => ({ ...action, roleLabel }))
  ));
  return [...seeded, ...overflow].slice(0, limit);
}

function commandSummaryMetricMarkup(label, value, note = "") {
  return `
    <article class="command-summary-metric">
      <span>${escapeHtml(label)}</span>
      <strong>${escapeHtml(value)}</strong>
      ${note ? `<small>${escapeHtml(note)}</small>` : ""}
    </article>
  `;
}

function commandCompactBlockerMarkup(blocker) {
  return `
    <button
      type="button"
      class="command-summary-item command-summary-blocker ${commandSeverityClass(blocker.severity)}"
      data-command-target="${escapeHtml(blocker.actionTarget || "overview")}"
    >
      <span>${escapeHtml(String(blocker.severity || "medium").toUpperCase())}</span>
      <strong>${escapeHtml(blocker.title || "Blocker")}</strong>
    </button>
  `;
}

function commandCompactActionMarkup(action) {
  return `
    <button
      type="button"
      class="command-summary-item command-summary-next ${commandUrgencyClass(action.urgency)}"
      data-command-target="${escapeHtml(action.target || "overview")}"
    >
      <span>${escapeHtml(action.roleLabel || "Team")}</span>
      <strong>${escapeHtml(action.label || "Review item")}</strong>
    </button>
  `;
}

function commandTabsMarkup(activeTab, tabs = []) {
  return `
    <div class="command-tab-list" role="tablist" aria-label="Property command center sections">
      ${tabs.map((tab) => `
        <button
          type="button"
          class="command-tab ${activeTab === tab.key ? "is-active" : ""}"
          data-command-tab="${escapeHtml(tab.key)}"
          role="tab"
          aria-selected="${activeTab === tab.key ? "true" : "false"}"
        >
          <span>${escapeHtml(tab.label)}</span>
          <small>${escapeHtml(tab.meta || "")}</small>
        </button>
      `).join("")}
    </div>
  `;
}

function commandAccordionMarkup({
  key,
  kicker,
  title,
  summary = "",
  metaHtml = "",
  bodyHtml = "",
  open = false,
}) {
  return `
    <section class="command-accordion ${open ? "is-open" : ""}" data-command-accordion-id="${escapeHtml(key)}">
      <button
        type="button"
        class="command-accordion-toggle"
        data-command-accordion-toggle="${escapeHtml(key)}"
        aria-expanded="${open ? "true" : "false"}"
      >
        <div class="command-accordion-copy">
          <div class="panel-kicker">${escapeHtml(kicker)}</div>
          <h3>${escapeHtml(title)}</h3>
          ${summary ? `<p>${escapeHtml(summary)}</p>` : ""}
        </div>
        <div class="command-accordion-side">
          ${metaHtml ? `<div class="command-accordion-meta">${metaHtml}</div>` : ""}
          <span class="command-accordion-chevron" aria-hidden="true"></span>
        </div>
      </button>
      <div class="command-accordion-body" ${open ? "" : "hidden"}>
        ${bodyHtml}
      </div>
    </section>
  `;
}

function commandStickySummaryMarkup({
  property,
  enriched,
  scoreModel,
  activeLens,
  blockers,
  nextActions,
  compareIds,
  favoriteIds,
  visit,
}) {
  const basePath = window.SFC_APP_CONFIG?.basePath || "";
  const propertyId = enriched?.id || property?.id || 0;
  const scoreValue = Math.round(Number(scoreModel?.finalScore || 0));
  const deltaValue = Math.round(Number(scoreModel?.delta || 0));
  const topBlockers = blockers.slice(0, 2);
  const remainingBlockers = Math.max(0, blockers.length - topBlockers.length);
  const priorityActions = prioritizedCommandActions(nextActions, 2);
  const remainingActions = Math.max(0, nextActions.length - priorityActions.length);
  const messagingAction = role === "guest"
    ? `<a href="${basePath}/investor-login.php" class="btn-shell btn-shell-secondary">${icon("user")}Investor Login</a>`
    : `<button type="button" class="btn-shell btn-shell-secondary" data-command-target="${visit ? "visits" : "messaging"}">${visit ? `${icon("clock")}Visit Workflow` : `${icon("inbox")}Open Messaging`}</button>`;

  return `
    <section class="command-summary-shell">
      <div class="command-summary-bar" id="propertyCommandTop">
        <div class="command-summary-topline">
          <div class="panel-kicker">Executive summary</div>
          ${serviceChip(`${activeLens?.label || "Default"} lens`, "live")}
        </div>
        <div class="command-summary-header">
          <div class="command-summary-title-copy">
            <h2>${escapeHtml(enriched?.name || property?.name || "Property")}</h2>
            <p>${escapeHtml(propertyStory(enriched || property))}</p>
          </div>
          <div class="command-summary-side">
            <div class="command-summary-score-shell">
              <span>Command score</span>
              <strong id="propertyStickyCommandScoreValue">${scoreValue}</strong>
              <small
                id="propertyStickyCommandDelta"
                class="command-summary-score-delta ${deltaValue >= 0 ? "is-positive" : "is-negative"}"
              >
                ${signedMetric(deltaValue)} vs base IAI
              </small>
            </div>
            <div class="command-summary-actions">
              <div class="command-summary-primary-actions">
                <button type="button" class="btn-shell btn-shell-primary" data-command-target="documents">${icon("file")}Review Documents</button>
                <button type="button" class="btn-shell btn-shell-secondary" data-command-target="due-diligence">${icon("shield")}Open Checklist</button>
                ${messagingAction}
              </div>
            </div>
          </div>
        </div>
        <div class="decision-stats command-summary-status-row">
          <div id="propertyLensScoreSlot">${enriched?.lensResult ? investmentLensScorePill(enriched.lensResult) : scorePill(enriched?.opportunityScore || property?.marketScore || 0)}</div>
          ${statusPill(enriched?.status)}
          ${approvalStatePill(enriched?.approvalState)}
          ${verificationPill(enriched?.listingVerificationStatus)}
          ${groundTruthPill(enriched)}
          <span class="tag">${escapeHtml(voteLabel(enriched?.topNeed || "No demand yet"))}</span>
        </div>
      </div>
      <div class="command-summary-signals">
        <section class="command-summary-signal-group">
          <div class="command-summary-lane-head">
            <div class="panel-kicker">Top blockers</div>
            ${serviceChip(`${blockers.length} total`, topBlockers.length ? "fallback" : "live")}
          </div>
          <div class="command-summary-item-list">
            ${topBlockers.length
              ? `
                ${topBlockers.map((blocker) => commandCompactBlockerMarkup(blocker)).join("")}
                ${remainingBlockers ? `
                  <button type="button" class="command-summary-item command-summary-more" data-command-target="analysis">
                    <span>More</span>
                    <strong>+${remainingBlockers} additional blocker${remainingBlockers === 1 ? "" : "s"}</strong>
                  </button>
                ` : ""}
              `
              : `<div class="command-summary-empty">No critical blockers. This property is clear to move.</div>`}
          </div>
        </section>
        <section class="command-summary-signal-group">
          <div class="command-summary-lane-head">
            <div class="panel-kicker">Next actions</div>
            ${serviceChip("Role aware", "live")}
          </div>
          <div class="command-summary-item-list">
            ${priorityActions.length
              ? `
                ${priorityActions.map((action) => commandCompactActionMarkup(action)).join("")}
                ${remainingActions ? `
                  <button type="button" class="command-summary-item command-summary-more" data-command-target="operations">
                    <span>Queue</span>
                    <strong>+${remainingActions} more action${remainingActions === 1 ? "" : "s"}</strong>
                  </button>
                ` : ""}
              `
              : `<div class="command-summary-empty">No queued next actions right now.</div>`}
          </div>
        </section>
      </div>
    </section>
  `;
}

function commandOverviewStageMarkup({
  property,
  enriched,
  scoreModel,
  decision,
  activeLens,
  lastConfirmed,
  readiness,
  trust,
  duePct,
  visit,
  documentRequests,
  conversationSummary,
  nextActions,
  compareIds,
  favoriteIds,
}) {
  const propertyId = enriched?.id || property?.id || 0;
  const leadAction = prioritizedCommandActions(nextActions, 1)[0] || null;
  const requestCount = Array.isArray(documentRequests) ? documentRequests.length : 0;
  const messageCount = Number(conversationSummary?.messageCount || 0);
  const visitState = visit?.status ? titleCase(String(visit.status).replace(/_/g, " ")) : "Unscheduled";

  return `
    <section class="command-overview-stage" id="propertyCommandOverview">
      <article class="command-overview-hero">
        <div class="command-overview-media">
          <img src="${escapeHtml(enriched?.imageUrl || property?.imageUrl || "")}" alt="${escapeHtml(enriched?.name || property?.name || "Property")}">
        </div>
        <div class="command-overview-copy">
          <div class="command-overview-head">
            <div>
              <div class="panel-kicker">Property brief</div>
              <h3>${escapeHtml(enriched?.name || property?.name || "Property")} through the ${escapeHtml(activeLens?.label || "Default")} lens</h3>
            </div>
            <div class="service-chip-row">
              ${serviceChip(formatFreshness(lastConfirmed, "Awaiting confirmation"), "neutral")}
              ${serviceChip(corridorLabel(enriched?.corridor), "fallback")}
            </div>
          </div>
          <p>${escapeHtml(propertyStory(enriched || property))}</p>
          <div class="command-overview-facts">
            <div><span>${icon("map")}Corridor</span><strong>${escapeHtml(corridorLabel(enriched?.corridor))}</strong></div>
            <div><span>${icon("area")}Land Area</span><strong>${escapeHtml(enriched?.area)} ha</strong></div>
            <div><span>${icon("money")}Guide Price</span><strong>${escapeHtml(moneyShort(enriched?.price))}</strong></div>
            <div><span>${icon("clock")}Freshness</span><strong>${escapeHtml(formatFreshness(lastConfirmed, "Awaiting confirmation"))}</strong></div>
          </div>
          <div class="trust-badge-row">${trustBadgeRow(enriched?.trustBadges || [], { compact: true })}</div>
        </div>
      </article>
      <aside class="command-overview-side">
        <div class="command-overview-tools">
          <a href="${votingHref(propertyId)}" class="btn-shell btn-shell-ghost">${icon("vote")}Open Voting</a>
          <button type="button" class="btn-shell btn-shell-ghost" id="propertyProspectusPrintButton">${icon("file")}Print Prospectus</button>
          <button type="button" class="btn-shell btn-shell-ghost" data-compare-toggle="${propertyId}">${icon("compare")}${compareIds.includes(propertyId) ? "Compared" : "Compare"}</button>
          <button type="button" class="btn-shell btn-shell-ghost" data-favorite-toggle="${propertyId}">${icon("save")}${favoriteActionLabel(favoriteIds.includes(propertyId))}</button>
        </div>
        <article class="command-ribbon-score command-overview-score" id="propertyCommandMetricSlot">
          ${commandScoreSummaryMarkup(scoreModel, activeLens)}
        </article>
        ${decisionSignalPanelMarkup(decision, {
          compact: true,
          title: `${decision?.toneLabel || "Decision Signal"} for this property`,
          kicker: "Decision Engine",
        })}
        <article class="panel-card command-focus-card">
          <div class="panel-kicker">Workflow pulse</div>
          <h3>What the team should watch next</h3>
          <div class="command-focus-grid">
            ${commandSummaryMetricMarkup("Readiness", `${Math.round(Number(readiness?.totalScore || 0))}%`, readiness?.label || "Awaiting read")}
            ${commandSummaryMetricMarkup("Due diligence", `${Number(trust?.dueDiligencePct || duePct || 0)}%`, `${Number(trust?.documentCompletenessPct || 0)}% docs complete`)}
            ${commandSummaryMetricMarkup("Visit state", visitState, visit?.confirmedWindow?.startAt ? formatVisitWindow(visit.confirmedWindow) : "No confirmed slot")}
            ${commandSummaryMetricMarkup("Active threads", `${messageCount}`, `${requestCount} document request${requestCount === 1 ? "" : "s"}`)}
          </div>
          ${leadAction ? `
            <button type="button" class="command-focus-next ${commandUrgencyClass(leadAction.urgency)}" data-command-target="${escapeHtml(leadAction.target || "overview")}">
              <span>${escapeHtml(leadAction.roleLabel || "Team")}</span>
              <strong>${escapeHtml(leadAction.label || "Review item")}</strong>
              <small>${escapeHtml(leadAction.reason || "No context supplied.")}</small>
            </button>
          ` : `<div class="command-summary-empty">No immediate workflow action is queued right now.</div>`}
        </article>
      </aside>
    </section>
  `;
}

async function initPropertyDetails() {
  const root = document.getElementById("propertyDetailsRoot");
  if (!root) return;

  const propertyId = Number(document.body.dataset.propertyId || 0);
  if (!propertyId) {
    root.innerHTML = emptyState("Property not found", "A valid property id is required.");
    return;
  }

  const bootstrap = await api.bootstrap();
  let properties = bootstrap.properties || [];
  const [commandCenterResponse, weatherResponse, summaryResponse] = await Promise.all([
    api.propertyCommandCenter(propertyId),
    api.weatherByProperty(propertyId).catch(() => ({ weather: null })),
    api.aiSummary(propertyId).catch(() => ({ summary: null })),
  ]);
  let property = null;
  let votesState = { votes: {}, selectedVoteOptionId: null };
  let conversationThread = null;
  let conversationMessages = [];
  let conversationThreads = [];
  let conversationSummary = { threadCount: 0, messageCount: 0 };
  let visitLog = null;
  let documentRequests = [];
  let auditLogs = [];
  let commandTimeline = [];
  let commandBlockers = [];
  let commandTrust = {};
  let commandNextActions = {};
  const weather = weatherResponse?.weather || null;
  const summary = summaryResponse?.summary || null;
  const dueItems = bootstrap.meta?.dueDiligenceItems || [];
  const decisionPersonas = normalizeDecisionPersonas(bootstrap.meta?.decisionPersonas || []);
  let dueState = {};
  let dueDrawerOpen = false;
  let activeReadinessPillar = "spatial";
  let adminReadinessDraft = null;
  let investmentLensKey = getActiveInvestmentLensKey();
  let decisionPersonaKey = getStoredDecisionPersonaKey(decisionPersonas);
  let visitCounterMode = false;
  let selectedCommandAuditId = null;
  let activeCommandTab = "command";
  let commandAccordionState = {
    "command-workflow": true,
    "command-trail": false,
    "analysis-score": true,
    "analysis-readiness": false,
    "analysis-context": false,
    "trust-compliance": true,
    "trust-documents": false,
    "operations-logistics": true,
    "operations-messaging": false,
    "operations-location": false,
  };

  const applyCommandCenterPayload = (payload) => {
    if (!payload) return;
    property = payload.property || property;
    if (!property) return;
    votesState = payload.votes || votesState;
    const conversation = payload.conversation || {};
    conversationThread = conversation.thread || null;
    conversationMessages = conversation.messages || [];
    conversationThreads = conversation.threads || [];
    conversationSummary = conversation.summary || conversationSummary;
    visitLog = conversation.visit || payload.visit || visitLog;
    documentRequests = payload.documentRequests || [];
    auditLogs = payload.auditLogs || [];
    commandTimeline = payload.timeline || [];
    commandBlockers = payload.blockers || [];
    commandTrust = payload.trust || {};
    commandNextActions = payload.nextActions || {};
    dueState = payload.dueState || {};
    if (!auditLogs.some((entry) => Number(entry.id) === Number(selectedCommandAuditId))) {
      selectedCommandAuditId = null;
    }
  };

  applyCommandCenterPayload(commandCenterResponse?.commandCenter || {});
  if (!property) {
    root.innerHTML = emptyState("Property not found", "The requested property could not be loaded.");
    return;
  }

  const refreshCommandCenter = async () => {
    const response = await api.propertyCommandCenter(propertyId);
    applyCommandCenterPayload(response.commandCenter || {});
    syncPropertyCollection(property);
  };

  const syncPropertyCollection = (nextProperty) => {
    if (!nextProperty?.id) return;
    const nextId = Number(nextProperty.id);
    const exists = properties.some((entry) => Number(entry.id) === nextId);
    properties = exists
      ? properties.map((entry) => (Number(entry.id) === nextId ? nextProperty : entry))
      : [nextProperty, ...properties];
  };

  const buildAllProperties = (current = property) => {
    const merged = new Map();
    [...properties, current].forEach((entry) => {
      if (!entry?.id) return;
      merged.set(Number(entry.id), entry);
    });
    return Array.from(merged.values());
  };

  const buildPreviewProperty = () => {
    const canEditReadiness = role === "admin";
    if (!canEditReadiness || !adminReadinessDraft) {
      return property;
    }

    return {
      ...property,
      ...adminReadinessDraft,
    };
  };

  const buildReadiness = (targetProperty) => {
    const canEditReadiness = role === "admin";
    if (!targetProperty) {
      return null;
    }

    if (!canEditReadiness && targetProperty.investmentReadiness) {
      return targetProperty.investmentReadiness;
    }

    return calculateInvestmentReadiness(
      targetProperty,
      buildAllProperties(targetProperty),
      calcDueDiligencePct(dueItems, dueState)
    );
  };

  const syncReadinessPreview = () => {
    const slot = document.getElementById("readinessMatrixSlot");
    if (!slot) return;

    const readiness = buildReadiness(buildPreviewProperty());
    const pillarKeys = Object.keys(readiness?.pillars || {});
    if ((!activeReadinessPillar || !pillarKeys.includes(activeReadinessPillar)) && pillarKeys.length) {
      activeReadinessPillar = pillarKeys[0];
    }

    slot.innerHTML = readinessMatrixMarkup(readiness, activeReadinessPillar, role === "admin");
    slot.querySelectorAll("[data-readiness-pillar]").forEach((button) => {
      button.addEventListener("click", () => {
        const nextKey = String(button.dataset.readinessPillar || "");
        activeReadinessPillar = activeReadinessPillar === nextKey ? null : nextKey;
        syncReadinessPreview();
      });
    });
  };

  const syncInvestmentLensPreview = () => {
    const previewProperty = buildPreviewProperty();
    const readiness = buildReadiness(previewProperty);
    const currentVotes = votesState?.votes || {};
    const lensResult = calculateInvestmentLensResult(
      previewProperty,
      buildAllProperties(previewProperty),
      investmentLensKey,
      { readiness }
    );
    const activeLens = getInvestmentLensConfig(investmentLensKey);
    const scoreSlot = document.getElementById("propertyLensScoreSlot");
    if (scoreSlot) {
      scoreSlot.innerHTML = lensResult ? investmentLensScorePill(lensResult) : scorePill(previewProperty.opportunityScore || 0);
    }

    const previewAllProperties = buildAllProperties(previewProperty);
    const previewEnriched = enrichProperties(
      [previewProperty],
      previewAllProperties,
      { [previewProperty.id]: currentVotes },
      null,
      investmentLensKey,
      { readinessById: { [previewProperty.id]: readiness } }
    )[0] || previewProperty;
    const scoreModel = buildCommandScoreModel({
      property: previewProperty,
      enriched: previewEnriched,
      readiness,
      votes: currentVotes,
      conversationSummary,
      visit: visitLog,
      documentRequests,
      activeLens,
    });
    const metricSlot = document.getElementById("propertyCommandMetricSlot");
    if (metricSlot) {
      metricSlot.innerHTML = commandScoreSummaryMarkup(scoreModel, activeLens);
    }
    const stickyScoreValue = document.getElementById("propertyStickyCommandScoreValue");
    if (stickyScoreValue) {
      stickyScoreValue.textContent = `${Math.round(Number(scoreModel?.finalScore || 0))}`;
    }
    const stickyDelta = document.getElementById("propertyStickyCommandDelta");
    if (stickyDelta) {
      const delta = Math.round(Number(scoreModel?.delta || 0));
      stickyDelta.textContent = `${signedMetric(delta)} vs base IAI`;
      stickyDelta.className = `command-summary-score-delta ${delta >= 0 ? "is-positive" : "is-negative"}`;
    }
    const scorePanelSlot = document.getElementById("propertyCommandScorePanelSlot");
    if (scorePanelSlot) {
      scorePanelSlot.innerHTML = commandScorePanelInnerMarkup(scoreModel, activeLens);
    }

    const thesisSlot = document.getElementById("investmentLensThesisSlot");
    if (thesisSlot) {
      thesisSlot.innerHTML = investmentLensThesisMarkup(previewProperty, lensResult, {
        kicker: `Why it fits ${activeLens.label}`,
        heading: `${previewProperty.name} through the ${activeLens.label} lens`,
        metricLimit: 5,
      });
      animateLensMetricBars(thesisSlot);
    }
    const clupSlot = document.getElementById("propertyClupSlot");
    if (clupSlot) clupSlot.innerHTML = clupDecisionCardMarkup(previewEnriched.clupCompliance);
  };

  const collectReadinessDraft = (form) => {
    const formData = new FormData(form);
    const parseNullableNumber = (value, decimals = null) => {
      const raw = String(value ?? "").trim();
      if (!raw) return null;
      const numeric = Number(raw);
      if (!Number.isFinite(numeric) || numeric < 0) return null;
      return decimals === null ? numeric : Number(numeric.toFixed(decimals));
    };
    const parseNullableInteger = (value) => {
      const raw = String(value ?? "").trim();
      if (!raw) return null;
      const numeric = Number.parseInt(raw, 10);
      if (!Number.isFinite(numeric) || numeric < 0) return null;
      return numeric;
    };

    const splitUses = (name) => String(formData.get(name) || "").split(",").map((value) => value.trim()).filter(Boolean);
    const clupProfile = {
      existingLandUse: String(formData.get("existingLandUse") || "").trim() || null,
      zoningClassification: String(formData.get("zoningClassification") || "").trim() || null,
      allowedUses: splitUses("clupAllowedUses"),
      conditionalUses: splitUses("clupConditionalUses"),
      restrictedUses: splitUses("clupRestrictedUses"),
      sourceReference: String(formData.get("clupSourceReference") || "").trim() || null,
      isVerified: Boolean(form.elements.clupVerified?.checked),
      verifiedAt: form.elements.clupVerified?.checked ? (property?.clupProfile?.verifiedAt || new Date().toISOString()) : null,
    };
    return {
      distToRoadKm: parseNullableNumber(formData.get("distToRoadKm"), 2),
      utilityStatus: String(formData.get("utilityStatus") || "").trim() || null,
      zoningScore: parseNullableInteger(formData.get("zoningScore")),
      assessedValueSqm: parseNullableInteger(formData.get("assessedValueSqm")),
      readinessNotes: String(formData.get("readinessNotes") || ""),
      existingLandUse: clupProfile.existingLandUse,
      zoningClassification: clupProfile.zoningClassification,
      clupAllowedUses: clupProfile.allowedUses,
      clupConditionalUses: clupProfile.conditionalUses,
      clupRestrictedUses: clupProfile.restrictedUses,
      clupSourceReference: clupProfile.sourceReference,
      clupVerified: clupProfile.isVerified ? 1 : 0,
      clupProfile,
    };
  };

  const render = () => {
    const compareIds = getCompareIds();
    const favoriteIds = getFavoriteIds();
    const canRequestDocuments = role === "investor" || role === "admin";
    const canManageRequests = role === "seller" || role === "admin";
    const canEditDueDiligence = role === "admin" || (role === "seller" && Number(property?.sellerUserId || 0) === Number(currentUser?.id || 0));
    const canEditReadiness = role === "admin";
    const votes = votesState?.votes || {};
    const duePct = calcDueDiligencePct(dueItems, dueState);
    const viewProperty = property;
    const formProperty = buildPreviewProperty();
    const readiness = buildReadiness(formProperty);
    const allProperties = buildAllProperties(formProperty);
    const votesMap = { [formProperty.id]: votes };
    const enriched = enrichProperties(
      [formProperty],
      allProperties,
      votesMap,
      null,
      investmentLensKey,
      { readinessById: { [formProperty.id]: readiness } }
    )[0] || formProperty;
    const mapPeersBase = allProperties.filter((entry) => Number(entry.id) === Number(enriched.id) || String(entry.corridor || "") === String(enriched.corridor || ""));
    const mapPeers = (mapPeersBase.length ? mapPeersBase : allProperties).slice(0, 6);
    const checklistItems = Array.isArray(viewProperty.documentChecklist) && viewProperty.documentChecklist.length
      ? viewProperty.documentChecklist
      : DOCUMENT_FIELDS;
    const lastConfirmed = enriched.lastConfirmedAvailableAt || enriched.updatedAt;
    const activeLens = getInvestmentLensConfig(investmentLensKey);
    const scoreModel = buildCommandScoreModel({
      property: formProperty,
      enriched,
      readiness,
      votes,
      conversationSummary,
      visit: visitLog,
      documentRequests,
      activeLens,
    });
    const decision = resolveDecisionVariant(formProperty.decision, decisionPersonaKey)
      || resolveDecisionVariant(property?.decision, decisionPersonaKey)
      || null;
    const prospectusProperties = enrichProperties(
      allProperties,
      allProperties,
      votesMap,
      null,
      investmentLensKey
    );
    const pillarKeys = Object.keys(readiness?.pillars || {});
    if ((!activeReadinessPillar || !pillarKeys.includes(activeReadinessPillar)) && pillarKeys.length) {
      activeReadinessPillar = pillarKeys[0];
    }
    const activeAudit = auditLogs.find((entry) => Number(entry.id) === Number(selectedCommandAuditId)) || null;
    const priorityActions = prioritizedCommandActions(commandNextActions, 3);
    const commandTabs = [
      {
        key: "command",
        label: "Command",
        meta: `${commandBlockers.length} blocker${commandBlockers.length === 1 ? "" : "s"}`,
      },
      {
        key: "analysis",
        label: "Analysis",
        meta: `${Math.round(Number(scoreModel?.finalScore || 0))} score`,
      },
      {
        key: "trust",
        label: "Trust & Docs",
        meta: `${Number(commandTrust?.auditLogCount || 0)} audit event${Number(commandTrust?.auditLogCount || 0) === 1 ? "" : "s"}`,
      },
      {
        key: "operations",
        label: "Operations",
        meta: visitLog?.status ? titleCase(String(visitLog.status).replace(/_/g, " ")) : `${conversationSummary.messageCount || 0} message${Number(conversationSummary.messageCount || 0) === 1 ? "" : "s"}`,
      },
    ];
    const analysisScorePanel = `
      <div class="command-tab-grid command-tab-grid-double" id="propertyCommandScoreSection">
        <div class="stack">
          ${investmentLensSelectorMarkup(investmentLensKey, {
            title: "Select Investment Lens",
            description: "Reweight this property for the purpose you want to test. The command score and thesis update live without changing the underlying record.",
          })}
          ${decisionPersonaSelectorMarkup(decisionPersonas, decisionPersonaKey, {
            title: "Select Investor Persona",
            description: "Move from generic scoring to buyer-specific intelligence without leaving the command desk.",
          })}
        </div>
        <div class="stack">
          ${decisionSignalPanelMarkup(decision, {
            title: `${decision?.toneLabel || "Decision Signal"} for this property`,
            kicker: "Decision Engine",
          })}
          <article class="panel-card command-score-panel" id="propertyCommandScorePanelSlot">
            ${commandScorePanelInnerMarkup(scoreModel, activeLens)}
          </article>
        </div>
      </div>
    `;
    const analysisReadinessPanel = `
      <div class="command-tab-grid command-tab-grid-double">
        <div class="stack">
          <div id="propertyClupSlot">${clupDecisionCardMarkup(enriched.clupCompliance)}</div>
          <div id="investmentLensThesisSlot">${investmentLensThesisMarkup(enriched, enriched.lensResult, {
            kicker: `Why it fits ${activeLens.label}`,
            heading: `${enriched.name} through the ${activeLens.label} lens`,
            metricLimit: 5,
          })}</div>
          ${canEditReadiness ? inlineReadinessEditorMarkup(formProperty) : ""}
        </div>
        <div id="readinessMatrixSlot">${readinessMatrixMarkup(readiness, activeReadinessPillar, canEditReadiness)}</div>
      </div>
    `;
    const marketContextPanel = `
      <div class="command-tab-grid command-tab-grid-double">
        <article class="panel-card">
          <div class="panel-kicker">Local demand</div>
          <h3>What this location appears to need</h3>
          <div class="bar-list">${voteBars(votes)}</div>
        </article>
        <article class="panel-card">
          <div class="panel-kicker">AI investment brief</div>
          <div class="service-chip-row">
            ${serviceChip(summary?.live ? "Live summary" : "Structured summary", summary?.live ? "live" : "fallback")}
            ${serviceChip(summary?.provider || "Property narrative", "neutral")}
          </div>
          <h3>${escapeHtml(summary?.headline || "Property briefing")}</h3>
          <p>${escapeHtml(summary?.summary || propertyStory(enriched))}</p>
          <div class="insight-strip">
            ${(summary?.takeaways || []).slice(0, 3).map((item) => `<span class="insight-pill">${escapeHtml(item)}</span>`).join("")}
          </div>
        </article>
      </div>
    `;
    const trustDocumentsPanel = `
      <article class="panel-card" id="propertyDocumentWorkflowSection">
        <div class="panel-kicker">Document workflow</div>
        <h3>Verification requests and document package</h3>
        <div class="document-grid">
          ${documentChecklistMarkup(enriched)}
        </div>
        ${canRequestDocuments ? `
          <form class="crud-form-grid" id="propertyDocumentRequestForm">
            <label class="form-shell">
              <span>Document to request</span>
              <select class="input-shell" id="documentRequestName">
                ${checklistItems.map((item) => `<option value="${escapeHtml(item.label || titleCase(item.key))}">${escapeHtml(item.label || titleCase(item.key))}</option>`).join("")}
                <option value="Other supporting document">Other supporting document</option>
              </select>
            </label>
            <label class="form-shell form-span-2">
              <span>Request note</span>
              <textarea class="input-shell input-textarea" id="documentRequestNote" placeholder="Ask for the exact document, version date, or supporting attachment you need."></textarea>
            </label>
            <div class="crud-actions form-span-2">
              <button type="submit" class="btn-shell btn-shell-primary">Request Document</button>
            </div>
          </form>
        ` : role === "guest" ? `
          <div class="auth-form-note">Investor accounts can request title copies, surveys, and supporting verification files from this property page.</div>
        ` : ""}
        <div class="request-stack">
          ${requestTimelineMarkup(documentRequests, {
            manage: canManageRequests,
            emptyCopy: "Document requests will appear here once an investor or admin asks for supporting files.",
          })}
        </div>
      </article>
    `;
    const messagingPanel = `
      <div class="command-tab-grid command-tab-grid-double">
        <article class="contact-card" id="propertyMessagingSection">
          <div class="panel-kicker">${role === "investor" ? "Direct seller chat" : "Seller contact"}</div>
          <h3>${escapeHtml(property.ownerContact?.name || "Listing desk")}</h3>
          <div class="mini-list">
            <div class="mini-row"><span>Email</span><strong>${escapeHtml(property.ownerContact?.email || "portfolio@sfcelerate.local")}</strong></div>
            <div class="mini-row"><span>Phone</span><strong>${escapeHtml(property.ownerContact?.phone || "+63 917 555 0199")}</strong></div>
            <div class="mini-row"><span>Response SLA</span><strong>${escapeHtml(property.ownerContact?.responseSla || "24 HOURS")}</strong></div>
          </div>
          ${role === "investor" ? `
            <div class="chat-thread-surface">
              ${conversationBubbles(conversationMessages, "investor", "No messages yet. Introduce yourself and ask the seller about documents, schedule, or pricing.", visitLog)}
            </div>
            <form class="thread-compose" id="propertyChatForm">
              <textarea class="input-shell input-textarea" id="propertyChatInput" placeholder="Message the seller directly about this property."></textarea>
              <button type="submit" class="btn-shell btn-shell-primary">${conversationThread ? "Send Message" : "Start Conversation"}</button>
            </form>
          ` : role === "guest" ? `
            <div class="auth-form-note">Investor accounts can now message the seller directly from this property page.</div>
            <a href="${window.SFC_APP_CONFIG.basePath || ""}/investor-login.php" class="btn-shell btn-shell-primary">Investor Login / Sign Up</a>
          ` : `
            <div class="auth-form-note">This property currently has ${conversationSummary.threadCount || 0} direct thread(s) and ${conversationSummary.messageCount || 0} stored message(s).</div>
          `}
        </article>
        <article class="panel-card">
          <div class="panel-kicker">Inquiry preview</div>
          <h3>${conversationSummary.messageCount ? `${conversationSummary.messageCount} stored messages` : "No inquiries yet"}</h3>
          <div class="mini-list">
            ${conversationThreads.length ? conversationThreads.slice(0, 3).map((thread) => `
              <div class="mini-row"><span>${escapeHtml(thread.investorName || "Investor")}</span><strong>${escapeHtml(truncate(thread.lastMessageText || thread.subject || "Recent conversation", 42))}</strong></div>
            `).join("") : conversationMessages.length ? conversationMessages.slice(0, 3).map((message) => `
              <div class="mini-row"><span>${escapeHtml(message.senderName)}</span><strong>${escapeHtml(truncate(message.text, 42))}</strong></div>
            `).join("") : `<div class="loading-panel">Inquiry activity appears here once residents or investors message this listing.</div>`}
          </div>
        </article>
      </div>
    `;
    const locationPanel = `
      <div class="command-tab-grid command-tab-grid-double">
        <article class="panel-card map-panel-card">
          <div class="map-panel-head">
            <div>
              <div class="panel-kicker">Nearby corridor view</div>
              <h3>Live location map</h3>
            </div>
            <div class="service-chip-row">
              ${serviceChip("Leaflet map", "live")}
              ${serviceChip("OSM", "neutral")}
            </div>
          </div>
          <div class="leaflet-frame compact-leaflet-frame">
            <div id="propertyDetailMap" class="leaflet-shell detail-map"></div>
          </div>
          ${googleEarthActionsMarkup({
            property: enriched || property,
            scope: "property-details",
            showView: true,
            note: "Use Google Earth as a complementary site inspection layer for parcel context, report export, and visual validation.",
          })}
        </article>
        <article class="panel-card">
          <div class="panel-kicker">Climate context</div>
          <div class="service-chip-row">
            ${serviceChip(weather?.live ? "Live weather" : "Climate note", weather?.live ? "live" : "fallback")}
            ${serviceChip(weather?.provider || "Weather", "neutral")}
          </div>
          <h3>${escapeHtml(weather?.summary || "Location context")}</h3>
          <div class="mini-list">
            <div class="mini-row"><span>${icon("map")}Location</span><strong>${escapeHtml(weather?.location || property.barangay || "San Fernando")}</strong></div>
            <div class="mini-row"><span>${icon("pulse")}Temperature</span><strong>${weather?.temperatureC != null ? `${Math.round(Number(weather.temperatureC))}Â°C` : "Not configured"}</strong></div>
            <div class="mini-row"><span>${icon("vote")}Humidity</span><strong>${weather?.humidity != null ? `${weather.humidity}%` : "Not configured"}</strong></div>
          </div>
        </article>
      </div>
    `;
    const commandTabContent = {
      command: `
        <div class="command-tab-panel">
          ${commandAccordionMarkup({
            key: "command-workflow",
            kicker: "Workflow",
            title: "Resolve blockers and move the operating loop forward",
            summary: priorityActions.length
              ? `${priorityActions[0].roleLabel}: ${priorityActions[0].label}`
              : "No queued next actions right now.",
            metaHtml: `${serviceChip(`${commandBlockers.length} blocker${commandBlockers.length === 1 ? "" : "s"}`, commandBlockers.length ? "fallback" : "live")}${serviceChip(`${priorityActions.length} next`, "live")}`,
            bodyHtml: `
              <div class="command-tab-grid command-tab-grid-double" id="propertyCommandWorkflowSection">
                ${commandBlockersMarkup(commandBlockers)}
                ${commandNextActionsMarkup(commandNextActions)}
              </div>
            `,
            open: Boolean(commandAccordionState["command-workflow"]),
          })}
          ${commandAccordionMarkup({
            key: "command-trail",
            kicker: "Activity trail",
            title: "Recent movement across trust, logistics, and messaging",
            summary: commandTimeline.length
              ? `${commandTimeline[0].title || "Recent update"}`
              : "No recent events on this property yet.",
            metaHtml: `${serviceChip("Live property trail", "neutral")}`,
            bodyHtml: `<section id="propertyCommandTrailSection">${commandTimelineMarkup(commandTimeline)}</section>`,
            open: Boolean(commandAccordionState["command-trail"]),
          })}
        </div>
      `,
      analysis: `
        <div class="command-tab-panel">
          ${commandAccordionMarkup({
            key: "analysis-score",
            kicker: "Lens and scoring",
            title: "Reframe the property and inspect the current command score",
            summary: `${activeLens.label} is active. Score ${Math.round(Number(scoreModel?.finalScore || 0))}.`,
            metaHtml: `${serviceChip(`${activeLens.label} active`, "live")}${serviceChip(`${signedMetric(Math.round(Number(scoreModel?.delta || 0)))} delta`, Number(scoreModel?.delta || 0) >= 0 ? "live" : "fallback")}`,
            bodyHtml: analysisScorePanel,
            open: Boolean(commandAccordionState["analysis-score"]),
          })}
          ${commandAccordionMarkup({
            key: "analysis-readiness",
            kicker: "Readiness",
            title: "See why the thesis holds up and where certainty breaks",
            summary: `${Math.round(Number(readiness?.totalScore || 0))}% readiness. ${readiness?.label || "Awaiting structured read."}`,
            metaHtml: `${serviceChip(`${Number(readiness?.missingDataCount || 0)} missing`, Number(readiness?.missingDataCount || 0) ? "fallback" : "live")}${serviceChip("IRIE", "neutral")}`,
            bodyHtml: analysisReadinessPanel,
            open: Boolean(commandAccordionState["analysis-readiness"]),
          })}
          ${commandAccordionMarkup({
            key: "analysis-context",
            kicker: "Market context",
            title: "Demand signal and AI brief",
            summary: summary?.headline || "Demand, narrative, and quick context for the property.",
            metaHtml: `${serviceChip(voteLabel(enriched?.topNeed || "No demand yet"), "fallback")}${serviceChip(summary?.live ? "Live summary" : "Structured summary", summary?.live ? "live" : "neutral")}`,
            bodyHtml: marketContextPanel,
            open: Boolean(commandAccordionState["analysis-context"]),
          })}
        </div>
      `,
      trust: `
        <div class="command-tab-panel">
          ${commandAccordionMarkup({
            key: "trust-compliance",
            kicker: "Trust",
            title: "Institutional confidence, verification, and ledger visibility",
            summary: `${Number(commandTrust?.auditLogCount || 0)} audit event${Number(commandTrust?.auditLogCount || 0) === 1 ? "" : "s"} recorded.`,
            metaHtml: `${serviceChip(`${Number(commandTrust?.auditLogCount || 0)} audit events`, Number(commandTrust?.auditLogCount || 0) ? "live" : "neutral")}`,
            bodyHtml: commandTrustMarkup({ trust: commandTrust, property: enriched, enriched }),
            open: Boolean(commandAccordionState["trust-compliance"]),
          })}
          ${commandAccordionMarkup({
            key: "trust-documents",
            kicker: "Documents",
            title: "Document package, requests, and response workflow",
            summary: `${checklistItems.length} tracked document field${checklistItems.length === 1 ? "" : "s"} with ${documentRequests.length} active request${documentRequests.length === 1 ? "" : "s"}.`,
            metaHtml: `${serviceChip(`${documentRequests.length} request${documentRequests.length === 1 ? "" : "s"}`, documentRequests.length ? "fallback" : "neutral")}`,
            bodyHtml: trustDocumentsPanel,
            open: Boolean(commandAccordionState["trust-documents"]),
          })}
        </div>
      `,
      operations: `
        <div class="command-tab-panel">
          ${commandAccordionMarkup({
            key: "operations-logistics",
            kicker: "Logistics",
            title: "Ground truth orchestration and visit workflow",
            summary: visitLog?.status
              ? `Current visit state: ${titleCase(String(visitLog.status).replace(/_/g, " "))}`
              : "No visit workflow is active yet.",
            metaHtml: `${serviceChip(visitLog?.status ? titleCase(String(visitLog.status).replace(/_/g, " ")) : "Awaiting visit", visitLog?.status ? "live" : "neutral")}`,
            bodyHtml: `<section id="propertyLogisticsSection">${logisticsHubMarkup({
              property: enriched,
              visit: visitLog,
              currentRole: role,
              counterMode: visitCounterMode,
              compact: true,
            })}</section>`,
            open: Boolean(commandAccordionState["operations-logistics"]),
          })}
          ${commandAccordionMarkup({
            key: "operations-messaging",
            kicker: "Messaging",
            title: "Seller conversation and inquiry context",
            summary: conversationSummary.messageCount
              ? `${conversationSummary.messageCount} stored message${Number(conversationSummary.messageCount || 0) === 1 ? "" : "s"}.`
              : "No inquiries are stored for this property yet.",
            metaHtml: `${serviceChip(`${conversationSummary.threadCount || 0} thread${Number(conversationSummary.threadCount || 0) === 1 ? "" : "s"}`, conversationSummary.threadCount ? "live" : "neutral")}`,
            bodyHtml: messagingPanel,
            open: Boolean(commandAccordionState["operations-messaging"]),
          })}
          ${commandAccordionMarkup({
            key: "operations-location",
            kicker: "Location context",
            title: "Map, weather, and parcel context",
            summary: weather?.summary || "Inspect the property in the corridor and review live location context.",
            metaHtml: `${serviceChip("Leaflet map", "live")}${serviceChip(weather?.provider || "Weather", "neutral")}`,
            bodyHtml: commandAccordionState["operations-location"] ? locationPanel : "",
            open: Boolean(commandAccordionState["operations-location"]),
          })}
        </div>
      `,
    };
    if (!window.__SFC_LEGACY_PROPERTY_DETAILS__) {
      root.innerHTML = `
        <div class="property-command-shell">
          ${commandStickySummaryMarkup({
            property: formProperty,
            enriched,
            scoreModel,
            activeLens,
            blockers: commandBlockers,
            nextActions: commandNextActions,
            compareIds,
            favoriteIds,
            visit: visitLog,
          })}

          ${commandOverviewStageMarkup({
            property: formProperty,
            enriched,
            scoreModel,
            decision,
            activeLens,
            lastConfirmed,
            readiness,
            trust: commandTrust,
            duePct,
            visit: visitLog,
            documentRequests,
            conversationSummary,
            nextActions: commandNextActions,
            compareIds,
            favoriteIds,
          })}

          <section class="command-workspace">
            <div class="command-workspace-head">
              <div>
                <div class="panel-kicker">Deep dive workspace</div>
                <h3>Move from executive scan to analyst detail without losing the thread</h3>
                <p>Primary workflow stays visible above. Supporting detail is organized below by command, analysis, trust, and operations.</p>
              </div>
              ${commandTabsMarkup(activeCommandTab, commandTabs)}
            </div>
            <div class="command-tab-stage">
              ${commandTabContent[activeCommandTab] || commandTabContent.command}
            </div>
          </section>

          ${dueDiligenceDrawerMarkup(dueItems, dueState, {
            open: dueDrawerOpen,
            editable: canEditDueDiligence,
          })}
          ${auditDrawerMarkup(activeAudit)}
          ${prospectusMarkup({
            property: enriched,
            readiness,
            lensResult: enriched.lensResult,
            lensKey: investmentLensKey,
            votes,
            summary,
            weather,
            visit: visitLog,
            conversationSummary,
            conversationThread,
            allProperties: prospectusProperties,
            duePct,
            generatedAt: bootstrap.generatedAt,
          })}
        </div>
      `;
    }
    if (window.__SFC_LEGACY_PROPERTY_DETAILS__) root.innerHTML = `
      <div class="property-command-shell">
        ${investmentLensSelectorMarkup(investmentLensKey, {
          title: "Select Investment Lens",
          description: "Reweight this property for the purpose you want to test. The command score, thesis, and blocker hierarchy update live.",
        })}

        ${commandRibbonMarkup({
          property: formProperty,
          enriched,
          scoreModel,
          activeLens,
          lastConfirmed,
          compareIds,
          favoriteIds,
        })}

        <div class="command-grid">
          <section class="stack">
            <article class="panel-card command-score-panel" id="propertyCommandScorePanelSlot">
              ${commandScorePanelInnerMarkup(scoreModel, activeLens)}
            </article>
            ${commandTimelineMarkup(commandTimeline)}
          </section>
          <aside class="stack command-rail">
            ${commandBlockersMarkup(commandBlockers)}
            ${commandTrustMarkup({ trust: commandTrust, property: enriched, enriched })}
            ${commandNextActionsMarkup(commandNextActions)}
          </aside>
        </div>

        <div class="detail-layout property-support-layout">
        <section class="stack">

          <div id="investmentLensThesisSlot">${investmentLensThesisMarkup(enriched, enriched.lensResult, {
            kicker: `Why it fits ${activeLens.label}`,
            heading: `${enriched.name} through the ${activeLens.label} lens`,
            metricLimit: 5,
          })}</div>

          <div id="readinessMatrixSlot">${readinessMatrixMarkup(readiness, activeReadinessPillar, canEditReadiness)}</div>
          ${canEditReadiness ? inlineReadinessEditorMarkup(formProperty) : ""}

          <article class="panel-card">
            <div class="panel-kicker">Local demand</div>
            <h3>What this location appears to need</h3>
            <div class="bar-list">${voteBars(votes)}</div>
          </article>

          <article class="panel-card">
            <div class="panel-kicker">AI investment brief</div>
            <div class="service-chip-row">
              ${serviceChip(summary?.live ? "Live summary" : "Structured summary", summary?.live ? "live" : "fallback")}
              ${serviceChip(summary?.provider || "Property narrative", "neutral")}
            </div>
            <h3>${escapeHtml(summary?.headline || "Property briefing")}</h3>
            <p>${escapeHtml(summary?.summary || propertyStory(enriched))}</p>
            <div class="insight-strip">
              ${(summary?.takeaways || []).slice(0, 3).map((item) => `<span class="insight-pill">${escapeHtml(item)}</span>`).join("")}
            </div>
          </article>

          <section id="propertyLogisticsSection">
            ${logisticsHubMarkup({
              property: enriched,
              visit: visitLog,
              currentRole: role,
              counterMode: visitCounterMode,
            })}
          </section>

          <article class="contact-card" id="propertyMessagingSection">
            <div class="panel-kicker">${role === "investor" ? "Direct seller chat" : "Seller contact"}</div>
            <h3>${escapeHtml(property.ownerContact?.name || "Listing desk")}</h3>
            <div class="mini-list">
              <div class="mini-row"><span>Email</span><strong>${escapeHtml(property.ownerContact?.email || "portfolio@sfcelerate.local")}</strong></div>
              <div class="mini-row"><span>Phone</span><strong>${escapeHtml(property.ownerContact?.phone || "+63 917 555 0199")}</strong></div>
              <div class="mini-row"><span>Response SLA</span><strong>${escapeHtml(property.ownerContact?.responseSla || "24 HOURS")}</strong></div>
            </div>
            ${role === "investor" ? `
              <div class="chat-thread-surface">
                ${conversationBubbles(conversationMessages, "investor", "No messages yet. Introduce yourself and ask the seller about documents, schedule, or pricing.", visitLog)}
              </div>
              <form class="thread-compose" id="propertyChatForm">
                <textarea class="input-shell input-textarea" id="propertyChatInput" placeholder="Message the seller directly about this property."></textarea>
                <button type="submit" class="btn-shell btn-shell-primary">${conversationThread ? "Send Message" : "Start Conversation"}</button>
              </form>
            ` : role === "guest" ? `
              <div class="auth-form-note">Investor accounts can now message the seller directly from this property page.</div>
              <a href="${window.SFC_APP_CONFIG.basePath || ""}/investor-login.php" class="btn-shell btn-shell-primary">Investor Login / Sign Up</a>
            ` : `
              <div class="auth-form-note">This property currently has ${conversationSummary.threadCount || 0} direct thread(s) and ${conversationSummary.messageCount || 0} stored message(s).</div>
            `}
          </article>

          <article class="panel-card" id="propertyDocumentWorkflowSection">
            <div class="panel-kicker">Document workflow</div>
            <h3>Verification requests and document package</h3>
            <div class="document-grid">
              ${documentChecklistMarkup(enriched)}
            </div>
            ${canRequestDocuments ? `
              <form class="crud-form-grid" id="propertyDocumentRequestForm">
                <label class="form-shell">
                  <span>Document to request</span>
                  <select class="input-shell" id="documentRequestName">
                    ${checklistItems.map((item) => `<option value="${escapeHtml(item.label || titleCase(item.key))}">${escapeHtml(item.label || titleCase(item.key))}</option>`).join("")}
                    <option value="Other supporting document">Other supporting document</option>
                  </select>
                </label>
                <label class="form-shell form-span-2">
                  <span>Request note</span>
                  <textarea class="input-shell input-textarea" id="documentRequestNote" placeholder="Ask for the exact document, version date, or supporting attachment you need."></textarea>
                </label>
                <div class="crud-actions form-span-2">
                  <button type="submit" class="btn-shell btn-shell-primary">Request Document</button>
                </div>
              </form>
            ` : role === "guest" ? `
              <div class="auth-form-note">Investor accounts can request title copies, surveys, and supporting verification files from this property page.</div>
            ` : ""}
            <div class="request-stack">
              ${requestTimelineMarkup(documentRequests, {
                manage: canManageRequests,
                emptyCopy: "Document requests will appear here once an investor or admin asks for supporting files.",
              })}
            </div>
          </article>
        </section>

        <aside class="stack">
          <article class="panel-card map-panel-card">
            <div class="map-panel-head">
              <div>
                <div class="panel-kicker">Nearby corridor view</div>
                <h3>Live location map</h3>
              </div>
              <div class="service-chip-row">
                ${serviceChip("Leaflet map", "live")}
                ${serviceChip("OSM", "neutral")}
              </div>
            </div>
            <div class="leaflet-frame compact-leaflet-frame">
              <div id="propertyDetailMap" class="leaflet-shell detail-map"></div>
            </div>
          </article>

          <article class="panel-card">
            <div class="panel-kicker">Climate context</div>
            <div class="service-chip-row">
              ${serviceChip(weather?.live ? "Live weather" : "Climate note", weather?.live ? "live" : "fallback")}
              ${serviceChip(weather?.provider || "Weather", "neutral")}
            </div>
            <h3>${escapeHtml(weather?.summary || "Location context")}</h3>
            <div class="mini-list">
              <div class="mini-row"><span>${icon("map")}Location</span><strong>${escapeHtml(weather?.location || property.barangay || "San Fernando")}</strong></div>
              <div class="mini-row"><span>${icon("pulse")}Temperature</span><strong>${weather?.temperatureC != null ? `${Math.round(Number(weather.temperatureC))}°C` : "Not configured"}</strong></div>
              <div class="mini-row"><span>${icon("vote")}Humidity</span><strong>${weather?.humidity != null ? `${weather.humidity}%` : "Not configured"}</strong></div>
            </div>
          </article>

          <article class="panel-card">
            <div class="panel-kicker">Inquiry preview</div>
            <h3>${conversationSummary.messageCount ? `${conversationSummary.messageCount} stored messages` : "No inquiries yet"}</h3>
            <div class="mini-list">
              ${conversationThreads.length ? conversationThreads.slice(0, 3).map((thread) => `
                <div class="mini-row"><span>${escapeHtml(thread.investorName || "Investor")}</span><strong>${escapeHtml(truncate(thread.lastMessageText || thread.subject || "Recent conversation", 42))}</strong></div>
              `).join("") : conversationMessages.length ? conversationMessages.slice(0, 3).map((message) => `
                <div class="mini-row"><span>${escapeHtml(message.senderName)}</span><strong>${escapeHtml(truncate(message.text, 42))}</strong></div>
              `).join("") : `<div class="loading-panel">Inquiry activity appears here once residents or investors message this listing.</div>`}
            </div>
          </article>
        </aside>
      </div>
      </div>
      ${dueDiligenceDrawerMarkup(dueItems, dueState, {
        open: dueDrawerOpen,
        editable: canEditDueDiligence,
      })}
      ${auditDrawerMarkup(activeAudit)}
      ${prospectusMarkup({
        property: enriched,
        readiness,
        lensResult: enriched.lensResult,
        lensKey: investmentLensKey,
        votes,
        summary,
        weather,
        visit: visitLog,
        conversationSummary,
        conversationThread,
        allProperties: prospectusProperties,
        duePct,
        generatedAt: bootstrap.generatedAt,
      })}
    `;

    bindCollectionActions(root, render);
    document.getElementById("propertyProspectusPrintButton")?.addEventListener("click", () => {
      window.print();
    });
    root.querySelectorAll("[data-command-tab]").forEach((button) => {
      button.addEventListener("click", () => {
        const nextTab = String(button.dataset.commandTab || "command");
        if (!nextTab || nextTab === activeCommandTab) return;
        activeCommandTab = nextTab;
        render();
      });
    });
    root.querySelectorAll("[data-command-accordion-toggle]").forEach((button) => {
      button.addEventListener("click", () => {
        const key = String(button.dataset.commandAccordionToggle || "");
        if (!key) return;
        commandAccordionState[key] = !Boolean(commandAccordionState[key]);
        render();
      });
    });
    syncReadinessPreview();

    const readinessForm = document.getElementById("readinessInlineForm");
    if (readinessForm) {
      const updateReadinessPreview = () => {
        adminReadinessDraft = collectReadinessDraft(readinessForm);
        syncReadinessPreview();
        syncInvestmentLensPreview();
      };

      readinessForm.querySelectorAll("input, textarea").forEach((control) => {
        control.addEventListener("input", updateReadinessPreview);
      });
      readinessForm.querySelectorAll("select").forEach((control) => {
        control.addEventListener("change", updateReadinessPreview);
      });
      readinessForm.querySelector("[data-readiness-reset]")?.addEventListener("click", () => {
        adminReadinessDraft = null;
        render();
      });
      readinessForm.addEventListener("submit", async (event) => {
        event.preventDefault();
        await api.updateProperty(property.id, collectReadinessDraft(readinessForm));
        adminReadinessDraft = null;
        await refreshCommandCenter();
        render();
      });
    }

    bindInvestmentLensSelector(root, (nextLensKey) => {
      investmentLensKey = nextLensKey;
      saveActiveInvestmentLensKey(nextLensKey);
      const previewProperty = buildPreviewProperty();
      const nextReadiness = buildReadiness(previewProperty);
      const nextLensResult = calculateInvestmentLensResult(
        previewProperty,
        buildAllProperties(previewProperty),
        nextLensKey,
        { readiness: nextReadiness }
      );
      activeReadinessPillar = nextLensResult?.emphasizedPillars?.[0]?.key || activeReadinessPillar;
      render();
    });
    root.querySelectorAll("[data-decision-persona]").forEach((button) => {
      button.addEventListener("click", () => {
        decisionPersonaKey = String(button.dataset.decisionPersona || decisionPersonaKey);
        saveDecisionPersonaKey(decisionPersonaKey);
        render();
      });
    });

    const handleCommandTarget = (target, auditId = null) => {
      if (auditId) {
        selectedCommandAuditId = Number(auditId);
        activeCommandTab = "command";
        commandAccordionState["command-trail"] = true;
        render();
        return;
      }

      if (target === "due-diligence") {
        dueDrawerOpen = true;
        render();
        return;
      }

      const targetMap = {
        messaging: {
          id: "propertyMessagingSection",
          tab: "operations",
          accordion: "operations-messaging",
        },
        visits: {
          id: "propertyLogisticsSection",
          tab: "operations",
          accordion: "operations-logistics",
        },
        documents: {
          id: "propertyDocumentWorkflowSection",
          tab: "trust",
          accordion: "trust-documents",
        },
        trust: {
          id: "propertyTrustSection",
          tab: "trust",
          accordion: "trust-compliance",
        },
        audit: {
          id: "propertyCommandTrailSection",
          tab: "command",
          accordion: "command-trail",
        },
        overview: {
          id: "propertyCommandTop",
          tab: "command",
        },
      };
      const mapping = targetMap[target] || targetMap.overview;
      if (mapping.tab) {
        activeCommandTab = mapping.tab;
      }
      if (mapping.accordion) {
        commandAccordionState[mapping.accordion] = true;
      }
      render();
      window.requestAnimationFrame(() => {
        const element = document.getElementById(mapping.id || "propertyCommandTop");
        element?.scrollIntoView({ behavior: "smooth", block: "start" });
      });
    };

    root.querySelectorAll("[data-command-target]").forEach((button) => {
      button.addEventListener("click", () => {
        handleCommandTarget(
          String(button.dataset.commandTarget || "overview"),
          Number(button.dataset.commandAudit || 0) || null
        );
      });
    });
    root.querySelectorAll("[data-audit-close]").forEach((button) => {
      button.addEventListener("click", () => {
        selectedCommandAuditId = null;
        render();
      });
    });

    document.getElementById("dueDiligenceFab")?.addEventListener("click", () => {
      dueDrawerOpen = true;
      render();
    });
    document.getElementById("dueDiligenceClose")?.addEventListener("click", () => {
      dueDrawerOpen = false;
      render();
    });
    document.getElementById("dueDiligenceBackdrop")?.addEventListener("click", () => {
      dueDrawerOpen = false;
      render();
    });
    document.getElementById("dueDiligenceForm")?.addEventListener("submit", async (event) => {
      event.preventDefault();
      if (!canEditDueDiligence) return;
      const formData = new FormData(event.currentTarget);
      const nextState = {};
      dueItems.forEach((item) => {
        nextState[item.key] = formData.get(item.key) === "on";
      });
      await api.saveDueDiligence(property.id, nextState);
      dueDrawerOpen = false;
      await refreshCommandCenter();
      render();
    });

    document.getElementById("propertyChatForm")?.addEventListener("submit", async (event) => {
      event.preventDefault();
      const input = document.getElementById("propertyChatInput");
      const text = input?.value?.trim() || "";
      if (!text) return;
      await (conversationThread
        ? api.sendMessage({ threadId: conversationThread.id, text })
        : api.sendMessage({ propertyId: property.id, text }));
      if (input) input.value = "";
      await refreshCommandCenter();
      render();
    });
    bindVisitInteractions(root, {
      property: enriched,
      visit: visitLog,
      propertyId: property.id,
      counterMode: visitCounterMode,
      setCounterMode: (nextMode) => {
        visitCounterMode = Boolean(nextMode);
        render();
      },
      onUpdated: async () => {
        visitCounterMode = false;
        await refreshCommandCenter();
        render();
      },
    });
    document.getElementById("propertyDocumentRequestForm")?.addEventListener("submit", async (event) => {
      event.preventDefault();
      const documentName = document.getElementById("documentRequestName")?.value?.trim() || "";
      const note = document.getElementById("documentRequestNote")?.value?.trim() || "";
      if (!documentName) return;
      await api.createDocumentRequest({
        propertyId: property.id,
        documentName,
        note,
      });
      await refreshCommandCenter();
      render();
    });
    root.querySelectorAll("[data-request-manage]").forEach((formElement) => {
      formElement.addEventListener("submit", async (event) => {
        event.preventDefault();
        const requestId = Number(formElement.dataset.requestManage || 0);
        if (!requestId) return;
        const formData = new FormData(formElement);
        await api.updateDocumentRequest({
          requestId,
          status: formData.get("status"),
          responseNote: formData.get("responseNote"),
        });
        await refreshCommandCenter();
        render();
      });
    });

    if (document.getElementById("propertyDetailMap")) {
      mountPropertyMap({
        containerId: "propertyDetailMap",
        properties: mapPeers,
        activeId: enriched.id,
        onSelect: (nextId) => {
          if (Number(nextId) === Number(enriched.id)) return;
          window.location.href = propertyHref(nextId);
        },
      });
    } else {
      destroyMap("propertyDetailMap");
    }
    animateLensMetricBars(root);
  };

  syncPropertyCollection(property);
  render();
  window.clearInterval(root._propertyCommandTimer);
  root._propertyCommandTimer = window.setInterval(async () => {
    if (!document.body.contains(root) || root.contains(document.activeElement)) {
      return;
    }
    await refreshCommandCenter();
    render();
  }, 60000);
}

async function initAdminProperties() {
  const root = document.getElementById("adminPropertiesRoot");
  if (!root) return;

  const modal = document.getElementById("propertyCrudModal");
  const deleteModal = document.getElementById("propertyDeleteModal");
  const form = document.getElementById("propertyCrudForm");
  const landAreaInput = document.getElementById("crudLandArea");
  const landAreaUnitInput = document.getElementById("crudLandAreaUnit");
  const landAreaHint = document.getElementById("crudLandAreaHint");
  const deleteLabel = document.getElementById("deletePropertyLabel");
  const confirmDeleteButton = document.getElementById("confirmDeleteProperty");
  const addButton = document.getElementById("adminAddProperty");
  let properties = [];
  let search = "";
  let deleteId = null;
  let currentAreaUnit = "ha";

  const SQM_PER_HECTARE = 10000;
  const normalizeAreaUnit = (value) => {
    const normalized = String(value || "").trim().toLowerCase();
    return normalized === "sqm" ? "sqm" : "ha";
  };
  const parseAreaValue = (value) => {
    if (value === null || value === undefined || value === "") return null;
    const numeric = Number(String(value).replace(/,/g, "").trim());
    return Number.isFinite(numeric) ? numeric : null;
  };
  const formatAreaValue = (value, unit = "ha") => {
    const numeric = parseAreaValue(value);
    if (numeric === null) return "";
    const normalizedUnit = normalizeAreaUnit(unit);
    const decimals = normalizedUnit === "sqm" ? 0 : (numeric < 1 ? 4 : 2);
    return numeric.toFixed(decimals).replace(/\.?0+$/, "");
  };
  const areaValueInHectares = (value, unit = "ha") => {
    const numeric = parseAreaValue(value);
    if (numeric === null) return null;
    return normalizeAreaUnit(unit) === "sqm" ? numeric / SQM_PER_HECTARE : numeric;
  };
  const syncAreaHint = () => {
    if (!landAreaHint) return;
    const rawValue = parseAreaValue(landAreaInput?.value);
    const unit = normalizeAreaUnit(landAreaUnitInput?.value);
    if (rawValue === null || rawValue <= 0) {
      landAreaHint.textContent = unit === "sqm"
        ? "Use square meters from your source document. The system converts it to hectares automatically on save."
        : "Use hectares directly. The saved value stays aligned with the existing ranking and pricing logic.";
      return;
    }

    if (unit === "sqm") {
      const hectares = rawValue / SQM_PER_HECTARE;
      landAreaHint.textContent = `${Math.round(rawValue).toLocaleString()} sqm will be saved as ${formatAreaValue(hectares, "ha")} ha.`;
      return;
    }

    const sqm = Math.round(rawValue * SQM_PER_HECTARE);
    landAreaHint.textContent = `${formatAreaValue(rawValue, "ha")} ha is ${sqm.toLocaleString()} sqm.`;
  };
  const setAreaUnit = (nextUnit, { convertValue = true } = {}) => {
    const normalizedUnit = normalizeAreaUnit(nextUnit);
    const existingValue = parseAreaValue(landAreaInput?.value);
    if (convertValue && existingValue !== null && existingValue > 0 && landAreaInput) {
      const hectares = currentAreaUnit === "sqm" ? existingValue / SQM_PER_HECTARE : existingValue;
      const nextValue = normalizedUnit === "sqm" ? hectares * SQM_PER_HECTARE : hectares;
      landAreaInput.value = formatAreaValue(nextValue, normalizedUnit);
    }

    currentAreaUnit = normalizedUnit;
    if (landAreaUnitInput) {
      landAreaUnitInput.value = normalizedUnit;
    }
    if (landAreaInput) {
      landAreaInput.step = normalizedUnit === "sqm" ? "1" : "0.0001";
      landAreaInput.min = normalizedUnit === "sqm" ? "1" : "0.0001";
      landAreaInput.placeholder = normalizedUnit === "sqm" ? "e.g. 3500" : "e.g. 0.35";
    }
    syncAreaHint();
  };

  const openModal = (target) => {
    if (target) {
      target.hidden = false;
      form?.scrollTo({ top: 0, behavior: "auto" });
    }
  };

  const closeModal = (target) => {
    if (target) target.hidden = true;
  };

  const fillCrudForm = (property = null) => {
    const documentStatuses = property?.documentStatuses || {};
    document.getElementById("crudModalTitle").textContent = property ? "Edit Property" : "Add Property";
    document.getElementById("crudPropertyId").value = property?.id || "";
    document.getElementById("crudPropertyName").value = property?.name || "";
    document.getElementById("crudCity").value = property?.city || "San Fernando, La Union";
    document.getElementById("crudBarangay").value = property?.barangay || "";
    document.getElementById("crudPropertyType").value = property?.type || "commercial";
    document.getElementById("crudCorridor").value = property?.corridor || "highway";
    document.getElementById("crudStatus").value = property?.status || "Available";
    document.getElementById("crudApprovalState").value = property?.approvalState || "approved";
    document.getElementById("crudSellerIdentityStatus").value = property?.sellerIdentityStatus || "unverified";
    document.getElementById("crudPrice").value = property?.price || "";
    const areaHectares = parseAreaValue(property?.area);
    const preferredAreaUnit = areaHectares !== null && areaHectares > 0 && areaHectares < 1 ? "sqm" : "ha";
    document.getElementById("crudLandArea").value = areaHectares === null
      ? ""
      : formatAreaValue(preferredAreaUnit === "sqm" ? areaHectares * SQM_PER_HECTARE : areaHectares, preferredAreaUnit);
    setAreaUnit(preferredAreaUnit, { convertValue: false });
    document.getElementById("crudScore").value = property?.marketScore || property?.score || 82;
    document.getElementById("crudAccess").value = property?.roadAccess || 85;
    document.getElementById("crudDocumentsReviewed").value = property?.documentsReviewedAt ? "1" : "0";
    document.getElementById("crudSiteVerified").value = property?.siteVerifiedAt ? "1" : "0";
    document.getElementById("crudLastConfirmedAvailableAt").value = toDatetimeLocalValue(property?.lastConfirmedAvailableAt || "");
    document.getElementById("crudDescription").value = property?.description || "";
    document.getElementById("crudImagePath").value = property?.imageUrl || "assets/images/Property10.png";
    document.getElementById("crudTags").value = (property?.tags || []).join(", ");
    document.getElementById("crudFacilities").value = (property?.facilities || []).join(", ");
    document.getElementById("crudDocTitleCopy").value = documentStatuses.title_copy || "missing";
    document.getElementById("crudDocTaxDeclaration").value = documentStatuses.tax_declaration || "missing";
    document.getElementById("crudDocSurveyPlan").value = documentStatuses.survey_plan || "missing";
    document.getElementById("crudDocZoningClearance").value = documentStatuses.zoning_clearance || "missing";
    document.getElementById("crudDocSitePhotos").value = documentStatuses.site_photos || "missing";
    document.getElementById("crudDocHazardReport").value = documentStatuses.hazard_report || "missing";
    document.getElementById("crudImage").value = "";
    form?.scrollTo({ top: 0, behavior: "auto" });
  };

  const render = () => {
    const visible = properties.filter((property) => {
      const haystack = `${property.name} ${property.city} ${property.barangay || ""}`.toLowerCase();
      return !search || haystack.includes(search.toLowerCase());
    });
    const earthPreviewProperty = visible[0] || null;
    const approvedCount = properties.filter((property) => String(property.approvalState || "").toLowerCase() === "approved").length;
    const pendingCount = properties.filter((property) => String(property.approvalState || "").toLowerCase() === "pending_review").length;
    const nonVisibleCount = properties.filter((property) => ["draft", "rejected", "archived"].includes(String(property.approvalState || "").toLowerCase())).length;

    root.innerHTML = `
      <div class="inventory-layout">
        <aside class="stack">
          <article class="panel-card">
            <div class="panel-kicker">Inventory status</div>
            <h3>Portfolio control</h3>
            <div class="mini-list">
              <div class="mini-row"><span>${icon("ranking")}Total listings</span><strong>${properties.length}</strong></div>
              <div class="mini-row"><span>${icon("shield")}Approved</span><strong>${approvedCount}</strong></div>
              <div class="mini-row"><span>${icon("clock")}Pending review</span><strong>${pendingCount}</strong></div>
              <div class="mini-row"><span>${icon("file")}Hidden / blocked</span><strong>${nonVisibleCount}</strong></div>
            </div>
          </article>

          <article class="panel-card">
            <div class="panel-kicker">Role coverage</div>
            <h3>Platform users</h3>
            <div class="mini-list">
              <div class="mini-row"><span>${icon("user")}Admin</span><strong>1 active role</strong></div>
              <div class="mini-row"><span>${icon("user")}Seller</span><strong>Seller studio</strong></div>
              <div class="mini-row"><span>${icon("user")}Investor / Resident</span><strong>Demand tracking enabled</strong></div>
            </div>
          </article>
        </aside>

        <section class="stack">
          <article class="panel-card">
            <div class="panel-kicker">Search inventory</div>
            <h3>Live listing management</h3>
            <div class="property-actions" style="margin-top:18px;">
              <input class="input-shell" id="adminSearch" value="${escapeHtml(search)}" placeholder="Search by name, city, barangay" style="flex:1 1 280px;">
              <button type="button" class="btn-shell btn-shell-primary" id="adminAddInline">Add Property</button>
            </div>
            ${visible.length ? googleEarthActionsMarkup({
              property: earthPreviewProperty,
              properties: visible,
              scope: "admin-visible",
              showView: Boolean(earthPreviewProperty),
              note: visible.length === 1
                ? "Open this filtered listing directly in Google Earth or export it as KML or KMZ for validation."
                : "Open the first filtered listing in Google Earth or export the current filtered inventory for LGU review, site validation, and thesis-ready walkthroughs.",
            }) : ""}
          </article>

          <div class="listing-stack">
            ${visible.length ? visible.map((property) => `
              <article class="listing-row admin-listing-row">
                <div class="listing-main admin-listing-main">
                  <div class="admin-listing-head">
                    <div class="admin-listing-head-copy">
                      <div class="property-title">${escapeHtml(property.name)}</div>
                      <div class="property-subline">${escapeHtml(property.city || "San Fernando, La Union")} | ${escapeHtml(property.barangay || "Unassigned")}</div>
                    </div>
                    <div class="admin-listing-statuses">
                      ${statusPill(property.status)}
                      ${approvalStatePill(property.approvalState)}
                    </div>
                  </div>
                  <p>${escapeHtml(truncate(property.description || propertyStory(property), 150))}</p>
                  <div class="property-stat-row">
                    <span>${icon("money")}${escapeHtml(moneyShort(property.price))}</span>
                    <span>${icon("area")}${escapeHtml(property.area)} ha</span>
                    <span>${icon("ranking")}${Number(property.marketScore || property.score || 0)} market score</span>
                    <span>${icon("file")}${Math.round(Number(property.documentCompletenessPct || 0))}% docs</span>
                  </div>
                  <div class="listing-meta-row admin-listing-meta-row">
                    ${verificationPill(property.listingVerificationStatus)}
                    ${metaChip(`${Number(property.openDocumentRequestCount || 0)} open requests`)}
                    ${property.lastConfirmedAvailableAt ? metaChip(`Confirmed ${formatDate(property.lastConfirmedAvailableAt)}`) : metaChip("Awaiting confirmation")}
                  </div>
                  <div class="trust-badge-row">${trustBadgeRow(property.trustBadges || [], { compact: true })}</div>
                </div>
                <div class="listing-actions admin-listing-actions">
                  <a href="${propertyHref(property.id)}" class="btn-shell btn-shell-secondary">View</a>
                  <a href="${escapeHtml(googleEarthViewHref(property))}" target="_blank" rel="noreferrer" class="btn-shell btn-shell-secondary">${icon("earth")}Google Earth</a>
                  <button type="button" class="btn-shell btn-shell-secondary" data-admin-confirm-availability="${property.id}">${icon("clock")}Confirm Available</button>
                  ${String(property.approvalState || "").toLowerCase() !== "approved"
                    ? `<button type="button" class="btn-shell btn-shell-primary" data-admin-approve="${property.id}">${icon("shield")}Approve Submission</button>`
                    : ""}
                  <button type="button" class="btn-shell ${String(property.approvalState || "").toLowerCase() !== "approved" ? "btn-shell-secondary" : "btn-shell-primary"}" data-admin-edit="${property.id}">Edit</button>
                  <button type="button" class="btn-shell btn-shell-danger" data-admin-delete="${property.id}">Delete</button>
                </div>
              </article>
            `).join("") : emptyState("No listings match this search", "Try a broader term or reset the listing search.")}
          </div>
        </section>
      </div>
    `;

    document.getElementById("adminSearch")?.addEventListener("input", (event) => {
      search = event.target.value;
      render();
    });

    document.getElementById("adminAddInline")?.addEventListener("click", () => {
      fillCrudForm();
      openModal(modal);
    });

    root.querySelectorAll("[data-admin-edit]").forEach((button) => {
      button.addEventListener("click", () => {
        const property = properties.find((entry) => entry.id === Number(button.dataset.adminEdit));
        fillCrudForm(property);
        openModal(modal);
      });
    });
    root.querySelectorAll("[data-admin-approve]").forEach((button) => {
      button.addEventListener("click", async () => {
        const propertyId = Number(button.dataset.adminApprove || 0);
        if (!propertyId) return;

        try {
          await api.updateProperty(propertyId, {
            approval_state: "approved",
          });
          await reload();
        } catch (error) {
          window.alert(error.message || "Unable to approve this listing right now.");
        }
      });
    });

    root.querySelectorAll("[data-admin-delete]").forEach((button) => {
      button.addEventListener("click", () => {
        const property = properties.find((entry) => entry.id === Number(button.dataset.adminDelete));
        if (!property) return;
        deleteId = property.id;
        if (deleteLabel) {
          deleteLabel.textContent = `Delete ${property.name} from the live property inventory.`;
        }
        openModal(deleteModal);
      });
    });
    root.querySelectorAll("[data-admin-confirm-availability]").forEach((button) => {
      button.addEventListener("click", async () => {
        const propertyId = Number(button.dataset.adminConfirmAvailability || 0);
        if (!propertyId) return;
        await api.updateProperty(propertyId, {
          lastConfirmedAvailableAt: new Date().toISOString(),
        });
        await reload();
      });
    });
  };

  const reload = async () => {
    const response = await api.properties();
    properties = response.properties || [];
    render();
  };

  addButton?.addEventListener("click", () => {
    fillCrudForm();
    openModal(modal);
  });

  landAreaInput?.addEventListener("input", () => {
    syncAreaHint();
  });
  landAreaUnitInput?.addEventListener("change", (event) => {
    setAreaUnit(event.target.value);
  });
  setAreaUnit("ha", { convertValue: false });

  document.addEventListener("click", (event) => {
    const closeTarget = event.target.closest("[data-modal-close]");
    if (!closeTarget) return;
    closeModal(document.getElementById(closeTarget.dataset.modalClose));
  });

  form?.addEventListener("submit", async (event) => {
    event.preventDefault();
    const propertyId = Number(document.getElementById("crudPropertyId").value || 0);
    const areaUnit = normalizeAreaUnit(landAreaUnitInput?.value);
    const areaValue = parseAreaValue(landAreaInput?.value);
    const areaHectares = areaValueInHectares(areaValue, areaUnit);
    if (areaHectares === null || areaHectares <= 0) {
      landAreaInput?.focus();
      syncAreaHint();
      return;
    }
    const payload = new FormData();
    payload.append("property_name", document.getElementById("crudPropertyName").value);
    payload.append("city", document.getElementById("crudCity").value);
    payload.append("barangay", document.getElementById("crudBarangay").value);
    payload.append("property_type", document.getElementById("crudPropertyType").value);
    payload.append("corridor", document.getElementById("crudCorridor").value);
    payload.append("status", document.getElementById("crudStatus").value);
    payload.append("approval_state", document.getElementById("crudApprovalState").value);
    payload.append("seller_identity_verification_status", document.getElementById("crudSellerIdentityStatus").value);
    payload.append("price", document.getElementById("crudPrice").value);
    payload.append("land_area", String(areaValue));
    payload.append("land_area_unit", areaUnit);
    payload.append("score", document.getElementById("crudScore").value);
    payload.append("road_access", document.getElementById("crudAccess").value);
    payload.append("documents_reviewed", document.getElementById("crudDocumentsReviewed").value);
    payload.append("site_verified", document.getElementById("crudSiteVerified").value);
    payload.append("description", document.getElementById("crudDescription").value);
    payload.append("image_path", document.getElementById("crudImagePath").value);
    payload.append("tags", document.getElementById("crudTags").value);
    payload.append("facilities", document.getElementById("crudFacilities").value);
    const lastConfirmedAvailableAt = document.getElementById("crudLastConfirmedAvailableAt").value;
    if (lastConfirmedAvailableAt) payload.append("last_confirmed_available_at", lastConfirmedAvailableAt);
    payload.append("document_statuses", JSON.stringify({
      title_copy: document.getElementById("crudDocTitleCopy").value,
      tax_declaration: document.getElementById("crudDocTaxDeclaration").value,
      survey_plan: document.getElementById("crudDocSurveyPlan").value,
      zoning_clearance: document.getElementById("crudDocZoningClearance").value,
      site_photos: document.getElementById("crudDocSitePhotos").value,
      hazard_report: document.getElementById("crudDocHazardReport").value,
    }));
    const imageFile = document.getElementById("crudImage").files?.[0];
    if (imageFile) payload.append("image_file", imageFile);

    try {
      if (propertyId > 0) {
        await api.updateProperty(propertyId, payload);
      } else {
        await api.createProperty(payload);
      }

      closeModal(modal);
      await reload();
    } catch (error) {
      window.alert(error.message || "Unable to save this property right now.");
    }
  });

  confirmDeleteButton?.addEventListener("click", async () => {
    if (!deleteId) return;
    await api.deleteProperty(deleteId);
    deleteId = null;
    closeModal(deleteModal);
    await reload();
  });

  await reload();

  const urlParams = new URLSearchParams(window.location.search);
  const editId = Number(urlParams.get("edit") || 0);
  if (editId) {
    const targetProperty = properties.find((entry) => entry.id === editId);
    if (targetProperty) {
      fillCrudForm(targetProperty);
      openModal(modal);
    }
  }
}

async function initScenarioSimulator() {
  const root = document.getElementById("scenarioSimulatorRoot");
  if (!root) return;
  const bootstrap = await api.bootstrap();
  const properties = bootstrap.properties || [];
  let propertyId = Number(properties[0]?.id || 0);
  let investmentType = "commercial";
  let roadLift = 0;
  let utilityLift = 0;

  const render = () => {
    const property = properties.find((item) => Number(item.id) === propertyId) || properties[0];
    if (!property) {
      root.innerHTML = emptyState("No candidate sites", "Add an approved candidate site before running a scenario.");
      return;
    }
    const compliance = evaluateClup(property, investmentType);
    const baseScore = Number(property.marketScore || 0);
    const simulatedIai = Math.min(100, Math.round(baseScore + (roadLift * .18) + (utilityLift * .14)));
    const decisionAllowed = compliance.status !== "FAIL";
    root.innerHTML = `
      <div class="clup-simulator-layout">
        <aside class="stack">
          <article class="panel-card">
            <div class="panel-kicker">Scenario Inputs</div><h3>Candidate site + proposed investment</h3>
            <div class="filter-grid">
              <label class="form-shell"><span>Candidate site</span><select class="input-shell" id="scenarioClupSite">${properties.map((item) => `<option value="${item.id}" ${Number(item.id) === propertyId ? "selected" : ""}>${escapeHtml(item.name)}</option>`).join("")}</select></label>
              <label class="form-shell"><span>Proposed investment type</span><select class="input-shell" id="scenarioClupType">
                ${[["commercial","Commercial / Retail"],["logistics","Logistics / Warehousing"],["hotel","Tourism / Hospitality"],["bpo","Office / BPO"],["manufacturing","Light Manufacturing"],["mixed_use","Mixed-use Development"]].map(([key,label]) => `<option value="${key}" ${investmentType === key ? "selected" : ""}>${label}</option>`).join("")}
              </select></label>
              <label class="form-shell"><span>Road improvement (+ points)</span><input class="input-shell" id="scenarioRoadLift" type="range" min="0" max="20" value="${roadLift}"><strong>${roadLift}</strong></label>
              <label class="form-shell"><span>Utility expansion (+ points)</span><input class="input-shell" id="scenarioUtilityLift" type="range" min="0" max="20" value="${utilityLift}"><strong>${utilityLift}</strong></label>
            </div>
          </article>
          <article class="panel-card"><div class="panel-kicker">Policy Rule</div><h3>Attractiveness cannot override incompatibility.</h3><p>Infrastructure interventions can improve IAI and readiness, but a CLUP <b>FAIL</b> remains blocked until the proposed use or lawful zoning basis changes.</p></article>
        </aside>
        <section class="stack">
          <article class="decision-card clup-scenario-result">
            <div class="panel-kicker">Scenario Result</div><h2>${decisionAllowed ? "Eligible for prioritization" : "Blocked by CLUP gate"}</h2>
            <p>${decisionAllowed ? `${property.name} may proceed to the next LGU review stage for the proposed use.` : `${property.name} should not be recommended for this use regardless of its attractiveness score.`}</p>
            <div class="decision-stats">${clupStatusPill(compliance)}<span class="tag">Simulated IAI ${simulatedIai}</span><span class="tag">CLUP ${compliance.suitabilityScore}</span></div>
          </article>
          ${clupDecisionCardMarkup(compliance)}
          <article class="panel-card"><div class="panel-kicker">Before / After</div><h3>Intervention effect</h3><div class="clup-fact-grid"><div><span>Base IAI</span><strong>${baseScore}</strong></div><div><span>Simulated IAI</span><strong>${simulatedIai}</strong></div><div><span>CLUP Gate</span><strong>${compliance.status}</strong></div><div><span>Priority Decision</span><strong>${decisionAllowed ? "May proceed" : "Do not prioritize"}</strong></div></div></article>
        </section>
      </div>`;
    root.querySelector("#scenarioClupSite")?.addEventListener("change", (event) => { propertyId = Number(event.target.value); render(); });
    root.querySelector("#scenarioClupType")?.addEventListener("change", (event) => { investmentType = event.target.value; render(); });
    root.querySelector("#scenarioRoadLift")?.addEventListener("input", (event) => { roadLift = Number(event.target.value); render(); });
    root.querySelector("#scenarioUtilityLift")?.addEventListener("input", (event) => { utilityLift = Number(event.target.value); render(); });
  };
  render();
}

async function initDecisionReports() {
  const root = document.getElementById("decisionReportsRoot");
  if (!root) return;
  const bootstrap = await api.bootstrap();
  const properties = bootstrap.properties || [];
  const investmentLensKey = getActiveInvestmentLensKey();
  const enriched = enrichProperties(properties, properties, {}, null, investmentLensKey);
  const counts = { PASS: 0, CONDITIONAL: 0, FAIL: 0, UNVERIFIED: 0 };
  enriched.forEach((p) => {
    const s = p.clupCompliance.status;
    if (counts[s] !== undefined) counts[s] += 1;
    else counts.UNVERIFIED += 1;
  });

  /* ── helpers ── */
  const clupPill = (status) => {
    if (status === "PASS")        return `<span style="display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:999px;background:#ecfdf5;border:1px solid #bbf7d0;font-size:10px;font-weight:800;color:#15803d;"><span style="width:6px;height:6px;border-radius:50%;background:#22c55e;display:inline-block;"></span>PASS</span>`;
    if (status === "CONDITIONAL")  return `<span style="display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:999px;background:#fffbeb;border:1px solid #fde68a;font-size:10px;font-weight:800;color:#92400e;"><span style="width:6px;height:6px;border-radius:50%;background:#f59e0b;display:inline-block;"></span>CONDITIONAL</span>`;
    if (status === "FAIL")         return `<span style="display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:999px;background:#fff1f2;border:1px solid #fecdd3;font-size:10px;font-weight:800;color:#9f1239;"><span style="width:6px;height:6px;border-radius:50%;background:#f43f5e;display:inline-block;"></span>FAIL</span>`;
    return `<span style="display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:999px;background:#f8fafc;border:1px solid #e2e8f0;font-size:10px;font-weight:800;color:#475569;"><span style="width:6px;height:6px;border-radius:50%;background:#94a3b8;display:inline-block;"></span>Unverified</span>`;
  };

  const evidencePill = (level, src) => {
    const lower = (level || "").toLowerCase();
    let badge = "";
    if (lower === "complete" || lower === "confirmed")
      badge = `<span style="display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:999px;background:#ecfdf5;border:1px solid #bbf7d0;font-size:10px;font-weight:800;color:#15803d;">&#10003; Complete</span>`;
    else if (lower === "partial" || lower === "submitted")
      badge = `<span style="display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:999px;background:#f5f3ff;border:1px solid #ddd6fe;font-size:10px;font-weight:800;color:#5b21b6;">&#9682; Partial</span>`;
    else
      badge = `<span style="display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:999px;background:#fffbeb;border:1px solid #fde68a;font-size:10px;font-weight:800;color:#92400e;">&#9888; Unverified</span>`;
    return `${badge}<div style="font-size:10px;color:#94a3b8;font-weight:500;margin-top:5px;">${escapeHtml(src || "Corridor screen")}</div>`;
  };

  const suitabilityCell = (score) => {
    if (score === null || score === undefined || String(score) === "null")
      return `<div style="font-size:13px;font-weight:700;color:#cbd5e1;">&#8212;&#8212; <span style="color:#e2e8f0;">/100</span></div><div style="font-size:10px;color:#cbd5e1;font-weight:500;margin-top:3px;">Pending data</div>`;
    const n = Number(score);
    const col = n >= 75 ? "#15803d" : n >= 50 ? "#92400e" : "#9f1239";
    const bg  = n >= 75 ? "#22c55e" : n >= 50 ? "#f59e0b" : "#f43f5e";
    return `<div style="font-size:13px;font-weight:800;color:${col};">${n}<span style="color:#cbd5e1;font-weight:400;">/100</span></div><div style="height:5px;width:52px;border-radius:999px;background:#f1f5f9;margin-top:5px;overflow:hidden;"><div style="height:100%;width:${n}%;border-radius:999px;background:${bg};"></div></div>`;
  };

  const actionChecklist = (compliance) => {
    const raw = compliance.recommendedLguAction || "";
    const steps = [
      { label: "Import Zoning Map dataset",           done: compliance.status !== "UNVERIFIED" },
      { label: "Resolve against zoning polygon",      done: compliance.status === "PASS" || compliance.status === "CONDITIONAL" || compliance.status === "FAIL" },
      { label: "Attach parcel-to-zone evidence",      done: (compliance.evidenceLevel || "").toLowerCase() !== "inferred" && compliance.status !== "UNVERIFIED" },
      { label: "Admin review & sign-off",             done: compliance.status === "PASS" },
    ];
    return steps.map((s) => {
      if (s.done)
        return `<div style="display:flex;align-items:flex-start;gap:6px;margin-bottom:4px;"><span style="width:15px;height:15px;border-radius:4px;background:#dcfce7;border:1px solid #bbf7d0;flex-shrink:0;display:flex;align-items:center;justify-content:center;margin-top:1px;"><svg width="9" height="9" viewBox="0 0 12 12" fill="none"><path d="M2 6l3 3 5-5" stroke="#16a34a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></span><span style="font-size:11px;color:#94a3b8;text-decoration:line-through;line-height:1.4;">${escapeHtml(s.label)}</span></div>`;
      return `<div style="display:flex;align-items:flex-start;gap:6px;margin-bottom:4px;"><span style="width:15px;height:15px;border-radius:4px;background:#fff;border:2px solid #e2e8f0;flex-shrink:0;margin-top:1px;"></span><span style="font-size:11px;color:#374151;font-weight:600;line-height:1.4;">${escapeHtml(s.label)}</span></div>`;
    }).join("");
  };

  const actionBtn = (property) => {
    const c = property.clupCompliance;
    const btnStyle = "display:inline-flex;align-items:center;gap:6px;padding:8px 14px;border-radius:12px;font-size:11px;font-weight:700;cursor:pointer;transition:all 0.2s cubic-bezier(0.4, 0, 0.2, 1);outline:none;border:none;";
    
    if (c.status === "PASS") {
      return `<button onclick="window.location.href='admin-properties.php?edit=${property.id}'" 
        style="${btnStyle}background:#ecfdf5;border:1px solid #bbf7d0;color:#15803d;"
        onmouseover="this.style.background='#d1fae5';this.style.transform='translateY(-1px)';"
        onmouseout="this.style.background='#ecfdf5';this.style.transform='none';">
        &#10003; Cleared
      </button>`;
    }
    
    const allDone = ["PASS","CONDITIONAL","FAIL"].includes(c.status);
    if (allDone && c.status !== "PASS") {
      return `<button onclick="window.location.href='admin-properties.php?edit=${property.id}'" 
        style="${btnStyle}background:#f5f3ff;border:1px solid #ddd6fe;color:#5b21b6;"
        onmouseover="this.style.background='#ede9fe';this.style.transform='translateY(-1px)';"
        onmouseout="this.style.background='#f5f3ff';this.style.transform='none';">
        &#128196; Upload Evidence
      </button>`;
    }
    
    return `<button onclick="window.location.href='admin-properties.php?edit=${property.id}'" 
      style="${btnStyle}background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;box-shadow:0 3px 10px rgba(245,158,11,0.3);"
      onmouseover="this.style.transform='translateY(-1px)';this.style.boxShadow='0 5px 15px rgba(245,158,11,0.45)';"
      onmouseout="this.style.transform='none';this.style.boxShadow='0 3px 10px rgba(245,158,11,0.3)';">
      &#9889; Resolve Now
    </button>`;
  };

  const rows = enriched.map((property, index) => {
    const c = property.clupCompliance;
    return `<tr style="border-bottom:1px solid #f1f5f9;transition:background 140ms ease;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
      <td style="padding:14px 16px;vertical-align:top;"><div style="width:28px;height:28px;border-radius:8px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;color:#475569;">${index + 1}</div></td>
      <td style="padding:14px 12px;vertical-align:top;min-width:155px;"><div style="font-size:13px;font-weight:700;color:#0f172a;line-height:1.3;margin-bottom:3px;">${escapeHtml(property.name)}</div><div style="font-size:11px;color:#94a3b8;font-weight:500;">${escapeHtml(property.barangay || "San Fernando")} &middot; ${escapeHtml(corridorLabel(property.corridor))}</div></td>
      <td style="padding:14px 12px;vertical-align:top;min-width:115px;"><div style="font-size:12px;font-weight:600;color:#374151;">${escapeHtml(c.proposedInvestmentLabel)}</div></td>
      <td style="padding:14px 12px;vertical-align:top;text-align:center;"><span style="display:inline-block;padding:4px 9px;border-radius:8px;background:${Number(property.lensScore||0)>=90?"#ecfdf5":"#f8fafc"};font-size:12px;font-weight:800;color:${Number(property.lensScore||0)>=90?"#15803d":"#475569"};">${Math.round(Number(property.lensScore || 0))}</span></td>
      <td style="padding:14px 12px;vertical-align:top;">${clupPill(c.status)}</td>
      <td style="padding:14px 12px;vertical-align:top;min-width:95px;">${suitabilityCell(c.suitabilityScore)}</td>
      <td style="padding:14px 12px;vertical-align:top;min-width:125px;">${evidencePill(c.evidenceLevel, c.sourceReference)}</td>
      <td style="padding:14px 12px;vertical-align:top;min-width:185px;">${actionChecklist(c)}</td>
      <td style="padding:14px 16px;vertical-align:top;text-align:right;">${actionBtn(property)}</td>
    </tr>`;
  }).join("");

  const unverifiedCount = enriched.filter((p) => !["PASS","CONDITIONAL","FAIL"].includes(p.clupCompliance.status)).length;

  root.innerHTML = `
    <div class="stack clup-report-shell" style="gap:20px;">

      <!-- Stat Cards -->
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;">
        <div style="background:#fff;border-radius:18px;padding:20px;border:1px solid #d1fae5;box-shadow:0 2px 8px rgba(0,0,0,.06);overflow:hidden;position:relative;">
          <div style="position:absolute;top:0;left:0;width:4px;height:100%;background:#22c55e;border-radius:2px;"></div>
          <div style="padding-left:10px;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
              <div style="width:30px;height:30px;border-radius:9px;background:#dcfce7;border:1px solid #bbf7d0;display:flex;align-items:center;justify-content:center;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg></div>
              <span style="font-size:9px;font-weight:800;letter-spacing:.15em;text-transform:uppercase;color:#16a34a;background:#dcfce7;padding:2px 8px;border-radius:999px;">Compliant</span>
            </div>
            <div style="font-size:38px;font-weight:900;color:#15803d;line-height:1;margin-bottom:3px;">${counts.PASS}</div>
            <div style="font-size:11px;font-weight:700;color:#64748b;">CLUP PASS</div>
            <div style="font-size:10px;color:#94a3b8;font-weight:500;margin-top:3px;">Cleared for investment recommendation</div>
          </div>
        </div>
        <div style="background:#fff;border-radius:18px;padding:20px;border:1px solid #fde68a;box-shadow:0 2px 8px rgba(0,0,0,.06);overflow:hidden;position:relative;">
          <div style="position:absolute;top:0;left:0;width:4px;height:100%;background:#f59e0b;border-radius:2px;"></div>
          <div style="padding-left:10px;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
              <div style="width:30px;height:30px;border-radius:9px;background:#fef3c7;border:1px solid #fde68a;display:flex;align-items:center;justify-content:center;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg></div>
              <span style="font-size:9px;font-weight:800;letter-spacing:.15em;text-transform:uppercase;color:#d97706;background:#fef3c7;padding:2px 8px;border-radius:999px;">Conditional</span>
            </div>
            <div style="font-size:38px;font-weight:900;color:#92400e;line-height:1;margin-bottom:3px;">${counts.CONDITIONAL}</div>
            <div style="font-size:11px;font-weight:700;color:#64748b;">CONDITIONAL</div>
            <div style="font-size:10px;color:#94a3b8;font-weight:500;margin-top:3px;">Permitted with LGU conditions applied</div>
          </div>
        </div>
        <div style="background:#fff;border-radius:18px;padding:20px;border:1px solid #fecdd3;box-shadow:0 2px 8px rgba(0,0,0,.06);overflow:hidden;position:relative;">
          <div style="position:absolute;top:0;left:0;width:4px;height:100%;background:#f43f5e;border-radius:2px;"></div>
          <div style="padding-left:10px;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
              <div style="width:30px;height:30px;border-radius:9px;background:#fff1f2;border:1px solid #fecdd3;display:flex;align-items:center;justify-content:center;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#e11d48" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></div>
              <span style="font-size:9px;font-weight:800;letter-spacing:.15em;text-transform:uppercase;color:#e11d48;background:#fff1f2;padding:2px 8px;border-radius:999px;">Disqualified</span>
            </div>
            <div style="font-size:38px;font-weight:900;color:#9f1239;line-height:1;margin-bottom:3px;">${counts.FAIL}</div>
            <div style="font-size:11px;font-weight:700;color:#64748b;">CLUP FAIL</div>
            <div style="font-size:10px;color:#94a3b8;font-weight:500;margin-top:3px;">Incompatible with land-use classification</div>
          </div>
        </div>
      </div>

      ${unverifiedCount > 0 ? `<div style="display:flex;align-items:center;gap:10px;padding:12px 18px;border-radius:14px;background:#fffbeb;border:1px solid #fde68a;">
        <div style="width:22px;height:22px;border-radius:50%;background:#fef3c7;border:1px solid #fde68a;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4m0 4h.01"/></svg></div>
        <p style="font-size:12px;font-weight:600;color:#92400e;margin:0;"><strong>${unverifiedCount}</strong> candidate site${unverifiedCount !== 1 ? "s" : ""} pending CLUP verification. Complete the evidence queue to unlock compliance scoring.</p>
      </div>` : ""}

      <!-- Priority Register Table -->
      <article class="panel-card" style="overflow:hidden;padding:0;">
        <div style="padding:20px 24px 16px;border-bottom:1px solid #f1f5f9;">
          <div class="panel-kicker">Priority Register</div>
          <h3 style="margin:4px 0 0;font-size:1rem;font-weight:800;">CLUP-gated candidate sites</h3>
        </div>
        <div class="clup-report-table-wrap" style="overflow-x:auto;">
          <table style="width:100%;border-collapse:collapse;">
            <thead>
              <tr style="background:#f8fafc;border-bottom:1px solid #f1f5f9;">
                <th style="text-align:left;padding:10px 16px;font-size:9px;font-weight:800;letter-spacing:.16em;text-transform:uppercase;color:#94a3b8;white-space:nowrap;">Rank</th>
                <th style="text-align:left;padding:10px 12px;font-size:9px;font-weight:800;letter-spacing:.16em;text-transform:uppercase;color:#94a3b8;">Candidate Site</th>
                <th style="text-align:left;padding:10px 12px;font-size:9px;font-weight:800;letter-spacing:.16em;text-transform:uppercase;color:#94a3b8;">Proposed Use</th>
                <th style="text-align:center;padding:10px 12px;font-size:9px;font-weight:800;letter-spacing:.16em;text-transform:uppercase;color:#94a3b8;">IAI</th>
                <th style="text-align:left;padding:10px 12px;font-size:9px;font-weight:800;letter-spacing:.16em;text-transform:uppercase;color:#94a3b8;">CLUP</th>
                <th style="text-align:left;padding:10px 12px;font-size:9px;font-weight:800;letter-spacing:.16em;text-transform:uppercase;color:#94a3b8;">Suitability</th>
                <th style="text-align:left;padding:10px 12px;font-size:9px;font-weight:800;letter-spacing:.16em;text-transform:uppercase;color:#94a3b8;">Evidence</th>
                <th style="text-align:left;padding:10px 12px;font-size:9px;font-weight:800;letter-spacing:.16em;text-transform:uppercase;color:#94a3b8;">Action Checklist</th>
                <th style="text-align:right;padding:10px 16px;font-size:9px;font-weight:800;letter-spacing:.16em;text-transform:uppercase;color:#94a3b8;">Action</th>
              </tr>
            </thead>
            <tbody>${rows}</tbody>
          </table>
        </div>
        <div style="padding:12px 24px;border-top:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;">
          <p style="font-size:11px;color:#94a3b8;font-weight:500;margin:0;">Showing ${enriched.length} candidate site${enriched.length !== 1 ? "s" : ""}</p>
          <div style="display:flex;align-items:center;gap:10px;font-size:11px;color:#94a3b8;font-weight:500;">
            <span style="display:flex;align-items:center;gap:4px;"><span style="width:8px;height:8px;border-radius:50%;background:#22c55e;display:inline-block;"></span>Pass</span>
            <span style="display:flex;align-items:center;gap:4px;"><span style="width:8px;height:8px;border-radius:50%;background:#f59e0b;display:inline-block;"></span>Conditional</span>
            <span style="display:flex;align-items:center;gap:4px;"><span style="width:8px;height:8px;border-radius:50%;background:#f43f5e;display:inline-block;"></span>Fail</span>
            <span style="display:flex;align-items:center;gap:4px;"><span style="width:8px;height:8px;border-radius:50%;background:#94a3b8;display:inline-block;"></span>Unverified</span>
          </div>
        </div>
      </article>

      <article class="panel-card clup-method-note"><div class="panel-kicker">Method and Limitation</div><p>CLUP status is a preliminary corridor-level screen derived from stored site attributes. It is not a zoning certificate or locational clearance. Validate every parcel against the latest adopted CLUP, official zoning map, Zoning Ordinance, overlays, and applicable national agency requirements.</p></article>
    </div>`;

  document.getElementById("printDecisionReport")?.addEventListener("click", () => window.print());
}

async function boot() {
  initPortalMenu();
  initCityBrief();
  initStudioMotion();
  initNotificationCenter();
  await ensureFavoriteIdsLoaded();
  if (page === "landing") initHeroStage();
  if (page === "landing") initLandingChrome();
  if (page === "landing") await initLanding();
  if (page === "admin-dashboard") await initAdminDashboard();
  if (page === "seller-dashboard") await initSellerDashboard();
  if (page === "investor-dashboard") await initInvestorDashboard();
  if (page === "property-ranking") await initRankingPage();
  if (page === "voting-dashboard") await initVotingDashboard();
  if (page === "offer-board") await initShowcasePage("offerBoardRoot", "offer_board");
  if (page === "city-pipeline") await initShowcasePage("cityPipelineRoot", "city_pipeline");
  if (page === "property-explorer" || page === "property-explorer-terminal") await initExplorer();
  if (page === "compare-decision") await initCompare();
  if (page === "property-details") await initPropertyDetails();
  if (page === "admin-properties") await initAdminProperties();
  if (page === "admin-showcase") await initAdminShowcase();
  if (page === "scenario-simulator") await initScenarioSimulator();
  if (page === "decision-reports") await initDecisionReports();
}

boot().catch((error) => {
  console.error(error);
  const root = document.getElementById("homeRankingPreview")
    || document.getElementById("adminDashboardRoot")
    || document.getElementById("sellerDashboardRoot")
    || document.getElementById("investorDashboardRoot")
    || document.getElementById("rankingPageRoot")
    || document.getElementById("votingDashboardRoot")
    || document.getElementById("offerBoardRoot")
    || document.getElementById("cityPipelineRoot")
    || document.getElementById("explorerAppRoot")
    || document.getElementById("compareDecisionRoot")
    || document.getElementById("propertyDetailsRoot")
    || document.getElementById("adminPropertiesRoot")
    || document.getElementById("adminShowcaseRoot")
    || document.getElementById("scenarioSimulatorRoot")
    || document.getElementById("decisionReportsRoot");

  if (root) {
    root.innerHTML = emptyState("Unable to load this screen", error.message || "Unexpected error.");
  }
}).finally(() => {
  window.LOCUS_PRELOADER?.dismiss();
});


