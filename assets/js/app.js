import { api } from "./api.js";
import {
  DEFAULT_DECISION_INPUTS,
  DEFAULT_FILTERS,
  DEFAULT_LAB_INPUTS,
  DEFAULT_SCENARIO,
  DEFAULT_WEIGHTS,
  STORAGE_KEYS,
  averageScore,
  buildDecisionPackModel,
  buildInvestmentLabModel,
  calcDueDiligencePct,
  createScenarioName,
  filterProperties,
  formatMoneyFull,
  formatYears,
  loadJSON,
  normalizeVoteLabel,
  normalizeWeights,
  propertyLocation,
  rankProperties,
  saveJSON,
} from "./utils.js";
import {
  renderAnalyticsCards,
  renderComparison,
  renderDecisionPack,
  renderDueDiligence,
  renderInvestmentLab,
  renderMapCanvas,
  renderMarketStrip,
  renderMessaging,
  renderPropertyDelete,
  renderPropertyDetail,
  renderPropertyEditor,
  renderPropertyInventorySummary,
  renderPropertiesResultsSub,
  renderPropertyCollection,
  renderToasts,
  renderVoting,
} from "./renderers.js";

const state = {
  loading: true,
  error: "",
  meta: { barangays: [], dueDiligenceItems: [], votePresets: [], services: { maps: {}, marketData: {} } },
  stats: { activeInquiries: 0, globalReach: 0, marketSnapshot: null },
  properties: [],
  compareList: loadJSON(STORAGE_KEYS.compare, []).map(Number).filter((value) => Number.isFinite(value)),
  viewMode: loadJSON(STORAGE_KEYS.viewMode, "grid"),
  filters: loadJSON(STORAGE_KEYS.filters, { ...DEFAULT_FILTERS }),
  weights: normalizeWeights(loadJSON(STORAGE_KEYS.weights, { ...DEFAULT_WEIGHTS })),
  scenario: loadJSON(STORAGE_KEYS.scenario, { ...DEFAULT_SCENARIO }),
  decisionInputs: loadJSON(STORAGE_KEYS.decisionInputs, { ...DEFAULT_DECISION_INPUTS }),
  labInputs: loadJSON(STORAGE_KEYS.labInputs, { ...DEFAULT_LAB_INPUTS }),
  selectedPropertyId: Number(loadJSON(STORAGE_KEYS.selectedProperty, null)) || null,
  barangaySearch: "",
  dueDiligenceByProperty: {},
  votesByProperty: {},
  messagesByProperty: {},
  scenariosByProperty: {},
  votePreset: "",
  customVote: "",
  chatRole: loadJSON(STORAGE_KEYS.chatRole, "investor"),
  chatSenderName: loadJSON(STORAGE_KEYS.chatSenderName, "Local Analyst"),
  chatInput: "",
  propertyEditorMode: "create",
  propertyForm: {},
  propertyImageFile: null,
  activeModal: null,
  toasts: [],
};

const dom = {
  analyticsCards: document.getElementById("analyticsCards"),
  selectedPropertyHint: document.getElementById("selectedPropertyHint"),
  marketStrip: document.getElementById("marketStrip"),
  mapSubcopy: document.getElementById("mapSubcopy"),
  mapServiceChips: document.getElementById("mapServiceChips"),
  mapCanvas: document.getElementById("mapCanvas"),
  propertyInventorySummary: document.getElementById("propertyInventorySummary"),
  propertiesResultsSub: document.getElementById("propertiesResultsSub"),
  propertyCollection: document.getElementById("propertyCollection"),
  decisionBarangay: document.getElementById("decisionBarangay"),
  decisionNotes: document.getElementById("decisionNotes"),
  filterBarangay: document.getElementById("filterBarangay"),
  barangayList: document.getElementById("barangayList"),
  weightsTotal: document.getElementById("weightsTotal"),
  dueDiligenceModal: document.getElementById("dueDiligenceModal"),
  dueDiligenceModalSub: document.getElementById("dueDiligenceModalSub"),
  dueDiligenceContent: document.getElementById("dueDiligenceContent"),
  comparisonModal: document.getElementById("comparisonModal"),
  comparisonContent: document.getElementById("comparisonContent"),
  decisionPackModal: document.getElementById("decisionPackModal"),
  decisionPackModalSub: document.getElementById("decisionPackModalSub"),
  decisionPackContent: document.getElementById("decisionPackContent"),
  investmentLabModal: document.getElementById("investmentLabModal"),
  investmentLabModalSub: document.getElementById("investmentLabModalSub"),
  investmentLabContent: document.getElementById("investmentLabContent"),
  votingModal: document.getElementById("votingModal"),
  votingModalSub: document.getElementById("votingModalSub"),
  votingContent: document.getElementById("votingContent"),
  messagingModal: document.getElementById("messagingModal"),
  messagingModalSub: document.getElementById("messagingModalSub"),
  messagingContent: document.getElementById("messagingContent"),
  propertyDetailModal: document.getElementById("propertyDetailModal"),
  propertyDetailModalSub: document.getElementById("propertyDetailModalSub"),
  propertyDetailContent: document.getElementById("propertyDetailContent"),
  propertyEditorModal: document.getElementById("propertyEditorModal"),
  propertyEditorModalSub: document.getElementById("propertyEditorModalSub"),
  propertyEditorContent: document.getElementById("propertyEditorContent"),
  propertyDeleteModal: document.getElementById("propertyDeleteModal"),
  propertyDeleteModalSub: document.getElementById("propertyDeleteModalSub"),
  propertyDeleteContent: document.getElementById("propertyDeleteContent"),
  toastHost: document.getElementById("toastHost"),
};

