<?php
declare(strict_types=1);

require __DIR__ . '/app/Support/web.php';

$context = sfc_web_context();
sfc_require_role('admin', sfc_path('/admin-login.php'));
$context = sfc_web_context();
sfc_render_head('Admin Showcase Studio | LOCUS-SF', $context, ['page' => 'admin-showcase', 'role' => 'admin']);
sfc_render_header($context, 'admin-showcase');
?>
<main class="page-shell admin-showcase-page">
  <section class="site-shell page-intro-card">
    <div>
      <div class="eyebrow">Admin Showcase Studio</div>
      <div class="page-role-strip">
        <span class="page-role-pill is-role">Admin Workspace</span>
        <span class="page-role-pill">Offer Board + City Pipeline</span>
      </div>
      <h1>Manage the Offer Board and City Pipeline with one polished control surface.</h1>
      <p>Create, curate, publish, and reorder the hidden showcase features that live under the platform's More menu, including investor gap briefs for businesses and establishments San Fernando still wants to attract.</p>
    </div>
    <div class="intro-actions">
      <button type="button" class="btn-shell btn-shell-primary" id="adminShowcaseAdd">Add Showcase Item</button>
      <a href="<?= htmlspecialchars(sfc_path('/offer-board.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn-shell btn-shell-secondary">Open Offer Board</a>
    </div>
  </section>

  <section class="site-shell admin-showcase-root-grid" id="adminShowcaseRoot">
    <div class="loading-panel">Loading showcase studio...</div>
  </section>
</main>

