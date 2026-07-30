import { useMemo, useState } from "react";

export function PropertyCommandCenter({
  property,
  scoreModel,
  activeLens,
  blockers = [],
  nextActions = [],
  tabs = [],
  renderAnalysis,
  renderTrust,
  renderOperations,
  onNavigate,
}) {
  const [activeTab, setActiveTab] = useState("command");
  const [openSections, setOpenSections] = useState({
    workflow: true,
    trail: false,
    analysis: true,
    readiness: false,
    trust: true,
    documents: false,
    logistics: true,
    location: false,
  });

  const priorityActions = useMemo(() => nextActions.slice(0, 3), [nextActions]);

  const toggleSection = (key) => {
    setOpenSections((current) => ({ ...current, [key]: !current[key] }));
  };

  return (
    <div className="property-command-shell">
      <StickyCommandSummary
        property={property}
        scoreModel={scoreModel}
        activeLens={activeLens}
        blockers={blockers.slice(0, 3)}
        nextActions={priorityActions}
        onNavigate={onNavigate}
      />

      <CommandOverviewStage
        property={property}
        scoreModel={scoreModel}
        activeLens={activeLens}
        nextAction={priorityActions[0] ?? null}
        onNavigate={onNavigate}
      />

      <section className="command-workspace">
        <div className="command-workspace-head">
          <div>
            <div className="panel-kicker">Deep dive workspace</div>
            <h3>Move from executive scan to analyst detail without losing the thread</h3>
            <p>Primary workflow stays visible above. Supporting detail is organized below by command, analysis, trust, and operations.</p>
          </div>
          <CommandTabs tabs={tabs} activeTab={activeTab} onChange={setActiveTab} />
        </div>

        {activeTab === "command" && (
          <div className="command-tab-panel">
            <CommandAccordion
              kicker="Workflow"
              title="Resolve blockers and move the operating loop forward"
              summary={priorityActions[0]?.label ?? "No queued next actions right now."}
              meta={`${blockers.length} blockers`}
              open={openSections.workflow}
              onToggle={() => toggleSection("workflow")}
            >
              <div className="command-tab-grid command-tab-grid-double">
                <div>{/* Blockers card */}</div>
                <div>{/* Next actions card */}</div>
              </div>
            </CommandAccordion>

            <CommandAccordion
              kicker="Activity trail"
              title="Recent movement across trust, logistics, and messaging"
              summary="Recent updates, approvals, and thread activity."
              meta="Live property trail"
              open={openSections.trail}
              onToggle={() => toggleSection("trail")}
            >
              <div>{/* Timeline card */}</div>
            </CommandAccordion>
          </div>
        )}

        {activeTab === "analysis" && (
          <div className="command-tab-panel">
            <CommandAccordion
              kicker="Lens and scoring"
              title="Reframe the property and inspect the current command score"
              summary={`${activeLens.label} is active.`}
              meta={`${scoreModel.finalScore} score`}
              open={openSections.analysis}
              onToggle={() => toggleSection("analysis")}
            >
              {renderAnalysis?.()}
            </CommandAccordion>

            <CommandAccordion
              kicker="Readiness"
              title="See why the thesis holds up and where certainty breaks"
              summary="Readiness, thesis, and data gaps."
              meta="IRIE"
              open={openSections.readiness}
              onToggle={() => toggleSection("readiness")}
            >
              <div>{/* readiness + thesis */}</div>
            </CommandAccordion>
          </div>
        )}

        {activeTab === "trust" && (
          <div className="command-tab-panel">
            <CommandAccordion
              kicker="Trust"
              title="Institutional confidence, verification, and ledger visibility"
              summary="Verification state, audit trail, and compliance posture."
              meta="Trust"
              open={openSections.trust}
              onToggle={() => toggleSection("trust")}
            >
              {renderTrust?.()}
            </CommandAccordion>

            <CommandAccordion
              kicker="Documents"
              title="Document package, requests, and response workflow"
              summary="Preserve all existing document fields, but collapse them into a focused workflow."
              meta="Docs"
              open={openSections.documents}
              onToggle={() => toggleSection("documents")}
            >
              <div>{/* document workflow */}</div>
            </CommandAccordion>
          </div>
        )}

        {activeTab === "operations" && (
          <div className="command-tab-panel">
            <CommandAccordion
              kicker="Logistics"
              title="Ground truth orchestration and visit workflow"
              summary="Visit status, proposed windows, and field execution."
              meta="Logistics"
              open={openSections.logistics}
              onToggle={() => toggleSection("logistics")}
            >
              {renderOperations?.()}
            </CommandAccordion>

            <CommandAccordion
              kicker="Location context"
              title="Map, weather, and parcel context"
              summary="Map height stays compact by default and expands only when needed."
              meta="Location"
              open={openSections.location}
              onToggle={() => toggleSection("location")}
            >
              <div>{/* map + climate + parcel context */}</div>
            </CommandAccordion>
          </div>
        )}
      </section>
    </div>
  );
}