let toastId = 1;

function defaultPropertyForm() {
  return {
    property_name: "",
    city: "San Fernando, La Union",
    barangay: "",
    property_type: "commercial",
    corridor: "highway",
    price: "",
    land_area: "",
    status: "Available",
    score: "82",
    road_access: "85",
    description: "",
    image_path: "assets/images/Property10.png",
    image_file_name: "",
    tags: "Investor Ready, Strategic Location, San Fernando",
    facilities: "Highway Access, Utilities, Commercial Activity",
  };
}

function marketSnapshot() {
  return state.stats.marketSnapshot || state.meta.services?.marketData || { benchmarks: {}, highlights: [] };
}

function allScoredProperties() {
  return rankProperties(state.properties, state.properties, state.weights, state.scenario.sector || null);
}

function visibleProperties() {
  return rankProperties(
    filterProperties(state.properties, state.filters),
    state.properties,
    state.weights,
    state.scenario.sector || null
  );
}

function selectedProperty() {
  return state.properties.find((property) => property.id === state.selectedPropertyId) || null;
}

function propertyMatchesFilters(property, filters) {
  if (!property) {
    return false;
  }

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
}

function revealPropertyInInventory(property) {
  if (propertyMatchesFilters(property, state.filters)) {
    return false;
  }

  state.filters = { ...DEFAULT_FILTERS };
  state.barangaySearch = "";
  return true;
}

function selectedScoredProperty() {
  return allScoredProperties().find((property) => property.id === state.selectedPropertyId) || selectedProperty();
}

function propertyToForm(property) {
  return {
    property_name: property?.name || "",
    city: property?.city || "San Fernando, La Union",
    barangay: property?.barangay || "",
    property_type: property?.type || "commercial",
    corridor: property?.corridor || "highway",
    price: property?.price ?? "",
    land_area: property?.area ?? "",
    status: property?.status || "Available",
    score: property?.marketScore ?? 82,
    road_access: property?.roadAccess ?? 85,
    description: property?.description || "",
    image_path: property?.imagePath || property?.imageUrl || "assets/images/Property10.png",
    image_file_name: "",
    tags: (property?.tags || []).join(", "),
    facilities: (property?.facilities || []).join(", "),
  };
}

function buildPropertyFormData() {
  const payload = {
    ...state.propertyForm,
    price: Number(state.propertyForm.price || 0),
    land_area: Number(state.propertyForm.land_area || 0),
    score: Number(state.propertyForm.score || 0),
    road_access: Number(state.propertyForm.road_access || 0),
  };

  const formData = new FormData();
  Object.entries(payload).forEach(([key, value]) => {
    if (key === "image_file_name") {
      return;
    }

    formData.append(key, value ?? "");
  });

  if (state.propertyImageFile) {
    formData.append("image_file", state.propertyImageFile);
  }

  return { payload, formData };
}

function dueDiligenceState() {
  return state.dueDiligenceByProperty[state.selectedPropertyId] || {};
}

function selectedVotes() {
  return state.votesByProperty[state.selectedPropertyId] || {};
}

function selectedMessages() {
  return state.messagesByProperty[state.selectedPropertyId] || [];
}

function selectedScenarios() {
  return state.scenariosByProperty[state.selectedPropertyId] || [];
}

function metrics() {
  return {
    totalProperties: state.properties.length,
    activeInquiries: state.stats.activeInquiries || 0,
    globalReach: state.stats.globalReach || 0,
    weightedScoreAvg: averageScore(
      state.properties,
      state.properties,
      state.weights,
      state.scenario.sector || null
    ),
  };
}

function decisionPackModel() {
  return buildDecisionPackModel(
    selectedProperty(),
    state.decisionInputs,
    dueDiligenceState(),
    state.meta.dueDiligenceItems || [],
    state.weights,
    state.properties,
    state.scenario.sector || null,
    marketSnapshot()
  );
}

function investmentLabModel() {
  return buildInvestmentLabModel(
    selectedProperty(),
    state.labInputs,
    dueDiligenceState(),
    state.meta.dueDiligenceItems || [],
    state.weights,
    state.properties,
    state.scenario.sector || null,
    marketSnapshot()
  );
}

function syncControls() {
  document.querySelectorAll("[data-decision-field]").forEach((element) => {
    const key = element.dataset.decisionField;
    if (key && key in state.decisionInputs && element !== document.activeElement) {
      element.value = state.decisionInputs[key] ?? "";
    }
  });

  document.querySelectorAll("[data-scenario-field]").forEach((element) => {
    const key = element.dataset.scenarioField;
    if (key && key in state.scenario && element !== document.activeElement) {
      element.value = state.scenario[key] ?? "";
    }
  });

  document.querySelectorAll("[data-weight-field]").forEach((element) => {
    const key = element.dataset.weightField;
    if (key && key in state.weights) {
      element.value = state.weights[key];
      const valueNode = document.getElementById(`weight${key.charAt(0).toUpperCase()}${key.slice(1)}Value`);
      if (valueNode) valueNode.textContent = state.weights[key];
    }
  });

  dom.filterBarangay.value = state.barangaySearch;
  dom.weightsTotal.textContent = Object.values(state.weights).reduce((sum, value) => sum + Number(value), 0);

  document.querySelectorAll("[data-type-filter]").forEach((button) => {
    button.classList.toggle("active", button.dataset.typeFilter === state.filters.type);
  });
  document.querySelectorAll("[data-corridor-filter]").forEach((button) => {
    button.classList.toggle("active", button.dataset.corridorFilter === state.filters.corridor);
  });
  document.querySelectorAll("[data-view-mode]").forEach((button) => {
    button.classList.toggle("active", button.dataset.viewMode === state.viewMode);
  });
}