<div class="modal-shell" id="showcaseCrudModal" hidden>
  <div class="modal-card showcase-crud-modal">
    <div class="modal-head">
      <div>
        <div class="panel-kicker">Admin Showcase Editor</div>
        <h3 class="section-title" id="showcaseCrudTitle">Add Showcase Item</h3>
      </div>
      <button type="button" class="modal-close" data-modal-close="showcaseCrudModal">Close</button>
    </div>
    <form id="showcaseCrudForm" class="crud-form-grid showcase-crud-form" novalidate>
      <input type="hidden" id="showcaseItemId">

      <div class="showcase-form-intro form-span-2">
        <div class="showcase-form-intro-copy">
          <div class="panel-kicker">Quick Setup</div>
          <h4 id="showcaseCrudModeLabel">Start with the essentials first.</h4>
          <p id="showcaseCrudGuide">Title, board type, stage, and summary are enough to create the first version. Timing, media, and extra metrics can be added later.</p>
        </div>
        <div class="showcase-form-intro-badges">
          <span class="meta-chip" id="showcaseFormFeatureBadge">Offer Board</span>
          <span class="meta-chip" id="showcaseFormModeBadge">Timed opportunity</span>
        </div>
      </div>

      <div class="showcase-wizard-progress form-span-2" aria-label="Showcase form steps">
        <button type="button" class="showcase-step-pill is-active" data-showcase-step-trigger="0"><span>1</span><strong>Basic Info</strong></button>
        <button type="button" class="showcase-step-pill" data-showcase-step-trigger="1"><span>2</span><strong>Positioning</strong></button>
        <button type="button" class="showcase-step-pill" data-showcase-step-trigger="2"><span>3</span><strong>Signals</strong></button>
        <button type="button" class="showcase-step-pill" data-showcase-step-trigger="3"><span>4</span><strong>Narrative</strong></button>
        <button type="button" class="showcase-step-pill" data-showcase-step-trigger="4"><span>5</span><strong>Media &amp; Publish</strong></button>
      </div>

      <section class="showcase-form-step form-span-2" data-showcase-step="0" data-step-title="Basic Info" data-step-hint="Start with the identity of the card. If these fields are clear, the board already has a usable first draft.">
        <div class="showcase-form-section-head">
          <div>
            <div class="panel-kicker">Step 1</div>
            <h4>Start with the card identity.</h4>
            <p>Keep this first pass light. Name the opportunity, choose its lane, and give it a clear public summary.</p>
          </div>
        </div>
        <div class="showcase-form-grid">
          <label class="form-shell">
            <span>Where should this appear?</span>
            <select class="input-shell" id="showcaseFeatureType">
              <option value="offer_board">Offer Board</option>
              <option value="city_pipeline">City Pipeline</option>
            </select>
          </label>
          <label class="form-shell showcase-pipeline-only">
            <span>What kind of pipeline entry is it?</span>
            <select class="input-shell" id="showcasePipelineMode">
              <option value="future_project">Future Project</option>
              <option value="investment_gap">Investor Gap / Missing Establishment</option>
            </select>
          </label>
          <label class="form-shell form-span-2">
            <span>What should the card be called?</span>
            <input class="input-shell" id="showcaseTitle" placeholder="North Gateway Retail Commons" required>
          </label>
          <label class="form-shell">
            <span>Which city lane does it belong to?</span>
            <input class="input-shell" id="showcaseCategory" placeholder="Logistics, Retail, Healthcare">
          </label>
          <label class="form-shell">
            <span>What is its current stage?</span>
            <select class="input-shell" id="showcaseStatus"></select>
          </label>
          <label class="form-shell form-span-2">
            <span>Short public summary</span>
            <textarea class="input-shell input-textarea showcase-summary-textarea" id="showcaseSummary" placeholder="Give the shortest public explanation of why this entry matters right now." required></textarea>
          </label>
        </div>
      </section>

      <section class="showcase-form-step form-span-2" data-showcase-step="1" data-step-title="Positioning" data-step-hint="Place the card in the city story. These fields help investors understand where the opportunity belongs and what it connects to.">
        <div class="showcase-form-section-head">
          <div>
            <div class="panel-kicker">Step 2</div>
            <h4>Position it inside the city story.</h4>
            <p>Use only the context the investor actually needs. You can leave the rest for later.</p>
          </div>
        </div>
        <div class="showcase-form-grid">
          <label class="form-shell">
            <span>Who is attached to this card?</span>
            <input class="input-shell" id="showcasePartnerLabel" placeholder="City Investment Desk">
          </label>
          <label class="form-shell">
            <span>Link it to a property?</span>
            <select class="input-shell" id="showcaseRelatedProperty">
              <option value="">None</option>
            </select>
          </label>
          <label class="form-shell">
            <span>Public location label</span>
            <input class="input-shell" id="showcaseLocationLabel" value="San Fernando, La Union">
          </label>
          <label class="form-shell">
            <span>Barangay</span>
            <input class="input-shell" id="showcaseBarangay" placeholder="Poro">
          </label>
        </div>
      </section>

      <section class="showcase-form-step form-span-2" data-showcase-step="2" data-step-title="Signals" data-step-hint="Add only the signals that sharpen investor understanding. Dates and ordering stay tucked away until you need them.">
        <div class="showcase-form-section-head">
          <div>
            <div class="panel-kicker">Step 3</div>
            <h4>Add the signals that make the card feel credible.</h4>
            <p>These are optional confidence builders, not a wall of requirements.</p>
          </div>
        </div>
        <div class="showcase-form-grid">
          <label class="form-shell showcase-gap-only">
            <span>Supply signal</span>
            <select class="input-shell" id="showcaseSupplySignal">
              <option value="not_present">Not In Tracked Supply</option>
              <option value="under_supplied">Under-Supplied</option>
              <option value="balanced">Balanced Supply</option>
              <option value="crowded">Already Crowded</option>
            </select>
          </label>
          <label class="form-shell">
            <span>Primary metric label</span>
            <input class="input-shell" id="showcasePrimaryMetricLabel" placeholder="Offer window, launch target, or need level">
          </label>
          <label class="form-shell">
            <span>Primary metric value</span>
            <input class="input-shell" id="showcasePrimaryMetricValue" placeholder="PHP 78.2M, Q4 2028, or High-priority need">
          </label>
          <label class="form-shell">
            <span>Secondary metric label</span>
            <input class="input-shell" id="showcaseSecondaryMetricLabel" placeholder="Current offer, supply signal, or stage">
          </label>
          <label class="form-shell">
            <span>Secondary metric value</span>
            <input class="input-shell" id="showcaseSecondaryMetricValue" placeholder="Closes soon, Under-supplied, Planned">
          </label>
        </div>
        <details class="showcase-form-advanced" id="showcaseAdvancedFields">
          <summary>Timing, ordering, and schedule</summary>
          <div class="showcase-form-advanced-grid">
            <label class="form-shell showcase-offer-only">
              <span>Countdown end</span>
              <input type="datetime-local" class="input-shell" id="showcaseCountdownAt">
            </label>
            <label class="form-shell showcase-pipeline-only">
              <span>Completion target</span>
              <input type="datetime-local" class="input-shell" id="showcaseCompletionTarget">
            </label>
            <label class="form-shell">
              <span>Sort order</span>
              <input type="number" class="input-shell" id="showcaseSortOrder" value="1">
            </label>
          </div>
        </details>
      </section>

      <section class="showcase-form-step form-span-2" data-showcase-step="3" data-step-title="Narrative" data-step-hint="Explain why the card matters only where it improves trust or investor clarity.">
        <div class="showcase-form-section-head">
          <div>
            <div class="panel-kicker">Step 4</div>
            <h4>Write the explanation behind the signal.</h4>
            <p>This is where the board stops feeling like data and starts sounding like informed guidance.</p>
          </div>
        </div>
        <div class="showcase-form-grid">
          <label class="form-shell showcase-gap-only form-span-2">
            <span>Why should an investor care?</span>
            <textarea class="input-shell input-textarea" id="showcaseInvestorThesis" placeholder="Why should an investor consider building this in San Fernando?"></textarea>
          </label>
          <label class="form-shell showcase-gap-only">
            <span>Ideal operator</span>
            <input class="input-shell" id="showcaseIdealOperator" placeholder="Regional brand, healthcare group, logistics operator">
          </label>
          <label class="form-shell showcase-gap-only">
            <span>What should they avoid duplicating?</span>
            <input class="input-shell" id="showcaseAvoidanceNote" placeholder="What should investors avoid copying or oversupplying?">
          </label>
          <label class="form-shell form-span-2">
            <span>Longer description</span>
            <textarea class="input-shell input-textarea" id="showcaseDescription" placeholder="Add a richer internal or public-facing explanation when the short summary is not enough."></textarea>
          </label>
        </div>
      </section>

      <section class="showcase-form-step form-span-2" data-showcase-step="4" data-step-title="Media & Publish" data-step-hint="Finish with the cover image and publish settings. This step should feel like a final check, not another heavy form.">
        <div class="showcase-form-section-head">
          <div>
            <div class="panel-kicker">Step 5</div>
            <h4>Choose the cover and final visibility.</h4>
            <p>Upload a cover if it is ready. If not, the fallback image is enough for a clean first release.</p>
          </div>
        </div>
        <div class="showcase-media-preview" id="showcaseMediaPreview">
          <div class="showcase-media-preview-frame">
            <img id="showcaseMediaPreviewImage" src="assets/images/Property10.png" alt="Showcase cover preview">
          </div>
          <div class="showcase-media-preview-copy">
            <div class="panel-kicker">Cover Preview</div>
            <h4 id="showcaseMediaPreviewTitle">Default cover</h4>
            <p id="showcaseMediaPreviewNote">Upload a new image or keep the fallback path for a first draft.</p>
          </div>
        </div>
        <div class="showcase-form-grid">
          <label class="form-shell">
            <span>Publish now?</span>
            <select class="input-shell" id="showcasePublished">
              <option value="1">Yes</option>
              <option value="0">No</option>
            </select>
          </label>
          <label class="form-shell">
            <span>Feature this entry?</span>
            <select class="input-shell" id="showcaseFeatured">
              <option value="0">No</option>
              <option value="1">Yes</option>
            </select>
          </label>
          <label class="form-shell form-span-2">
            <span>Upload cover image</span>
            <input type="file" class="input-shell" id="showcaseImage" accept="image/*">
          </label>
          <label class="form-shell form-span-2">
            <span>Fallback image path</span>
            <input class="input-shell" id="showcaseImagePath" value="assets/images/Property10.png">
          </label>
        </div>
      </section>

      <div class="auth-form-note form-span-2 showcase-form-note" id="showcasePipelineNote">Offer Board is for timed, curated opportunities. City Pipeline is for not-yet-built developments and investor gap briefs showing what San Fernando still needs.</div>
      <div class="auth-form-note form-span-2 showcase-form-feedback" id="showcaseCrudFeedback" hidden></div>
      <div class="showcase-wizard-footer form-span-2">
        <div class="showcase-wizard-foot-copy">
          <div class="panel-kicker" id="showcaseStepCount">Step 1 of 5</div>
          <h4 id="showcaseStepTitle">Basic Info</h4>
          <p id="showcaseStepHint">Start with the identity of the card. If these fields are clear, the board already has a usable first draft.</p>
        </div>
        <div class="crud-actions showcase-wizard-actions">
          <button type="button" class="btn-shell btn-shell-secondary" data-modal-close="showcaseCrudModal">Cancel</button>
          <button type="button" class="btn-shell btn-shell-secondary" id="showcaseWizardBack" hidden>Back</button>
          <button type="button" class="btn-shell btn-shell-primary" id="showcaseWizardNext">Next</button>
          <button type="submit" class="btn-shell btn-shell-primary" id="showcaseWizardSubmit" hidden>Save Showcase Item</button>
        </div>
      </div>
    </form>
  </div>
</div>

<div class="modal-shell" id="showcaseDeleteModal" hidden>
  <div class="modal-card compact-modal">
    <div class="modal-head">
      <div>
        <div class="panel-kicker">Delete Showcase Item</div>
        <h3 class="section-title">Remove this entry?</h3>
      </div>
      <button type="button" class="modal-close" data-modal-close="showcaseDeleteModal">Close</button>
    </div>
    <p class="panel-text" id="deleteShowcaseLabel">This will remove the selected showcase item.</p>
    <div class="crud-actions">
      <button type="button" class="btn-shell btn-shell-secondary" data-modal-close="showcaseDeleteModal">Cancel</button>
      <button type="button" class="btn-shell btn-shell-danger" id="confirmDeleteShowcase">Delete Item</button>
    </div>
  </div>
</div>
<?php sfc_render_footer($context); ?>