function StickyCommandSummary({ property, scoreModel, activeLens, blockers, nextActions, onNavigate }) {
  return (
    <section className="command-summary-bar">
      <div className="command-summary-main">
        <div className="command-summary-topline">
          <div className="panel-kicker">Executive summary</div>
          <span className="service-chip service-chip-live">{activeLens.label} lens</span>
        </div>

        <div className="command-summary-title-row">
          <div className="command-summary-title-copy">
            <h2>{property.name}</h2>
            <p>{property.story}</p>
          </div>
          <div className="command-summary-score-shell">
            <span>Command score</span>
            <strong>{scoreModel.finalScore}</strong>
            <small className={`command-summary-score-delta ${scoreModel.delta >= 0 ? "is-positive" : "is-negative"}`}>
              {scoreModel.delta >= 0 ? "+" : ""}
              {scoreModel.delta} vs base IAI
            </small>
          </div>
        </div>

        <div className="command-summary-lanes">
          <SummaryLane title="Top blockers" items={blockers} onNavigate={onNavigate} />
          <SummaryLane title="Next actions" items={nextActions} onNavigate={onNavigate} />
        </div>
      </div>

      <div className="command-summary-actions">
        <div className="command-summary-primary-actions">
          <button className="btn-shell btn-shell-primary">Review Documents</button>
          <button className="btn-shell btn-shell-secondary">Open Checklist</button>
          <button className="btn-shell btn-shell-secondary">Open Messaging</button>
        </div>
      </div>
    </section>
  );
}

function CommandOverviewStage({ property, scoreModel, activeLens, nextAction, onNavigate }) {
  return (
    <section className="command-overview-stage">
      <article className="command-overview-hero">
        <div className="command-overview-media">
          <img src={property.imageUrl} alt={property.name} />
        </div>
        <div className="command-overview-copy">
          <div className="command-overview-head">
            <div>
              <div className="panel-kicker">Property brief</div>
              <h3>{property.name} through the {activeLens.label} lens</h3>
            </div>
          </div>
          <p>{property.story}</p>
        </div>
      </article>

      <aside className="command-overview-side">
        <article className="command-ribbon-score command-overview-score">
          <div className="command-metric-label">Command score</div>
          <div className="command-metric-value">{scoreModel.finalScore}</div>
        </article>

        <article className="panel-card command-focus-card">
          <div className="panel-kicker">Workflow pulse</div>
          <h3>What the team should watch next</h3>
          {nextAction && (
            <button className="command-focus-next" onClick={() => onNavigate?.(nextAction.target)}>
              <span>{nextAction.roleLabel}</span>
              <strong>{nextAction.label}</strong>
              <small>{nextAction.reason}</small>
            </button>
          )}
        </article>
      </aside>
    </section>
  );
}

function CommandTabs({ tabs, activeTab, onChange }) {
  return (
    <div className="command-tab-list" role="tablist" aria-label="Property command center sections">
      {tabs.map((tab) => (
        <button
          key={tab.key}
          type="button"
          className={`command-tab ${activeTab === tab.key ? "is-active" : ""}`}
          onClick={() => onChange(tab.key)}
        >
          <span>{tab.label}</span>
          <small>{tab.meta}</small>
        </button>
      ))}
    </div>
  );
}

function CommandAccordion({ kicker, title, summary, meta, open, onToggle, children }) {
  return (
    <section className={`command-accordion ${open ? "is-open" : ""}`}>
      <button type="button" className="command-accordion-toggle" onClick={onToggle} aria-expanded={open}>
        <div className="command-accordion-copy">
          <div className="panel-kicker">{kicker}</div>
          <h3>{title}</h3>
          <p>{summary}</p>
        </div>
        <div className="command-accordion-side">
          <div className="command-accordion-meta">
            <span className="service-chip service-chip-neutral">{meta}</span>
          </div>
          <span className="command-accordion-chevron" aria-hidden="true" />
        </div>
      </button>
      {open && <div className="command-accordion-body">{children}</div>}
    </section>
  );
}

function SummaryLane({ title, items, onNavigate }) {
  return (
    <section className="command-summary-lane">
      <div className="command-summary-lane-head">
        <div className="panel-kicker">{title}</div>
      </div>
      <div className="command-summary-item-list">
        {items.map((item, index) => (
          <button
            key={item.id ?? `${title}-${index}`}
            type="button"
            className="command-summary-item"
            onClick={() => onNavigate?.(item.target)}
          >
            <span>{item.roleLabel ?? item.severity ?? "Info"}</span>
            <strong>{item.label ?? item.title}</strong>
            <small>{item.reason ?? item.summary}</small>
          </button>
        ))}
      </div>
    </section>
  );
}