function setModalState(name, open) {
  const modal = dom[`${name}Modal`];
  if (!modal) return;
  modal.classList.toggle("active", open);
  modal.setAttribute("aria-hidden", open ? "false" : "true");
}

function addToast(title, message, kind = "ok") {
  const id = toastId++;
  state.toasts.push({ id, title, message, kind });
  dom.toastHost.innerHTML = renderToasts(state.toasts);
  window.setTimeout(() => {
    state.toasts = state.toasts.filter((toast) => toast.id !== id);
    dom.toastHost.innerHTML = renderToasts(state.toasts);
  }, 2800);
}

function persistState() {
  saveJSON(STORAGE_KEYS.compare, state.compareList);
  saveJSON(STORAGE_KEYS.viewMode, state.viewMode);
  saveJSON(STORAGE_KEYS.filters, state.filters);
  saveJSON(STORAGE_KEYS.weights, state.weights);
  saveJSON(STORAGE_KEYS.scenario, state.scenario);
  saveJSON(STORAGE_KEYS.selectedProperty, state.selectedPropertyId);
  saveJSON(STORAGE_KEYS.decisionInputs, state.decisionInputs);
  saveJSON(STORAGE_KEYS.labInputs, state.labInputs);
  saveJSON(STORAGE_KEYS.chatRole, state.chatRole);
  saveJSON(STORAGE_KEYS.chatSenderName, state.chatSenderName);
}

function renderDashboard() {
  const scored = allScoredProperties();
  const visible = visibleProperties();
  const currentProperty = selectedProperty() || visible[0] || scored[0] || null;

  if (!state.selectedPropertyId && currentProperty) {
    state.selectedPropertyId = currentProperty.id;
    state.decisionInputs.barangay = currentProperty.barangay || "";
  }

  dom.analyticsCards.innerHTML = renderAnalyticsCards(metrics());
  dom.marketStrip.innerHTML = renderMarketStrip(marketSnapshot());
  dom.propertyInventorySummary.innerHTML = renderPropertyInventorySummary(state.properties.length, currentProperty);
  dom.mapSubcopy.textContent =
    state.meta.services?.maps?.note || "Interactive map visualization is not enabled in this demo yet.";
  dom.mapServiceChips.innerHTML = `
    <div class="service-chip ${(state.meta.services?.maps?.enabled ? "ready" : "fallback")}">
      ${String(state.meta.services?.maps?.provider || "MAP").toUpperCase()}
    </div>
    <div class="service-chip soft">${state.meta.services?.maps?.enabled ? String(state.meta.services?.maps?.mode || "live").toUpperCase() : "SUMMARY MODE"}</div>
  `;
  dom.mapCanvas.innerHTML = renderMapCanvas(scored, visible, currentProperty, state.meta.services?.maps || {});
  dom.propertiesResultsSub.textContent = renderPropertiesResultsSub(visible, state.filters);
  dom.propertyCollection.innerHTML = renderPropertyCollection(
    visible,
    state.filters,
    state.viewMode,
    state.selectedPropertyId,
    state.compareList
  );
  dom.selectedPropertyHint.textContent = currentProperty ? "SELECTED" : "SELECT A PROPERTY";
}

