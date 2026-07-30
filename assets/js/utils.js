export const DEFAULT_FILTERS = {
  type: "all",
  corridor: "all",
  barangay: "",
};

export const DEFAULT_WEIGHTS = {
  access: 30,
  facilities: 25,
  area: 20,
  price: 15,
  sector: 10,
};

export const READINESS_PILLAR_WEIGHTS = {
  spatial: 18,
  infrastructure: 22,
  economic: 22,
  institutional: 18,
  legal: 20,
};

export const UTILITY_STATUS_SCORES = {
  full_ready: 100,
  power_water: 82,
  partial: 62,
  limited: 40,
  off_grid: 18,
};

export const UTILITY_STATUS_LABELS = {
  full_ready: "Full Fiber / Power / Water",
  power_water: "Power / Water Ready",
  partial: "Partial Utility Service",
  limited: "Limited Utility Service",
  off_grid: "Off Grid",
};

export const DEFAULT_SCENARIO = {
  budget: "",
  sector: "",
  size: "",
};

export const DEFAULT_DECISION_INPUTS = {
  intent: "logistics",
  horizon: "5",
  risk: "balanced",
  barangay: "",
  notes: "",
  capex: "",
  revenue: "",
  opex: "",
  modelNotes: "",
};

export const DEFAULT_LAB_INPUTS = {
  capex: "",
  revenue: "",
  opex: "",
  equityPct: 40,
  interest: "",
  exitCap: "",
  sensitivity: 0,
};

export const STORAGE_KEYS = {
  compare: "sfc.compare",
  viewMode: "sfc.viewMode",
  filters: "sfc.filters",
  weights: "sfc.weights",
  scenario: "sfc.scenario",
  selectedProperty: "sfc.selectedProperty",
  decisionInputs: "sfc.decisionInputs",
  labInputs: "sfc.labInputs",
  chatRole: "sfc.chatRole",
  chatSenderName: "sfc.chatSenderName",
};

export const DEFAULT_INVESTMENT_LENS = "logistics";

export const INVESTMENT_LENSES = [
  {
    key: "university",
    icon: "🎓",
    label: "University",
    shortLabel: "University",
    subtitle: "Prioritize area, access, and expansion",
    areaIdeal: { min: 4, max: 14 },
    corridorScores: { downtown: 92, highway: 84, coastal: 56 },
    weights: {
      road_access: 0.22,
      lot_area: 0.22,
      residential_support: 0.18,
      expansion_potential: 0.16,
      institutional_fit: 0.12,
      legal_readiness: 0.10,
    },
  },
  {
    key: "hospital",
    icon: "🏥",
    label: "Hospital",
    shortLabel: "Hospital",
    subtitle: "Prioritize utilities, access, and compliance",
    areaIdeal: { min: 3, max: 10 },
    corridorScores: { downtown: 88, highway: 90, coastal: 48 },
    weights: {
      utility_readiness: 0.24,
      road_access: 0.22,
      institutional_fit: 0.18,
      legal_readiness: 0.14,
      residential_support: 0.12,
      expansion_potential: 0.10,
    },
  },
  {
    key: "logistics",
    icon: "🏗️",
    label: "Logistics",
    shortLabel: "Logistics",
    subtitle: "Prioritize road, power, and throughput",
    areaIdeal: { min: 6, max: 20 },
    corridorScores: { highway: 100, downtown: 68, coastal: 52 },
    weights: {
      road_access: 0.30,
      utility_readiness: 0.20,
      lot_area: 0.18,
      transport_access: 0.14,
      industrial_utility_fit: 0.12,
      legal_readiness: 0.06,
    },
  },
  {
    key: "commercial_center",
    icon: "🛍️",
    label: "Commercial Center",
    shortLabel: "Commercial",
    subtitle: "Prioritize demand, traffic, and urban fit",
    areaIdeal: { min: 2.5, max: 9 },
    corridorScores: { downtown: 96, highway: 88, coastal: 70 },
    weights: {
      residential_support: 0.22,
      corridor_fit: 0.20,
      road_access: 0.18,
      market_score: 0.16,
      office_readiness: 0.14,
      legal_readiness: 0.10,
    },
  },
  {
    key: "resort",
    icon: "🏝️",
    label: "Resort / Tourism",
    shortLabel: "Resort",
    subtitle: "Prioritize destination fit and site appeal",
    areaIdeal: { min: 5, max: 18 },
    corridorScores: { coastal: 100, highway: 74, downtown: 60 },
    weights: {
      tourism_fit: 0.30,
      lot_area: 0.18,
      road_access: 0.14,
      utility_readiness: 0.12,
      market_score: 0.10,
      legal_readiness: 0.08,
      expansion_potential: 0.08,
    },
  },
  {
    key: "bpo",
    icon: "💼",
    label: "Office / BPO",
    shortLabel: "BPO",
    subtitle: "Prioritize utilities, staff support, and urban access",
    areaIdeal: { min: 2, max: 8 },
    corridorScores: { downtown: 96, highway: 90, coastal: 54 },
    weights: {
      office_readiness: 0.28,
      utility_readiness: 0.22,
      road_access: 0.16,
      institutional_fit: 0.14,
      residential_support: 0.10,
      market_score: 0.10,
    },
  },
  {
    key: "manufacturing",
    icon: "🏭",
    label: "Manufacturing",
    shortLabel: "Manufacturing",
    subtitle: "Prioritize utilities, area, and industrial fit",
    areaIdeal: { min: 6, max: 20 },
    corridorScores: { highway: 96, downtown: 54, coastal: 48 },
    weights: {
      industrial_utility_fit: 0.26,
      utility_readiness: 0.24,
      lot_area: 0.18,
      road_access: 0.18,
      expansion_potential: 0.08,
      legal_readiness: 0.06,
    },
  },
];

export function clamp(value, min, max) {
  return Math.max(min, Math.min(max, value));
}

export function loadJSON(key, fallbackValue) {
  try {
    const raw = window.localStorage.getItem(key);
    if (!raw) {
      return fallbackValue;
    }

    const parsed = JSON.parse(raw);
    return parsed ?? fallbackValue;
  } catch {
    return fallbackValue;
  }
}

export function saveJSON(key, value) {
  try {
    window.localStorage.setItem(key, JSON.stringify(value));
  } catch {
    return;
  }
}

export function normalizeWeights(weights) {
  const total =
    Number(weights.access || 0) +
    Number(weights.facilities || 0) +
    Number(weights.area || 0) +
    Number(weights.price || 0) +
    Number(weights.sector || 0);

  if (total <= 0) {
    return { ...DEFAULT_WEIGHTS };
  }

  const scaled = {
    access: Math.round((Number(weights.access || 0) / total) * 100),
    facilities: Math.round((Number(weights.facilities || 0) / total) * 100),
    area: Math.round((Number(weights.area || 0) / total) * 100),
    price: Math.round((Number(weights.price || 0) / total) * 100),
    sector: Math.round((Number(weights.sector || 0) / total) * 100),
  };

  const diff =
    100 -
    (scaled.access +
      scaled.facilities +
      scaled.area +
      scaled.price +
      scaled.sector);

  scaled.access += diff;
  return scaled;
}

