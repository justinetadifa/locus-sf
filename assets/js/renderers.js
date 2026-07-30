import {
  buildResultsSubtitle,
  chartPath,
  chartPointsMarkup,
  escapeHtml,
  formatMoneyCompact,
  formatMoneyFull,
  formatRatio,
  formatYears,
  propertyLocation,
  scoreTone,
  sortVoteEntries,
} from "./utils.js";

function actionButton(propertyId, action, label, tone) {
  return `
    <button type="button" class="btn-soft" data-property-action="${action}" data-property-id="${propertyId}">
      <span class="btn-dot tone-${tone}"></span>
      ${label}
    </button>
  `;
}

const CORRIDOR_LABELS = {
  highway: "Highway Corridor",
  coastal: "Coastal Edge",
  downtown: "Downtown Core",
};

const PROPERTY_TYPE_LABELS = {
  logistics: "Logistics Hub",
  hotel: "Resort / Tourism",
  commercial: "Commercial",
  bpo: "Office / BPO",
  manufacturing: "Manufacturing",
};

function labelize(value) {
  return String(value || "")
    .replace(/[_-]+/g, " ")
    .replace(/\b\w/g, (character) => character.toUpperCase());
}

function corridorLabel(value) {
  return CORRIDOR_LABELS[value] || labelize(value);
}

function propertyTypeLabel(value) {
  return PROPERTY_TYPE_LABELS[value] || labelize(value);
}

function availabilityTone(status) {
  return String(status || "").toLowerCase() === "available" ? "ok" : "warn";
}

function compactCopy(text, maxLength = 140) {
  const normalized = String(text || "").trim();
  if (!normalized) {
    return "Presentation-ready shortlist asset with strong corridor visibility and clear investor positioning.";
  }

  if (normalized.length <= maxLength) {
    return normalized;
  }

  return `${normalized.slice(0, maxLength - 1).trimEnd()}...`;
}

function formatPercent(value) {
  return `${Math.round(Number(value || 0) * 1000) / 10}%`;
}

function formatDateLabel(value) {
  if (!value) {
    return "Just Added";
  }

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return "Recent";
  }

  return date.toLocaleDateString("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
  });
}

function corridorCoverage(properties) {
  return Object.entries(
    properties.reduce((accumulator, property) => {
      const key = String(property.corridor || "unassigned");
      accumulator[key] ??= { count: 0, totalScore: 0, totalAccess: 0 };
      accumulator[key].count += 1;
      accumulator[key].totalScore += Number(property.score || 0);
      accumulator[key].totalAccess += Number(property.roadAccess || 0);
      return accumulator;
    }, {})
  )
    .map(([key, value]) => ({
      key,
      label: corridorLabel(key),
      count: value.count,
      avgScore: Math.round(value.totalScore / value.count),
      avgAccess: Math.round(value.totalAccess / value.count),
    }))
    .sort((left, right) => right.count - left.count);
}

function renderPropertyCard(property, viewMode, selectedPropertyId, compareList) {
  const selected = property.id === selectedPropertyId;
  const compared = compareList.includes(property.id);
  const leadImage = property.media?.[0]?.url || property.media?.[0]?.source || property.imageUrl;
  const summary = compactCopy(property.description);

  return `
    <article
      class="property-card ${viewMode === "list" ? "list" : ""} ${selected ? "selected" : ""}"
      data-select-property="${property.id}"
    >
      <div class="property-media-panel">
        <div class="property-image-shell">
          <img src="${escapeHtml(leadImage)}" alt="${escapeHtml(property.name)}" class="property-image">
        </div>

        <div class="property-card-badges">
          <div class="pill">
            <span class="status-pill-dot ${availabilityTone(property.status)}"></span>
            ${escapeHtml(property.status)}
          </div>

          <div class="score-badge">
            <span class="score-dot tone-${scoreTone(property.score)}"></span>
            <span>${property.score}/100</span>
          </div>
        </div>

        <div class="property-media-meta">
          <span class="property-meta-pill accent">${escapeHtml(propertyTypeLabel(property.type))}</span>
          <span class="property-meta-pill">${escapeHtml(corridorLabel(property.corridor))}</span>
        </div>
      </div>

      <div class="property-content">
        <div class="property-heading">
          <div>
            <div class="property-title">${escapeHtml(property.name)}</div>
            <div class="property-location">${escapeHtml(propertyLocation(property))}</div>
          </div>

          <div class="property-price-callout">
            <div class="property-price-label">Guide Price</div>
            <div class="property-price-value">${escapeHtml(formatMoneyCompact(property.price))}</div>
          </div>
        </div>

        <div class="property-summary">${escapeHtml(summary)}</div>

        <div class="property-specs">
          <div class="spec-item">
            <div class="spec-label">AREA</div>
            <div class="spec-value">${escapeHtml(`${property.area} HA`)}</div>
          </div>
          <div class="spec-item">
            <div class="spec-label">PRICE PER SQM</div>
            <div class="spec-value">PHP ${Number(property.pricePerSqm).toLocaleString()}</div>
          </div>
          <div class="spec-item">
            <div class="spec-label">ACCESS</div>
            <div class="spec-value">${property.roadAccess}%</div>
          </div>
          <div class="spec-item">
            <div class="spec-label">FIT SCORE</div>
            <div class="spec-value">${property.score}/100</div>
          </div>
          <div class="spec-item">
            <div class="spec-label">MARKET SCORE</div>
            <div class="spec-value">${property.marketScore ?? 82}/100</div>
          </div>
        </div>

        <div class="property-tags">
          ${property.tags
            .slice(0, 3)
            .map((tag) => `<span class="tag">${escapeHtml(tag.toUpperCase())}</span>`)
            .join("")}
        </div>

        <div class="property-footer">
          <div class="property-card-meta">
            <div class="property-created-label">Synced ${escapeHtml(formatDateLabel(property.createdAt))}</div>
            <div class="property-city-label">${escapeHtml(String(property.city || "San Fernando, La Union").toUpperCase())}</div>
          </div>

          <div class="property-actions property-actions-crud">
            ${actionButton(property.id, "property-details", "VIEW DETAILS", "blue")}
            ${actionButton(property.id, "property-edit", "EDIT", "blue")}
            ${actionButton(property.id, "decision-pack", "MEMO", "yellow")}
            ${actionButton(property.id, "property-delete", "DELETE", "orange")}
          </div>

          <div class="compare-row">
            <div>ADD TO COMPARE</div>
            <input
              type="checkbox"
              aria-label="Compare ${escapeHtml(property.name)}"
              data-toggle-compare="${property.id}"
              ${compared ? "checked" : ""}
            >
          </div>
        </div>
      </div>
    </article>
  `;
}