function renderModals() {
  const property = selectedProperty();
  const scoredProperty = selectedScoredProperty();
  const ddPercent = calcDueDiligencePct(state.meta.dueDiligenceItems || [], dueDiligenceState());

  dom.comparisonContent.innerHTML = renderComparison(
    allScoredProperties().filter((propertyItem) => state.compareList.includes(propertyItem.id))
  );
  dom.dueDiligenceModalSub.textContent = property ? propertyLocation(property) : "SELECT A PROPERTY";
  dom.dueDiligenceContent.innerHTML = renderDueDiligence(property, state.meta.dueDiligenceItems || [], dueDiligenceState(), ddPercent);
  dom.decisionPackModalSub.textContent = property ? propertyLocation(property) : "GENERATE A ONE PAGE INVESTMENT MEMO";
  dom.decisionPackContent.innerHTML = renderDecisionPack(property, decisionPackModel(), state.decisionInputs);
  dom.investmentLabModalSub.textContent = property ? propertyLocation(property) : "SELECT A PROPERTY";
  dom.investmentLabContent.innerHTML = renderInvestmentLab(property, investmentLabModel(), state.labInputs, selectedScenarios());
  dom.votingModalSub.textContent = property ? propertyLocation(property) : "COMMUNITY FIT CHECK";
  dom.votingContent.innerHTML = renderVoting(property, state.meta.votePresets || [], state.votePreset, state.customVote, selectedVotes());
  dom.messagingModalSub.textContent = property ? propertyLocation(property) : "PERSISTED PROPERTY THREAD";
  dom.messagingContent.innerHTML = renderMessaging(property, selectedMessages(), state.chatRole, state.chatSenderName, state.chatInput);
  dom.propertyDetailModalSub.textContent = property ? propertyLocation(property) : "INVESTMENT PROPERTY BRIEF";
  dom.propertyDetailContent.innerHTML = renderPropertyDetail(scoredProperty);
  dom.propertyEditorModalSub.textContent =
    state.propertyEditorMode === "edit" && property
      ? `EDIT ${property.name.toUpperCase()}`
      : "CREATE OR UPDATE MYSQL LISTINGS";
  dom.propertyEditorContent.innerHTML = renderPropertyEditor(state.propertyEditorMode, state.propertyForm);
  dom.propertyDeleteModalSub.textContent = property ? propertyLocation(property) : "THIS ACTION REMOVES THE RECORD FROM MYSQL";
  dom.propertyDeleteContent.innerHTML = renderPropertyDelete(property);

  ["comparison", "dueDiligence", "decisionPack", "investmentLab", "voting", "messaging", "propertyDetail", "propertyEditor", "propertyDelete"].forEach((name) => {
    setModalState(name, state.activeModal === name);
  });
}

function renderAll() {
  if (state.error) {
    dom.propertyCollection.innerHTML = `
      <div class="empty-state-card">
        <div class="properties-title">UNABLE TO LOAD DATA</div>
        <div class="results-sub">${state.error.toUpperCase()}</div>
      </div>
    `;
    return;
  }

  renderDashboard();
  renderModals();
  syncControls();
  persistState();
}

function setSelectedProperty(propertyId) {
  const property = state.properties.find((entry) => entry.id === Number(propertyId));
  if (!property) return;
  state.selectedPropertyId = property.id;
  state.decisionInputs.barangay = property.barangay || state.decisionInputs.barangay || "";
  renderAll();
}

async function loadBootstrap() {
  state.loading = true;
  state.error = "";

  try {
    const response = await api.bootstrap();
    state.meta = response.meta;
    state.stats = response.stats;
    state.properties = response.properties;
    dom.barangayList.innerHTML = (state.meta.barangays || [])
      .map((barangay) => `<option value="${barangay}"></option>`)
      .join("");

    if (!state.properties.some((property) => property.id === state.selectedPropertyId)) {
      state.selectedPropertyId = state.properties[0]?.id || null;
    }

    state.votePreset = state.meta.votePresets?.[0] || "";
    if (!Object.keys(state.propertyForm || {}).length) {
      state.propertyForm = defaultPropertyForm();
    }
  } catch (error) {
    state.error = error.message || "Unable to load the PHP application.";
    console.error("Bootstrap request failed", error);
  } finally {
    state.loading = false;
    renderAll();
  }
}

function mergeProperty(property) {
  state.properties = state.properties.map((entry) => (entry.id === property.id ? property : entry));
}

async function refreshPropertyInventory() {
  const response = await api.properties();
  state.properties = response.properties || [];

  if (!state.properties.some((property) => property.id === state.selectedPropertyId)) {
    state.selectedPropertyId = state.properties[0]?.id || null;
  }

  state.compareList = state.compareList.filter((propertyId) =>
    state.properties.some((property) => property.id === propertyId)
  );
}

function openPropertyCreate() {
  state.propertyEditorMode = "create";
  state.propertyForm = defaultPropertyForm();
  state.propertyImageFile = null;
  state.activeModal = "propertyEditor";
  renderAll();
}

function openPropertyEdit(propertyId = state.selectedPropertyId) {
  const resolvedId = requireSelectedProperty(propertyId);
  if (!resolvedId) return;

  const property = state.properties.find((entry) => entry.id === resolvedId);
  if (!property) return;

  state.propertyEditorMode = "edit";
  state.propertyForm = propertyToForm(property);
  state.propertyImageFile = null;
  state.activeModal = "propertyEditor";
  renderAll();
}

function openPropertyDetails(propertyId = state.selectedPropertyId) {
  const resolvedId = requireSelectedProperty(propertyId);
  if (!resolvedId) return;
  state.activeModal = "propertyDetail";
  renderAll();
}

function openPropertyDelete(propertyId = state.selectedPropertyId) {
  const resolvedId = requireSelectedProperty(propertyId);
  if (!resolvedId) return;
  state.activeModal = "propertyDelete";
  renderAll();
}