export function computeComponents(property, allProperties, targetSector) {
  const access = clamp(Number(property.roadAccess || 0), 0, 100);
  const maxFacilities = Math.max(...allProperties.map((entry) => entry.facilities.length), 1);
  const facilities = clamp((property.facilities.length / maxFacilities) * 100, 0, 100);
  const areaFit = property.area >= 5 && property.area <= 15 ? 100 : 60;

  const allPricePerSqm = allProperties.map((entry) => Number(entry.pricePerSqm || 0));
  const minPrice = Math.min(...allPricePerSqm);
  const maxPrice = Math.max(...allPricePerSqm);
  const priceValue =
    maxPrice === minPrice
      ? 100
      : clamp(((maxPrice - property.pricePerSqm) / (maxPrice - minPrice)) * 100, 0, 100);

  const sectorMatch = targetSector
    ? property.type === targetSector
      ? 100
      : 55
    : 75;

  return { access, facilities, areaFit, priceValue, sectorMatch };
}

export function calculateWeightedScore(property, allProperties, weights, targetSector) {
  const components = computeComponents(property, allProperties, targetSector);
  const score =
    components.access * weights.access +
    components.facilities * weights.facilities +
    components.areaFit * weights.area +
    components.priceValue * weights.price +
    components.sectorMatch * weights.sector;

  return Math.round(score / 100);
}

export function rankProperties(properties, allProperties, weights, targetSector) {
  return properties
    .map((property) => ({
      ...property,
      score: calculateWeightedScore(property, allProperties, weights, targetSector),
    }))
    .sort((left, right) => right.score - left.score);
}

export function filterProperties(properties, filters) {
  return properties.filter((property) => {
    if (filters.type && filters.type !== "all" && property.type !== filters.type) {
      return false;
    }
    if (filters.corridor && filters.corridor !== "all" && property.corridor !== filters.corridor) {
      return false;
    }
    if (filters.barangay && property.barangay !== filters.barangay) {
      return false;
    }
    return true;
  });
}

export function buildResultsSubtitle(count, filters) {
  const type = filters.type === "all" ? "ALL TYPES" : filters.type.toUpperCase();
  const corridor =
    filters.corridor === "all" ? "ALL CORRIDORS" : filters.corridor.toUpperCase();
  const barangay = filters.barangay
    ? `BARANGAY ${filters.barangay.toUpperCase()}`
    : "ALL BARANGAYS";

  return `SHOWING ${count} PROPERTIES. ${type}. ${corridor}. ${barangay}.`;
}

export function averageScore(properties, allProperties, weights, targetSector) {
  if (!properties.length) {
    return 0;
  }

  const total = properties.reduce(
    (sum, property) =>
      sum + calculateWeightedScore(property, allProperties, weights, targetSector),
    0
  );

  return Math.round(total / properties.length);
}

export function safeNumber(value) {
  const numeric = Number(value);
  if (!Number.isFinite(numeric) || numeric < 0) {
    return null;
  }
  return numeric;
}

export function calcDueDiligencePct(items, state) {
  if (!items.length) {
    return 0;
  }

  const complete = items.filter((item) => Boolean(state[item.key])).length;
  return Math.round((complete / items.length) * 100);
}

export function humanizeIdentifier(value) {
  const normalized = String(value || "")
    .trim()
    .replace(/[_-]+/g, " ")
    .toLowerCase();
  if (!normalized) return "";
  return normalized.replace(/\b\w/g, (char) => char.toUpperCase());
}

export function normalizeUtilityStatus(value) {
  const normalized = String(value || "")
    .trim()
    .toLowerCase()
    .replace(/[\s-]+/g, "_");

  if (!normalized) return null;
  if (["full", "ready", "full_ready", "full_fiber_power_water", "full_fiber_/_power_/_water"].includes(normalized)) return "full_ready";
  if (["power_water", "power_and_water"].includes(normalized)) return "power_water";
  if (["partial", "partial_ready", "partial_service"].includes(normalized)) return "partial";
  if (["limited", "limited_service"].includes(normalized)) return "limited";
  if (["off_grid", "offgrid"].includes(normalized)) return "off_grid";
  return null;
}

export function utilityStatusLabel(status) {
  return UTILITY_STATUS_LABELS[status] || "Missing utility status";
}

function readinessLabel(score) {
  if (score >= 80) return "Highly Ready";
  if (score >= 60) return "Moderately Ready";
  return "Needs More Validation";
}

function readinessStatus(score, missingCount) {
  if (missingCount > 0 && score < 70) return "incomplete";
  if (score >= 80) return "strong";
  if (score >= 60) return "neutral";
  return "warning";
}

function readinessIndicator(key, label, displayValue, normalizedScore) {
  const score = Number.isFinite(Number(normalizedScore))
    ? clamp(Math.round(Number(normalizedScore)), 0, 100)
    : null;
  return {
    key,
    label,
    displayValue,
    normalizedScore: score,
    missing: score === null,
    status: score === null ? "missing" : readinessStatus(score, 0),
  };
}

function roadClassLabel(roadAccess) {
  const access = Number(roadAccess || 0);
  if (access >= 90) return "Primary";
  if (access >= 75) return "Secondary";
  return "Tertiary";
}

function roadClassScore(roadClass) {
  if (String(roadClass).toLowerCase() === "primary") return 100;
  if (String(roadClass).toLowerCase() === "secondary") return 78;
  return 58;
}

function serviceCoverageScore(facilities, utilityStatus) {
  const keywords = ["utilities", "fiber", "power", "water", "backbone", "transport", "highway"];
  let hits = 0;
  (facilities || []).forEach((facility) => {
    const text = String(facility || "").toLowerCase();
    if (keywords.some((keyword) => text.includes(keyword))) {
      hits += 1;
    }
  });
  const utilityScore = utilityStatus ? (UTILITY_STATUS_SCORES[utilityStatus] ?? 52) : 52;
  return clamp(Math.round(Math.min(100, 48 + (hits * 10) + (utilityScore * 0.32))), 20, 100);
}

function serviceCoverageLabel(facilities) {
  const items = (facilities || []).map((item) => String(item || "").trim()).filter(Boolean);
  if (!items.length) return "Facility coverage not detailed";
  return items.slice(0, 3).join(" / ");
}

function approvalStateScore(state) {
  switch (String(state || "").toLowerCase()) {
    case "approved": return 100;
    case "pending_review": return 70;
    case "draft": return 45;
    case "rejected": return 18;
    case "archived": return 10;
    default: return 40;
  }
}

function planningFitScore(type, corridor, facilities) {
  const typeKey = String(type || "").toLowerCase();
  const corridorKey = String(corridor || "").toLowerCase();
  let base = 72;
  if ((typeKey === "logistics" || typeKey === "manufacturing") && corridorKey === "highway") base = 92;
  else if (typeKey === "hotel" && corridorKey === "coastal") base = 90;
  else if (typeKey === "commercial" && (corridorKey === "downtown" || corridorKey === "highway")) base = 86;
  else if (typeKey === "bpo" && (corridorKey === "downtown" || corridorKey === "highway")) base = 88;

  let boost = 0;
  (facilities || []).forEach((facility) => {
    const text = String(facility || "").toLowerCase();
    if (text.includes("fiber") || text.includes("highway") || text.includes("utilities")) {
      boost += 3;
    }
  });
  return clamp(base + Math.min(boost, 8), 0, 100);
}

