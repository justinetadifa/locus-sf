CREATE DATABASE IF NOT EXISTS sfcelerate_bizstart
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE sfcelerate_bizstart;

CREATE TABLE IF NOT EXISTS users (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  role VARCHAR(40) NOT NULL,
  name VARCHAR(140) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  identity_verification_status VARCHAR(40) NOT NULL DEFAULT 'unverified',
  identity_verified_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_users_email (email),
  KEY idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_preferences (
  user_id INT NOT NULL PRIMARY KEY,
  notification_cadence VARCHAR(20) NOT NULL DEFAULT 'instant',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_user_preferences_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS seller_profiles (
  user_id INT NOT NULL PRIMARY KEY,
  seller_type VARCHAR(40) NOT NULL DEFAULT 'individual',
  legal_name VARCHAR(190) NULL,
  display_name VARCHAR(190) NULL,
  phone VARCHAR(60) NULL,
  company_name VARCHAR(190) NULL,
  business_registration_no VARCHAR(120) NULL,
  government_id_no VARCHAR(120) NULL,
  address_line VARCHAR(255) NULL,
  barangay VARCHAR(120) NULL,
  city VARCHAR(120) NOT NULL DEFAULT 'San Fernando, La Union',
  authorization_basis VARCHAR(190) NULL,
  application_status VARCHAR(40) NOT NULL DEFAULT 'draft',
  review_notes TEXT NULL,
  submitted_at TIMESTAMP NULL DEFAULT NULL,
  reviewed_at TIMESTAMP NULL DEFAULT NULL,
  reviewed_by_user_id INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_seller_profiles_phone (phone),
  UNIQUE KEY uniq_seller_profiles_business_reg (business_registration_no),
  UNIQUE KEY uniq_seller_profiles_government_id (government_id_no),
  KEY idx_seller_profiles_status (application_status),
  KEY idx_seller_profiles_reviewed_by (reviewed_by_user_id),
  CONSTRAINT fk_seller_profiles_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_seller_profiles_reviewer
    FOREIGN KEY (reviewed_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS properties (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  city VARCHAR(120) NOT NULL DEFAULT 'San Fernando, La Union',
  lat DECIMAL(10, 6) NOT NULL,
  lng DECIMAL(10, 6) NOT NULL,
  area DECIMAL(12, 4) NOT NULL,
  price BIGINT NOT NULL,
  price_per_sqm INT NOT NULL,
  status VARCHAR(80) NOT NULL,
  approval_state VARCHAR(40) NOT NULL DEFAULT 'approved',
  score INT NOT NULL DEFAULT 82,
  type VARCHAR(80) NOT NULL,
  corridor VARCHAR(80) NOT NULL,
  tags_json JSON NOT NULL,
  facilities_json JSON NOT NULL,
  road_access INT NOT NULL,
  image_url VARCHAR(255) NOT NULL,
  description TEXT NOT NULL,
  barangay VARCHAR(120) NULL,
  owner_contact_json JSON NULL,
  documents_json JSON NULL,
  seller_user_id INT NULL,
  documents_reviewed_at TIMESTAMP NULL DEFAULT NULL,
  site_verified_at TIMESTAMP NULL DEFAULT NULL,
  last_confirmed_available_at TIMESTAMP NULL DEFAULT NULL,
  dist_to_road_km DECIMAL(8, 2) NULL,
  utility_status VARCHAR(40) NULL,
  zoning_score INT NULL,
  existing_land_use VARCHAR(180) NULL,
  zoning_classification VARCHAR(180) NULL,
  clup_allowed_uses_json JSON NULL,
  clup_conditional_uses_json JSON NULL,
  clup_restricted_uses_json JSON NULL,
  clup_source_reference VARCHAR(255) NULL,
  clup_verified_at TIMESTAMP NULL DEFAULT NULL,
  assessed_value_sqm INT NULL,
  readiness_notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_properties_seller_user (seller_user_id),
  KEY idx_properties_approval_state (approval_state),
  KEY idx_properties_last_confirmed_available (last_confirmed_available_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clup_datasets (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(180) NOT NULL,
  version_label VARCHAR(80) NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'draft',
  source_agency VARCHAR(180) NULL,
  source_reference VARCHAR(255) NULL,
  source_url VARCHAR(500) NULL,
  effective_date DATE NULL,
  coordinate_reference_system VARCHAR(80) NULL,
  checksum_sha256 CHAR(64) NULL,
  notes TEXT NULL,
  activated_by_user_id INT NULL,
  activated_at TIMESTAMP NULL DEFAULT NULL,
  created_by_user_id INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_clup_dataset_version (name, version_label),
  KEY idx_clup_datasets_status (status),
  CONSTRAINT fk_clup_datasets_activated_by FOREIGN KEY (activated_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_clup_datasets_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clup_zones (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  dataset_id INT NOT NULL,
  zone_code VARCHAR(80) NOT NULL,
  name VARCHAR(180) NOT NULL,
  description TEXT NULL,
  geometry_type VARCHAR(40) NOT NULL DEFAULT 'Polygon',
  geometry_json JSON NULL,
  bbox_json JSON NULL,
  properties_json JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_clup_zone_code (dataset_id, zone_code),
  KEY idx_clup_zones_dataset (dataset_id),
  CONSTRAINT fk_clup_zones_dataset FOREIGN KEY (dataset_id) REFERENCES clup_datasets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clup_use_types (
  code VARCHAR(80) NOT NULL PRIMARY KEY,
  label VARCHAR(160) NOT NULL,
  category VARCHAR(100) NOT NULL,
  description TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clup_zone_use_rules (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  dataset_id INT NOT NULL,
  zone_id INT NOT NULL,
  use_code VARCHAR(80) NOT NULL,
  permission_status VARCHAR(30) NOT NULL,
  conditions_json JSON NULL,
  ordinance_reference VARCHAR(255) NULL,
  effective_date DATE NULL,
  expires_at DATE NULL,
  created_by_user_id INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_clup_zone_use_rule (zone_id, use_code),
  KEY idx_clup_rules_dataset (dataset_id),
  KEY idx_clup_rules_permission (permission_status),
  CONSTRAINT fk_clup_rules_dataset FOREIGN KEY (dataset_id) REFERENCES clup_datasets(id) ON DELETE CASCADE,
  CONSTRAINT fk_clup_rules_zone FOREIGN KEY (zone_id) REFERENCES clup_zones(id) ON DELETE CASCADE,
  CONSTRAINT fk_clup_rules_use FOREIGN KEY (use_code) REFERENCES clup_use_types(code) ON DELETE RESTRICT,
  CONSTRAINT fk_clup_rules_creator FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clup_site_profiles (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  property_id INT NOT NULL,
  version_no INT NOT NULL,
  dataset_id INT NULL,
  zone_id INT NULL,
  existing_land_use VARCHAR(180) NULL,
  zoning_classification VARCHAR(180) NULL,
  allowed_uses_json JSON NULL,
  conditional_uses_json JSON NULL,
  restricted_uses_json JSON NULL,
  source_reference VARCHAR(255) NULL,
  source_document_path VARCHAR(500) NULL,
  review_status VARCHAR(30) NOT NULL DEFAULT 'draft',
  prepared_by_user_id INT NULL,
  submitted_at TIMESTAMP NULL DEFAULT NULL,
  reviewed_by_user_id INT NULL,
  reviewed_at TIMESTAMP NULL DEFAULT NULL,
  review_notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_clup_profile_version (property_id, version_no),
  KEY idx_clup_profiles_property_status (property_id, review_status),
  KEY idx_clup_profiles_dataset (dataset_id),
  CONSTRAINT fk_clup_profiles_property FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE RESTRICT,
  CONSTRAINT fk_clup_profiles_dataset FOREIGN KEY (dataset_id) REFERENCES clup_datasets(id) ON DELETE RESTRICT,
  CONSTRAINT fk_clup_profiles_zone FOREIGN KEY (zone_id) REFERENCES clup_zones(id) ON DELETE RESTRICT,
  CONSTRAINT fk_clup_profiles_preparer FOREIGN KEY (prepared_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_clup_profiles_reviewer FOREIGN KEY (reviewed_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clup_evidence_documents (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  profile_id INT NOT NULL,
  document_type VARCHAR(80) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  storage_path VARCHAR(500) NOT NULL,
  mime_type VARCHAR(120) NOT NULL,
  file_size BIGINT NOT NULL,
  checksum_sha256 CHAR(64) NOT NULL,
  uploaded_by_user_id INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_clup_evidence_profile (profile_id),
  UNIQUE KEY uniq_clup_evidence_checksum (profile_id, checksum_sha256),
  CONSTRAINT fk_clup_evidence_profile FOREIGN KEY (profile_id) REFERENCES clup_site_profiles(id) ON DELETE RESTRICT,
  CONSTRAINT fk_clup_evidence_uploader FOREIGN KEY (uploaded_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clup_review_events (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  profile_id INT NOT NULL,
  event_type VARCHAR(40) NOT NULL,
  from_status VARCHAR(30) NULL,
  to_status VARCHAR(30) NOT NULL,
  notes TEXT NULL,
  actor_user_id INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_clup_review_events_profile (profile_id, created_at),
  CONSTRAINT fk_clup_review_events_profile FOREIGN KEY (profile_id) REFERENCES clup_site_profiles(id) ON DELETE RESTRICT,
  CONSTRAINT fk_clup_review_events_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clup_evaluations (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  property_id INT NOT NULL,
  profile_id INT NULL,
  dataset_id INT NULL,
  proposed_use_code VARCHAR(80) NOT NULL,
  compliance_status VARCHAR(30) NOT NULL,
  suitability_score INT NULL,
  evidence_level VARCHAR(30) NOT NULL,
  engine_version VARCHAR(80) NOT NULL,
  input_snapshot_json JSON NOT NULL,
  result_snapshot_json JSON NOT NULL,
  evaluated_by_user_id INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_clup_evaluations_property (property_id, created_at),
  KEY idx_clup_evaluations_status (compliance_status),
  CONSTRAINT fk_clup_evaluations_property FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE RESTRICT,
  CONSTRAINT fk_clup_evaluations_profile FOREIGN KEY (profile_id) REFERENCES clup_site_profiles(id) ON DELETE RESTRICT,
  CONSTRAINT fk_clup_evaluations_dataset FOREIGN KEY (dataset_id) REFERENCES clup_datasets(id) ON DELETE RESTRICT,
  CONSTRAINT fk_clup_evaluations_use FOREIGN KEY (proposed_use_code) REFERENCES clup_use_types(code) ON DELETE RESTRICT,
  CONSTRAINT fk_clup_evaluations_actor FOREIGN KEY (evaluated_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clup_reports (
  id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  report_number VARCHAR(80) NOT NULL,
  report_type VARCHAR(60) NOT NULL,
  title VARCHAR(255) NOT NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'generated',
  snapshot_json JSON NOT NULL,
  checksum_sha256 CHAR(64) NOT NULL,
  generated_by_user_id INT NULL,
  reviewed_by_user_id INT NULL,
  reviewed_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_clup_report_number (report_number),
  KEY idx_clup_reports_type_created (report_type, created_at),
  CONSTRAINT fk_clup_reports_generator FOREIGN KEY (generated_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_clup_reports_reviewer FOREIGN KEY (reviewed_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS property_media (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  property_id INT NOT NULL,
  kind VARCHAR(40) NOT NULL DEFAULT 'image',
  source VARCHAR(255) NOT NULL,
  alt_text VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_property_media_property
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS property_due_diligence (
  property_id INT NOT NULL PRIMARY KEY,
  state_json JSON NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_due_diligence_property
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vote_options (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(160) NOT NULL,
  slug VARCHAR(190) NOT NULL,
  description VARCHAR(255) NULL,
  image_url VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_by_user_id INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_vote_options_slug (slug),
  KEY idx_vote_options_active (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS showcase_items (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  feature_type VARCHAR(40) NOT NULL,
  title VARCHAR(190) NOT NULL,
  slug VARCHAR(190) NOT NULL,
  partner_label VARCHAR(160) NULL,
  summary VARCHAR(255) NOT NULL,
  description TEXT NOT NULL,
  category VARCHAR(120) NULL,
  location_label VARCHAR(190) NULL,
  barangay VARCHAR(120) NULL,
  status VARCHAR(80) NOT NULL,
  pipeline_mode VARCHAR(40) NULL,
  supply_signal VARCHAR(40) NULL,
  cover_image_url VARCHAR(255) NOT NULL,
  primary_metric_label VARCHAR(120) NULL,
  primary_metric_value VARCHAR(120) NULL,
  secondary_metric_label VARCHAR(120) NULL,
  secondary_metric_value VARCHAR(120) NULL,
  investor_thesis VARCHAR(255) NULL,
  ideal_operator VARCHAR(160) NULL,
  avoidance_note VARCHAR(255) NULL,
  countdown_at DATETIME NULL,
  completion_target DATETIME NULL,
  related_property_id INT NULL,
  is_published TINYINT(1) NOT NULL DEFAULT 1,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  created_by_user_id INT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_showcase_items_slug (slug),
  KEY idx_showcase_items_feature (feature_type, is_published, sort_order),
  KEY idx_showcase_items_related_property (related_property_id),
  CONSTRAINT fk_showcase_items_property
    FOREIGN KEY (related_property_id) REFERENCES properties(id) ON DELETE SET NULL,
  CONSTRAINT fk_showcase_items_creator
    FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS property_votes (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  property_id INT NOT NULL,
  vote_option_id INT NULL,
  voter_user_id INT NULL,
  label VARCHAR(160) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_property_votes_property (property_id),
  KEY idx_property_votes_label (label),
  KEY idx_property_votes_vote_option (vote_option_id),
  UNIQUE KEY uniq_property_votes_voter (property_id, voter_user_id),
  CONSTRAINT fk_property_votes_property
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS message_threads (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  property_id INT NOT NULL,
  investor_user_id INT NOT NULL,
  seller_user_id INT NULL,
  subject VARCHAR(190) NULL,
  last_message_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_message_threads_property_investor (property_id, investor_user_id),
  KEY idx_message_threads_seller (seller_user_id),
  KEY idx_message_threads_last_message (last_message_at),
  CONSTRAINT fk_message_threads_property
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS property_messages (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  thread_id INT NULL,
  property_id INT NOT NULL,
  sender_user_id INT NULL,
  recipient_user_id INT NULL,
  sender_name VARCHAR(120) NOT NULL,
  role VARCHAR(40) NOT NULL,
  text TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_property_messages_property (property_id),
  KEY idx_property_messages_thread (thread_id),
  KEY idx_property_messages_sender (sender_user_id),
  CONSTRAINT fk_property_messages_property
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS property_shortlists (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  investor_user_id INT NOT NULL,
  property_id INT NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_property_shortlists_investor_property (investor_user_id, property_id),
  KEY idx_property_shortlists_property (property_id),
  CONSTRAINT fk_property_shortlists_user
    FOREIGN KEY (investor_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_property_shortlists_property
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS property_document_requests (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  property_id INT NOT NULL,
  requester_user_id INT NULL,
  seller_user_id INT NULL,
  requester_name VARCHAR(140) NOT NULL,
  requester_role VARCHAR(40) NOT NULL,
  document_name VARCHAR(160) NOT NULL,
  note TEXT NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'requested',
  response_note TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  resolved_at TIMESTAMP NULL DEFAULT NULL,
  KEY idx_document_requests_property (property_id),
  KEY idx_document_requests_seller (seller_user_id),
  KEY idx_document_requests_requester (requester_user_id),
  KEY idx_document_requests_status (status),
  CONSTRAINT fk_document_requests_property
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS visit_logs (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  property_id INT NOT NULL,
  thread_id INT NOT NULL,
  investor_user_id INT NOT NULL,
  seller_user_id INT NULL,
  investment_purpose VARCHAR(140) NOT NULL,
  primary_start_at DATETIME NOT NULL,
  primary_end_at DATETIME NOT NULL,
  secondary_start_at DATETIME NOT NULL,
  secondary_end_at DATETIME NOT NULL,
  counter_start_at DATETIME NULL,
  counter_end_at DATETIME NULL,
  confirmed_start_at DATETIME NULL,
  confirmed_end_at DATETIME NULL,
  started_at DATETIME NULL,
  visited_at DATETIME NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'proposed',
  field_audit_json JSON NULL,
  ground_truth_multiplier DECIMAL(5,2) NULL,
  activity_json JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_visit_logs_thread (thread_id),
  KEY idx_visit_logs_property_status (property_id, status),
  KEY idx_visit_logs_investor (investor_user_id),
  KEY idx_visit_logs_seller (seller_user_id),
  KEY idx_visit_logs_visited (visited_at),
  CONSTRAINT fk_visit_logs_property
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
  CONSTRAINT fk_visit_logs_thread
    FOREIGN KEY (thread_id) REFERENCES message_threads(id) ON DELETE CASCADE,
  CONSTRAINT fk_visit_logs_investor
    FOREIGN KEY (investor_user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_visit_logs_seller
    FOREIGN KEY (seller_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  actor_user_id INT NULL,
  property_id INT NULL,
  thread_id INT NULL,
  document_request_id INT NULL,
  category VARCHAR(40) NOT NULL DEFAULT 'transactional',
  kind VARCHAR(80) NOT NULL DEFAULT 'update',
  priority VARCHAR(20) NOT NULL DEFAULT 'normal',
  tone VARCHAR(20) NOT NULL DEFAULT 'system',
  icon VARCHAR(24) NOT NULL DEFAULT 'signal',
  title VARCHAR(190) NOT NULL,
  body TEXT NOT NULL,
  action_label VARCHAR(60) NULL,
  action_url VARCHAR(255) NULL,
  meta_json JSON NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  read_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_notifications_user_created (user_id, created_at),
  KEY idx_notifications_user_read (user_id, is_read),
  KEY idx_notifications_property (property_id),
  CONSTRAINT fk_notifications_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_notifications_actor
    FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_notifications_property
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  actor_id INT NULL,
  action_type VARCHAR(40) NOT NULL DEFAULT 'EDIT',
  entity_type VARCHAR(40) NOT NULL DEFAULT 'PROPERTY',
  entity_id INT NOT NULL,
  metadata JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_audit_logs_created (created_at),
  KEY idx_audit_logs_entity (entity_type, entity_id),
  KEY idx_audit_logs_actor (actor_id),
  CONSTRAINT fk_audit_logs_actor
    FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS investment_scenarios (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  property_id INT NOT NULL,
  name VARCHAR(255) NOT NULL,
  created_by VARCHAR(120) NOT NULL DEFAULT 'Local Analyst',
  budget BIGINT NULL,
  sector VARCHAR(80) NULL,
  size DECIMAL(10, 2) NULL,
  weights_json JSON NULL,
  assumptions_json JSON NULL,
  results_json JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_investment_scenarios_property (property_id),
  CONSTRAINT fk_investment_scenarios_property
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS spatial_overlays (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  slug VARCHAR(190) NOT NULL,
  overlay_type VARCHAR(40) NOT NULL DEFAULT 'parcel',
  match_key VARCHAR(190) NULL,
  geometry_type VARCHAR(40) NOT NULL DEFAULT 'polygon',
  geometry_json JSON NULL,
  style_json JSON NULL,
  description TEXT NULL,
  property_id INT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_spatial_overlays_slug (slug),
  KEY idx_spatial_overlays_type_active (overlay_type, is_active),
  KEY idx_spatial_overlays_match_key (match_key),
  KEY idx_spatial_overlays_property (property_id),
  CONSTRAINT fk_spatial_overlays_property
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