export function renderAnalyticsCards(metrics) {
  return `
    <div class="stat-card">
      <div class="stat-top">
        <div class="stat-label">TOTAL PROPERTIES</div>
        <div class="stat-mark tone-blue"></div>
      </div>
      <div class="stat-value">${metrics.totalProperties}</div>
      <div class="stat-change">ACTIVE INVENTORY</div>
    </div>
    <div class="stat-card">
      <div class="stat-top">
        <div class="stat-label">ACTIVE INQUIRIES</div>
        <div class="stat-mark tone-orange"></div>
      </div>
      <div class="stat-value">${metrics.activeInquiries}</div>
      <div class="stat-change">PIPELINE SIGNAL</div>
    </div>
    <div class="stat-card">
      <div class="stat-top">
        <div class="stat-label">GLOBAL REACH</div>
        <div class="stat-mark tone-yellow"></div>
      </div>
      <div class="stat-value">${metrics.globalReach}</div>
      <div class="stat-change">COUNTRIES INTERESTED</div>
    </div>
    <div class="stat-card">
      <div class="stat-top">
        <div class="stat-label">WEIGHTED SCORE AVG</div>
        <div class="stat-mark tone-blue"></div>
      </div>
      <div class="stat-value">${metrics.weightedScoreAvg}</div>
      <div class="stat-change">QUALITY INDEX</div>
    </div>
  `;
}

export function renderMarketStrip(marketSnapshot) {
  return `
    <div class="properties-header">
      <div>
        <div class="properties-title">MARKET PULSE</div>
        <div class="results-sub">${escapeHtml(marketSnapshot.note || "Seeded market snapshot ready")}</div>
      </div>
      <div class="service-chip-row">
        <div class="service-chip ${marketSnapshot.mode === "configured" ? "ready" : "fallback"}">
          ${escapeHtml((marketSnapshot.provider || "MARKET").toUpperCase())}
        </div>
        <div class="service-chip soft">LOCAL DEMO</div>
      </div>
    </div>

    <div class="market-strip-grid">
      <section class="market-strip-card pulse-summary-card">
        <div class="pulse-kicker">Investment Climate</div>
        <div class="pulse-headline">Concise market context for shortlist reviews and live class presentations.</div>
        <div class="pulse-copy">
          Keep the dashboard realistic with benchmark guidance and current demand signals, even while
          live integrations are still being phased in.
        </div>

        <div class="pulse-metrics">
          <div class="pulse-metric">
            <div class="pulse-metric-label">Debt Rate</div>
            <div class="pulse-metric-value">${formatPercent(marketSnapshot.benchmarks?.debtRate)}</div>
          </div>
          <div class="pulse-metric">
            <div class="pulse-metric-label">Exit Cap</div>
            <div class="pulse-metric-value">${formatPercent(marketSnapshot.benchmarks?.exitCapRate)}</div>
          </div>
          <div class="pulse-metric">
            <div class="pulse-metric-label">Tourism Growth</div>
            <div class="pulse-metric-value">${formatPercent(marketSnapshot.benchmarks?.tourismGrowth)}</div>
          </div>
        </div>
      </section>

      <section class="market-strip-card">
        <div class="pulse-kicker">What Matters Now</div>
        <div class="pulse-highlight-list">
          ${(marketSnapshot.highlights || [])
            .map(
              (highlight) => `
                <div class="pulse-highlight-item">
                  <span class="pulse-highlight-dot"></span>
                  <span>${escapeHtml(String(highlight))}</span>
                </div>
              `
            )
            .join("")}
        </div>
      </section>
    </div>
  `;
}