async function submitPropertyForm() {
  const { payload, formData } = buildPropertyFormData();

  if (!payload.property_name?.trim()) {
    addToast("MISSING", "ENTER A PROPERTY NAME", "warn");
    return;
  }
  if (!payload.description?.trim()) {
    addToast("MISSING", "ADD A PROPERTY DESCRIPTION", "warn");
    return;
  }
  if (payload.price <= 0 || payload.land_area <= 0) {
    addToast("INVALID", "PRICE AND LAND AREA MUST BE POSITIVE", "warn");
    return;
  }

  try {
    if (state.propertyEditorMode === "edit" && state.selectedPropertyId) {
      const response = await api.updateProperty(state.selectedPropertyId, formData);
      mergeProperty(response.property);
      await refreshPropertyInventory();
      const resetFilters = revealPropertyInInventory(response.property);
      setSelectedProperty(response.property.id);
      state.propertyForm = propertyToForm(response.property);
      state.propertyImageFile = null;
      state.activeModal = "propertyDetail";
      addToast("SAVED", "PROPERTY UPDATED", "ok");
      if (resetFilters) {
        addToast("FILTERS RESET", "SHOWING UPDATED PROPERTY", "ok");
      }
    } else {
      const response = await api.createProperty(formData);
      await refreshPropertyInventory();
      const resetFilters = revealPropertyInInventory(response.property);
      setSelectedProperty(response.property.id);
      state.propertyForm = propertyToForm(response.property);
      state.propertyImageFile = null;
      state.propertyEditorMode = "edit";
      state.activeModal = "propertyDetail";
      addToast("CREATED", "PROPERTY ADDED TO MYSQL", "ok");
      if (resetFilters) {
        addToast("FILTERS RESET", "SHOWING NEW PROPERTY", "ok");
      }
    }

    renderAll();
  } catch (error) {
    addToast("API ERROR", error.message || "UNABLE TO SAVE PROPERTY", "warn");
  }
}

async function confirmPropertyDelete() {
  const propertyId = requireSelectedProperty();
  if (!propertyId) return;

  try {
    await api.deleteProperty(propertyId);
    delete state.dueDiligenceByProperty[propertyId];
    delete state.votesByProperty[propertyId];
    delete state.messagesByProperty[propertyId];
    delete state.scenariosByProperty[propertyId];
    await refreshPropertyInventory();
    state.propertyEditorMode = "create";
    state.propertyForm = defaultPropertyForm();
    state.propertyImageFile = null;
    state.activeModal = null;
    addToast("DELETED", "PROPERTY REMOVED", "warn");
    renderAll();
  } catch (error) {
    addToast("API ERROR", error.message || "UNABLE TO DELETE PROPERTY", "warn");
  }
}

async function ensureDueDiligence(propertyId) {
  if (state.dueDiligenceByProperty[propertyId]) return;
  const response = await api.getDueDiligence(propertyId);
  state.dueDiligenceByProperty[propertyId] = response.state || {};
}

async function ensureVotes(propertyId) {
  if (state.votesByProperty[propertyId]) return;
  const response = await api.getVotes(propertyId);
  state.votesByProperty[propertyId] = response.votes || {};
}

async function ensureMessages(propertyId) {
  if (state.messagesByProperty[propertyId]) return;
  const response = await api.getMessages(propertyId);
  state.messagesByProperty[propertyId] = response.messages || [];
}

async function ensureScenarios(propertyId) {
  if (state.scenariosByProperty[propertyId]) return;
  const response = await api.getScenarios(propertyId);
  state.scenariosByProperty[propertyId] = response.scenarios || [];
}

function requireSelectedProperty(propertyId = state.selectedPropertyId) {
  if (!propertyId) {
    addToast("SELECT A PROPERTY", "CHOOSE A PROPERTY FIRST", "warn");
    return null;
  }

  setSelectedProperty(propertyId);
  return propertyId;
}

async function openModal(name, propertyId = state.selectedPropertyId) {
  const resolvedId = name === "comparison" ? state.selectedPropertyId : requireSelectedProperty(propertyId);
  if (name !== "comparison" && !resolvedId) return;

  try {
    if (name === "dueDiligence" || name === "decisionPack" || name === "investmentLab") {
      await ensureDueDiligence(resolvedId);
    }
    if (name === "voting") {
      await ensureVotes(resolvedId);
      state.votePreset = state.votePreset || state.meta.votePresets?.[0] || "";
      state.customVote = "";
    }
    if (name === "messaging") {
      await ensureMessages(resolvedId);
      state.chatInput = "";
    }
    if (name === "investmentLab") {
      await ensureScenarios(resolvedId);
    }
  } catch (error) {
    addToast("API ERROR", error.message || "REQUEST FAILED", "warn");
  }

  if (name === "comparison" && state.compareList.length < 2) {
    addToast("SELECT MORE", "PICK AT LEAST TWO PROPERTIES", "warn");
    return;
  }

  state.activeModal = name;
  renderAll();
}

function closeModal() {
  state.activeModal = null;
  renderAll();
}

function toggleCompare(propertyId) {
  const id = Number(propertyId);
  if (state.compareList.includes(id)) {
    state.compareList = state.compareList.filter((entry) => entry !== id);
    addToast("REMOVED", "FROM COMPARISON", "warn");
  } else if (state.compareList.length >= 3) {
    addToast("LIMIT", "MAX THREE PROPERTIES", "warn");
  } else {
    state.compareList = [...state.compareList, id];
    addToast("ADDED", "TO COMPARISON", "ok");
  }
  renderAll();
}

async function saveBarangay() {
  const propertyId = requireSelectedProperty();
  if (!propertyId) return;

  const barangay = (state.decisionInputs.barangay || "").trim();
  if (!barangay) {
    addToast("MISSING", "ENTER A BARANGAY", "warn");
    return;
  }
  if (!(state.meta.barangays || []).includes(barangay)) {
    addToast("NOT FOUND", "SELECT FROM THE LIST", "warn");
    return;
  }

  try {
    const response = await api.updateBarangay(propertyId, barangay);
    mergeProperty(response.property);
    state.decisionInputs.barangay = response.property.barangay || "";
    addToast("SAVED", "BARANGAY ASSIGNED", "ok");
    renderAll();
  } catch (error) {
    addToast("API ERROR", error.message || "UNABLE TO SAVE", "warn");
  }
}