function planningFitLabel(type, corridor) {
  return `${humanizeIdentifier(type)} aligned with ${humanizeIdentifier(corridor)} corridor`;
}

function legalTrustScore(listingVerificationStatus, sellerIdentityStatus) {
  let base = 40;
  switch (String(listingVerificationStatus || "").toLowerCase()) {
    case "verified": base = 100; break;
    case "partially_verified": base = 72; break;
    case "unverified": base = 45; break;
    case "pending_review": base = 36; break;
    case "draft": base = 28; break;
    case "rejected": base = 15; break;
    case "archived": base = 10; break;
  }
  if (String(sellerIdentityStatus || "").toLowerCase() === "verified") base += 6;
  else if (String(sellerIdentityStatus || "").toLowerCase() === "pending") base += 2;
  return clamp(base, 0, 100);
}

function legalTrustLabel(listingVerificationStatus, sellerIdentityStatus) {
  if (String(listingVerificationStatus || "").toLowerCase() === "verified") return "Verified listing";
  if (String(sellerIdentityStatus || "").toLowerCase() === "verified") return "Seller verified, listing still completing checks";
  return "Trust state still building";
}

function priceBenchmarkMap(allProperties) {
  const groups = {};
  (allProperties || []).forEach((property) => {
    const type = String(property.type || "").toLowerCase() || "*";
    const pricePerSqm = Number(property.pricePerSqm || 0);
    if (!Number.isFinite(pricePerSqm) || pricePerSqm <= 0) return;
    groups[type] ||= [];
    groups[type].push(pricePerSqm);
    groups["*"] ||= [];
    groups["*"].push(pricePerSqm);
  });

  const benchmarks = {};
  Object.entries(groups).forEach(([type, prices]) => {
    const sorted = [...prices].sort((a, b) => a - b);
    benchmarks[type] = {
      min: sorted[0],
      max: sorted[sorted.length - 1],
      median: sorted[Math.floor((sorted.length - 1) / 2)],
    };
  });
  return benchmarks;
}

function priceCompetitivenessScore(pricePerSqm, benchmark) {
  const price = Number(pricePerSqm || 0);
  if (!benchmark || !Number.isFinite(price) || price <= 0) return 70;
  if (benchmark.max <= benchmark.min) return 100;
  return clamp(Math.round(((benchmark.max - price) / (benchmark.max - benchmark.min)) * 100), 0, 100);
}

function pillarSummary(label, score, missingCount) {
  if (missingCount > 0) return `${label} has ${missingCount} missing input${missingCount === 1 ? "" : "s"}.`;
  if (score >= 80) return `${label} is currently strong.`;
  if (score >= 60) return `${label} is usable but still uneven.`;
  return `${label} still needs validation.`;
}

function readinessPillar(key, label, indicators) {
  const availableScores = indicators
    .filter((indicator) => !indicator.missing)
    .map((indicator) => Number(indicator.normalizedScore || 0));
  const missingFields = indicators.filter((indicator) => indicator.missing).map((indicator) => indicator.label);
  const average = availableScores.length
    ? availableScores.reduce((sum, value) => sum + value, 0) / availableScores.length
    : 0;
  const completenessRatio = indicators.length ? availableScores.length / indicators.length : 0;
  const score = Math.round(average * completenessRatio);
  return {
    key,
    label,
    weight: READINESS_PILLAR_WEIGHTS[key] || 20,
    score,
    status: readinessStatus(score, missingFields.length),
    summary: pillarSummary(label, score, missingFields.length),
    indicators,
    missingFields,
  };
}

export function calculateInvestmentReadiness(property, allProperties = [], dueDiligencePct = 0) {
  if (!property) return null;

  const utilityStatus = normalizeUtilityStatus(property.utilityStatus);
  const benchmarks = priceBenchmarkMap(allProperties);
  const benchmark = benchmarks[String(property.type || "").toLowerCase()] || benchmarks["*"] || null;
  const roadClass = roadClassLabel(property.roadAccess);
  const spatialIndicators = [
    readinessIndicator(
      "dist_to_road",
      "Distance to Primary Road",
      property.distToRoadKm != null ? `${Number(property.distToRoadKm).toFixed(2)} km` : "Missing",
      property.distToRoadKm != null ? clamp(Math.round(100 - Math.min(72, Number(property.distToRoadKm) * 18)), 28, 100) : null
    ),
    readinessIndicator(
      "corridor_quality",
      "Corridor Quality",
      humanizeIdentifier(property.corridor),
      property.corridor === "highway" ? 88 : property.corridor === "downtown" ? 84 : property.corridor === "coastal" ? 78 : 70
    ),
    readinessIndicator(
      "location_clarity",
      "Location Clarity",
      property.barangay || "Barangay missing",
      property.barangay ? 96 : 52
    ),
    readinessIndicator(
      "map_confidence",
      "Map Confidence",
      (Number(property.lat) || Number(property.lng)) ? "Mapped coordinates available" : "Mapped coordinates missing",
      (Number(property.lat) || Number(property.lng)) ? 95 : 30
    ),
  ];

  const infrastructureIndicators = [
    readinessIndicator("road_access", "Road Access", `${Number(property.roadAccess || 0)} / 100`, Number(property.roadAccess || 0)),
    readinessIndicator("road_class", "Road Class", roadClass, roadClassScore(roadClass)),
    readinessIndicator("utility_status", "Utility Status", utilityStatusLabel(utilityStatus), utilityStatus ? UTILITY_STATUS_SCORES[utilityStatus] : null),
    readinessIndicator("service_coverage", "Service Coverage", serviceCoverageLabel(property.facilities), serviceCoverageScore(property.facilities, utilityStatus)),
  ];

  const assessedValueSqm = Number(property.assessedValueSqm || 0);
  const pricePerSqm = Number(property.pricePerSqm || 0);
  const economicIndicators = [
    readinessIndicator("market_score", "Market Score", `${Number(property.marketScore || property.score || 0)} / 100`, Number(property.marketScore || property.score || 0)),
    readinessIndicator("price_competitiveness", "Price Competitiveness", `PHP ${Math.round(pricePerSqm).toLocaleString()} / sqm`, priceCompetitivenessScore(pricePerSqm, benchmark)),
    readinessIndicator("assessed_value_sqm", "Assessed Value / SQM", assessedValueSqm > 0 ? `PHP ${Math.round(assessedValueSqm).toLocaleString()}` : "Missing assessed value", assessedValueSqm > 0 ? clamp(Math.round((assessedValueSqm / Math.max(pricePerSqm, 1)) * 100), 35, 100) : null),
    readinessIndicator("value_spread", "Value Spread", assessedValueSqm > 0 ? (assessedValueSqm >= pricePerSqm ? "At or above assessed" : "Below assessed") : "Awaiting assessed benchmark", assessedValueSqm > 0 ? clamp(Math.round(100 - (((pricePerSqm - assessedValueSqm) / Math.max(pricePerSqm, 1)) * 100)), 30, 100) : null),
  ];

  const institutionalIndicators = [
    readinessIndicator("zoning_score", "Zoning Score", property.zoningScore != null ? `${Number(property.zoningScore)} / 100` : "Missing zoning score", property.zoningScore != null ? Number(property.zoningScore) : null),
    readinessIndicator("approval_state", "Approval State", humanizeIdentifier(property.approvalState), approvalStateScore(property.approvalState)),
    readinessIndicator("site_verified", "Site Verification", property.siteVerifiedAt ? "Site verified" : "Site not verified", property.siteVerifiedAt ? 100 : 34),
    readinessIndicator("planning_fit", "Planning Fit", planningFitLabel(property.type, property.corridor), planningFitScore(property.type, property.corridor, property.facilities)),
  ];

  const legalIndicators = [
    readinessIndicator("dd_completion_pct", "Due Diligence Completion", `${Number(dueDiligencePct || 0)}% complete`, Number(dueDiligencePct || 0)),
    readinessIndicator("document_completeness_pct", "Document Completeness", `${Number(property.documentCompletenessPct || 0)}% complete`, Number(property.documentCompletenessPct || 0)),
    readinessIndicator("documents_reviewed", "Documents Reviewed", property.documentsReviewedAt ? "Reviewed by admin" : "Pending review", property.documentsReviewedAt ? 100 : 36),
    readinessIndicator("legal_trust_state", "Legal Trust State", legalTrustLabel(property.listingVerificationStatus, property.sellerIdentityStatus), legalTrustScore(property.listingVerificationStatus, property.sellerIdentityStatus)),
  ];

  const pillars = {
    spatial: readinessPillar("spatial", "Spatial", spatialIndicators),
    infrastructure: readinessPillar("infrastructure", "Infrastructure", infrastructureIndicators),
    economic: readinessPillar("economic", "Economic", economicIndicators),
    institutional: readinessPillar("institutional", "Institutional", institutionalIndicators),
    legal: readinessPillar("legal", "Legal", legalIndicators),
  };

  const totalWeight = Object.values(pillars).reduce((sum, pillar) => sum + Number(pillar.weight || 0), 0);
  const weightedTotal = Object.values(pillars).reduce((sum, pillar) => sum + (Number(pillar.score || 0) * Number(pillar.weight || 0)), 0);
  const missingDataCount = Object.values(pillars).reduce((sum, pillar) => sum + pillar.missingFields.length, 0);
  const totalScore = totalWeight ? Math.round(weightedTotal / totalWeight) : 0;

  return {
    totalScore,
    label: readinessLabel(totalScore),
    status: readinessStatus(totalScore, missingDataCount),
    missingDataCount,
    lastComputedAt: new Date().toISOString(),
    pillars,
    notes: property.readinessNotes || "",
  };
}

