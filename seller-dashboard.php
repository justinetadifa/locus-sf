<?php
declare(strict_types=1);

require __DIR__ . '/app/Support/web.php';

$context = sfc_web_context();
sfc_require_role('seller', sfc_path('/seller-login.php'));
$context = sfc_web_context();
sfc_render_head('Seller Dashboard | LOCUS-SF', $context, ['page' => 'seller-dashboard', 'role' => 'seller']);
sfc_render_header($context, 'seller');
?>
<main class="page-shell dashboard-page">
  <section class="site-shell page-intro-card">
    <div>
      <div class="eyebrow">Seller Dashboard</div>
      <div class="page-role-strip">
        <span class="page-role-pill is-role">Seller Workspace</span>
        <span class="page-role-pill">Submissions + reply flow</span>
      </div>
      <h1>Manage your listings in a cleaner seller workspace.</h1>
      <p>Submit properties, update your records, and reply to investor conversations without stepping into the admin side of the platform.</p>
    </div>
    <div class="intro-actions">
      <button type="button" class="btn-shell btn-shell-primary" id="sellerAddListing">Submit Listing</button>
      <a href="<?= htmlspecialchars(sfc_path('/property-ranking.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn-shell btn-shell-secondary">View Rankings</a>
    </div>
  </section>

  <section class="site-shell dashboard-root-grid" id="sellerDashboardRoot">
    <div class="loading-panel">Loading seller dashboard...</div>
  </section>
</main>

<div class="modal-shell" id="sellerListingModal" hidden>
  <div class="modal-card property-crud-modal">
    <div class="modal-head">
      <div>
        <div class="panel-kicker">Seller Listing</div>
        <h3 class="section-title" id="sellerModalTitle">Submit Listing</h3>
      </div>
      <button type="button" class="modal-close" data-modal-close="sellerListingModal">Close</button>
    </div>
    <form id="sellerListingForm" class="crud-form-grid property-crud-form">
      <input type="hidden" id="sellerPropertyId">
      <label class="form-shell"><span>Property Name</span><input class="input-shell" id="sellerPropertyName" required></label>
      <label class="form-shell"><span>City</span><input class="input-shell" id="sellerCity" value="San Fernando, La Union" required></label>
      <label class="form-shell"><span>Barangay</span><input class="input-shell" id="sellerBarangay"></label>
      <label class="form-shell"><span>Property Type</span><select class="input-shell" id="sellerPropertyType"><option value="commercial">Commercial</option><option value="logistics">Logistics</option><option value="hotel">Resort / Tourism</option><option value="bpo">Office / BPO</option><option value="manufacturing">Manufacturing</option></select></label>
      <label class="form-shell"><span>Corridor</span><select class="input-shell" id="sellerCorridor"><option value="highway">Highway</option><option value="downtown">Downtown</option><option value="coastal">Coastal</option></select></label>
      <label class="form-shell"><span>Status</span><select class="input-shell" id="sellerStatus"><option value="Available">Available</option><option value="Under Review">Under Review</option><option value="Reserved">Reserved</option><option value="Negotiating">Negotiating</option></select></label>
      <label class="form-shell"><span>Price (PHP)</span><input type="number" class="input-shell" id="sellerPrice" required></label>
      <label class="form-shell"><span>Land Area (Ha)</span><input type="number" step="0.1" class="input-shell" id="sellerLandArea" required></label>
      <label class="form-shell"><span>Road Access</span><input type="number" min="40" max="100" class="input-shell" id="sellerAccess" value="85"></label>
      <label class="form-shell form-span-2"><span>Description</span><textarea class="input-shell input-textarea" id="sellerDescription" required></textarea></label>
      <label class="form-shell form-span-2"><span>Fallback Image Path</span><input class="input-shell" id="sellerImagePath" value="assets/images/Property10.png"></label>
      <label class="form-shell form-span-2"><span>Upload Image</span><input type="file" class="input-shell" id="sellerImage" accept="image/*"></label>
      <label class="form-shell form-span-2"><span>Tags</span><input class="input-shell" id="sellerTags" placeholder="Prime Location, Investor Ready"></label>
      <label class="form-shell form-span-2"><span>Facilities</span><input class="input-shell" id="sellerFacilities" placeholder="Utilities, Highway Access"></label>
      <label class="form-shell"><span>Contact Name</span><input class="input-shell" id="sellerOwnerName"></label>
      <label class="form-shell"><span>Contact Email</span><input class="input-shell" id="sellerOwnerEmail"></label>
      <label class="form-shell"><span>Phone</span><input class="input-shell" id="sellerOwnerPhone"></label>
      <label class="form-shell"><span>Response SLA</span><input class="input-shell" id="sellerOwnerSla" value="24 HOURS"></label>
      <div class="crud-actions form-span-2">
        <button type="button" class="btn-shell btn-shell-secondary" data-modal-close="sellerListingModal">Cancel</button>
        <button type="submit" class="btn-shell btn-shell-primary">Save Listing</button>
      </div>
    </form>
  </div>
</div>
<?php sfc_render_footer($context); ?>
