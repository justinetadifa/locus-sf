<?php
declare(strict_types=1);

require __DIR__ . '/app/Support/web.php';

$context = sfc_web_context();
sfc_render_head('Offer Board | LOCUS-SF', $context, ['page' => 'offer-board', 'role' => $context['user']['role'] ?? 'guest']);
sfc_render_header($context, 'offer-board');
?>
<main class="page-shell showcase-page">
  <section class="site-shell showcase-page-banner is-offer is-condensed">
    <div class="showcase-page-grid is-condensed">
      <div class="showcase-page-copy">
        <div class="showcase-page-prelude">
          <div>
            <div class="eyebrow">Offer Board</div>
            <span class="showcase-page-subtitle">Curated timed opportunities</span>
          </div>
          <span class="showcase-page-pill">Public Surface</span>
        </div>
        <h1>Offer Board</h1>
        <p>A cleaner release surface for premium, admin-curated opportunities with stronger imagery, timing cues, and direct routes into the deeper property view.</p>
      </div>

      <aside class="showcase-page-brief is-compact">
        <div class="showcase-page-brief-grid">
          <div><span>Board Mode</span><strong>Editorial spotlight</strong></div>
          <div><span>Routing</span><strong>Property + rankings</strong></div>
        </div>
        <div class="showcase-page-actions">
          <a href="<?= htmlspecialchars(sfc_path('/property-ranking.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn-shell btn-shell-secondary">View Rankings</a>
          <a href="<?= htmlspecialchars(sfc_path('/property-explorer.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn-shell btn-shell-primary">Open Explorer</a>
        </div>
      </aside>
    </div>
  </section>

  <section class="site-shell showcase-root-grid" id="offerBoardRoot">
    <div class="loading-panel">Loading offer board...</div>
  </section>
</main>
<?php sfc_render_footer($context); ?>
