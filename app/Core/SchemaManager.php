<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

final class SchemaManager
{
    public static function ensure(PDO $pdo): void
    {
        $statements = [
            <<<'SQL'
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
)
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS user_preferences (
  user_id INT NOT NULL PRIMARY KEY,
  notification_cadence VARCHAR(20) NOT NULL DEFAULT 'instant',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_user_preferences_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)
SQL,
            <<<'SQL'
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
)
SQL,
            <<<'SQL'
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
)
SQL,
            <<<'SQL'
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
)
SQL,
            <<<'SQL'
CREATE TABLE IF NOT EXISTS property_due_diligence (
  property_id INT NOT NULL PRIMARY KEY,
  state_json JSON NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_due_diligence_property
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
)
SQL,
            <<<'SQL'
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
)
SQL,
            <<<'SQL'
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
  KEY idx_showcase_items_related_property (related_property_id)
)
SQL,
            <<<'SQL'
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
)
SQL,
            <<<'SQL'
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
)
SQL,
            <<<'SQL'
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
)
SQL,
            <<<'SQL'
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
)
SQL,
            <<<'SQL'
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
)
SQL,
            <<<'SQL'
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
)
SQL,
            <<<'SQL'
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
)
SQL,
            <<<'SQL'
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
)
SQL,
            <<<'SQL'
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
)
SQL,
            <<<'SQL'
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
)
SQL,
        ];

        foreach ($statements as $statement) {
            $pdo->exec($statement);
        }

        self::ensureUsersColumns($pdo);
        self::ensureSellerProfilesColumns($pdo);
        self::ensurePropertiesColumns($pdo);
        self::ensurePropertyVotesColumns($pdo);
        self::ensurePropertyMessagesColumns($pdo);
        self::ensureShowcaseItemsColumns($pdo);
        self::ensureUserPreferenceRows($pdo);
        self::ensureIndexes($pdo);
    }

    public static function tableExists(PDO $pdo, string $tableName): bool
    {
        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table_name'
        );
        $statement->execute(['table_name' => $tableName]);

        return (int) $statement->fetchColumn() > 0;
    }

    public static function columnExists(PDO $pdo, string $tableName, string $columnName): bool
    {
        return self::columnDetails($pdo, $tableName, $columnName) !== null;
    }

    private static function ensureUsersColumns(PDO $pdo): void
    {
        if (!self::tableExists($pdo, 'users')) {
            return;
        }

        if (!self::columnExists($pdo, 'users', 'identity_verification_status')) {
            $pdo->exec(
                "ALTER TABLE users ADD COLUMN identity_verification_status VARCHAR(40) NOT NULL DEFAULT 'unverified' AFTER password_hash"
            );
        }

        if (!self::columnExists($pdo, 'users', 'identity_verified_at')) {
            $pdo->exec(
                'ALTER TABLE users ADD COLUMN identity_verified_at TIMESTAMP NULL DEFAULT NULL AFTER identity_verification_status'
            );
        }

        $pdo->exec(
            "UPDATE users
             SET identity_verification_status = 'unverified'
             WHERE identity_verification_status IS NULL OR TRIM(identity_verification_status) = ''"
        );
    }

    private static function ensureSellerProfilesColumns(PDO $pdo): void
    {
        if (!self::tableExists($pdo, 'seller_profiles')) {
            return;
        }

        if (!self::columnExists($pdo, 'seller_profiles', 'seller_type')) {
            $pdo->exec("ALTER TABLE seller_profiles ADD COLUMN seller_type VARCHAR(40) NOT NULL DEFAULT 'individual' AFTER user_id");
        }

        if (!self::columnExists($pdo, 'seller_profiles', 'legal_name')) {
            $pdo->exec('ALTER TABLE seller_profiles ADD COLUMN legal_name VARCHAR(190) NULL AFTER seller_type');
        }

        if (!self::columnExists($pdo, 'seller_profiles', 'display_name')) {
            $pdo->exec('ALTER TABLE seller_profiles ADD COLUMN display_name VARCHAR(190) NULL AFTER legal_name');
        }

        if (!self::columnExists($pdo, 'seller_profiles', 'phone')) {
            $pdo->exec('ALTER TABLE seller_profiles ADD COLUMN phone VARCHAR(60) NULL AFTER display_name');
        }

        if (!self::columnExists($pdo, 'seller_profiles', 'company_name')) {
            $pdo->exec('ALTER TABLE seller_profiles ADD COLUMN company_name VARCHAR(190) NULL AFTER phone');
        }

        if (!self::columnExists($pdo, 'seller_profiles', 'business_registration_no')) {
            $pdo->exec('ALTER TABLE seller_profiles ADD COLUMN business_registration_no VARCHAR(120) NULL AFTER company_name');
        }

        if (!self::columnExists($pdo, 'seller_profiles', 'government_id_no')) {
            $pdo->exec('ALTER TABLE seller_profiles ADD COLUMN government_id_no VARCHAR(120) NULL AFTER business_registration_no');
        }

        if (!self::columnExists($pdo, 'seller_profiles', 'address_line')) {
            $pdo->exec('ALTER TABLE seller_profiles ADD COLUMN address_line VARCHAR(255) NULL AFTER government_id_no');
        }

        if (!self::columnExists($pdo, 'seller_profiles', 'barangay')) {
            $pdo->exec('ALTER TABLE seller_profiles ADD COLUMN barangay VARCHAR(120) NULL AFTER address_line');
        }

        if (!self::columnExists($pdo, 'seller_profiles', 'city')) {
            $pdo->exec("ALTER TABLE seller_profiles ADD COLUMN city VARCHAR(120) NOT NULL DEFAULT 'San Fernando, La Union' AFTER barangay");
        }

        if (!self::columnExists($pdo, 'seller_profiles', 'authorization_basis')) {
            $pdo->exec('ALTER TABLE seller_profiles ADD COLUMN authorization_basis VARCHAR(190) NULL AFTER city');
        }

        if (!self::columnExists($pdo, 'seller_profiles', 'application_status')) {
            $pdo->exec("ALTER TABLE seller_profiles ADD COLUMN application_status VARCHAR(40) NOT NULL DEFAULT 'draft' AFTER authorization_basis");
        }

        if (!self::columnExists($pdo, 'seller_profiles', 'review_notes')) {
            $pdo->exec('ALTER TABLE seller_profiles ADD COLUMN review_notes TEXT NULL AFTER application_status');
        }

        if (!self::columnExists($pdo, 'seller_profiles', 'submitted_at')) {
            $pdo->exec('ALTER TABLE seller_profiles ADD COLUMN submitted_at TIMESTAMP NULL DEFAULT NULL AFTER review_notes');
        }

        if (!self::columnExists($pdo, 'seller_profiles', 'reviewed_at')) {
            $pdo->exec('ALTER TABLE seller_profiles ADD COLUMN reviewed_at TIMESTAMP NULL DEFAULT NULL AFTER submitted_at');
        }

        if (!self::columnExists($pdo, 'seller_profiles', 'reviewed_by_user_id')) {
            $pdo->exec('ALTER TABLE seller_profiles ADD COLUMN reviewed_by_user_id INT NULL AFTER reviewed_at');
        }

        $pdo->exec(
            "UPDATE seller_profiles
             SET application_status = 'draft'
             WHERE application_status IS NULL OR TRIM(application_status) = ''"
        );
        $pdo->exec(
            "UPDATE seller_profiles
             SET city = 'San Fernando, La Union'
             WHERE city IS NULL OR TRIM(city) = ''"
        );
    }

    private static function ensurePropertiesColumns(PDO $pdo): void
    {
        if (!self::tableExists($pdo, 'properties')) {
            return;
        }

        if (!self::columnExists($pdo, 'properties', 'city')) {
            $pdo->exec(
                "ALTER TABLE properties ADD COLUMN city VARCHAR(120) NOT NULL DEFAULT 'San Fernando, La Union' AFTER name"
            );
        }

        if (!self::columnExists($pdo, 'properties', 'score')) {
            $pdo->exec("ALTER TABLE properties ADD COLUMN score INT NOT NULL DEFAULT 82 AFTER status");
            $pdo->exec(
                'UPDATE properties
                 SET score = LEAST(96, GREATEST(68, ROUND((road_access * 0.65) + 22)))'
            );
        }

        if (!self::columnExists($pdo, 'properties', 'approval_state')) {
            $pdo->exec(
                "ALTER TABLE properties ADD COLUMN approval_state VARCHAR(40) NOT NULL DEFAULT 'approved' AFTER status"
            );
        }

        if (!self::columnExists($pdo, 'properties', 'documents_json')) {
            $pdo->exec('ALTER TABLE properties ADD COLUMN documents_json JSON NULL AFTER owner_contact_json');
        }

        if (!self::columnExists($pdo, 'properties', 'seller_user_id')) {
            $pdo->exec('ALTER TABLE properties ADD COLUMN seller_user_id INT NULL AFTER documents_json');
        }

        if (!self::columnExists($pdo, 'properties', 'documents_reviewed_at')) {
            $pdo->exec('ALTER TABLE properties ADD COLUMN documents_reviewed_at TIMESTAMP NULL DEFAULT NULL AFTER seller_user_id');
        }

        if (!self::columnExists($pdo, 'properties', 'site_verified_at')) {
            $pdo->exec('ALTER TABLE properties ADD COLUMN site_verified_at TIMESTAMP NULL DEFAULT NULL AFTER documents_reviewed_at');
        }

        if (!self::columnExists($pdo, 'properties', 'last_confirmed_available_at')) {
            $pdo->exec('ALTER TABLE properties ADD COLUMN last_confirmed_available_at TIMESTAMP NULL DEFAULT NULL AFTER site_verified_at');
        }

        if (!self::columnExists($pdo, 'properties', 'dist_to_road_km')) {
            $pdo->exec('ALTER TABLE properties ADD COLUMN dist_to_road_km DECIMAL(8, 2) NULL AFTER last_confirmed_available_at');
        }

        if (!self::columnExists($pdo, 'properties', 'utility_status')) {
            $pdo->exec('ALTER TABLE properties ADD COLUMN utility_status VARCHAR(40) NULL AFTER dist_to_road_km');
        }

        if (!self::columnExists($pdo, 'properties', 'zoning_score')) {
            $pdo->exec('ALTER TABLE properties ADD COLUMN zoning_score INT NULL AFTER utility_status');
        }

        if (!self::columnExists($pdo, 'properties', 'existing_land_use')) {
            $pdo->exec('ALTER TABLE properties ADD COLUMN existing_land_use VARCHAR(180) NULL AFTER zoning_score');
        }
        if (!self::columnExists($pdo, 'properties', 'zoning_classification')) {
            $pdo->exec('ALTER TABLE properties ADD COLUMN zoning_classification VARCHAR(180) NULL AFTER existing_land_use');
        }
        if (!self::columnExists($pdo, 'properties', 'clup_allowed_uses_json')) {
            $pdo->exec('ALTER TABLE properties ADD COLUMN clup_allowed_uses_json JSON NULL AFTER zoning_classification');
        }
        if (!self::columnExists($pdo, 'properties', 'clup_conditional_uses_json')) {
            $pdo->exec('ALTER TABLE properties ADD COLUMN clup_conditional_uses_json JSON NULL AFTER clup_allowed_uses_json');
        }
        if (!self::columnExists($pdo, 'properties', 'clup_restricted_uses_json')) {
            $pdo->exec('ALTER TABLE properties ADD COLUMN clup_restricted_uses_json JSON NULL AFTER clup_conditional_uses_json');
        }
        if (!self::columnExists($pdo, 'properties', 'clup_source_reference')) {
            $pdo->exec('ALTER TABLE properties ADD COLUMN clup_source_reference VARCHAR(255) NULL AFTER clup_restricted_uses_json');
        }
        if (!self::columnExists($pdo, 'properties', 'clup_verified_at')) {
            $pdo->exec('ALTER TABLE properties ADD COLUMN clup_verified_at TIMESTAMP NULL DEFAULT NULL AFTER clup_source_reference');
        }

        if (!self::columnExists($pdo, 'properties', 'assessed_value_sqm')) {
            $pdo->exec('ALTER TABLE properties ADD COLUMN assessed_value_sqm INT NULL AFTER zoning_score');
        }

        if (!self::columnExists($pdo, 'properties', 'readiness_notes')) {
            $pdo->exec('ALTER TABLE properties ADD COLUMN readiness_notes TEXT NULL AFTER assessed_value_sqm');
        }

        $areaColumn = self::columnDetails($pdo, 'properties', 'area');
        $areaScale = isset($areaColumn['NUMERIC_SCALE']) ? (int) $areaColumn['NUMERIC_SCALE'] : null;
        if ($areaScale === null || $areaScale < 4) {
            $pdo->exec('ALTER TABLE properties MODIFY area DECIMAL(12, 4) NOT NULL');
        }

        $pdo->exec(
            "UPDATE properties
             SET city = 'San Fernando, La Union'
             WHERE city IS NULL OR TRIM(city) = ''"
        );
        $pdo->exec(
            'UPDATE properties
             SET score = LEAST(96, GREATEST(68, ROUND((road_access * 0.65) + 22)))
             WHERE score IS NULL OR score = 0'
        );
        $pdo->exec(
            "UPDATE properties
             SET approval_state = 'approved'
             WHERE approval_state IS NULL OR TRIM(approval_state) = ''"
        );
        $statement = $pdo->prepare(
            'UPDATE properties
             SET documents_json = :documents_json
             WHERE documents_json IS NULL'
        );
        $statement->execute([
            'documents_json' => json_encode(self::defaultDocumentStatuses(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
        $statement = $pdo->prepare(
            'UPDATE properties
             SET last_confirmed_available_at = COALESCE(updated_at, created_at, CURRENT_TIMESTAMP)
             WHERE last_confirmed_available_at IS NULL
               AND LOWER(COALESCE(approval_state, \'approved\')) = \'approved\''
        );
        $statement->execute();
        $pdo->exec(
            'UPDATE properties
             SET dist_to_road_km = ROUND(GREATEST(0.2, (100 - road_access) / 18), 2)
             WHERE dist_to_road_km IS NULL'
        );
        $pdo->exec(
            "UPDATE properties
             SET utility_status = CASE
                WHEN LOWER(type) = 'bpo' THEN 'full_ready'
                WHEN LOWER(type) = 'hotel' THEN 'power_water'
                WHEN road_access >= 90 THEN 'full_ready'
                WHEN road_access >= 75 THEN 'partial'
                ELSE 'limited'
             END
             WHERE utility_status IS NULL OR TRIM(COALESCE(utility_status, '')) = ''"
        );
        $pdo->exec(
            'UPDATE properties
             SET zoning_score = LEAST(100, GREATEST(35, score - 4))
             WHERE zoning_score IS NULL'
        );
        $pdo->exec(
            'UPDATE properties
             SET price_per_sqm = GREATEST(1, ROUND(price / GREATEST(area * 10000, 1)))
             WHERE price > 0
               AND area > 0'
        );
        $pdo->exec(
            'UPDATE properties
             SET assessed_value_sqm = GREATEST(1, ROUND(price_per_sqm * 0.92))
             WHERE assessed_value_sqm IS NULL
                OR assessed_value_sqm < 1
                OR assessed_value_sqm > (price_per_sqm * 3)'
        );
        self::normalizeLegacyPropertyAuditLogs($pdo);

        $idColumn = self::columnDetails($pdo, 'properties', 'id');
        $extra = strtolower((string) ($idColumn['EXTRA'] ?? ''));
        if (!str_contains($extra, 'auto_increment')) {
            $pdo->exec('ALTER TABLE properties MODIFY id INT NOT NULL AUTO_INCREMENT');
        }
    }

    private static function normalizeLegacyPropertyAuditLogs(PDO $pdo): void
    {
        if (!self::tableExists($pdo, 'audit_logs')) {
            return;
        }

        $areaMap = [];
        foreach ($pdo->query('SELECT id, area FROM properties WHERE area > 0')->fetchAll() as $row) {
            $areaMap[(int) ($row['id'] ?? 0)] = (float) ($row['area'] ?? 0);
        }

        if ($areaMap === []) {
            return;
        }

        $statement = $pdo->query(
            "SELECT id, entity_id, metadata
             FROM audit_logs
             WHERE entity_type = 'PROPERTY'
               AND metadata IS NOT NULL"
        );
        $update = $pdo->prepare('UPDATE audit_logs SET metadata = :metadata WHERE id = :id');

        foreach ($statement->fetchAll() as $row) {
            $propertyId = (int) ($row['entity_id'] ?? 0);
            $area = (float) ($areaMap[$propertyId] ?? 0);
            if ($propertyId < 1 || $area <= 0) {
                continue;
            }

            $metadata = json_decode((string) ($row['metadata'] ?? ''), true);
            if (!is_array($metadata)) {
                continue;
            }

            $changed = false;
            foreach (['before', 'after'] as $snapshotKey) {
                $snapshot = is_array($metadata[$snapshotKey] ?? null) ? $metadata[$snapshotKey] : null;
                if (!is_array($snapshot) || !isset($snapshot['price']) || !is_numeric($snapshot['price'])) {
                    continue;
                }

                $computedPricePerSqm = max(1, (int) round(((int) $snapshot['price']) / max($area * 10000, 1)));
                $storedPricePerSqm = isset($snapshot['pricePerSqm']) && is_numeric($snapshot['pricePerSqm'])
                    ? (int) $snapshot['pricePerSqm']
                    : 0;

                if ($storedPricePerSqm < 1 || abs($storedPricePerSqm - $computedPricePerSqm) > max(25, (int) round($computedPricePerSqm * 0.35))) {
                    $metadata[$snapshotKey]['pricePerSqm'] = $computedPricePerSqm;
                    $changed = true;
                }

                if (isset($snapshot['assessedValueSqm']) && is_numeric($snapshot['assessedValueSqm'])) {
                    $storedAssessedValue = (int) $snapshot['assessedValueSqm'];
                    if ($storedAssessedValue < 1 || $storedAssessedValue > ($computedPricePerSqm * 3)) {
                        $metadata[$snapshotKey]['assessedValueSqm'] = max(1, (int) round($computedPricePerSqm * 0.92));
                        $changed = true;
                    }
                }
            }

            if (!$changed) {
                continue;
            }

            $before = is_array($metadata['before'] ?? null) ? $metadata['before'] : [];
            $after = is_array($metadata['after'] ?? null) ? $metadata['after'] : [];
            if (
                isset($before['pricePerSqm'], $after['pricePerSqm'])
                && is_numeric($before['pricePerSqm'])
                && is_numeric($after['pricePerSqm'])
                && (int) $before['pricePerSqm'] !== (int) $after['pricePerSqm']
            ) {
                $metadata['summary'] = sprintf(
                    'Price Per Sqm changed from PHP %s / sqm to PHP %s / sqm.',
                    number_format((int) $before['pricePerSqm']),
                    number_format((int) $after['pricePerSqm'])
                );
            }

            $update->execute([
                'id' => (int) ($row['id'] ?? 0),
                'metadata' => json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    private static function ensurePropertyVotesColumns(PDO $pdo): void
    {
        if (!self::tableExists($pdo, 'property_votes')) {
            return;
        }

        if (!self::columnExists($pdo, 'property_votes', 'vote_option_id')) {
            $pdo->exec('ALTER TABLE property_votes ADD COLUMN vote_option_id INT NULL AFTER property_id');
        }

        if (!self::columnExists($pdo, 'property_votes', 'voter_user_id')) {
            $pdo->exec('ALTER TABLE property_votes ADD COLUMN voter_user_id INT NULL AFTER vote_option_id');
        }
    }

    private static function ensurePropertyMessagesColumns(PDO $pdo): void
    {
        if (!self::tableExists($pdo, 'property_messages')) {
            return;
        }

        if (!self::columnExists($pdo, 'property_messages', 'thread_id')) {
            $pdo->exec('ALTER TABLE property_messages ADD COLUMN thread_id INT NULL AFTER id');
        }

        if (!self::columnExists($pdo, 'property_messages', 'sender_user_id')) {
            $pdo->exec('ALTER TABLE property_messages ADD COLUMN sender_user_id INT NULL AFTER property_id');
        }

        if (!self::columnExists($pdo, 'property_messages', 'recipient_user_id')) {
            $pdo->exec('ALTER TABLE property_messages ADD COLUMN recipient_user_id INT NULL AFTER sender_user_id');
        }
    }

    private static function ensureShowcaseItemsColumns(PDO $pdo): void
    {
        if (!self::tableExists($pdo, 'showcase_items')) {
            return;
        }

        if (!self::columnExists($pdo, 'showcase_items', 'pipeline_mode')) {
            $pdo->exec('ALTER TABLE showcase_items ADD COLUMN pipeline_mode VARCHAR(40) NULL AFTER status');
        }

        if (!self::columnExists($pdo, 'showcase_items', 'supply_signal')) {
            $pdo->exec('ALTER TABLE showcase_items ADD COLUMN supply_signal VARCHAR(40) NULL AFTER pipeline_mode');
        }

        if (!self::columnExists($pdo, 'showcase_items', 'investor_thesis')) {
            $pdo->exec('ALTER TABLE showcase_items ADD COLUMN investor_thesis VARCHAR(255) NULL AFTER secondary_metric_value');
        }

        if (!self::columnExists($pdo, 'showcase_items', 'ideal_operator')) {
            $pdo->exec('ALTER TABLE showcase_items ADD COLUMN ideal_operator VARCHAR(160) NULL AFTER investor_thesis');
        }

        if (!self::columnExists($pdo, 'showcase_items', 'avoidance_note')) {
            $pdo->exec('ALTER TABLE showcase_items ADD COLUMN avoidance_note VARCHAR(255) NULL AFTER ideal_operator');
        }
    }

    private static function ensureUserPreferenceRows(PDO $pdo): void
    {
        if (!self::tableExists($pdo, 'user_preferences') || !self::tableExists($pdo, 'users')) {
            return;
        }

        $pdo->exec(
            "INSERT INTO user_preferences (user_id, notification_cadence)
             SELECT u.id, 'instant'
             FROM users u
             LEFT JOIN user_preferences up ON up.user_id = u.id
             WHERE up.user_id IS NULL"
        );

        $pdo->exec(
            "UPDATE user_preferences
             SET notification_cadence = 'instant'
             WHERE notification_cadence IS NULL
                OR TRIM(notification_cadence) = ''
                OR LOWER(notification_cadence) NOT IN ('instant', 'daily_digest', 'weekly')"
        );
    }

    private static function ensureIndexes(PDO $pdo): void
    {
        self::ensureIndex($pdo, 'properties', 'idx_properties_seller_user', 'CREATE INDEX idx_properties_seller_user ON properties (seller_user_id)');
        self::ensureIndex($pdo, 'properties', 'idx_properties_approval_state', 'CREATE INDEX idx_properties_approval_state ON properties (approval_state)');
        self::ensureIndex($pdo, 'properties', 'idx_properties_last_confirmed_available', 'CREATE INDEX idx_properties_last_confirmed_available ON properties (last_confirmed_available_at)');
        self::ensureIndex($pdo, 'property_votes', 'idx_property_votes_vote_option', 'CREATE INDEX idx_property_votes_vote_option ON property_votes (vote_option_id)');
        self::ensureIndex($pdo, 'property_messages', 'idx_property_messages_thread', 'CREATE INDEX idx_property_messages_thread ON property_messages (thread_id)');
        self::ensureIndex($pdo, 'property_messages', 'idx_property_messages_sender', 'CREATE INDEX idx_property_messages_sender ON property_messages (sender_user_id)');
        self::ensureIndex($pdo, 'showcase_items', 'idx_showcase_items_feature', 'CREATE INDEX idx_showcase_items_feature ON showcase_items (feature_type, is_published, sort_order)');
        self::ensureIndex($pdo, 'showcase_items', 'idx_showcase_items_related_property', 'CREATE INDEX idx_showcase_items_related_property ON showcase_items (related_property_id)');
        self::ensureIndex($pdo, 'property_shortlists', 'uniq_property_shortlists_investor_property', 'CREATE UNIQUE INDEX uniq_property_shortlists_investor_property ON property_shortlists (investor_user_id, property_id)');
        self::ensureIndex($pdo, 'property_votes', 'uniq_property_votes_voter', 'CREATE UNIQUE INDEX uniq_property_votes_voter ON property_votes (property_id, voter_user_id)');
        self::ensureIndex($pdo, 'property_document_requests', 'idx_document_requests_property', 'CREATE INDEX idx_document_requests_property ON property_document_requests (property_id)');
        self::ensureIndex($pdo, 'property_document_requests', 'idx_document_requests_seller', 'CREATE INDEX idx_document_requests_seller ON property_document_requests (seller_user_id)');
        self::ensureIndex($pdo, 'property_document_requests', 'idx_document_requests_requester', 'CREATE INDEX idx_document_requests_requester ON property_document_requests (requester_user_id)');
        self::ensureIndex($pdo, 'property_document_requests', 'idx_document_requests_status', 'CREATE INDEX idx_document_requests_status ON property_document_requests (status)');
        self::ensureIndex($pdo, 'visit_logs', 'uniq_visit_logs_thread', 'CREATE UNIQUE INDEX uniq_visit_logs_thread ON visit_logs (thread_id)');
        self::ensureIndex($pdo, 'visit_logs', 'idx_visit_logs_property_status', 'CREATE INDEX idx_visit_logs_property_status ON visit_logs (property_id, status)');
        self::ensureIndex($pdo, 'visit_logs', 'idx_visit_logs_investor', 'CREATE INDEX idx_visit_logs_investor ON visit_logs (investor_user_id)');
        self::ensureIndex($pdo, 'visit_logs', 'idx_visit_logs_seller', 'CREATE INDEX idx_visit_logs_seller ON visit_logs (seller_user_id)');
        self::ensureIndex($pdo, 'visit_logs', 'idx_visit_logs_visited', 'CREATE INDEX idx_visit_logs_visited ON visit_logs (visited_at)');
        self::ensureIndex($pdo, 'notifications', 'idx_notifications_user_created', 'CREATE INDEX idx_notifications_user_created ON notifications (user_id, created_at)');
        self::ensureIndex($pdo, 'notifications', 'idx_notifications_user_read', 'CREATE INDEX idx_notifications_user_read ON notifications (user_id, is_read)');
        self::ensureIndex($pdo, 'notifications', 'idx_notifications_property', 'CREATE INDEX idx_notifications_property ON notifications (property_id)');
        self::ensureIndex($pdo, 'spatial_overlays', 'idx_spatial_overlays_type_active', 'CREATE INDEX idx_spatial_overlays_type_active ON spatial_overlays (overlay_type, is_active)');
        self::ensureIndex($pdo, 'spatial_overlays', 'idx_spatial_overlays_match_key', 'CREATE INDEX idx_spatial_overlays_match_key ON spatial_overlays (match_key)');
        self::ensureIndex($pdo, 'spatial_overlays', 'idx_spatial_overlays_property', 'CREATE INDEX idx_spatial_overlays_property ON spatial_overlays (property_id)');
        self::ensureIndex($pdo, 'seller_profiles', 'uniq_seller_profiles_phone', 'CREATE UNIQUE INDEX uniq_seller_profiles_phone ON seller_profiles (phone)');
        self::ensureIndex($pdo, 'seller_profiles', 'uniq_seller_profiles_business_reg', 'CREATE UNIQUE INDEX uniq_seller_profiles_business_reg ON seller_profiles (business_registration_no)');
        self::ensureIndex($pdo, 'seller_profiles', 'uniq_seller_profiles_government_id', 'CREATE UNIQUE INDEX uniq_seller_profiles_government_id ON seller_profiles (government_id_no)');
        self::ensureIndex($pdo, 'seller_profiles', 'idx_seller_profiles_status', 'CREATE INDEX idx_seller_profiles_status ON seller_profiles (application_status)');
        self::ensureIndex($pdo, 'seller_profiles', 'idx_seller_profiles_reviewed_by', 'CREATE INDEX idx_seller_profiles_reviewed_by ON seller_profiles (reviewed_by_user_id)');
    }

    private static function ensureIndex(PDO $pdo, string $tableName, string $indexName, string $statement): void
    {
        if (!self::tableExists($pdo, $tableName) || self::indexExists($pdo, $tableName, $indexName)) {
            return;
        }

        $pdo->exec($statement);
    }

    private static function indexExists(PDO $pdo, string $tableName, string $indexName): bool
    {
        $statement = $pdo->prepare(
            'SELECT COUNT(*)
             FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name = :table_name
               AND index_name = :index_name'
        );
        $statement->execute([
            'table_name' => $tableName,
            'index_name' => $indexName,
        ]);

        return (int) $statement->fetchColumn() > 0;
    }

    private static function columnDetails(PDO $pdo, string $tableName, string $columnName): ?array
    {
        $statement = $pdo->prepare(
            'SELECT COLUMN_NAME, COLUMN_DEFAULT, EXTRA, COLUMN_TYPE, DATA_TYPE, NUMERIC_PRECISION, NUMERIC_SCALE
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = :table_name
               AND column_name = :column_name
             LIMIT 1'
        );
        $statement->execute([
            'table_name' => $tableName,
            'column_name' => $columnName,
        ]);

        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    private static function defaultDocumentStatuses(): array
    {
        return [
            'title_copy' => 'missing',
            'tax_declaration' => 'missing',
            'survey_plan' => 'missing',
            'zoning_clearance' => 'missing',
            'site_photos' => 'missing',
            'hazard_report' => 'missing',
        ];
    }
}