function defaultLensMetric(label, score, weight, pillar, displayValue, summary, thesisFragment, missing = false) {
  const normalizedScore =
    score === null || score === undefined || Number.isNaN(Number(score))
      ? null
      : clamp(Math.round(Number(score)), 0, 100);
  const normalizedWeight = Number(weight || 0);
  return {
    key: label
      .trim()
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, "_"),
    label,
    score: normalizedScore,
    weight: normalizedWeight,
    pillar,
    displayValue,
    summary,
    thesisFragment,
    missing: missing || normalizedScore === null,
    contribution:
      normalizedScore === null ? 0 : Number((normalizedScore * normalizedWeight).toFixed(4)),
  };
}

function lensStatus(score, missingCount) {
  if (missingCount > 0 && score < 70) return "incomplete";
  if (score >= 85) return "strong";
  if (score >= 65) return "neutral";
  return "warning";
}

function findReadinessIndicator(readiness, pillarKey, indicatorKey) {
  const indicators = readiness?.pillars?.[pillarKey]?.indicators || [];
  return indicators.find((indicator) => String(indicator.key) === String(indicatorKey)) || null;
}

function idealAreaScore(area, idealRange) {
  const numericArea = Number(area || 0);
  if (!Number.isFinite(numericArea) || numericArea <= 0) return null;
  const minimum = Number(idealRange?.min || 0);
  const maximum = Number(idealRange?.max || 0);
  if (!minimum || !maximum || minimum >= maximum) return clamp(Math.round(40 + (numericArea * 5)), 35, 100);
  if (numericArea >= minimum && numericArea <= maximum) return 100;
  if (numericArea < minimum) {
    return clamp(Math.round(100 - ((minimum - numericArea) * 12)), 24, 94);
  }
  return clamp(Math.round(100 - ((numericArea - maximum) * 7)), 28, 94);
}

function finiteNumberOrNull(value) {
  const numeric = Number(value);
  return Number.isFinite(numeric) ? numeric : null;
}

function corridorLensScore(property, lensConfig) {
  const corridorKey = String(property?.corridor || "").toLowerCase();
  const configured = lensConfig?.corridorScores?.[corridorKey];
  if (Number.isFinite(Number(configured))) {
    return clamp(Math.round(Number(configured)), 0, 100);
  }
  if (!corridorKey) return null;
  return 68;
}

function normalizedMarketScore(property) {
  const numeric = Number(property?.marketScore ?? property?.score ?? 0);
  if (!Number.isFinite(numeric) || numeric <= 0) return null;
  return clamp(Math.round(numeric), 0, 100);
}

function distToRoadScore(property, readiness) {
  const rawValue =
    property?.distToRoadKm != null
      ? Number(property.distToRoadKm)
      : finiteNumberOrNull(findReadinessIndicator(readiness, "spatial", "dist_to_road")?.normalizedScore);
  if (!Number.isFinite(rawValue)) return null;
  if (property?.distToRoadKm != null) {
    return clamp(Math.round(100 - Math.min(72, rawValue * 18)), 28, 100);
  }
  return clamp(Math.round(rawValue), 0, 100);
}

function residentialSupportScore(property) {
  const corridorBase = {
    downtown: 94,
    highway: 76,
    coastal: 64,
  }[String(property?.corridor || "").toLowerCase()];
  if (corridorBase == null) return null;

  const corpus = [
    ...(property?.facilities || []),
    ...(property?.tags || []),
    property?.description || "",
  ]
    .join(" ")
    .toLowerCase();

  let boost = 0;
  ["retail", "commercial", "school", "housing", "residential", "transport", "terminal", "market"].forEach((keyword) => {
    if (corpus.includes(keyword)) boost += 3;
  });

  return clamp(corridorBase + Math.min(boost, 12), 0, 100);
}

function expansionPotentialScore(property) {
  const area = Number(property?.area || 0);
  if (!Number.isFinite(area) || area <= 0) return null;
  const distScore = property?.distToRoadKm != null
    ? clamp(Math.round(100 - Math.min(55, Number(property.distToRoadKm) * 12)), 45, 100)
    : 72;
  return clamp(Math.round((Math.min(area, 18) / 18) * 70 + (distScore * 0.3)), 0, 100);
}