export function renderMapCanvas(allProperties, visibleProperties, selectedProperty, serviceConfig) {
  if (!allProperties.length) {
    return `
      <section class="map-fallback-hero">
        <div class="map-fallback-eyebrow">No Inventory Loaded</div>
        <div class="map-fallback-title">Import the database schema and reload the page.</div>
        <div class="map-fallback-copy">
          The coverage overview will populate automatically once property records are available.
        </div>
      </section>
    `;
  }

  const coverageProperties = visibleProperties.length ? visibleProperties : allProperties;
  const coverageRows = corridorCoverage(coverageProperties);
  const leadProperty = selectedProperty || coverageProperties[0] || allProperties[0] || null;
  const averageVisibleScore = coverageProperties.length
    ? Math.round(
        coverageProperties.reduce((sum, property) => sum + Number(property.score || 0), 0) / coverageProperties.length
      )
    : 0;
  const topScore = coverageProperties.length
    ? Math.max(...coverageProperties.map((property) => Number(property.score || 0)))
    : 0;
  const activeCorridors = coverageRows.length;

  return `
    <div class="map-fallback-shell">
      <section class="map-fallback-hero">
        <div class="map-fallback-eyebrow">Map Visualization Coming Soon</div>
        <div class="map-fallback-title">Coverage intelligence stays presentation-ready even without live geospatial layers.</div>
        <div class="map-fallback-copy">
          ${escapeHtml(
            serviceConfig?.note ||
              "Interactive parcel plotting is not enabled in this local XAMPP build yet. This fallback keeps the dashboard useful by surfacing corridor coverage, shortlist quality, and the active site."
          )}
        </div>

        <div class="map-fallback-metrics">
          <div class="map-fallback-stat">
            <div class="map-fallback-stat-value">${coverageProperties.length}</div>
            <div class="map-fallback-stat-label">Visible Sites</div>
          </div>
          <div class="map-fallback-stat">
            <div class="map-fallback-stat-value">${activeCorridors}</div>
            <div class="map-fallback-stat-label">Coverage Zones</div>
          </div>
          <div class="map-fallback-stat">
            <div class="map-fallback-stat-value">${averageVisibleScore}</div>
            <div class="map-fallback-stat-label">Average Fit Score</div>
          </div>
          <div class="map-fallback-stat">
            <div class="map-fallback-stat-value">${topScore}</div>
            <div class="map-fallback-stat-label">Top Score</div>
          </div>
        </div>
      </section>

      <div class="map-fallback-grid">
        <section class="map-fallback-card">
          <div class="map-card-title">Coverage Zones</div>
          <div class="coverage-zone-list">
            ${coverageRows
              .map(
                (row) => `
                  <div class="coverage-zone-row">
                    <div class="coverage-zone-head">
                      <div>
                        <div class="coverage-zone-name">${escapeHtml(row.label)}</div>
                        <div class="coverage-zone-meta">${row.count} site${row.count === 1 ? "" : "s"} in view</div>
                      </div>
                      <div class="coverage-zone-score">${row.avgScore}/100</div>
                    </div>
                    <div class="coverage-zone-bar">
                      <span class="coverage-zone-fill" style="width:${row.avgAccess}%"></span>
                    </div>
                    <div class="coverage-zone-meta">Average access ${row.avgAccess}%</div>
                  </div>
                `
              )
              .join("")}
          </div>
        </section>

        <section class="map-fallback-card spotlight-card">
          <div class="map-card-title">Current Spotlight</div>
          ${
            leadProperty
              ? `
                <div class="spotlight-head">
                  <div>
                    <div class="property-title">${escapeHtml(leadProperty.name)}</div>
                    <div class="property-location">${escapeHtml(propertyLocation(leadProperty))}</div>
                  </div>
                  <div class="map-score-chip tone-${scoreTone(leadProperty.score)}">${leadProperty.score}/100</div>
                </div>

                <div class="map-focus-grid">
                  <div class="spec-item">
                    <div class="spec-label">TYPE</div>
                    <div class="spec-value">${escapeHtml(propertyTypeLabel(leadProperty.type))}</div>
                  </div>
                  <div class="spec-item">
                    <div class="spec-label">CORRIDOR</div>
                    <div class="spec-value">${escapeHtml(corridorLabel(leadProperty.corridor))}</div>
                  </div>
                  <div class="spec-item">
                    <div class="spec-label">PRICE</div>
                    <div class="spec-value">${escapeHtml(formatMoneyCompact(leadProperty.price))}</div>
                  </div>
                  <div class="spec-item">
                    <div class="spec-label">ACCESS</div>
                    <div class="spec-value">${leadProperty.roadAccess}%</div>
                  </div>
                </div>

                <div class="spotlight-copy">${escapeHtml(compactCopy(leadProperty.description, 180))}</div>
              `
              : `
                <div class="spotlight-copy">
                  Select a property to surface corridor and investment context in this panel.
                </div>
              `
          }
        </section>
      </div>
    </div>
  `;
}

export function renderPropertyCollection(properties, filters, viewMode, selectedPropertyId, compareList) {
  if (!properties.length) {
    return `
      <div class="empty-state-card">
        <div class="properties-title">NO MATCHES</div>
        <div class="results-sub">
          ADJUST SMART FILTERS OR THE SCENARIO BUILDER TO BROADEN THE RESULTS.
        </div>
      </div>
    `;
  }

  return `
    <div class="${viewMode === "grid" ? "properties-grid" : "properties-list"}">
      ${properties
        .map((property) =>
          renderPropertyCard(property, viewMode, selectedPropertyId, compareList)
        )
        .join("")}
    </div>
  `;
}

export function renderPropertiesResultsSub(properties, filters) {
  return buildResultsSubtitle(properties.length, filters);
}

export function renderPropertyInventorySummary(totalProperties, selectedProperty) {
  return `
    <div class="service-chip ready">MYSQL CRUD LIVE</div>
    <div class="service-chip soft">${totalProperties} RECORD${totalProperties === 1 ? "" : "S"}</div>
    <div class="service-chip soft">
      ${escapeHtml(String(selectedProperty?.name || "NO PROPERTY SELECTED").toUpperCase())}
    </div>
  `;
}

