<?php
declare(strict_types=1);

require __DIR__ . '/app/Support/web.php';

$context = sfc_web_context();
$heroImage = $context['assetBase'] . '/images/sfcpanoramicView.png';
sfc_render_head('LOCUS-SF', $context, ['page' => 'landing', 'role' => $context['user']['role'] ?? 'guest']);
sfc_render_header($context, 'landing');
?>
<main class="page-shell landing-shell landing-editorial-shell landing-calm-shell">
  <section class="hero-home hero-home-editorial hero-home-refined" data-hero-stage tabindex="0" style="--hero-image:url('<?= htmlspecialchars($heroImage, ENT_QUOTES, 'UTF-8') ?>')">
    <div class="hero-canvas" id="hero-canvas" aria-hidden="true">
      <div class="hero-home-backdrop"></div>
      <div class="mouse-glow"></div>
    </div>
    <div class="site-shell hero-home-grid hero-home-grid-refined">
      <div class="hero-home-copy hero-home-copy-refined">
        <div class="hero-prelude">
          <div class="hero-prelude-copy">
            <div class="eyebrow">City Investment Brief</div>
            <span class="hero-location-seal">San Fernando, La Union</span>
          </div>
          <span class="hero-node-badge" id="heroNodeBadge">Looking toward Poro Point</span>
        </div>
        <h1>Read San Fernando opportunities with clarity.</h1>
        <p id="heroFocusSummary">Corridor fit, verified readiness, and local demand now sit inside one calmer first read of the city.</p>

        <div class="hero-focus-block">
          <span class="hero-focus-label">Choose a lens</span>
          <div class="hero-focus-row" aria-label="Investment lens selector">
            <button type="button" class="chip hero-focus-chip" data-hero-focus="university">University</button>
            <button type="button" class="chip hero-focus-chip" data-hero-focus="logistics">Logistics</button>
            <button type="button" class="chip hero-focus-chip" data-hero-focus="hospital">Hospital</button>
            <button type="button" class="chip hero-focus-chip" data-hero-focus="retail">Retail</button>
          </div>
          </div>

        <div class="hero-actions hero-command-actions hero-home-actions">
          <a href="<?= htmlspecialchars(sfc_path('/property-ranking.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn-shell btn-shell-hero is-primary">
            <span class="btn-shell-icon"><?= sfc_icon('ranking') ?></span>
            <span>View Top Opportunities</span>
          </a>
          <a href="<?= htmlspecialchars(sfc_path('/property-explorer.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn-shell btn-shell-hero is-secondary">
            <span class="btn-shell-icon"><?= sfc_icon('explorer') ?></span>
            <span>Explore the City</span>
          </a>
        </div>

        <div class="market-ticker-shell hero-sentiment-rail" aria-label="Live city read">
          <span class="hero-sentiment-label">Live city read <strong id="heroTickerMeta">Logistics lens</strong></span>
          <div class="market-ticker-track hero-sentiment-track" id="heroSentimentTicker">
            <span class="market-ticker-item">Loading live city signals</span>
          </div>
        </div>
      </div>

      <aside class="hero-feature-shell hero-feature-shell-refined">
        <article class="hero-home-panel">
          <div class="hero-home-panel-head">
            <div class="hero-home-panel-copy">
              <div class="panel-kicker">Live Investment Snapshot</div>
              <h2>Lead opportunity right now.</h2>
            </div>
            <div class="hero-brief-context">
              <span>Live node</span>
              <strong id="heroFeaturedMeta">Poro Point horizon</strong>
            </div>
          </div>

          <div class="hero-featured-opportunity" id="heroFeaturedOpportunity">
            <div class="hero-opportunity-loading">Synchronizing live property brief...</div>
          </div>

          <div class="hero-home-panel-body">
            <section class="hero-score-block">
              <div class="hero-score-copy">
                <span id="heroMetricMeta">Logistics / Poro Point</span>
                <strong class="hero-slab-score" id="heroIaiScore">87.0</strong>
                <p id="heroMetricSummary">Property 1 - Industrial Zone is the clearest opportunity currently visible at Poro Point.</p>
              </div>
              <div class="hero-score-chips">
                <span class="hero-slab-chip" id="heroFocusBadge">Logistics lens</span>
                <span class="hero-slab-chip hero-slab-chip-quiet"><strong id="heroOpportunityCount">3</strong> candidate sites</span>
              </div>
            </section>

            <div class="hero-proof-grid hero-proof-grid-refined" id="heroProofGrid">
              <article class="hero-proof-card">
                <span>Candidate Sites</span>
                <strong>Loading</strong>
              </article>
              <article class="hero-proof-card">
                <span>Verified</span>
                <strong>Loading</strong>
              </article>
              <article class="hero-proof-card">
                <span>Audits</span>
                <strong>Loading</strong>
              </article>
              <article class="hero-proof-card">
                <span>Ready</span>
                <strong>Loading</strong>
              </article>
            </div>
          </div>

          <article class="hero-story-panel hero-story-panel-refined">
            <div class="panel-kicker">Why It Leads</div>
            <p id="heroStoryCopy">Property 1 - Industrial Zone now sits closest to the horizon because logistics demand is surfacing around Poro Point, giving the corridor its clearest current read.</p>
          </article>

          <div class="hero-spatial-dock hero-spatial-dock-refined">
            <div class="hero-node-panel-head">
              <div class="hero-node-panel-copy">
                <div class="hero-node-dock-head">Spatial focus</div>
                <strong id="heroNodeMeta">Poro Point horizon</strong>
                <p id="heroOpportunitySummary">3 active listings currently orbit Poro Point on the city grid.</p>
              </div>
            </div>
            <div class="living-city-node-list" aria-label="Spatial trigger nodes">
              <button type="button" class="living-city-node-pill" data-city-node="poro-point">Poro Point</button>
              <button type="button" class="living-city-node-pill" data-city-node="city-center">City Center</button>
              <button type="button" class="living-city-node-pill" data-city-node="civic-belt">Civic Belt</button>
            </div>
          </div>
        </article>
      </aside>
    </div>
  </section>

  <section class="site-shell section-block landing-overview-grid">
    <article class="landing-panel landing-ranking-panel landing-panel-priority">
        <div class="landing-panel-head">
          <div class="section-heading section-heading-inline">
            <div class="eyebrow">Top Ranked Opportunities</div>
            <h2>The strongest opportunities, arranged with more conviction.</h2>
          <p>These areas rise first only when investment attractiveness, corridor fit, readiness, and CLUP suitability align.</p>
          </div>
        <a href="<?= htmlspecialchars(sfc_path('/property-ranking.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn-shell btn-shell-secondary">View Investment Board</a>
      </div>
      <div id="homeRankingPreview" class="landing-ranking-preview">
        <div class="loading-panel">Loading property rankings...</div>
      </div>
    </article>

    <div class="landing-side-stack">
      <article class="landing-panel landing-demand-panel landing-panel-secondary">
        <div class="landing-panel-head">
          <div class="section-heading section-heading-inline">
            <div class="eyebrow">Voting Signals</div>
          <h2>Signals the city is starting to ask for.</h2>
          <p>Investor and resident signals reveal which services or establishments are beginning to pull hardest in each area.</p>
          </div>
          <a href="<?= htmlspecialchars(sfc_path('/voting-dashboard.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn-shell btn-shell-secondary">View Demand Signals</a>
        </div>
        <div id="homeVotingPreview" class="landing-demand-preview">
          <div class="loading-panel">Loading demand insights...</div>
        </div>
      </article>

      <article class="landing-panel landing-role-panel landing-panel-contrast">
        <div class="eyebrow">Platform Paths</div>
        <h2>Each role enters through a workspace built for its exact decisions.</h2>
        <p>Planning officers govern site evidence and policy priorities, while investors explore and compare compliant opportunities.</p>
        <div class="landing-role-links">
          <a href="<?= htmlspecialchars(sfc_path('/admin-login.php'), ENT_QUOTES, 'UTF-8') ?>" class="landing-role-link role-admin-link">
            <span>Admin</span>
            <strong>Validate candidate sites, CLUP outcomes, scenarios, and reports.</strong>
          </a>
          <a href="<?= htmlspecialchars(sfc_path('/investor-login.php'), ENT_QUOTES, 'UTF-8') ?>" class="landing-role-link role-investor-link">
            <span>Investor / Resident</span>
            <strong>Explore the city and compare CLUP-screened investment areas.</strong>
          </a>
        </div>
      </article>
    </div>
  </section>

  <section class="site-shell section-block landing-curation-grid">
    <article class="landing-panel landing-curation-panel landing-panel-collection">
      <div class="landing-panel-head">
        <div class="section-heading section-heading-inline">
          <div class="eyebrow">CLUP Compliance Engine</div>
          <h2>Attractiveness never overrides land-use compatibility.</h2>
          <p>Test a candidate site and proposed investment type to receive a PASS, CONDITIONAL, or FAIL result with a suitability score and LGU action.</p>
        </div>
        <a href="<?= htmlspecialchars(sfc_path('/simulator.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn-shell btn-shell-secondary">Run a Scenario</a>
      </div>
      <div class="landing-showcase-preview clup-fact-grid">
        <div><span>Compliance Gate</span><strong>PASS · CONDITIONAL · FAIL</strong></div>
        <div><span>Decision Output</span><strong>Suitability, explanation, and LGU action</strong></div>
      </div>
    </article>

    <article class="landing-panel landing-curation-panel landing-panel-collection is-pipeline">
      <div class="landing-panel-head">
        <div class="section-heading section-heading-inline">
          <div class="eyebrow">City Pipeline</div>
          <h2>Planned and upcoming city developments, edited into one future-facing board.</h2>
          <p>Track what is coming next, from approved commercial additions to larger city-facing development momentum.</p>
        </div>
        <a href="<?= htmlspecialchars(sfc_path('/city-pipeline.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn-shell btn-shell-secondary">View City Pipeline</a>
      </div>
      <div id="homePipelinePreview" class="landing-showcase-preview">
        <div class="loading-panel">Loading city pipeline...</div>
      </div>
    </article>
  </section>

  <section class="site-shell section-block landing-city-editorial">
    <div class="landing-city-copy">
      <div class="section-heading section-heading-inline">
        <div class="eyebrow">Why San Fernando</div>
        <h2>A city where corridor logic, civic gravity, and coastal scale stay surprisingly legible.</h2>
        <p>San Fernando works because transport, commerce, social services, and future growth all remain visible in one frame. That makes the city easier to read and easier to curate convincingly.</p>
      </div>
      <div class="landing-city-pillars">
        <article class="landing-pillar-card">
          <span>Corridor Strength</span>
          <strong>Port, highway, and frontage alignment create stronger logistics logic than isolated land plays.</strong>
        </article>
        <article class="landing-pillar-card">
          <span>Demand Anchors</span>
          <strong>Schools, hospitals, and civic movement reveal what each district can realistically support next.</strong>
        </article>
        <article class="landing-pillar-card">
          <span>Urban Services</span>
          <strong>City-center activity gives mixed-use, retail, and service opportunities a clearer real-world floor.</strong>
        </article>
        <article class="landing-pillar-card">
          <span>Expansion Runway</span>
          <strong>Emerging frontage and larger land scale open room for slower, longer-horizon development bets.</strong>
        </article>
      </div>
    </div>

    <div class="landing-city-notes">
      <article class="landing-notebook-card">
        <div class="panel-kicker">Spatial Logic</div>
        <h3>Three city fronts. One investment frame.</h3>
        <p>Use the thesis stage to move between logistics at Poro Point, commercial pull in the city center, and civic expansion around the belt.</p>
        <div class="map-cluster landing-map-cluster">
          <span>Poro Point logistics spine</span>
          <span>City center commerce ring</span>
          <span>Civic belt expansion zone</span>
        </div>
      </article>

      <article class="landing-notebook-card is-soft">
        <div class="panel-kicker">Five-Minute Read</div>
        <h3>How to read a site quickly and cleanly.</h3>
        <div class="landing-note-list">
          <div>
            <span>01</span>
            <strong>Start with corridor fit before you judge the lot itself.</strong>
          </div>
          <div>
            <span>02</span>
            <strong>Compare access and frontage against the guide price, not just land area.</strong>
          </div>
          <div>
            <span>03</span>
            <strong>Use CLUP compliance and due diligence as mandatory final gates.</strong>
          </div>
        </div>
      </article>
    </div>
  </section>

  <section class="site-shell final-cta-card landing-final-cta">
    <div class="landing-final-cta-copy">
      <div class="eyebrow">Role Entry</div>
      <h2>Enter through the workflow that matches the kind of decision you need to make.</h2>
      <p>Choose the workspace that matches your role, with the same clear visual language carried into every next step.</p>
    </div>
    <div class="cta-card-actions">
      <a href="<?= htmlspecialchars(sfc_path('/admin-login.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn-shell btn-shell-ghost">Admin</a>
      <a href="<?= htmlspecialchars(sfc_path('/simulator.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn-shell btn-shell-secondary">Scenario Simulator</a>
      <a href="<?= htmlspecialchars(sfc_path('/investor-login.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn-shell btn-shell-primary">Investor / Resident</a>
    </div>
  </section>
</main>
<?php sfc_render_footer($context); ?>