function transportAccessScore(property, readiness, lensConfig) {
  const roadAccess = Number(property?.roadAccess || 0);
  const corridorScore = corridorLensScore(property, lensConfig);
  const roadDistanceScore = distToRoadScore(property, readiness);
  const hasRoadAccess = Number.isFinite(roadAccess) && roadAccess > 0;
  if (!hasRoadAccess && corridorScore === null && roadDistanceScore === null) return null;

  const base = hasRoadAccess ? clamp(roadAccess, 0, 100) * 0.58 : 0;
  const corridorLift = corridorScore === null ? 0 : corridorScore * 0.24;
  const roadDistanceLift = roadDistanceScore === null ? 0 : roadDistanceScore * 0.18;
  return clamp(Math.round(base + corridorLift + roadDistanceLift), 0, 100);
}

function tourismFitScore(property) {
  const corridorBase = {
    coastal: 100,
    highway: 72,
    downtown: 60,
  }[String(property?.corridor || "").toLowerCase()];
  if (corridorBase == null) return null;

  let boost = 0;
  const corpus = [
    String(property?.type || ""),
    ...(property?.facilities || []),
    ...(property?.tags || []),
    property?.description || "",
  ]
    .join(" ")
    .toLowerCase();

  if (String(property?.type || "").toLowerCase() === "hotel") boost += 8;
  ["tourism", "resort", "beach", "coastal", "view", "destination"].forEach((keyword) => {
    if (corpus.includes(keyword)) boost += 3;
  });

  return clamp(corridorBase + Math.min(boost, 16), 0, 100);
}

function officeReadinessScore(property, readiness) {
  const utilityStatus = normalizeUtilityStatus(property?.utilityStatus);
  const utilityScore = utilityStatus ? (UTILITY_STATUS_SCORES[utilityStatus] ?? null) : null;
  const institutional = Number(readiness?.pillars?.institutional?.score ?? null);
  const residential = residentialSupportScore(property);
  const corridorBase = {
    downtown: 96,
    highway: 88,
    coastal: 52,
  }[String(property?.corridor || "").toLowerCase()];

  if (utilityScore === null && !Number.isFinite(institutional) && residential === null && corridorBase == null) {
    return null;
  }

  return clamp(
    Math.round(
      (utilityScore === null ? 0 : utilityScore * 0.38) +
      (Number.isFinite(institutional) ? institutional * 0.24 : 0) +
      (residential === null ? 0 : residential * 0.18) +
      (corridorBase == null ? 0 : corridorBase * 0.20)
    ),
    0,
    100
  );
}

function industrialUtilityFitScore(property, readiness) {
  const utilityStatus = normalizeUtilityStatus(property?.utilityStatus);
  const utilityScore = utilityStatus ? (UTILITY_STATUS_SCORES[utilityStatus] ?? null) : null;
  const roadAccess = Number(property?.roadAccess || 0);
  const corridorBase = {
    highway: 98,
    downtown: 48,
    coastal: 42,
  }[String(property?.corridor || "").toLowerCase()];

  if (utilityScore === null && (!Number.isFinite(roadAccess) || roadAccess <= 0) && corridorBase == null) {
    return null;
  }

  const typeLift =
    String(property?.type || "").toLowerCase() === "manufacturing"
      ? 8
      : String(property?.type || "").toLowerCase() === "logistics"
        ? 5
        : 0;

  return clamp(
    Math.round(
      (utilityScore === null ? 0 : utilityScore * 0.46) +
      (Number.isFinite(roadAccess) ? clamp(roadAccess, 0, 100) * 0.30 : 0) +
      (corridorBase == null ? 0 : corridorBase * 0.24) +
      typeLift
    ),
    0,
    100
  );
}

function joinFragments(fragments) {
  if (!fragments.length) return "";
  if (fragments.length === 1) return fragments[0];
  if (fragments.length === 2) return `${fragments[0]} and ${fragments[1]}`;
  return `${fragments.slice(0, -1).join(", ")}, and ${fragments[fragments.length - 1]}`;
}

export function getInvestmentLensConfig(lensKey) {
  return INVESTMENT_LENSES.find((lens) => String(lens.key) === String(lensKey))
    || INVESTMENT_LENSES.find((lens) => lens.key === DEFAULT_INVESTMENT_LENS)
    || INVESTMENT_LENSES[0];
}