export function renderPropertyDetail(property) {
  if (!property) {
    return `
      <div class="empty-state-card">
        <div class="properties-title">SELECT A PROPERTY</div>
        <div class="results-sub">Choose a listing card to open the property brief.</div>
      </div>
    `;
  }

  const leadImage = property.media?.[0]?.url || property.imageUrl;
  const tags = property.tags || [];
  const facilities = property.facilities || [];

  return `
    <div class="property-detail-layout">
      <section class="property-detail-hero">
        <div class="property-detail-image-shell">
          <img src="${escapeHtml(leadImage)}" alt="${escapeHtml(property.name)}" class="property-detail-image">
        </div>

        <div class="property-detail-copy">
          <div class="property-detail-kicker">Property Brief</div>
          <div class="property-detail-title">${escapeHtml(property.name)}</div>
          <div class="property-location">${escapeHtml(propertyLocation(property))}</div>
          <div class="property-detail-description">${escapeHtml(property.description)}</div>

          <div class="property-tags">
            <span class="property-meta-pill accent">${escapeHtml(propertyTypeLabel(property.type))}</span>
            <span class="property-meta-pill">${escapeHtml(corridorLabel(property.corridor))}</span>
            <span class="property-meta-pill">${escapeHtml(String(property.status).toUpperCase())}</span>
          </div>

          <div class="property-detail-actions">
            <button type="button" class="btn btn-primary" data-action="open-decision-pack">
              <span class="btn-dot"></span>
              DECISION PACK
            </button>
            <button type="button" class="btn btn-secondary" data-action="open-investment-lab">
              <span class="btn-dot tone-blue"></span>
              INVESTMENT LAB
            </button>
            <button type="button" class="btn btn-secondary" data-action="open-due-diligence">
              <span class="btn-dot tone-orange"></span>
              DUE DILIGENCE
            </button>
          </div>
        </div>
      </section>

      <div class="property-detail-grid">
        <section class="pack-section">
          <h4>Investment Snapshot</h4>
          <div class="property-detail-metrics">
            <div class="kpi"><div class="k">Guide Price</div><div class="v">${escapeHtml(formatMoneyCompact(property.price).toUpperCase())}</div></div>
            <div class="kpi"><div class="k">Land Area</div><div class="v">${escapeHtml(`${property.area} HA`)}</div></div>
            <div class="kpi"><div class="k">Weighted Fit</div><div class="v">${property.score ?? 0}/100</div></div>
            <div class="kpi"><div class="k">Market Score</div><div class="v">${property.marketScore ?? 82}/100</div></div>
            <div class="kpi"><div class="k">Road Access</div><div class="v">${property.roadAccess}%</div></div>
            <div class="kpi"><div class="k">Price / SQM</div><div class="v">PHP ${Number(property.pricePerSqm || 0).toLocaleString()}</div></div>
          </div>
        </section>

        <section class="pack-section">
          <h4>Listing Data</h4>
          <div class="property-detail-data">
            <div class="detail-copy">CITY: ${escapeHtml(String(property.city || "San Fernando, La Union").toUpperCase())}</div>
            <div class="detail-copy">BARANGAY: ${escapeHtml(String(property.barangay || "UNASSIGNED").toUpperCase())}</div>
            <div class="detail-copy">STATUS: ${escapeHtml(String(property.status).toUpperCase())}</div>
            <div class="detail-copy">CREATED: ${escapeHtml(formatDateLabel(property.createdAt).toUpperCase())}</div>
            <div class="detail-copy">IMAGE PATH: ${escapeHtml(String(property.imagePath || property.imageUrl).toUpperCase())}</div>
          </div>
        </section>

        <section class="pack-section">
          <h4>Positioning Tags</h4>
          <div class="property-tags">
            ${tags.length
              ? tags.map((tag) => `<span class="tag">${escapeHtml(String(tag).toUpperCase())}</span>`).join("")
              : `<div class="tiny">NO TAGS SAVED</div>`}
          </div>
        </section>

        <section class="pack-section">
          <h4>Nearby Drivers</h4>
          <div class="property-tags">
            ${facilities.length
              ? facilities.map((item) => `<span class="tag">${escapeHtml(String(item).toUpperCase())}</span>`).join("")
              : `<div class="tiny">NO FACILITIES SAVED</div>`}
          </div>
        </section>

        <section class="pack-section">
          <h4>Property Actions</h4>
          <div class="btn-row">
            <button type="button" class="btn btn-secondary" data-action="open-property-edit">
              <span class="btn-dot tone-blue"></span>
              EDIT PROPERTY
            </button>
            <button type="button" class="btn btn-secondary" data-action="open-voting">
              <span class="btn-dot tone-yellow"></span>
              OPEN VOTING
            </button>
            <button type="button" class="btn btn-secondary" data-action="open-messaging">
              <span class="btn-dot tone-blue"></span>
              OPEN THREAD
            </button>
            <button type="button" class="btn btn-danger" data-action="open-property-delete">
              <span class="btn-dot tone-orange"></span>
              DELETE PROPERTY
            </button>
          </div>
        </section>
      </div>
    </div>
  `;
}

export function renderPropertyEditor(mode, form) {
  const editing = mode === "edit";

  return `
    <div class="property-editor-shell">
      <div class="property-editor-intro">
        <div class="pack-h">${editing ? "Edit Property" : "Add Property"}</div>
        <div class="results-sub">
          ${editing
            ? "Update the listing record and keep the investment dashboard aligned with MySQL."
            : "Create a new inventory record directly inside the dashboard."}
        </div>
      </div>

      <div class="property-editor-grid">
        <div class="form-group">
          <label class="form-label">PROPERTY NAME</label>
          <input class="form-input" data-property-field="property_name" value="${escapeHtml(form.property_name || "")}" placeholder="La Union Innovation Campus">
        </div>

        <div class="form-group">
          <label class="form-label">CITY</label>
          <input class="form-input" data-property-field="city" value="${escapeHtml(form.city || "")}" placeholder="San Fernando, La Union">
        </div>

        <div class="form-group">
          <label class="form-label">BARANGAY</label>
          <input class="form-input" data-property-field="barangay" list="barangayList" value="${escapeHtml(form.barangay || "")}" placeholder="Catbangen">
        </div>

        <div class="form-group">
          <label class="form-label">PROPERTY TYPE</label>
          <select class="form-select" data-property-field="property_type">
            ${["logistics", "hotel", "commercial", "bpo", "manufacturing"]
              .map(
                (option) => `
                  <option value="${option}" ${form.property_type === option ? "selected" : ""}>
                    ${escapeHtml(propertyTypeLabel(option).toUpperCase())}
                  </option>
                `
              )
              .join("")}
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">CORRIDOR</label>
          <select class="form-select" data-property-field="corridor">
            ${["highway", "downtown", "coastal"]
              .map(
                (option) => `
                  <option value="${option}" ${form.corridor === option ? "selected" : ""}>
                    ${escapeHtml(corridorLabel(option).toUpperCase())}
                  </option>
                `
              )
              .join("")}
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">STATUS</label>
          <select class="form-select" data-property-field="status">
            ${["Available", "Reserved", "Under Review", "Negotiating"]
              .map(
                (option) => `
                  <option value="${option}" ${form.status === option ? "selected" : ""}>
                    ${escapeHtml(String(option).toUpperCase())}
                  </option>
                `
              )
              .join("")}
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">PRICE (PHP)</label>
          <input type="number" class="form-input" data-property-field="price" value="${escapeHtml(String(form.price || ""))}" placeholder="85000000">
        </div>

        <div class="form-group">
          <label class="form-label">LAND AREA (HECTARES)</label>
          <input type="number" step="0.1" class="form-input" data-property-field="land_area" value="${escapeHtml(String(form.land_area || ""))}" placeholder="9.5">
        </div>

        <div class="form-group">
          <label class="form-label">MARKET SCORE</label>
          <input type="number" min="40" max="100" class="form-input" data-property-field="score" value="${escapeHtml(String(form.score || ""))}" placeholder="84">
        </div>

        <div class="form-group">
          <label class="form-label">ROAD ACCESS %</label>
          <input type="number" min="40" max="100" class="form-input" data-property-field="road_access" value="${escapeHtml(String(form.road_access || ""))}" placeholder="88">
        </div>

        <div class="form-group property-editor-span-2">
          <label class="form-label">DESCRIPTION</label>
          <textarea class="form-textarea" data-property-field="description" placeholder="High-confidence investment site positioned for logistics, mixed-use, or institutional demand.">${escapeHtml(form.description || "")}</textarea>
        </div>

        <div class="form-group property-editor-span-2">
          <label class="form-label">UPLOAD IMAGE</label>
          <label class="property-upload-field">
            <input type="file" class="property-upload-input" accept="image/*" data-property-image-upload>
            <span class="property-upload-copy">
              <span class="property-upload-title">${escapeHtml(form.image_file_name || "Choose a property image")}</span>
              <span class="property-upload-subtitle">JPG, PNG, WEBP, or GIF. Upload replaces the current card image.</span>
            </span>
          </label>
        </div>

        <div class="form-group property-editor-span-2">
          <label class="form-label">IMAGE PATH</label>
          <input class="form-input" data-property-field="image_path" value="${escapeHtml(form.image_path || "")}" placeholder="assets/images/Property10.png">
          <div class="field-hint">Use this only if you want to point to an existing local file. Uploaded images are saved automatically into <code>assets/uploads/properties</code>.</div>
        </div>

        <div class="form-group property-editor-span-2">
          <label class="form-label">POSITIONING TAGS</label>
          <input class="form-input" data-property-field="tags" value="${escapeHtml(form.tags || "")}" placeholder="Investor Ready, Highway Visibility, Institutional Fit">
        </div>

        <div class="form-group property-editor-span-2">
          <label class="form-label">FACILITIES / DRIVERS</label>
          <input class="form-input" data-property-field="facilities" value="${escapeHtml(form.facilities || "")}" placeholder="Highway Access, Utilities, Commercial District">
        </div>
      </div>

      <div class="btn-row modal-actions">
        ${editing
          ? `
            <button type="button" class="btn btn-danger" data-action="open-property-delete">
              <span class="btn-dot tone-orange"></span>
              DELETE PROPERTY
            </button>
          `
          : ""}
        <button type="button" class="btn btn-secondary" data-close-modal="propertyEditor">CANCEL</button>
        <button type="button" class="btn btn-primary" data-action="submit-property-form">
          <span class="btn-dot"></span>
          ${editing ? "SAVE CHANGES" : "CREATE PROPERTY"}
        </button>
      </div>
    </div>
  `;
}