async function clearBarangay() {
  const propertyId = requireSelectedProperty();
  if (!propertyId) return;

  try {
    const response = await api.updateBarangay(propertyId, null);
    mergeProperty(response.property);
    state.decisionInputs.barangay = "";
    addToast("CLEARED", "BARANGAY REMOVED", "warn");
    renderAll();
  } catch (error) {
    addToast("API ERROR", error.message || "UNABLE TO CLEAR", "warn");
  }
}

function runScenario() {
  let matches = [...state.properties];
  const budget = Number(state.scenario.budget);
  const minimumSize = Number(state.scenario.size);

  if (state.scenario.budget) {
    matches = matches.filter((property) => property.price <= budget);
  }
  if (state.scenario.sector) {
    matches = matches.filter((property) => property.type === state.scenario.sector);
  }
  if (state.scenario.size) {
    matches = matches.filter((property) => property.area >= minimumSize);
  }

  const rankedMatches = rankProperties(matches, state.properties, state.weights, state.scenario.sector || null);
  if (!rankedMatches.length) {
    addToast("NO MATCHES", "ADJUST BUDGET OR SIZE", "warn");
    return;
  }

  setSelectedProperty(rankedMatches[0].id);
  addToast("MATCHES FOUND", "OPENING TOP RESULT", "ok");
}

async function saveDueDiligenceState(nextState) {
  const propertyId = requireSelectedProperty();
  if (!propertyId) return;

  try {
    const response = await api.saveDueDiligence(propertyId, nextState);
    state.dueDiligenceByProperty[propertyId] = response.state || {};
    addToast("CHECKLIST UPDATED", "STATUS SAVED", "ok");
    renderAll();
  } catch (error) {
    addToast("API ERROR", error.message || "UNABLE TO SAVE", "warn");
  }
}

function exportDueDiligence() {
  const property = selectedProperty();
  if (!property) return;
  const lines = [
    "DUE DILIGENCE CHECKLIST",
    property.name.toUpperCase(),
    "SAN FERNANDO, LA UNION",
    "",
    ...(state.meta.dueDiligenceItems || []).map((item) =>
      `${dueDiligenceState()[item.key] ? "[X]" : "[ ]"} ${item.label}`
    ),
    "",
  ];
  const blob = new Blob([lines.join("\n")], { type: "text/plain;charset=utf-8" });
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = url;
  link.download = `due_diligence_${property.id}.txt`;
  link.click();
  URL.revokeObjectURL(url);
  addToast("EXPORTED", "CHECKLIST DOWNLOADED", "ok");
}

async function submitVote() {
  const propertyId = requireSelectedProperty();
  if (!propertyId) return;

  const label = normalizeVoteLabel(state.customVote || state.votePreset);
  if (!label) {
    addToast("MISSING", "SELECT OR TYPE A BUSINESS", "warn");
    return;
  }

  try {
    const response = await api.castVote(propertyId, label);
    state.votesByProperty[propertyId] = response.votes || {};
    state.customVote = "";
    addToast("VOTED", label, "ok");
    renderAll();
  } catch (error) {
    addToast("API ERROR", error.message || "UNABLE TO CAST VOTE", "warn");
  }
}

async function sendMessage() {
  const propertyId = requireSelectedProperty();
  if (!propertyId) return;

  const text = state.chatInput.trim();
  if (!text) {
    addToast("MISSING", "TYPE A MESSAGE", "warn");
    return;
  }

  try {
    const response = await api.sendMessage(propertyId, state.chatSenderName.trim() || "Local Analyst", state.chatRole, text);
    state.messagesByProperty[propertyId] = [...selectedMessages(), response.message];
    state.chatInput = "";
    addToast("SENT", "MESSAGE SAVED", "ok");
    renderAll();
  } catch (error) {
    addToast("API ERROR", error.message || "UNABLE TO SEND MESSAGE", "warn");
  }
}

async function clearMessages() {
  const propertyId = requireSelectedProperty();
  if (!propertyId) return;

  try {
    await api.clearMessages(propertyId);
    state.messagesByProperty[propertyId] = [];
    addToast("CLEARED", "CHAT REMOVED", "warn");
    renderAll();
  } catch (error) {
    addToast("API ERROR", error.message || "UNABLE TO CLEAR THREAD", "warn");
  }
}

async function saveScenario() {
  const propertyId = requireSelectedProperty();
  const property = selectedProperty();
  if (!propertyId || !property) return;

  try {
    const response = await api.saveScenario({
      propertyId,
      name: createScenarioName(property),
      createdBy: state.chatSenderName.trim() || "Local Analyst",
      budget: state.scenario.budget || null,
      sector: state.scenario.sector || property.type,
      size: state.scenario.size || property.area,
      weights: state.weights,
      assumptions: {
        ...state.decisionInputs,
        ...state.labInputs,
        dueDiligenceState: dueDiligenceState(),
      },
      results: investmentLabModel(),
    });
    state.scenariosByProperty[propertyId] = [response.scenario, ...selectedScenarios()];
    addToast("SCENARIO SAVED", property.name.toUpperCase(), "ok");
    renderAll();
  } catch (error) {
    addToast("API ERROR", error.message || "UNABLE TO SAVE SCENARIO", "warn");
  }
}

