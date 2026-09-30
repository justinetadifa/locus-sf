<?php
declare(strict_types=1);

require __DIR__ . '/app/Support/web.php';

$context = sfc_web_context();
sfc_require_role('admin', sfc_path('/admin-login.php'));
$context = sfc_web_context();
sfc_render_head('Admin Listings | LOCUS-SF', $context, ['page' => 'admin-properties', 'role' => 'admin']);
sfc_render_header($context, 'admin-properties');
?>
<main class="page-shell admin-listings-page">
  <section class="site-shell page-intro-card">
    <div>
      <div class="eyebrow">Admin Listings</div>
      <div class="page-role-strip">
        <span class="page-role-pill is-role">Admin Workspace</span>
        <span class="page-role-pill">Inventory + trust controls</span>
      </div>
      <h1>Manage the live property inventory with more discipline.</h1>
      <p>Create, edit, and monitor listings inside a cleaner admin listing control surface.</p>
    </div>
    <div class="intro-actions">
      <button type="button" class="btn-shell btn-shell-primary" id="adminAddProperty">Add Property</button>
      <a href="<?= htmlspecialchars(sfc_path('/property-ranking.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn-shell btn-shell-secondary">View Rankings</a>
    </div>
  </section>

  <section class="site-shell admin-properties-root-grid" id="adminPropertiesRoot">
    <div class="loading-panel">Loading admin listings...</div>
  </section>
</main>