export function calculateInvestmentLensResult(property, allProperties = [], lensKey = DEFAULT_INVESTMENT_LENS, options = {}) {
  if (!property) return null;

  const lensConfig = getInvestmentLensConfig(lensKey);
  const dueDiligencePct = Number(
    options?.dueDiligencePct
      ?? property?.investmentReadiness?.pillars?.legal?.indicators?.find((indicator) => indicator.key === "dd_completion_pct")?.normalizedScore
      ?? 0
  );
  const readiness =
    options?.readiness
    || property?.investmentReadiness
    || calculateInvestmentReadiness(property, allProperties, dueDiligencePct);
  const utilityStatus = normalizeUtilityStatus(property?.utilityStatus);
  const benchmarks = priceBenchmarkMap(allProperties);
  const benchmark =
    benchmarks[String(property?.type || "").toLowerCase()]
    || benchmarks["*"]
    || null;
  const pricePerSqm = Number(property?.pricePerSqm || 0);
  const assessedValueSqm = Number(property?.assessedValueSqm || 0);
  const corridorScore = corridorLensScore(property, lensConfig);
  const marketScore = normalizedMarketScore(property);
  const areaScore = idealAreaScore(property?.area, lensConfig.areaIdeal);
  const roadScore = Number.isFinite(Number(property?.roadAccess))
    ? clamp(Math.round(Number(property.roadAccess)), 0, 100)
    : null;
  const legalReadiness = finiteNumberOrNull(readiness?.pillars?.legal?.score);
  const institutionalReadiness = finiteNumberOrNull(readiness?.pillars?.institutional?.score);
  const residentialSupport = residentialSupportScore(property);
  const expansionPotential = expansionPotentialScore(property);
  const transportAccess = transportAccessScore(property, readiness, lensConfig);
  const tourismFit = tourismFitScore(property);
  const officeReadiness = officeReadinessScore(property, readiness);
  const industrialFit = industrialUtilityFitScore(property, readiness);

  const priceCompetitiveness = priceCompetitivenessScore(pricePerSqm, benchmark);
  const valueSpreadScore =
    assessedValueSqm > 0
      ? clamp(Math.round(100 - (((pricePerSqm - assessedValueSqm) / Math.max(pricePerSqm, 1)) * 100)), 30, 100)
      : null;
  const economicBlendedScore = assessedValueSqm > 0
    ? clamp(Math.round((priceCompetitiveness * 0.56) + (valueSpreadScore * 0.44)), 0, 100)
    : pricePerSqm > 0
      ? priceCompetitiveness
      : null;

  const metricLibrary = {
    road_access: () => defaultLensMetric(
      "Road Access",
      roadScore,
      lensConfig.weights.road_access,
      "infrastructure",
      roadScore === null ? "Road access missing" : `${roadClassLabel(property?.roadAccess)} road / ${roadScore}`,
      roadScore === null ? "Road access still needs to be logged." : `${roadClassLabel(property?.roadAccess)} road classification supports access.`,
      roadScore === null ? "road quality still needs confirmation" : `${roadClassLabel(property?.roadAccess).toLowerCase()} road access is already in place`,
      roadScore === null
    ),
    lot_area: () => defaultLensMetric(
      "Lot Area",
      areaScore,
      lensConfig.weights.lot_area,
      "spatial",
      property?.area ? `${Number(property.area).toFixed(1)} ha` : "Lot area missing",
      areaScore === null ? "Lot area still needs to be confirmed." : `The site footprint is being judged against the ${lensConfig.label.toLowerCase()} size window.`,
      areaScore === null ? "the usable footprint is still unclear" : `the ${Number(property.area).toFixed(1)} hectare footprint matches this lens well`,
      areaScore === null
    ),
    utility_readiness: () => defaultLensMetric(
      "Utility Readiness",
      utilityStatus ? UTILITY_STATUS_SCORES[utilityStatus] : null,
      lensConfig.weights.utility_readiness,
      "infrastructure",
      utilityStatus ? utilityStatusLabel(utilityStatus) : "Utility status missing",
      utilityStatus ? "Utilities are already described in the listing." : "Utility service still needs confirmation.",
      utilityStatus ? `${utilityStatusLabel(utilityStatus).toLowerCase()} supports near-term activation` : "utility capacity still needs validation",
      !utilityStatus
    ),
    corridor_fit: () => defaultLensMetric(
      "Corridor Fit",
      corridorScore,
      lensConfig.weights.corridor_fit,
      "spatial",
      property?.corridor ? `${humanizeIdentifier(property.corridor)} corridor` : "Corridor missing",
      corridorScore === null ? "Corridor alignment is not yet tagged." : `${lensConfig.label} favors this corridor profile.`,
      corridorScore === null ? "corridor alignment is still unknown" : `the ${humanizeIdentifier(property.corridor).toLowerCase()} corridor fits this strategy`,
      corridorScore === null
    ),
    market_score: () => defaultLensMetric(
      "Market Score",
      marketScore,
      lensConfig.weights.market_score,
      "economic",
      marketScore === null ? "Market score missing" : `${marketScore} / 100`,
      marketScore === null ? "Market score is still missing." : "Existing market demand and platform momentum remain supportive.",
      marketScore === null ? "market traction is still unclear" : `market momentum is already supporting the opportunity`,
      marketScore === null
    ),
    legal_readiness: () => defaultLensMetric(
      "Legal Readiness",
      Number.isFinite(legalReadiness) ? legalReadiness : null,
      lensConfig.weights.legal_readiness,
      "legal",
      Number.isFinite(legalReadiness) ? `${legalReadiness}% legal pillar` : "Legal readiness unavailable",
      Number.isFinite(legalReadiness) ? "Trust, documents, and diligence are contributing to certainty." : "Legal readiness still needs structured diligence.",
      Number.isFinite(legalReadiness) ? "legal readiness is already supporting execution confidence" : "legal readiness still needs validation",
      !Number.isFinite(legalReadiness)
    ),
    institutional_fit: () => defaultLensMetric(
      "Institutional Fit",
      Number.isFinite(institutionalReadiness) ? institutionalReadiness : property?.zoningScore != null ? clamp(Math.round(Number(property.zoningScore)), 0, 100) : null,
      lensConfig.weights.institutional_fit,
      "institutional",
      Number.isFinite(institutionalReadiness)
        ? `${institutionalReadiness}% institutional pillar`
        : property?.zoningScore != null
          ? `${Number(property.zoningScore)} / 100 zoning`
          : "Institutional fit missing",
      Number.isFinite(institutionalReadiness) || property?.zoningScore != null
        ? "Planning, zoning, and admin checks are contributing to this lens."
        : "Institutional fit still needs zoning or review detail.",
      Number.isFinite(institutionalReadiness) || property?.zoningScore != null
        ? "institutional alignment is already visible"
        : "institutional fit still needs clearer proof",
      !Number.isFinite(institutionalReadiness) && property?.zoningScore == null
    ),
    document_completeness: () => defaultLensMetric(
      "Document Completeness",
      property?.documentCompletenessPct != null ? clamp(Math.round(Number(property.documentCompletenessPct)), 0, 100) : null,
      lensConfig.weights.document_completeness,
      "legal",
      property?.documentCompletenessPct != null ? `${Math.round(Number(property.documentCompletenessPct))}% complete` : "Document package missing",
      property?.documentCompletenessPct != null ? "Document packaging is already contributing to trust." : "Document packaging still needs to be assembled.",
      property?.documentCompletenessPct != null ? "document packaging is already taking shape" : "document packaging still needs work",
      property?.documentCompletenessPct == null
    ),
    residential_support: () => defaultLensMetric(
      "Residential Support",
      residentialSupport,
      lensConfig.weights.residential_support,
      "spatial",
      property?.barangay ? `${property.barangay} support zone` : "Residential support proxy only",
      residentialSupport === null ? "Residential support is still being inferred." : "This score uses corridor and amenity proxies for nearby support demand.",
      residentialSupport === null ? "nearby support demand still needs validation" : "the surrounding support base helps this lens",
      residentialSupport === null
    ),
    expansion_potential: () => defaultLensMetric(
      "Expansion Potential",
      expansionPotential,
      lensConfig.weights.expansion_potential,
      "spatial",
      property?.area ? `${Number(property.area).toFixed(1)} ha expandable footprint` : "Expansion capacity missing",
      expansionPotential === null ? "Expansion capacity still needs a usable site footprint." : "The site footprint and access leave room for staged growth.",
      expansionPotential === null ? "future expansion capacity is still unclear" : "the footprint leaves room for phased expansion",
      expansionPotential === null
    ),
    transport_access: () => defaultLensMetric(
      "Transport Access",
      transportAccess,
      lensConfig.weights.transport_access,
      "infrastructure",
      property?.distToRoadKm != null
        ? `${Number(property.distToRoadKm).toFixed(2)} km to primary road`
        : roadScore === null
          ? "Transport access missing"
          : `${roadScore} / 100 access profile`,
      transportAccess === null ? "Transport routing quality still needs mapping detail." : "This score combines road access, corridor fit, and distance to primary roads.",
      transportAccess === null ? "throughput efficiency still needs mapping detail" : "transport throughput remains one of the stronger traits",
      transportAccess === null
    ),
    tourism_fit: () => defaultLensMetric(
      "Tourism Fit",
      tourismFit,
      lensConfig.weights.tourism_fit,
      "economic",
      property?.corridor ? `${humanizeIdentifier(property.corridor)} destination profile` : "Destination profile missing",
      tourismFit === null ? "Tourism fit still needs corridor context." : "Destination quality uses corridor, type, and amenity cues.",
      tourismFit === null ? "destination appeal still needs stronger evidence" : "destination fit is already working in this property's favor",
      tourismFit === null
    ),
    office_readiness: () => defaultLensMetric(
      "Office Readiness",
      officeReadiness,
      lensConfig.weights.office_readiness,
      "institutional",
      utilityStatus ? utilityStatusLabel(utilityStatus) : "Office utility profile still thin",
      officeReadiness === null ? "Office-readiness needs utility and corridor detail." : "Office-readiness blends utility depth, corridor fit, and support access.",
      officeReadiness === null ? "office-readiness still needs utility confirmation" : "the staffing and utility profile is already workable for office use",
      officeReadiness === null
    ),
    industrial_utility_fit: () => defaultLensMetric(
      "Industrial Utility Fit",
      industrialFit,
      lensConfig.weights.industrial_utility_fit,
      "infrastructure",
      utilityStatus ? utilityStatusLabel(utilityStatus) : "Industrial utility profile missing",
      industrialFit === null ? "Industrial utility fit still needs road or power detail." : "This score combines utility depth, road performance, and corridor suitability.",
      industrialFit === null ? "industrial utility readiness still needs confirmation" : "utility depth and corridor fit support heavier operations",
      industrialFit === null
    ),
    economic_viability: () => defaultLensMetric(
      "Economic Viability",
      economicBlendedScore,
      lensConfig.weights.economic_viability,
      "economic",
      assessedValueSqm > 0
        ? `PHP ${Math.round(pricePerSqm).toLocaleString()} vs assessed PHP ${Math.round(assessedValueSqm).toLocaleString()}`
        : pricePerSqm > 0
          ? `PHP ${Math.round(pricePerSqm).toLocaleString()} / sqm`
          : "Pricing inputs missing",
      economicBlendedScore === null ? "Pricing inputs still need benchmarking." : "Economic viability blends price competitiveness with assessed value spread.",
      economicBlendedScore === null ? "price benchmarking still needs more data" : "pricing still looks workable for this investment thesis",
      economicBlendedScore === null
    ),
  };

  const metrics = Object.entries(lensConfig.weights || {})
    .map(([metricKey]) => {
      const builder = metricLibrary[metricKey];
      if (!builder) {
        return defaultLensMetric(
          humanizeIdentifier(metricKey),
          null,
          lensConfig.weights[metricKey],
          "economic",
          "Metric not configured",
          "This lens metric has not been wired yet.",
          `${humanizeIdentifier(metricKey).toLowerCase()} still needs implementation`,
          true
        );
      }
      const metric = builder();
      return {
        ...metric,
        key: metricKey,
        weight: Number(lensConfig.weights[metricKey] || 0),
      };
    })
    .sort((left, right) => Number(right.contribution || 0) - Number(left.contribution || 0));

  const totalWeight = metrics.reduce((sum, metric) => sum + Number(metric.weight || 0), 0);
  const availableWeight = metrics.reduce(
    (sum, metric) => sum + (metric.score === null ? 0 : Number(metric.weight || 0)),
    0
  );
  const weightedAverage = availableWeight
    ? metrics.reduce((sum, metric) => sum + (metric.score === null ? 0 : Number(metric.score) * Number(metric.weight || 0)), 0) / availableWeight
    : 0;
  const coverageRatio = totalWeight ? availableWeight / totalWeight : 0;
  const score = Math.round(weightedAverage * coverageRatio);
  const missingMetricCount = metrics.filter((metric) => metric.score === null).length;

  const pillarMap = metrics.reduce((map, metric) => {
    if (!metric.pillar) return map;
    map[metric.pillar] ||= {
      key: metric.pillar,
      label: humanizeIdentifier(metric.pillar),
      contribution: 0,
      weight: 0,
    };
    map[metric.pillar].contribution += Number(metric.contribution || 0);
    map[metric.pillar].weight += Number(metric.weight || 0);
    return map;
  }, {});

  const emphasizedPillars = Object.values(pillarMap)
    .sort((left, right) => right.contribution - left.contribution)
    .map((pillar) => ({
      ...pillar,
      share: totalWeight ? Math.round((pillar.weight / totalWeight) * 100) : 0,
    }));

  const topMetrics = metrics.filter((metric) => metric.score !== null).slice(0, 3);
  const weakestMetric = [...metrics]
    .filter((metric) => metric.score !== null)
    .sort((left, right) => Number(left.score || 0) - Number(right.score || 0))[0] || null;

  const topFragments = topMetrics.map((metric) => metric.thesisFragment).filter(Boolean);
  let thesis = `${property?.name || "This property"} scores ${score}% for ${lensConfig.label} because ${joinFragments(topFragments.slice(0, 2)) || "its strongest lens inputs are still being established"}.`;
  if (weakestMetric && Number(weakestMetric.score || 0) < 62) {
    thesis += ` The main watchpoint is ${weakestMetric.label.toLowerCase()} (${weakestMetric.displayValue || "needs detail"}).`;
  }
  if (missingMetricCount) {
    thesis += ` ${missingMetricCount} lens input${missingMetricCount === 1 ? "" : "s"} are still missing, so this score is conservative.`;
  }

  return {
    key: lensConfig.key,
    icon: lensConfig.icon,
    label: lensConfig.label,
    shortLabel: lensConfig.shortLabel || lensConfig.label,
    subtitle: lensConfig.subtitle || "",
    score,
    status: lensStatus(score, missingMetricCount),
    metrics,
    topMetrics,
    weakestMetric,
    emphasizedPillars,
    missingMetricCount,
    readiness,
    thesis,
    thesisShort: topMetrics.length
      ? `${lensConfig.label}: ${topMetrics.slice(0, 2).map((metric) => metric.label).join(" + ")}`
      : `${lensConfig.label}: scoring still needs more data`,
  };
}

