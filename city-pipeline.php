<?php
declare(strict_types=1);

require __DIR__ . '/app/Support/web.php';

$context = sfc_web_context();
sfc_render_head('City Pipeline | LOCUS-SF', $context, ['page' => 'city-pipeline', 'role' => $context['user']['role'] ?? 'guest']);
sfc_render_header($context, 'city-pipeline');
?>
<main class="page-shell showcase-page">
  <section class="site-shell showcase-page-banner is-pipeline">
    <div class="showcase-page-grid">
      <div class="showcase-page-copy">
        <div class="showcase-page-prelude">
          <div>
            <div class="eyebrow">City Pipeline</div>
            <span class="showcase-page-subtitle">Investor signal atlas</span>
          </div>
          <span class="showcase-page-pill">Under More</span>
        </div>
        <h1>Read San Fernando&rsquo;s next growth wave before it gets mistaken for another generic listing wall.</h1>
        <p>City Pipeline separates active future projects from investor-gap briefs, giving the city a cleaner way to show momentum, unmet demand, and duplicate-build warnings in one strategic surface.</p>
        <div class="showcase-page-chip-row">
          <span>Whitespace briefs</span>
          <span>Pipeline momentum</span>
          <span>Duplicate-build warnings</span>
        </div>
      </div>

      <aside class="showcase-page-brief">
        <div class="panel-kicker">Board Position</div>
        <h2>One board. Two lenses. Much cleaner investment signals.</h2>
        <p>Use this surface to compare what is already forming in the city against what San Fernando still wants operators and investors to introduce next.</p>
        <div class="showcase-page-brief-grid">
          <div><span>Board Mode</span><strong>Signal-first atlas</strong></div>
          <div><span>Read Type</span><strong>Gap radar + project momentum</strong></div>
          <div><span>Curated By</span><strong>City investment desk</strong></div>
          <div><span>Best Use</span><strong>Pre-investment discovery</strong></div>
        </div>
        <div class="showcase-page-actions">
          <a href="<?= htmlspecialchars(sfc_path('/property-explorer.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn-shell btn-shell-secondary">Open Explorer</a>
          <a href="<?= htmlspecialchars(sfc_path('/offer-board.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn-shell btn-shell-primary">Open Offer Board</a>
        </div>
      </aside>
    </div>
  </section>

  <section class="site-shell showcase-root-grid" id="cityPipelineRoot">
    <div class="loading-panel">Loading city pipeline...</div>
  </section>
</main>
<?php sfc_render_footer($context); ?>