async function checkHealth() {
  try {
    const response = await api.health();
    addToast("API HEALTH", `${String(response.database?.name || "MYSQL").toUpperCase()} CONNECTED`, "ok");
  } catch (error) {
    addToast("API HEALTH", error.message || "HEALTH CHECK FAILED", "warn");
  }
}

function printDecisionPack() {
  const property = selectedProperty();
  const model = decisionPackModel();
  if (!property || !model) {
    addToast("SELECT A PROPERTY", "GENERATE A MEMO FIRST", "warn");
    return;
  }

  const printWindow = window.open("", "_blank");
  if (!printWindow) {
    addToast("POPUP BLOCKED", "ALLOW POPUPS TO PRINT", "warn");
    return;
  }

  printWindow.document.write(`
    <html>
      <head>
        <title>DECISION PACK</title>
        <style>
          body { font-family: Segoe UI, Arial, sans-serif; padding: 24px; color: #0A1423; }
          .wrap { max-width: 920px; margin: 0 auto; }
          .box { border: 1px solid rgba(10,20,35,0.12); border-radius: 16px; padding: 18px; }
          h1 { font-size: 16px; letter-spacing: 0.12em; margin: 0 0 8px 0; }
          .sub { font-size: 11px; letter-spacing: 0.1em; color: rgba(10,20,35,0.62); margin-bottom: 16px; }
          .section { margin-bottom: 16px; padding: 14px; border-radius: 14px; background: #F2F5FA; }
        </style>
      </head>
      <body>
        <div class="wrap">
          <div class="box">
            <h1>DECISION PACK</h1>
            <div class="sub">${property.name.toUpperCase()} | SAN FERNANDO, LA UNION</div>
            <div class="section"><strong>WEIGHTED SCORE:</strong> ${model.score}/100</div>
            <div class="section"><strong>SUMMARY:</strong> THIS SITE SUPPORTS A ${model.intentLabel} STRATEGY OVER ${model.horizonLabel}. RISK PROFILE IS ${model.riskLabel}.</div>
            <div class="section"><strong>LAND PRICE:</strong> ${formatMoneyFull(property.price)}<br><strong>NET ANNUAL:</strong> ${formatMoneyFull(model.netAnnual)}<br><strong>PAYBACK:</strong> ${formatYears(model.payback)}</div>
            <div class="section"><strong>DUE DILIGENCE:</strong> ${model.dueDiligencePct}% COMPLETE</div>
          </div>
        </div>
      </body>
    </html>
  `);
  printWindow.document.close();
  printWindow.focus();
  printWindow.print();
}

function handleClick(event) {
  const typeFilter = event.target.closest("[data-type-filter]");
  if (typeFilter) {
    state.filters.type = typeFilter.dataset.typeFilter;
    renderAll();
    return;
  }

  const corridorFilter = event.target.closest("[data-corridor-filter]");
  if (corridorFilter) {
    state.filters.corridor = corridorFilter.dataset.corridorFilter;
    renderAll();
    return;
  }

  const viewModeButton = event.target.closest("[data-view-mode]");
  if (viewModeButton) {
    state.viewMode = viewModeButton.dataset.viewMode;
    renderAll();
    return;
  }

  const closeButton = event.target.closest("[data-close-modal]");
  if (closeButton) {
    closeModal();
    return;
  }

  if (event.target.classList.contains("modal")) {
    closeModal();
    return;
  }

  const propertyAction = event.target.closest("[data-property-action]");
  if (propertyAction) {
    const propertyId = Number(propertyAction.dataset.propertyId);
    const action = propertyAction.dataset.propertyAction;
    if (action === "property-details") openPropertyDetails(propertyId);
    if (action === "property-edit") openPropertyEdit(propertyId);
    if (action === "property-delete") openPropertyDelete(propertyId);
    if (action === "due-diligence") openModal("dueDiligence", propertyId);
    if (action === "decision-pack") openModal("decisionPack", propertyId);
    if (action === "voting") openModal("voting", propertyId);
    if (action === "messaging") openModal("messaging", propertyId);
    return;
  }

  const toggleCompareInput = event.target.closest("[data-toggle-compare]");
  if (toggleCompareInput) {
    toggleCompare(toggleCompareInput.dataset.toggleCompare);
    return;
  }

  const propertyCard = event.target.closest("[data-select-property]");
  if (propertyCard) {
    setSelectedProperty(Number(propertyCard.dataset.selectProperty));
    return;
  }

  const navAction = event.target.closest("[data-nav-action]")?.dataset.navAction;
  if (navAction === "analytics") addToast("ANALYTICS", (marketSnapshot().highlights?.[0] || "MARKET SNAPSHOT READY").toUpperCase(), "ok");
  if (navAction === "compare") openModal("comparison");
  if (navAction === "decision-pack") openModal("decisionPack");
  if (navAction === "health") checkHealth();

  const action = event.target.closest("[data-action]")?.dataset.action;
  if (!action) return;
  if (action === "save-barangay") saveBarangay();
  if (action === "clear-barangay") clearBarangay();
  if (action === "open-property-create") openPropertyCreate();
  if (action === "open-property-edit") openPropertyEdit();
  if (action === "open-property-details") openPropertyDetails();
  if (action === "open-property-delete") openPropertyDelete();
  if (action === "submit-property-form") submitPropertyForm();
  if (action === "confirm-property-delete") confirmPropertyDelete();
  if (action === "run-scenario") runScenario();
  if (action === "apply-barangay-filter") applyBarangayFilter();
  if (action === "clear-barangay-filter") clearBarangayFilter();
  if (action === "reset-weights") resetWeights();
  if (action === "open-decision-pack") openModal("decisionPack");
  if (action === "open-due-diligence") openModal("dueDiligence");
  if (action === "open-investment-lab") openModal("investmentLab");
  if (action === "open-voting") openModal("voting");
  if (action === "open-messaging") openModal("messaging");
  if (action === "reset-due-diligence") saveDueDiligenceState({});
  if (action === "export-due-diligence") exportDueDiligence();
  if (action === "submit-vote") submitVote();
  if (action === "send-message") sendMessage();
  if (action === "clear-messages") clearMessages();
  if (action === "save-scenario") saveScenario();
  if (action === "print-decision-pack") printDecisionPack();
  if (action === "refresh-decision-pack") addToast("REFRESHED", "MEMO MODEL UPDATED", "ok");
  if (action === "reset-lab-inputs") resetLabInputs();
}