<!-- Add / Edit Property: grouped fields with a separate action area. -->
<div class="modal-shell" id="propertyCrudModal" hidden>
  <div class="modal-card property-crud-modal" role="dialog" aria-modal="true" aria-labelledby="crudModalTitle" aria-describedby="crudModalSubtitle" tabindex="-1">
    <div class="crud-header">
      <div>
        <div class="panel-kicker">Admin Listing Editor</div>
        <h3 id="crudModalTitle">Add Property</h3>
        <p id="crudModalSubtitle">Fields marked Required must be completed.</p>
      </div>
      <button type="button" class="modal-close" data-modal-close="propertyCrudModal" aria-label="Close property form" title="Close">&times;</button>
    </div>
    <form id="propertyCrudForm">
      <input type="hidden" id="crudPropertyId">
      <div id="crud-panels-wrap">
        <section class="crud-section" id="crud-panel-0" aria-labelledby="crud-section-0">
          <h4 id="crud-section-0">Property Details</h4>
          <p class="crud-section-note">Describe the property and its key features.</p>
          <div class="crud-section-fields">
            <div>
              <label for="crudPropertyName">Property Name <span class="crud-required">Required</span></label>
              <input class="input-shell" id="crudPropertyName" required />
            </div>
            <div>
              <div class="crud-field-label" id="crudPropertyTypeLabel">Property Type <span class="crud-required">Required</span></div>
              <div class="crud-options" role="group" aria-labelledby="crudPropertyTypeLabel" id="seg-crud-type">
                <button type="button" onclick="crudSeg('seg-crud-type','crudPropertyType',this,'commercial')" class="crud-seg-btn" data-val="commercial">Commercial</button>
                <button type="button" onclick="crudSeg('seg-crud-type','crudPropertyType',this,'logistics')" class="crud-seg-btn" data-val="logistics">Logistics</button>
                <button type="button" onclick="crudSeg('seg-crud-type','crudPropertyType',this,'hotel')" class="crud-seg-btn" data-val="hotel">Resort / Tourism</button>
                <button type="button" onclick="crudSeg('seg-crud-type','crudPropertyType',this,'bpo')" class="crud-seg-btn" data-val="bpo">Office / BPO</button>
                <button type="button" onclick="crudSeg('seg-crud-type','crudPropertyType',this,'manufacturing')" class="crud-seg-btn" data-val="manufacturing">Manufacturing</button>
              </div>
              <select class="input-shell" id="crudPropertyType" aria-hidden="true" tabindex="-1">
                <option value="commercial">Commercial</option><option value="logistics">Logistics</option><option value="hotel">Resort / Tourism</option><option value="bpo">Office / BPO</option><option value="manufacturing">Manufacturing</option>
              </select>
            </div>
            <div>
              <label for="crudDescription">Description <span class="crud-required">Required</span></label>
              <textarea class="input-shell input-textarea" id="crudDescription" required rows="3" ></textarea>
            </div>
            <div class="crud-field-grid">
              <div>
                <label for="crudTags">Tags <span class="crud-optional">Optional</span></label>
                <input class="input-shell" id="crudTags" placeholder="Investor Ready, Strategic Location" />
              </div>
              <div>
                <label for="crudFacilities">Facilities <span class="crud-optional">Optional</span></label>
                <input class="input-shell" id="crudFacilities" placeholder="Highway Access, Utilities" />
              </div>
            </div>
          </div>
        </section>
        <section class="crud-section" id="crud-panel-1" aria-labelledby="crud-section-1">
          <h4 id="crud-section-1">Location and Pricing</h4>
          <p class="crud-section-note">Set the location, asking price, and land area.</p>
          <div class="crud-section-fields">
            <div class="crud-field-grid">
              <div>
                <label for="crudCity">City <span class="crud-required">Required</span></label>
                <input class="input-shell" id="crudCity" value="San Fernando, La Union" required />
              </div>
              <div>
                <label for="crudBarangay">Barangay <span class="crud-optional">Optional</span></label>
                <input class="input-shell" id="crudBarangay" />
              </div>
            </div>
            <div>
              <div class="crud-field-label" id="crudCorridorLabel">City Corridor <span class="crud-optional">Optional</span></div>
              <div class="crud-options" role="group" aria-labelledby="crudCorridorLabel" id="seg-crud-corridor">
                <button type="button" onclick="crudSeg('seg-crud-corridor','crudCorridor',this,'highway')" class="crud-seg-btn" data-val="highway">Highway</button>
                <button type="button" onclick="crudSeg('seg-crud-corridor','crudCorridor',this,'downtown')" class="crud-seg-btn" data-val="downtown">Downtown</button>
                <button type="button" onclick="crudSeg('seg-crud-corridor','crudCorridor',this,'coastal')" class="crud-seg-btn" data-val="coastal">Coastal</button>
              </div>
              <select class="input-shell" id="crudCorridor" aria-hidden="true" tabindex="-1">
                <option value="highway">Highway</option><option value="downtown">Downtown</option><option value="coastal">Coastal</option>
              </select>
            </div>
            <div>
              <label for="crudPrice">Price (PHP) <span class="crud-required">Required</span></label>
              <div class="crud-price-input">
                <span>&#x20B1;</span>
                <input type="number" class="input-shell" id="crudPrice" required oninput="crudFormatPrice(this.value)" />
                <span id="crud-price-badge"></span>
              </div>
            </div>
            <div>
              <div class="crud-field-label">Land Area <span class="crud-required">Required</span></div>
              <div class="crud-field-grid">
                <div>
                  <label for="crudLandArea">Hectares (ha)</label>
                  <input type="number" step="0.0001" min="0.0001" class="input-shell" id="crudLandArea" inputmode="decimal" required oninput="crudSyncArea(this.value,'ha')" />
                </div>
                <div>
                  <label for="crudLandAreaSqm">Square meters (sqm) <span class="crud-optional">Optional conversion</span></label>
                  <input type="number" step="1" id="crudLandAreaSqm" oninput="crudSyncArea(this.value,'sqm')" />
                </div>
              </div>
              <div>
                <small class="form-helper" id="crudLandAreaHint">Use hectares directly. Sqm entries convert automatically on save.</small>
              </div>
              <input type="hidden" id="crudLandAreaUnit" value="ha" />
            </div>
            <div class="crud-note">
              <span>&#128205;</span>
              <div>
                <p>Map Coordinates</p>
                <p>Coordinates are pinned via the property explorer map editor. Use Tags to reference nearby anchors.</p>
              </div>
            </div>
          </div>
        </section>
        <section class="crud-section" id="crud-panel-2" aria-labelledby="crud-section-2">
          <h4 id="crud-section-2">Photos and Documents</h4>
          <p class="crud-section-note">Add a property photo and update the supporting document checklist.</p>
          <div class="crud-section-fields">
            <div>
              <label for="crudImage">Upload Image <span class="crud-optional">Optional</span></label>
              <input type="file" class="input-shell" id="crudImage" accept="image/*" />
            </div>
            <div>
              <label for="crudImagePath">Fallback Image Path <span class="crud-optional">Optional</span></label>
              <input class="input-shell" id="crudImagePath" value="assets/images/Property10.png" />
            </div>
            <fieldset class="crud-document-checklist">
              <legend>Document Checklist</legend>
              <p class="crud-section-note">Record document status here; this checklist does not upload files.</p>
              <div class="crud-field-grid">
                <?php foreach ([
                  ['crudDocTitleCopy',      'Title Copy'],
                  ['crudDocTaxDeclaration', 'Tax Declaration'],
                  ['crudDocSurveyPlan',     'Survey Plan'],
                  ['crudDocZoningClearance','Zoning Clearance'],
                  ['crudDocSitePhotos',     'Site Photos'],
                  ['crudDocHazardReport',   'Hazard Report'],
                ] as [$id, $label]): ?>
                <div>
                  <label for="<?= $id ?>"><?= $label ?> <span class="crud-optional">Optional</span></label>
                  <select class="input-shell" id="<?= $id ?>">
                    <option value="missing">Missing</option>
                    <option value="requested">Requested</option>
                    <option value="submitted">Submitted</option>
                    <option value="reviewed">Reviewed</option>
                  </select>
                </div>
                <?php endforeach; ?>
              </div>
            </fieldset>
          </div>
        </section>
        <section class="crud-section" id="crud-panel-3" aria-labelledby="crud-section-3">
          <h4 id="crud-section-3">Administrative Review</h4>
          <p class="crud-section-note">Review listing visibility, verification, and assessment values.</p>
          <div class="crud-section-fields">
            <div>
              <div class="crud-field-label" id="crudStatusLabel">Listing Status <span class="crud-optional">Optional</span></div>
              <div class="crud-options" role="group" aria-labelledby="crudStatusLabel" id="seg-crud-status">
                <button type="button" onclick="crudSeg('seg-crud-status','crudStatus',this,'Available')" class="crud-seg-btn" data-val="Available">&#9679; Available</button>
                <button type="button" onclick="crudSeg('seg-crud-status','crudStatus',this,'Reserved')" class="crud-seg-btn" data-val="Reserved">&#9680; Reserved</button>
                <button type="button" onclick="crudSeg('seg-crud-status','crudStatus',this,'Under Review')" class="crud-seg-btn" data-val="Under Review">&#9680; Under Review</button>
                <button type="button" onclick="crudSeg('seg-crud-status','crudStatus',this,'Negotiating')" class="crud-seg-btn" data-val="Negotiating">&#9672; Negotiating</button>
              </div>
              <select class="input-shell" id="crudStatus" aria-hidden="true" tabindex="-1">
                <option value="Available">Available</option><option value="Reserved">Reserved</option><option value="Under Review">Under Review</option><option value="Negotiating">Negotiating</option>
              </select>
            </div>
            <div>
              <div class="crud-field-label" id="crudApprovalStateLabel">Approval State <span class="crud-optional">Optional</span></div>
              <div class="crud-options" role="group" aria-labelledby="crudApprovalStateLabel" id="seg-crud-approval">
                <button type="button" onclick="crudSeg('seg-crud-approval','crudApprovalState',this,'draft')" class="crud-seg-btn" data-val="draft">Draft</button>
                <button type="button" onclick="crudSeg('seg-crud-approval','crudApprovalState',this,'pending_review')" class="crud-seg-btn" data-val="pending_review">&#9711; Pending</button>
                <button type="button" onclick="crudSeg('seg-crud-approval','crudApprovalState',this,'approved')" class="crud-seg-btn" data-val="approved">&#10003; Approved</button>
                <button type="button" onclick="crudSeg('seg-crud-approval','crudApprovalState',this,'rejected')" class="crud-seg-btn" data-val="rejected">&#10007; Rejected</button>
                <button type="button" onclick="crudSeg('seg-crud-approval','crudApprovalState',this,'archived')" class="crud-seg-btn" data-val="archived">Archived</button>
              </div>
              <select class="input-shell" id="crudApprovalState" aria-hidden="true" tabindex="-1">
                <option value="draft">Draft</option><option value="pending_review">Pending Review</option><option value="approved">Approved</option><option value="rejected">Rejected</option><option value="archived">Archived</option>
              </select>
            </div>
            <div>
              <div class="crud-field-label" id="crudSellerIdentityStatusLabel">Seller Verification <span class="crud-optional">Optional</span></div>
              <div class="crud-options" role="group" aria-labelledby="crudSellerIdentityStatusLabel" id="seg-crud-seller">
                <button type="button" onclick="crudSeg('seg-crud-seller','crudSellerIdentityStatus',this,'unverified')" class="crud-seg-btn" data-val="unverified">&#9888; Unverified</button>
                <button type="button" onclick="crudSeg('seg-crud-seller','crudSellerIdentityStatus',this,'pending')" class="crud-seg-btn" data-val="pending">&#9711; Pending</button>
                <button type="button" onclick="crudSeg('seg-crud-seller','crudSellerIdentityStatus',this,'verified')" class="crud-seg-btn" data-val="verified">&#10003; Verified</button>
              </div>
              <select class="input-shell" id="crudSellerIdentityStatus" aria-hidden="true" tabindex="-1">
                <option value="unverified">Unverified</option><option value="pending">Pending</option><option value="verified">Verified</option>
              </select>
            </div>
            <div class="crud-field-grid">
              <div>
                <div class="crud-field-label" id="crudDocumentsReviewedLabel">Documents Reviewed <span class="crud-optional">Optional</span></div>
                <div class="crud-options" role="group" aria-labelledby="crudDocumentsReviewedLabel" id="seg-crud-docs">
                  <button type="button" onclick="crudYesNo('seg-crud-docs','crudDocumentsReviewed',this,'1',true)" class="crud-seg-btn" data-val="1">&#10003; Yes</button>
                  <button type="button" onclick="crudYesNo('seg-crud-docs','crudDocumentsReviewed',this,'0',false)" class="crud-seg-btn" data-val="0">&#10007; No</button>
                </div>
                <select class="input-shell" id="crudDocumentsReviewed" aria-hidden="true" tabindex="-1">
                  <option value="0">No</option><option value="1">Yes</option>
                </select>
              </div>
              <div>
                <div class="crud-field-label" id="crudSiteVerifiedLabel">Site Verified <span class="crud-optional">Optional</span></div>
                <div class="crud-options" role="group" aria-labelledby="crudSiteVerifiedLabel" id="seg-crud-site">
                  <button type="button" onclick="crudYesNo('seg-crud-site','crudSiteVerified',this,'1',true)" class="crud-seg-btn" data-val="1">&#10003; Yes</button>
                  <button type="button" onclick="crudYesNo('seg-crud-site','crudSiteVerified',this,'0',false)" class="crud-seg-btn" data-val="0">&#10007; No</button>
                </div>
                <select class="input-shell" id="crudSiteVerified" aria-hidden="true" tabindex="-1">
                  <option value="0">No</option><option value="1">Yes</option>
                </select>
              </div>
            </div>
            <div>
              <label for="crudLastConfirmedAvailableAt">Last Confirmed Available <span class="crud-optional">Optional</span></label>
              <input type="datetime-local" class="input-shell" id="crudLastConfirmedAvailableAt" />
            </div>
            <div class="crud-field-grid">
              <div>
                <div class="crud-field-heading">
                  <label for="crudScore">Market Score <span class="crud-optional">Optional</span></label>
                  <span id="crud-score-display">82</span>
                </div>
                <input type="number" min="40" max="100" class="input-shell" id="crudScore" value="82" oninput="document.getElementById('crud-score-display').textContent = this.value" />
              </div>
              <div>
                <div class="crud-field-heading">
                  <label for="crudAccess">Road Access <span class="crud-optional">Optional</span></label>
                  <span id="crud-access-display">85</span>
                </div>
                <input type="number" min="40" max="100" class="input-shell" id="crudAccess" value="85" oninput="document.getElementById('crud-access-display').textContent = this.value" />
              </div>
            </div>
            <p class="auth-form-note">Seller verification updates the linked seller account. Approval state and document review control listing trust badges and visibility.</p>
          </div>
        </section>
      </div>
      <div class="crud-form-actions">
        <button type="button" class="btn-shell btn-shell-secondary" data-modal-close="propertyCrudModal">Cancel</button>
        <button type="submit" class="btn-shell btn-shell-primary" id="crud-btn-save">Save Property</button>
      </div>
    </form>
  </div>
