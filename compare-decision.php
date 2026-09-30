<?php
declare(strict_types=1);

require __DIR__ . '/app/Support/web.php';

$context = sfc_web_context();
sfc_render_head('Compare & Decision | LOCUS-SF', $context, ['page' => 'compare-decision', 'role' => $context['user']['role'] ?? 'guest']);
sfc_render_header($context, 'compare');
?>
<main class="page-shell compare-page">
  <section class="site-shell page-intro-card">
    <div>
      <div class="eyebrow">Compare & Decision</div>
      <h1>Compare fewer properties. Reach clearer conclusions.</h1>
      <p>Turn a shortlist into a structured recommendation using fit, price, corridor context, and diligence posture.</p>
    </div>
    <div class="intro-actions">
      <a href="<?= htmlspecialchars(sfc_path('/property-explorer.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn-shell btn-shell-secondary">Back to Explorer</a>
    </div>
  </section>

  <section class="site-shell compare-root-grid" id="compareDecisionRoot">
    <div class="loading-panel">Loading comparison workspace...</div>
  </section>
</main>
<?php sfc_render_footer($context); ?>