export function buildDecisionPackModel(
  property,
  decisionInputs,
  dueDiligenceState,
  dueDiligenceItems,
  weights,
  allProperties,
  targetSector,
  marketSnapshot
) {
  if (!property) {
    return null;
  }

  const capex = safeNumber(decisionInputs.capex);
  const revenue = safeNumber(decisionInputs.revenue);
  const opex = safeNumber(decisionInputs.opex);
  const netAnnual =
    revenue !== null && opex !== null ? revenue - opex : null;
  const payback =
    capex !== null && revenue !== null && opex !== null && netAnnual > 0
      ? (property.price + capex) / netAnnual
      : null;
  const dueDiligencePct = calcDueDiligencePct(dueDiligenceItems, dueDiligenceState);
  const score = calculateWeightedScore(property, allProperties, weights, targetSector);
  const riskLabel =
    decisionInputs.risk === "conservative"
      ? "LOW VOLATILITY"
      : decisionInputs.risk === "balanced"
        ? "BALANCED"
        : "HIGH GROWTH";

  return {
    score,
    riskLabel,
    netAnnual,
    payback,
    dueDiligencePct,
    marketSnapshot,
    intentLabel: String(decisionInputs.intent).replace(/_/g, " ").toUpperCase(),
    horizonLabel: `${decisionInputs.horizon} YEARS`,
    barangayLabel: property.barangay || "UNASSIGNED",
  };
}