</div>
<!-- Delete Modal (unchanged) -->
<div class="modal-shell" id="propertyDeleteModal" hidden>
  <div class="modal-card compact-modal">
    <div class="modal-head">
      <div>
        <div class="panel-kicker">Delete Listing</div>
        <h3 class="section-title">Remove this property?</h3>
      </div>
      <button type="button" class="modal-close" data-modal-close="propertyDeleteModal">Close</button>
    </div>
    <p class="panel-text" id="deletePropertyLabel">This will remove the selected property from MySQL.</p>
    <div class="crud-actions">
      <button type="button" class="btn-shell btn-shell-secondary" data-modal-close="propertyDeleteModal">Cancel</button>
      <button type="button" class="btn-shell btn-shell-danger" id="confirmDeleteProperty">Delete Property</button>
    </div>
  </div>
</div>

<!-- Property form presentation and segmented controls -->
<script>
(function () {
  window.crudSeg = function (groupId, selectId, btn, val) {
    var grp = document.getElementById(groupId);
    if (!grp) return;
    grp.querySelectorAll('.crud-seg-btn').forEach(function (b) {
      b.setAttribute('aria-pressed', 'false');
      b.style.background = '#fff'; b.style.color = '#64748b';
      b.style.border = '1px solid #e2e8f0'; b.style.fontWeight = '600';
    });
    btn.style.background = '#eef2ff'; btn.style.color = '#4338ca';
    btn.style.border = '1px solid #6366f1'; btn.style.fontWeight = '700';
    btn.setAttribute('aria-pressed', 'true');
    var sel = document.getElementById(selectId);
    if (sel) sel.value = val;
  };

  window.crudYesNo = function (groupId, selectId, btn, val, isYes) {
    var grp = document.getElementById(groupId);
    if (!grp) return;
    grp.querySelectorAll('.crud-seg-btn').forEach(function (b) {
      b.setAttribute('aria-pressed', 'false');
      b.style.background = '#fff'; b.style.color = '#64748b';
      b.style.border = '1px solid #e2e8f0'; b.style.fontWeight = '600';
    });
    if (isYes) {
      btn.style.background = '#ecfdf5'; btn.style.color = '#065f46'; btn.style.border = '1px solid #10b981';
    } else {
      btn.style.background = '#fff1f2'; btn.style.color = '#9f1239'; btn.style.border = '1px solid #fca5a5';
    }
    btn.style.fontWeight = '700';
    btn.setAttribute('aria-pressed', 'true');
    var sel = document.getElementById(selectId);
    if (sel) sel.value = val;
  };

  window.crudFormatPrice = function (rawVal) {
    var badge = document.getElementById('crud-price-badge');
    if (!badge) return;
    var n = parseFloat(rawVal);
    if (!rawVal || isNaN(n)) { badge.textContent = ''; return; }
    var fmt;
    if      (n >= 1e9)  fmt = '\u20B1 ' + (n / 1e9).toFixed(2) + 'B PHP';
    else if (n >= 1e6)  fmt = '\u20B1 ' + (n / 1e6).toFixed(1) + 'M PHP';
    else if (n >= 1e3)  fmt = '\u20B1 ' + (n / 1e3).toFixed(1) + 'K PHP';
    else                fmt = '\u20B1 ' + n.toLocaleString() + ' PHP';
    badge.textContent = fmt;
  };

  window.crudSyncArea = function (val, fromUnit) {
    var n = parseFloat(val) || 0;
    var sqmEl = document.getElementById('crudLandAreaSqm');
    var haEl  = document.getElementById('crudLandArea');
    var hint  = document.getElementById('crudLandAreaHint');
    if (fromUnit === 'ha') {
      var sqm = Math.round(n * 10000);
      if (sqmEl) sqmEl.value = sqm || '';
      if (hint && n > 0) hint.textContent = n + ' ha = ' + sqm.toLocaleString() + ' sqm';
    } else {
      var ha = +(n / 10000).toFixed(4);
      if (haEl) haEl.value = ha || '';
      if (hint && n > 0) hint.textContent = n.toLocaleString() + ' sqm = ' + ha + ' ha';
    }
  };

  // Auto-sync segmented buttons when fillCrudForm sets select values.
  // fillCrudForm in portal.js sets .value on the hidden selects.
  // We observe the modal opening and re-sync.
  function _syncSegsFromSelects() {
    var pairs = [
      ['crudPropertyType',        'seg-crud-type'],
      ['crudCorridor',            'seg-crud-corridor'],
      ['crudStatus',              'seg-crud-status'],
      ['crudApprovalState',       'seg-crud-approval'],
      ['crudSellerIdentityStatus','seg-crud-seller'],
    ];
    pairs.forEach(function (pair) {
      var sel = document.getElementById(pair[0]);
      var grp = document.getElementById(pair[1]);
      if (!sel || !grp) return;
      var val = sel.value;
      grp.querySelectorAll('.crud-seg-btn').forEach(function (b) {
        b.setAttribute('aria-pressed', 'false');
        b.style.background = '#fff'; b.style.color = '#64748b';
        b.style.border = '1px solid #e2e8f0'; b.style.fontWeight = '600';
      });
      var match = grp.querySelector('[data-val="' + val + '"]');
      if (match) {
        match.setAttribute('aria-pressed', 'true');
        match.style.background = '#eef2ff'; match.style.color = '#4338ca';
        match.style.border = '1px solid #6366f1'; match.style.fontWeight = '700';
      }
    });
    // Yes/No pairs
    var ynPairs = [
      ['crudDocumentsReviewed', 'seg-crud-docs'],
      ['crudSiteVerified',      'seg-crud-site'],
    ];
    ynPairs.forEach(function (pair) {
      var sel = document.getElementById(pair[0]);
      var grp = document.getElementById(pair[1]);
      if (!sel || !grp) return;
      var isYes = sel.value === '1';
      grp.querySelectorAll('.crud-seg-btn').forEach(function (b) {
        b.setAttribute('aria-pressed', 'false');
        b.style.background = '#fff'; b.style.color = '#64748b';
        b.style.border = '1px solid #e2e8f0'; b.style.fontWeight = '600';
      });
      var match = grp.querySelector('[data-val="' + sel.value + '"]');
      if (match) {
        match.setAttribute('aria-pressed', 'true');
        if (isYes) {
          match.style.background = '#ecfdf5'; match.style.color = '#065f46'; match.style.border = '1px solid #10b981';
        } else {
          match.style.background = '#fff1f2'; match.style.color = '#9f1239'; match.style.border = '1px solid #fca5a5';
        }
        match.style.fontWeight = '700';
      }
    });
    // Refresh live displays
    var priceEl = document.getElementById('crudPrice');
    if (priceEl) crudFormatPrice(priceEl.value);
    var haEl = document.getElementById('crudLandArea');
    if (haEl && haEl.value) crudSyncArea(haEl.value, 'ha');
    var scoreEl = document.getElementById('crudScore');
    var sdEl = document.getElementById('crud-score-display');
    if (scoreEl && sdEl) sdEl.textContent = scoreEl.value;
    var accEl = document.getElementById('crudAccess');
    var adEl = document.getElementById('crud-access-display');
    if (accEl && adEl) adEl.textContent = accEl.value;
  }

  var _modal = document.getElementById('propertyCrudModal');
  var _returnFocus = null;
  var _bodyOverflow = '';
  function focusableControls() {
    return Array.from(_modal.querySelectorAll('button, input, textarea, select, [tabindex]')).filter(function (el) {
      return !el.disabled && el.tabIndex >= 0 && el.getClientRects().length;
    });
  }
  if (_modal) {
    new MutationObserver(function () {
      if (!_modal.hidden) {
        _returnFocus = document.activeElement;
        _bodyOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        _syncSegsFromSelects();
        document.getElementById('crudModalSubtitle').textContent = 'Fields marked Required must be completed.';
        document.getElementById('crud-panels-wrap').scrollTop = 0;
        _modal.querySelector('[data-modal-close]').focus({ preventScroll: true });
      } else {
        document.body.style.overflow = _bodyOverflow;
        if (_returnFocus && _returnFocus.isConnected) _returnFocus.focus({ preventScroll: true });
      }
    }).observe(_modal, { attributes: true, attributeFilter: ['hidden'] });
  }

  _modal.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      event.preventDefault();
      _modal.querySelector('[data-modal-close]').click();
    }
    if (event.key === 'Tab') {
      var controls = focusableControls();
      var first = controls[0], last = controls[controls.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault(); last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault(); first.focus();
      }
    }
  });
})();
</script>
<?php sfc_render_footer($context); ?>