export function renderPropertyDelete(property) {
  if (!property) {
    return "";
  }

  return `
    <div class="property-delete-card">
      <div class="property-delete-kicker">Delete Listing</div>
      <div class="property-delete-title">${escapeHtml(property.name)}</div>
      <div class="property-location">${escapeHtml(propertyLocation(property))}</div>
      <div class="property-delete-copy">
        This removes the property from the live MySQL inventory and also clears related due diligence,
        messaging, votes, and saved scenarios through database cascades.
      </div>

      <div class="property-delete-data">
        <div class="detail-copy">PRICE ${escapeHtml(formatMoneyCompact(property.price).toUpperCase())}</div>
        <div class="detail-copy">TYPE ${escapeHtml(propertyTypeLabel(property.type).toUpperCase())}</div>
        <div class="detail-copy">STATUS ${escapeHtml(String(property.status).toUpperCase())}</div>
      </div>

      <div class="btn-row modal-actions">
        <button type="button" class="btn btn-secondary" data-close-modal="propertyDelete">KEEP PROPERTY</button>
        <button type="button" class="btn btn-danger" data-action="confirm-property-delete">
          <span class="btn-dot tone-orange"></span>
          DELETE NOW
        </button>
      </div>
    </div>
  `;
}

export function renderComparison(compareProperties) {
  if (compareProperties.length < 2) {
    return `
      <div class="empty-state-card">
        <div class="properties-title">SELECT MORE</div>
        <div class="results-sub">PICK AT LEAST TWO PROPERTIES TO OPEN COMPARISON.</div>
      </div>
    `;
  }

  const headers = compareProperties
    .map((property) => `<th>${escapeHtml(property.name.toUpperCase())}</th>`)
    .join("");

  const row = (label, getter) => `
    <tr>
      <td>${label}</td>
      ${compareProperties.map((property) => `<td>${getter(property)}</td>`).join("")}
    </tr>
  `;

  return `
    <div class="comparison-table-wrap">
      <table class="comparison-table">
        <thead>
          <tr>
            <th>FIELD</th>
            ${headers}
          </tr>
        </thead>
        <tbody>
          ${row("WEIGHTED SCORE", (property) => `${property.score}/100`)}
          ${row("AREA", (property) => `${property.area} HA`)}
          ${row("PRICE", (property) => formatMoneyFull(property.price))}
          ${row("TYPE", (property) => escapeHtml(property.type.toUpperCase()))}
          ${row("CORRIDOR", (property) => escapeHtml(property.corridor.toUpperCase()))}
          ${row("BARANGAY", (property) => escapeHtml((property.barangay || "UNASSIGNED").toUpperCase()))}
          ${row("ACCESS", (property) => `${property.roadAccess}%`)}
          ${row("TAGS", (property) => escapeHtml(property.tags.join(", ").toUpperCase()))}
        </tbody>
      </table>
    </div>
  `;
}

export function renderDueDiligence(property, items, state, percent) {
  if (!property) {
    return `
      <div class="empty-state-card">
        <div class="properties-title">SELECT A PROPERTY</div>
      </div>
    `;
  }

  return `
    <div class="pack-section">
      <h4>CHECKLIST COMPLETION</h4>
      <div class="pack-head no-margin">
        <div class="tiny">${escapeHtml(propertyLocation(property))}</div>
        <div class="pack-chip"><span class="c"></span>${percent}% COMPLETE</div>
      </div>
      <div class="dd-progress">
        <div class="dd-bar" style="width:${percent}%;"></div>
      </div>
    </div>

    <div class="dd-list dd-modal-list">
      ${items
        .map((item) => {
          const checked = Boolean(state[item.key]);
          return `
            <label class="dd-item">
              <div class="dd-left">
                <input
                  class="dd-check"
                  type="checkbox"
                  data-dd-key="${escapeHtml(item.key)}"
                  ${checked ? "checked" : ""}
                >
                <div class="dd-name">${escapeHtml(item.label)}</div>
              </div>
              <div class="dd-status">
                <span class="dd-dot ${checked ? "tone-ok" : "tone-warn"}"></span>
                <span>${checked ? "VERIFIED" : "PENDING"}</span>
              </div>
            </label>
          `;
        })
        .join("")}
    </div>

    <div class="btn-row modal-actions">
      <button type="button" class="btn btn-secondary" data-action="reset-due-diligence">
        <span class="btn-dot tone-orange"></span>
        RESET
      </button>
      <button type="button" class="btn btn-primary" data-action="export-due-diligence">
        <span class="btn-dot"></span>
        EXPORT CHECKLIST
      </button>
    </div>
  `;
}