export function buildSensitivitySeries(property, capex, revenueBase, opex) {
  const points = [];
  const totalCost = capex === null ? null : property.price + capex;

  for (let pct = -30; pct <= 30; pct += 10) {
    if (totalCost === null || revenueBase === null || opex === null) {
      points.push({ label: `${pct}%`, value: null, pct });
      continue;
    }

    const revenue = revenueBase * (1 + pct / 100);
    const netAnnual = revenue - opex;
    if (netAnnual <= 0) {
      points.push({ label: `${pct}%`, value: null, pct });
      continue;
    }

    points.push({
      label: `${pct}%`,
      value: Number((totalCost / netAnnual).toFixed(2)),
      pct,
    });
  }

  return points;
}

export function buildInvestmentLabModel(
  property,
  inputs,
  dueDiligenceState,
  dueDiligenceItems,
  weights,
  allProperties,
  targetSector,
  marketSnapshot
) {
  if (!property) {
    return null;
  }

  const capex = safeNumber(inputs.capex);
  const revenueBase = safeNumber(inputs.revenue);
  const opex = safeNumber(inputs.opex);
  const equityPct = Number(inputs.equityPct || 0) / 100;
  const interest =
    safeNumber(inputs.interest) ??
    Number(marketSnapshot?.benchmarks?.debtRate ?? 0);
  const exitCap =
    safeNumber(inputs.exitCap) ??
    Number(marketSnapshot?.benchmarks?.exitCapRate ?? 0);
  const sensitivity = Number(inputs.sensitivity || 0) / 100;
  const revenue = revenueBase === null ? null : revenueBase * (1 + sensitivity);
  const totalCost = capex === null ? null : property.price + capex;
  const equity = totalCost === null ? null : totalCost * equityPct;
  const debt = totalCost === null ? null : totalCost - equity;
  const netAnnual = revenue === null || opex === null ? null : revenue - opex;
  const debtService = debt === null || interest === null ? null : debt * interest;
  const dscr =
    netAnnual === null || debtService === null || debtService <= 0 ? null : netAnnual / debtService;
  const payback =
    netAnnual === null || totalCost === null || netAnnual <= 0 ? null : totalCost / netAnnual;
  const exitValue =
    netAnnual === null || exitCap === null || exitCap <= 0 ? null : netAnnual / exitCap;
  const dueDiligencePct = calcDueDiligencePct(dueDiligenceItems, dueDiligenceState);
  const score = calculateWeightedScore(property, allProperties, weights, targetSector);

  let readiness = 0;
  if (capex !== null) readiness += 15;
  if (revenueBase !== null) readiness += 15;
  if (opex !== null) readiness += 15;
  if (interest !== null) readiness += 10;
  if (exitCap !== null) readiness += 10;
  readiness += Math.round(dueDiligencePct * 0.35);
  readiness = clamp(readiness, 0, 100);

  let readinessMessage = "COMPLETE INPUTS AND DUE DILIGENCE";
  if (readiness >= 85) {
    readinessMessage = "INVESTOR READY. EXPORT DECISION PACK";
  } else if (readiness >= 70) {
    readinessMessage = "STRONG. FINALIZE DUE DILIGENCE";
  } else if (readiness >= 50) {
    readinessMessage = "GOOD START. ADD ASSUMPTIONS";
  } else {
    readinessMessage = "NEEDS BASELINE INPUTS";
  }

  return {
    score,
    netAnnual,
    debtService,
    dscr,
    payback,
    equity,
    debt,
    exitValue,
    readiness,
    readinessMessage,
    sensitivitySeries: buildSensitivitySeries(property, capex, revenueBase, opex),
    marketSnapshot,
  };
}

export function formatMoneyCompact(value) {
  if (value === null || value === undefined || Number.isNaN(value)) {
    return "NEEDS INPUTS";
  }
  return `PHP ${(value / 1000000).toFixed(1)}M`;
}

export function formatMoneyFull(value) {
  if (value === null || value === undefined || Number.isNaN(value)) {
    return "NEEDS INPUTS";
  }
  return `PHP ${Math.round(value).toLocaleString()}`;
}

export function formatRatio(value) {
  if (value === null || value === undefined || !Number.isFinite(value)) {
    return "NEEDS INPUTS";
  }
  return value.toFixed(2);
}

export function formatYears(value) {
  if (value === null || value === undefined || !Number.isFinite(value)) {
    return "NEEDS INPUTS";
  }
  return `${value.toFixed(1)} YEARS`;
}

export function scoreTone(score) {
  if (score >= 85) {
    return "blue";
  }
  if (score >= 75) {
    return "yellow";
  }
  if (score >= 65) {
    return "orange";
  }
  return "bad";
}

export function normalizeVoteLabel(value) {
  return String(value || "")
    .trim()
    .replace(/\s+/g, " ")
    .toUpperCase();
}

export function sortVoteEntries(votes) {
  return Object.entries(votes || {}).sort((left, right) => right[1] - left[1]);
}

export function escapeHtml(value) {
  return String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

export function chartPath(points, width = 520, height = 170, padding = 18) {
  const validPoints = points.filter((point) => Number.isFinite(point.value));
  if (!validPoints.length) {
    return "";
  }

  const values = validPoints.map((point) => point.value);
  const min = Math.min(...values);
  const max = Math.max(...values);
  const xStep = validPoints.length === 1 ? 0 : (width - padding * 2) / (validPoints.length - 1);

  return validPoints
    .map((point, index) => {
      const normalized =
        max === min ? 0.5 : (point.value - min) / (max - min);
      const x = padding + index * xStep;
      const y = height - padding - normalized * (height - padding * 2);
      return `${index === 0 ? "M" : "L"} ${x.toFixed(2)} ${y.toFixed(2)}`;
    })
    .join(" ");
}

export function chartPointsMarkup(points, width = 520, height = 170, padding = 18) {
  const validPoints = points.filter((point) => Number.isFinite(point.value));
  if (!validPoints.length) {
    return "";
  }

  const values = validPoints.map((point) => point.value);
  const min = Math.min(...values);
  const max = Math.max(...values);
  const xStep = validPoints.length === 1 ? 0 : (width - padding * 2) / (validPoints.length - 1);

  return validPoints
    .map((point, index) => {
      const normalized =
        max === min ? 0.5 : (point.value - min) / (max - min);
      const x = padding + index * xStep;
      const y = height - padding - normalized * (height - padding * 2);
      return `<circle cx="${x.toFixed(2)}" cy="${y.toFixed(2)}" r="4" fill="#1bb1f5"></circle>`;
    })
    .join("");
}

export function propertyLocation(property) {
  return `${String(property?.city || "San Fernando, La Union").toUpperCase()} | ${(property?.barangay || "UNASSIGNED").toUpperCase()}`;
}

export function createScenarioName(property) {
  const dateLabel = new Date().toISOString().slice(0, 10);
  return `${property.name} ${dateLabel} scenario`;
}