function handleInput(event) {
  const decisionField = event.target.dataset.decisionField;
  if (decisionField) state.decisionInputs[decisionField] = event.target.value;

  const scenarioField = event.target.dataset.scenarioField;
  if (scenarioField) state.scenario[scenarioField] = event.target.value;

  const weightField = event.target.dataset.weightField;
  if (weightField) state.weights = normalizeWeights({ ...state.weights, [weightField]: Number(event.target.value) });

  const labField = event.target.dataset.labField;
  if (labField) state.labInputs[labField] = event.target.value;

  const propertyField = event.target.dataset.propertyField;
  if (propertyField) state.propertyForm[propertyField] = event.target.value;

  if (event.target.dataset.propertyImageUpload !== undefined) {
    const file = event.target.files?.[0] || null;
    state.propertyImageFile = file;
    state.propertyForm.image_file_name = file?.name || "";
  }

  if (event.target === dom.filterBarangay) state.barangaySearch = event.target.value;
  if (event.target.dataset.voteCustom !== undefined) state.customVote = event.target.value;
  if (event.target.dataset.chatInput !== undefined) state.chatInput = event.target.value;
  if (event.target.dataset.chatSender !== undefined) state.chatSenderName = event.target.value;
  persistState();
}

function handleChange(event) {
  if (event.target.dataset.propertyImageUpload !== undefined) {
    const file = event.target.files?.[0] || null;
    state.propertyImageFile = file;
    state.propertyForm.image_file_name = file?.name || "";
    renderAll();
    return;
  }

  if (event.target.dataset.decisionField) {
    state.decisionInputs[event.target.dataset.decisionField] = event.target.value;
    renderAll();
    return;
  }
  if (event.target.dataset.scenarioField) {
    state.scenario[event.target.dataset.scenarioField] = event.target.value;
    renderAll();
    return;
  }
  if (event.target.dataset.weightField) {
    state.weights = normalizeWeights({
      ...state.weights,
      [event.target.dataset.weightField]: Number(event.target.value),
    });
    renderAll();
    return;
  }
  if (event.target.dataset.labField) {
    state.labInputs[event.target.dataset.labField] = event.target.value;
    renderAll();
    return;
  }
  if (event.target.dataset.ddKey) {
    saveDueDiligenceState({ ...dueDiligenceState(), [event.target.dataset.ddKey]: event.target.checked });
    return;
  }
  if (event.target.dataset.votePreset !== undefined) {
    state.votePreset = event.target.value;
    renderAll();
    return;
  }
  if (event.target.dataset.chatRole !== undefined) {
    state.chatRole = event.target.value;
    renderAll();
  }
}

function applyBarangayFilter() {
  const nextBarangay = state.barangaySearch.trim();
  if (!nextBarangay) {
    addToast("MISSING", "TYPE A BARANGAY", "warn");
    return;
  }
  if (!(state.meta.barangays || []).includes(nextBarangay)) {
    addToast("NOT FOUND", "SELECT FROM THE LIST", "warn");
    return;
  }
  state.filters.barangay = nextBarangay;
  addToast("FILTER ON", nextBarangay.toUpperCase(), "ok");
  renderAll();
}

function clearBarangayFilter() {
  state.barangaySearch = "";
  state.filters.barangay = "";
  addToast("FILTER OFF", "BARANGAY CLEARED", "warn");
  renderAll();
}

function resetWeights() {
  state.weights = { ...DEFAULT_WEIGHTS };
  renderAll();
}

function resetLabInputs() {
  state.labInputs = { ...DEFAULT_LAB_INPUTS };
  renderAll();
  addToast("RESET", "LAB INPUTS CLEARED", "warn");
}

function bindEvents() {
  document.addEventListener("click", handleClick);
  document.addEventListener("input", handleInput);
  document.addEventListener("change", handleChange);
  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape") closeModal();
  });
}

bindEvents();
loadBootstrap();