export function renderDecisionPack(property, model, inputs) {
  if (!property || !model) {
    return "";
  }

  return `
    <div class="pack-grid">
      <div class="pack-panel">
        <div class="pack-head">
          <div class="pack-h">INPUTS</div>
          <div class="pack-chip"><span class="c"></span>FAST MEMO</div>
        </div>

        <div class="form-group">
          <label class="form-label">CAPEX ESTIMATE (PHP)</label>
          <input type="number" class="form-input" data-decision-field="capex" value="${escapeHtml(inputs.capex)}" placeholder="150000000">
        </div>
        <div class="form-group">
          <label class="form-label">ANNUAL REVENUE (PHP)</label>
          <input type="number" class="form-input" data-decision-field="revenue" value="${escapeHtml(inputs.revenue)}" placeholder="60000000">
        </div>
        <div class="form-group">
          <label class="form-label">ANNUAL OPEX (PHP)</label>
          <input type="number" class="form-input" data-decision-field="opex" value="${escapeHtml(inputs.opex)}" placeholder="15000000">
        </div>
        <div class="form-group">
          <label class="form-label">MODEL NOTES</label>
          <textarea class="form-textarea" data-decision-field="modelNotes" placeholder="RENT RATE, OCCUPANCY, ADR, OR LEASE TERMS">${escapeHtml(inputs.modelNotes)}</textarea>
        </div>

        <div class="pack-section">
          <h4>OWNER CONTACT</h4>
          <div class="detail-copy">${escapeHtml(String(property.ownerContact?.name || "UNASSIGNED").toUpperCase())}</div>
          <div class="tiny">${escapeHtml(String(property.ownerContact?.email || "NO EMAIL").toUpperCase())}</div>
          <div class="tiny">${escapeHtml(String(property.ownerContact?.phone || "NO PHONE").toUpperCase())}</div>
          <div class="tiny">SLA ${escapeHtml(String(property.ownerContact?.responseSla || "N/A").toUpperCase())}</div>
        </div>

        <div class="btn-row">
          <button type="button" class="btn btn-primary" data-action="refresh-decision-pack">
            <span class="btn-dot"></span>
            REFRESH MEMO
          </button>
          <button type="button" class="btn btn-warn" data-action="print-decision-pack">
            <span class="btn-dot"></span>
            PRINT OR SAVE PDF
          </button>
        </div>
        <div class="tiny spacer-top-xs">SIMPLIFIED MODEL. NOT FINANCIAL ADVICE.</div>
      </div>

      <div class="pack-panel">
        <div class="pack-head">
          <div class="pack-h">INVESTMENT MEMO</div>
          <div class="pack-chip">
            <span class="c tone-${scoreTone(model.score)}"></span>
            WEIGHTED SCORE ${model.score}/100
          </div>
        </div>

        <div class="pack-section">
          <h4>SITE DETAILS</h4>
          <div class="tiny">BARANGAY ${escapeHtml(model.barangayLabel.toUpperCase())}</div>
          <div class="detail-copy">${escapeHtml(property.description.toUpperCase())}</div>
        </div>

        <div class="pack-section">
          <div class="pack-head no-margin">
            <h4>PROPERTY GALLERY</h4>
            <div class="pack-chip"><span class="c"></span>${property.media.length} IMAGE${property.media.length === 1 ? "" : "S"}</div>
          </div>
          <div class="media-gallery">
            ${property.media
              .map(
                (media) => `
                  <a href="${escapeHtml(media.url || media.source)}" target="_blank" rel="noreferrer" class="media-thumb">
                    <img src="${escapeHtml(media.url || media.source)}" alt="${escapeHtml(media.altText || property.name)}" class="media-thumb-image">
                  </a>
                `
              )
              .join("")}
          </div>
        </div>

        <div class="pack-section">
          <h4>DECISION SUMMARY</h4>
          <div class="summary-copy">
            THIS SITE SUPPORTS A <span class="tone-blue-text">${escapeHtml(model.intentLabel)}</span>
            STRATEGY OVER <span class="tone-orange-text">${escapeHtml(model.horizonLabel)}</span>.
            RISK PROFILE IS <span class="tone-yellow-text">${escapeHtml(model.riskLabel)}</span>.
          </div>
        </div>

        <div class="pack-section">
          <h4>KEY NUMBERS</h4>
          <div class="pack-kpis">
            <div class="kpi"><div class="k">LAND PRICE</div><div class="v">${escapeHtml(formatMoneyCompact(property.price).toUpperCase())}</div></div>
            <div class="kpi"><div class="k">NET ANNUAL</div><div class="v">${escapeHtml(formatMoneyFull(model.netAnnual).toUpperCase())}</div></div>
            <div class="kpi"><div class="k">PAYBACK</div><div class="v">${escapeHtml(formatYears(model.payback).toUpperCase())}</div></div>
          </div>
          <div class="tiny">
            AREA ${property.area} HA. PRICE PER SQM PHP ${Number(property.pricePerSqm).toLocaleString()}. ACCESS ${property.roadAccess}%.
          </div>
        </div>

        <div class="pack-section">
          <h4>DUE DILIGENCE STATUS</h4>
          <div class="pack-head no-margin">
            <div class="tiny">CHECKLIST COMPLETION</div>
            <div class="pack-chip"><span class="c"></span>${model.dueDiligencePct}% COMPLETE</div>
          </div>
          <div class="dd-progress">
            <div class="dd-bar" style="width:${model.dueDiligencePct}%;"></div>
          </div>
          <div class="btn-row compact-gap">
            <button type="button" class="btn btn-secondary" data-action="open-due-diligence">
              <span class="btn-dot tone-orange"></span>
              OPEN CHECKLIST
            </button>
            <button type="button" class="btn btn-secondary" data-action="open-voting">
              <span class="btn-dot tone-yellow"></span>
              OPEN VOTING
            </button>
            <button type="button" class="btn btn-secondary" data-action="open-messaging">
              <span class="btn-dot tone-blue"></span>
              CHAT OWNER
            </button>
          </div>
        </div>

        <div class="pack-section">
          <h4>ASSUMPTIONS AND NOTES</h4>
          <div class="detail-copy">${escapeHtml((inputs.notes || "NO NOTES PROVIDED.").toUpperCase())}</div>
          <div class="tiny">${escapeHtml((inputs.modelNotes || "NO MODEL NOTES PROVIDED.").toUpperCase())}</div>
        </div>

        <div class="pack-section">
          <h4>MARKET CONTEXT</h4>
          <div class="tiny">
            DEBT BENCHMARK ${Math.round((model.marketSnapshot?.benchmarks?.debtRate || 0) * 1000) / 10}%,
            EXIT CAP ${Math.round((model.marketSnapshot?.benchmarks?.exitCapRate || 0) * 1000) / 10}%.
          </div>
          ${(model.marketSnapshot?.highlights || [])
            .map((highlight) => `<div class="detail-copy muted-copy">${escapeHtml(String(highlight).toUpperCase())}</div>`)
            .join("")}
        </div>
      </div>
    </div>
  `;
}

