<?php
declare(strict_types=1);

require __DIR__ . '/app/Support/web.php';

$context = sfc_web_context();
$propertyId = (int) ($_GET['id'] ?? 0);
sfc_render_head('Area Intelligence Dossier | SFCelerate', $context, ['page' => 'property-details', 'role' => $context['user']['role'] ?? 'guest', 'property-id' => (string) $propertyId]);
sfc_render_header($context, 'explorer');
?>
<main class="page-shell details-page">
  <section class="site-shell page-intro-card">
    <div>
      <div class="eyebrow">Area Intelligence Dossier</div>
      <h1>One policy-ready record for suitability, compliance, and action.</h1>
      <p>See why the area ranks here, whether the proposed use passes CLUP screening, and what the LGU should do next.</p>
    </div>
    <div class="intro-actions">
      <a href="<?= htmlspecialchars(sfc_path('/property-explorer.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn-shell btn-shell-secondary">Back to Explorer</a>
    </div>
  </section>

  <section class="site-shell detail-root-grid" id="propertyDetailsRoot">
    <div class="loading-panel">Loading property details...</div>
  </section>
</main>
<?php sfc_render_footer($context); ?>