export function renderInvestmentLab(property, model, inputs, scenarios) {
  if (!property || !model) {
    return "";
  }

  const path = chartPath(model.sensitivitySeries);
  const pointsMarkup = chartPointsMarkup(model.sensitivitySeries);

  return `
    <div class="pack-grid">
      <div class="pack-panel">
        <div class="pack-head">
          <div class="pack-h">MODEL INPUTS</div>
          <div class="pack-chip"><span class="c"></span>SCENARIO READY</div>
        </div>

        <div class="form-group">
          <label class="form-label">CAPEX ESTIMATE (PHP)</label>
          <input type="number" class="form-input" data-lab-field="capex" value="${escapeHtml(inputs.capex)}" placeholder="150000000">
        </div>
        <div class="form-group">
          <label class="form-label">ANNUAL REVENUE (PHP)</label>
          <input type="number" class="form-input" data-lab-field="revenue" value="${escapeHtml(inputs.revenue)}" placeholder="60000000">
        </div>
        <div class="form-group">
          <label class="form-label">ANNUAL OPEX (PHP)</label>
          <input type="number" class="form-input" data-lab-field="opex" value="${escapeHtml(inputs.opex)}" placeholder="15000000">
        </div>
        <div class="form-group">
          <label class="form-label">EQUITY SPLIT ${escapeHtml(`${inputs.equityPct}%`)}</label>
          <input type="range" min="10" max="90" data-lab-field="equityPct" value="${escapeHtml(String(inputs.equityPct))}">
        </div>
        <div class="form-group">
          <label class="form-label">DEBT RATE</label>
          <input type="number" step="0.001" class="form-input" data-lab-field="interest" value="${escapeHtml(inputs.interest)}" placeholder="0.102">
        </div>
        <div class="form-group">
          <label class="form-label">EXIT CAP RATE</label>
          <input type="number" step="0.001" class="form-input" data-lab-field="exitCap" value="${escapeHtml(inputs.exitCap)}" placeholder="0.088">
        </div>
        <div class="form-group">
          <label class="form-label">REVENUE SENSITIVITY ${escapeHtml(`${inputs.sensitivity}%`)}</label>
          <input type="range" min="-30" max="30" step="10" data-lab-field="sensitivity" value="${escapeHtml(String(inputs.sensitivity))}">
        </div>

        <div class="btn-row">
          <button type="button" class="btn btn-secondary" data-action="reset-lab-inputs">
            <span class="btn-dot tone-orange"></span>
            RESET INPUTS
          </button>
          <button type="button" class="btn btn-primary" data-action="save-scenario">
            <span class="btn-dot"></span>
            SAVE SCENARIO
          </button>
        </div>

        <div class="pack-section">
          <h4>MARKET BENCHMARKS</h4>
          <div class="tiny">
            DEBT RATE ${Math.round((model.marketSnapshot?.benchmarks?.debtRate || 0) * 1000) / 10}% |
            EXIT CAP ${Math.round((model.marketSnapshot?.benchmarks?.exitCapRate || 0) * 1000) / 10}% |
            INFLATION ${Math.round((model.marketSnapshot?.benchmarks?.inflation || 0) * 1000) / 10}%
          </div>
        </div>
      </div>

      <div class="pack-panel">
        <div class="pack-head">
          <div class="pack-h">OUTPUTS</div>
          <div class="pack-chip"><span class="c tone-${scoreTone(model.score)}"></span>WEIGHTED SCORE ${model.score}/100</div>
        </div>

        <div class="pack-kpis">
          <div class="kpi"><div class="k">NET ANNUAL</div><div class="v">${escapeHtml(formatMoneyFull(model.netAnnual).toUpperCase())}</div></div>
          <div class="kpi"><div class="k">DSCR</div><div class="v">${escapeHtml(formatRatio(model.dscr).toUpperCase())}</div></div>
          <div class="kpi"><div class="k">PAYBACK</div><div class="v">${escapeHtml(formatYears(model.payback).toUpperCase())}</div></div>
          <div class="kpi"><div class="k">EQUITY</div><div class="v">${escapeHtml(formatMoneyFull(model.equity).toUpperCase())}</div></div>
          <div class="kpi"><div class="k">DEBT</div><div class="v">${escapeHtml(formatMoneyFull(model.debt).toUpperCase())}</div></div>
          <div class="kpi"><div class="k">EXIT VALUE</div><div class="v">${escapeHtml(formatMoneyFull(model.exitValue).toUpperCase())}</div></div>
        </div>

        <div class="pack-section">
          <h4>READINESS</h4>
          <div class="pack-head no-margin">
            <div class="tiny">${escapeHtml(propertyLocation(property))}</div>
            <div class="pack-chip"><span class="c"></span>${model.readiness}/100</div>
          </div>
          <div class="dd-progress"><div class="dd-bar" style="width:${model.readiness}%;"></div></div>
          <div class="detail-copy">${escapeHtml(model.readinessMessage.toUpperCase())}</div>
        </div>

        <div class="pack-section">
          <h4>SENSITIVITY</h4>
          <div class="chart-shell">
            ${
              path
                ? `
                  <svg class="chart-svg" viewBox="0 0 520 170" role="img" aria-label="Sensitivity chart">
                    <line x1="18" y1="152" x2="502" y2="152" class="chart-axis"></line>
                    <line x1="18" y1="18" x2="18" y2="152" class="chart-axis"></line>
                    <path d="${path}" fill="none" stroke="#1bb1f5" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></path>
                    ${pointsMarkup}
                  </svg>
                `
                : `
                  <div class="empty-state-card">
                    <div class="properties-title">NEEDS INPUTS</div>
                    <div class="results-sub">ADD CAPEX, REVENUE, AND OPEX TO DRAW THE CHART.</div>
                  </div>
                `
            }
            <div class="chart-label-row">
              ${model.sensitivitySeries.map((point) => `<span>${escapeHtml(point.label)}</span>`).join("")}
            </div>
          </div>
        </div>

        <div class="pack-section">
          <h4>SCENARIO HISTORY</h4>
          ${
            scenarios.length
              ? scenarios
                  .map(
                    (scenario) => `
                      <div class="pack-section">
                        <div class="detail-copy">${escapeHtml(String(scenario.name).toUpperCase())}</div>
                        <div class="tiny">${escapeHtml(String(scenario.createdBy).toUpperCase())} | ${escapeHtml(String(scenario.createdAt).replace("T", " ").slice(0, 16).toUpperCase())}</div>
                      </div>
                    `
                  )
                  .join("")
              : `<div class="tiny">NO SAVED SCENARIOS YET</div>`
          }
        </div>
      </div>
    </div>
  `;
}

export function renderVoting(property, votePresets, selectedPreset, customVote, votes) {
  if (!property) {
    return "";
  }

  const entries = sortVoteEntries(votes);
  const total = entries.reduce((sum, entry) => sum + Number(entry[1]), 0);

  return `
    <div class="pack-section">
      <h4>CAST A VOTE</h4>
      <div class="form-group">
        <label class="form-label">PRESET</label>
        <select class="form-select" data-vote-preset>
          ${votePresets
            .map(
              (preset) => `
                <option value="${escapeHtml(preset)}" ${preset === selectedPreset ? "selected" : ""}>
                  ${escapeHtml(preset)}
                </option>
              `
            )
            .join("")}
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">CUSTOM LABEL</label>
        <input class="form-input" data-vote-custom value="${escapeHtml(customVote)}" placeholder="TYPE A BUSINESS IDEA">
      </div>
      <button type="button" class="btn btn-primary" data-action="submit-vote">
        <span class="btn-dot"></span>
        CAST VOTE
      </button>
    </div>

    <div class="pack-section">
      <div class="vote-row-head">
        <span>RESULTS</span>
        <span>${total} VOTES</span>
      </div>
      <div class="vote-results">
        ${
          entries.length
            ? entries
                .map(([label, count]) => {
                  const pct = total > 0 ? Math.round((count / total) * 100) : 0;
                  return `
                    <div class="vote-row">
                      <div class="vote-row-head">
                        <span>${escapeHtml(String(label).toUpperCase())}</span>
                        <span>${count} (${pct}%)</span>
                      </div>
                      <div class="dd-progress">
                        <div class="dd-bar" style="width:${pct}%;"></div>
                      </div>
                    </div>
                  `;
                })
                .join("")
            : `<div class="tiny">NO VOTES YET. BE THE FIRST TO VOTE.</div>`
        }
      </div>
    </div>
  `;
}

export function renderMessaging(property, messages, chatRole, senderName, chatInput) {
  if (!property) {
    return "";
  }

  return `
    <div class="pack-grid">
      <div class="pack-panel">
        <div class="pack-head">
          <div class="pack-h">PARTICIPANT</div>
          <div class="pack-chip"><span class="c"></span>MYSQL THREAD</div>
        </div>

        <div class="form-group">
          <label class="form-label">ROLE</label>
          <select class="form-select" data-chat-role>
            <option value="investor" ${chatRole === "investor" ? "selected" : ""}>INVESTOR</option>
            <option value="owner" ${chatRole === "owner" ? "selected" : ""}>OWNER</option>
            <option value="admin" ${chatRole === "admin" ? "selected" : ""}>ADMIN</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">DISPLAY NAME</label>
          <input class="form-input" data-chat-sender value="${escapeHtml(senderName)}" placeholder="LOCAL ANALYST">
        </div>
        <button type="button" class="btn btn-secondary" data-action="clear-messages">
          <span class="btn-dot tone-orange"></span>
          CLEAR THREAD
        </button>
      </div>

      <div class="pack-panel">
        <div class="chat-thread">
          ${
            messages.length
              ? messages
                  .map(
                    (message) => `
                      <div class="chat-bubble">
                        <div class="chat-role">${escapeHtml(String(message.role).toUpperCase())} | ${escapeHtml(String(message.senderName).toUpperCase())}</div>
                        <div class="chat-text">${escapeHtml(String(message.text).toUpperCase())}</div>
                        <div class="tiny spacer-top-xs">${escapeHtml(String(message.createdAt).replace("T", " ").slice(0, 16).toUpperCase())}</div>
                      </div>
                    `
                  )
                  .join("")
              : `<div class="tiny">NO MESSAGES YET. SEND A MESSAGE TO START.</div>`
          }
        </div>

        <div class="form-group spacer-top-sm">
          <label class="form-label">MESSAGE</label>
          <textarea class="form-textarea" data-chat-input placeholder="TYPE YOUR MESSAGE">${escapeHtml(chatInput)}</textarea>
        </div>
        <button type="button" class="btn btn-primary" data-action="send-message">
          <span class="btn-dot"></span>
          SEND MESSAGE
        </button>
      </div>
    </div>
  `;
}

export function renderToasts(toasts) {
  return toasts
    .map(
      (toast) => `
        <div class="toast">
          <span class="toast-dot tone-${escapeHtml(toast.kind)}"></span>
          <div>
            <div class="toast-title">${escapeHtml(toast.title)}</div>
            <div class="toast-msg">${escapeHtml(toast.message)}</div>
          </div>
        </div>
      `
    )
    .join("");
}
