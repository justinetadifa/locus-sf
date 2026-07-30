# LOCUS-SF: Comprehensive City Investment Intelligence and Geospatial Decision-Support Platform

## 1. Project Title and Abstract

**LOCUS-SF** (San Fernando Geospatial Investment Intelligence System) is an enterprise-grade, web-based decision-support platform designed to evaluate, rank, and screen commercial property investments and urban development sites in San Fernando City, La Union, Philippines. The platform addresses the critical disconnect between investor site selection, real-time market demand, and local land-use governance by combining automated multi-criteria investment scoring with a spatial Comprehensive Land Use Plan (CLUP) compliance and suitability engine.

Through parcel-level spatial intelligence, weighted investment thesis scoring across multiple commercial corridors, and empirical community demand signaling, LOCUS-SF streamlines due diligence for private investors, commercial developers, land sellers, and municipal planners. The system serves as a deterministic recommendation gate where prospective land uses are rigorously validated against municipal zoning regulations, environmental restrictions, infrastructure readiness, and parcel-level evidence before investment priority status is conferred.

## 2. System Architecture and Tech Stack

The platform utilizes a modular, layered architecture adhering to the Repository-Service pattern in PHP, decoupled RESTful JSON API endpoints, and a responsive JavaScript front-end enriched with interactive Leaflet.js mapping components.

```
+-----------------------------------------------------------------------------------+
|                                Presentation Layer                                 |
|   HTML5 / CSS3 (Custom Properties & Glassmorphism) / Vanilla JavaScript (ES6+)    |
|   Leaflet.js Mapping / CARTO & OpenStreetMap Basemaps / Charting & Interfaces        |
+-----------------------------------------------------------------------------------+
                                         |
                                    HTTP / JSON API
                                         v
+-----------------------------------------------------------------------------------+
|                                 Application Layer                                 |
|   PHP 8.1+ (Object-Oriented Architecture, Standard Router & Controllers)          |
|   - Services: ClupCompliance, DecisionEngine, PropertyCommandCenter, External     |
|   - Repositories: Property, ClupGovernance, SellerProfile, User, VoteOption, etc. |
+-----------------------------------------------------------------------------------+
                                         |
                                    PDO Driver
                                         v
+-----------------------------------------------------------------------------------+
|                                  Data Storage                                     |
|   MySQL 8.0+ / MariaDB 10.4+ Database Engine (InnoDB Schema, JSON Attributes)     |
|   JSON File Cache Layer (`/data/cache`) & Static GeoJSON Datasets (`/data`)       |
+-----------------------------------------------------------------------------------+
                                         |
                            Third-Party Integrations
                                         v
+-----------------------------------------------------------------------------------+
|   LocationIQ (Geocoding) | OpenWeatherMap (Climate) | NewsAPI (Regional News)    |
|   Alpha Vantage (Market) | Google Gemini/OpenRouter | Cloudinary (Media Hosting) |
+-----------------------------------------------------------------------------------+
```

### Stack Specification

* **Front-End Interface:** HTML5, Modern Vanilla CSS3 (CSS Grid, Flexbox, Custom Design Tokens, Editorial Dark Theme), Vanilla JavaScript (ES6+ ES Modules), Leaflet.js (Interactive Geospatial Renderer), CARTO Positron & OpenStreetMap Tile Services.
* **Back-End Runtime:** PHP 8.1+ (Native OOP Architecture, PDO Data Access, Structured Exception Handling, Modular Bootstrap Container).
* **Database Engine:** MySQL 8.0+ / MariaDB 10.4+ utilizing the InnoDB storage engine, native JSON data types, relational integrity constraints, indexed lookups, and transactional audit logging.
* **External Integration Layers:**
  * **LocationIQ API:** Reverse geocoding and location auto-complete services.
  * **OpenWeatherMap API:** Parcel-level microclimate and environmental condition reporting.
  * **NewsAPI:** Live aggregation of local infrastructure, economic, and real estate news.
  * **Alpha Vantage API:** Financial market indicators and regional economic context tracking.
  * **Google Gemini API / OpenRouter API:** AI-driven executive synthesis and spatial opportunity summaries.
  * **Cloudinary API:** Cloud media hosting, optimization, and image transformation pipeline.
  * **Google Earth KML Exporter:** Spatial data serialisation for external GIS interoperability.

## 3. Key Features and Modules

### Automated CLUP Compliance and Suitability Engine
Hard-gated decision engine evaluating candidate sites against the San Fernando City Comprehensive Land Use Plan. For any proposed investment use (e.g., Commercial, Logistics, Healthcare, Institutional, Residential), the engine evaluates land-use compatibility and returns a deterministic evaluation status (`PASS`, `CONDITIONAL`, or `FAIL`), an indexed suitability score (0-100), detailed zoning justifications, and recommended LGU regulatory actions. Evidence tiers (`INFERRED`, `RECORDED`, and `VERIFIED`) are maintained to distinguish preliminary spatial screens from official municipal certifications.

### Multi-Lens Investment Scoring and Site Ranking
Analytical algorithm scoring properties across configurable commercial thesis lenses (e.g., University Hub, Logistics Corridor, Healthcare District, Commercial Retail). Weighting parameters synthesize road proximity, corridor accessibility, frontage, flood hazard exposure, utilities availability, land price per square meter, and zoning compatibility.

### Geospatial Explorer and Spatial Trigger Nodes
Interactive map interface driven by Leaflet.js displaying property parcels, key municipal corridors (e.g., Poro Point Freeport, City Center, Civic Belt), barangay boundaries, and infrastructure nodes. Features filterable spatial layers, cluster visualization, and fallback renderers for offline or resource-constrained environments.

### Area Intelligence Dossier and Property Command Center
Unified site management interface providing granular parcel data, document completion checklists, title readiness verification, tax declaration records, owner authorization tracking, site visit histories, and full property lifecycle management.

### Multi-Attribute Comparison and Decision Support Matrix
Side-by-side analytical matrix enabling prospective investors to compare shortlisted properties across financial metrics, physical dimensions, utility readiness, CLUP compatibility scores, and community demand alignment.

### Empirical Demand Signaling and Community Voting Engine
Citizen and investor voting mechanism capturing localized demand gaps (e.g., requirement for commercial banks, cold storage facilities, medical clinics, or retail centers). Vote aggregations feed directly into market readiness indicators to validate investment proposals against real community needs.

### Curated Investment Showcase (Offer Board and City Pipeline)
Structured showcase modules distinguishing immediate private investment offerings ("Offer Board") from public municipal development projects and infrastructure initiatives ("City Pipeline"). Admins curate and publish entries with rich media assets and investment highlights.

### Scenario Simulator and Policy Testing Terminal
Interactive sandbox allowing urban planners and developers to model hypothetical development scenarios, adjust land-use parameters, test financial thresholds, and preview CLUP compliance outcomes before submitting formal applications.

### Role-Based Access Control and Workspaces
* **Administrator:** Full governance suite for managing listing approvals, seller registrations, CLUP dataset updates, showcase publishing, voting parameters, system logs, and global configuration.
* **Seller / Developer:** Dedicated workspace for property registration, document submission, readiness monitoring, site visit tracking, and inquiry management.
* **Investor / Resident:** Portal for browsing opportunities, building property shortlists, running decision comparisons, executing scenario simulations, submitting document requests, logging inquiries, and casting demand votes.

### Threaded Communication and Document Governance
Integrated messaging pipeline, site visit scheduling system, document request tracking, automated notification engine, and audit logging system tracking all data modifications for compliance and accountability.

## 4. System Prerequisites

To deploy and execute the LOCUS-SF platform locally or on a production server, the runtime environment must satisfy the following minimum requirements:

* **Web Server:** Apache 2.4+ (with `mod_rewrite` enabled) or Nginx configured for PHP execution.
* **PHP Environment:** PHP Version 8.1.0 or higher.
  * **Required PHP Extensions:** `pdo_mysql`, `json`, `filter`, `curl`, `mbstring`, `openssl`, `fileinfo`.
* **Database Engine:** MySQL 8.0+ or MariaDB 10.4+ (supporting JSON data types and InnoDB foreign key constraints).
* **Development Stack (Local):** XAMPP for Windows/Linux v8.1+ (or equivalent WAMP/LAMP stack).
* **Client Interface:** Modern standards-compliant web browser (Google Chrome 100+, Mozilla Firefox 100+, Microsoft Edge 100+, Safari 15+) with JavaScript enabled.

## 5. Local Environment Setup and Installation Guide

Follow these sequential steps to set up a local development instance using XAMPP on Windows:

### Step 1: Repository Setup
Clone or place the project repository inside your local web server document root directory (e.g., `C:\xampp\htdocs\sfcelerate-bizstart`):

```bash
cd C:\xampp\htdocs
git clone <repository-url> sfcelerate-bizstart
cd sfcelerate-bizstart
```

### Step 2: Local Configuration Setup
Copy the configuration template `app/config.local.php.example` to `app/config.local.php`:

```bash
cp app/config.local.php.example app/config.local.php
```

Modify `app/config.local.php` if your local database credentials differ from the standard XAMPP defaults (Default: Host `127.0.0.1`, Port `3306`, User `root`, Password `""`, Database `sfcelerate_bizstart`).

### Step 3: Database Initialization
Ensure the Apache and MySQL services are running in the XAMPP Control Panel.

#### Option A: Unified Setup Script (Recommended for Quick Deployment)
Import the complete schema and seed dataset in a single operation using phpMyAdmin or the MySQL Command Line Client:

```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS sfcelerate_bizstart CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p sfcelerate_bizstart < database/setup.sql
```

#### Option B: Schema Import with Automated PHP Boot-Seeding
Import the structural DDL schema only:

```bash
mysql -u root -p sfcelerate_bizstart < database/schema.sql
```

When `app.auto_seed` is set to `true` in `app/config.local.php`, the application's `AutoSeeder` module will automatically populate tables from JSON files in the `/data` directory upon the first web HTTP request.

### Step 4: Verification and Server Execution
Open your web browser and navigate to the application endpoint:

```text
http://localhost/sfcelerate-bizstart/
```

Verify application health and API initialization by navigating to:

```text
http://localhost/sfcelerate-bizstart/api/health.php
http://localhost/sfcelerate-bizstart/api/bootstrap.php
```

### Default Local Demonstration Credentials

```text
+-------------------+---------------------------+--------------+
| Role              | Email Address             | Password     |
+-------------------+---------------------------+--------------+
| System Admin      | admin@sfcelerate.local    | Admin123!    |
| Verified Investor | investor@sfcelerate.local | Investor123! |
| Property Seller   | seller@sfcelerate.local   | Seller123!   |
+-------------------+---------------------------+--------------+
```

## 6. Environment Variables and Security

System configuration parameters are managed via `app/config.php` and overridden per environment using `app/config.local.php` or system environment variables.

### Key Configuration Directives

| Parameter Key | Environment Variable | Default Value | Technical Description |
| :--- | :--- | :--- | :--- |
| `app.environment` | `APP_ENV` | `local` | Application execution environment (`local`, `staging`, `production`). |
| `app.debug` | `APP_DEBUG` | `true` (non-prod) | Toggles detailed error reporting and debug outputs. |
| `app.auto_migrate` | `APP_AUTO_MIGRATE` | `true` (non-prod) | Automatically validates database schemas on application bootstrap. |
| `app.auto_seed` | `APP_AUTO_SEED` | `true` (local) | Enables automated population of fallback JSON data when database is empty. |
| `db.host` | `DB_HOST` | `127.0.0.1` | MySQL database host server address. |
| `db.port` | `DB_PORT` | `3306` | MySQL server communication port. |
| `db.name` | `DB_NAME` | `sfcelerate_bizstart` | Target MySQL database schema name. |
| `db.user` | `DB_USER` | `root` | Database user account name. |
| `db.pass` | `DB_PASS` | `""` | Database user account password. |
| `services.location.locationiq_key` | `LOCATIONIQ_KEY` | `""` | API key for LocationIQ geocoding services. |
| `services.market.alpha_vantage_key` | `ALPHA_VANTAGE_KEY` | `""` | API key for Alpha Vantage market intelligence data. |
| `services.news.newsapi_key` | `NEWSAPI_KEY` | `""` | API key for NewsAPI regional news integration. |
| `services.ai.gemini_key` | `GEMINI_API_KEY` | `""` | API key for Google Gemini model synthesis. |
| `services.ai.openrouter_key` | `OPENROUTER_API_KEY` | `""` | API key for OpenRouter AI routing services. |
| `services.media.cloudinary` | `CLOUDINARY_*` | `""` | Cloudinary account details (`cloud_name`, `api_key`, `api_secret`). |
| `services.weather.openweather_key` | `OPENWEATHER_API_KEY` | `""` | API key for OpenWeatherMap microclimate data. |

### Security Rules and Best Practices

1. **Version Control Exclusion:** Local configuration files containing raw credentials (`app/config.local.php`, `.env`) MUST remain listed in `.gitignore` and NEVER committed to public or private version control repositories.
2. **Credential Isolation:** Use distinct database users with restricted privilege sets in staging and production environments. Do not use the `root` administrative account in production.
3. **Parameter Sanitization:** All database interactions are executed via Prepared Statements using PHP Data Objects (PDO) to protect against SQL injection vulnerabilities.
4. **Graceful Fallbacks:** Third-party integration services (LocationIQ, OpenWeatherMap, Gemini, NewsAPI) are encapsulated within service classes that fall back seamlessly to cached or synthetic structured data when API keys are unconfigured or external networks fail.

## 7. Repository Structure Overview

```text
sfcelerate-bizstart/
├── admin-dashboard.php         # Admin administrative management dashboard
├── admin-login.php             # System administrator authentication gateway
├── admin-properties.php        # Property inventory CRUD management studio
├── admin-showcase.php          # Showcase (Offer Board & City Pipeline) studio
├── city-pipeline.php           # Public showcase page for future infrastructure
├── compare-decision.php        # Multi-property decision comparison matrix
├── index.php                   # Core editorial landing page & city investment thesis
├── investor-dashboard.php      # Investor portal workspace and tracking dashboard
├── investor-login.php          # Investor authentication & sign-up entry point
├── offer-board.php             # Curated commercial investment opportunity showcase
├── property-details.php        # Comprehensive property dossier & command center
├── property-explorer.php       # Interactive Leaflet map explorer and search terminal
├── property-ranking.php        # Live property ranking board across investment lenses
├── reports.php                 # Printable CLUP compliance & investment priority reports
├── seller-dashboard.php        # Seller workspace for property readiness tracking
├── seller-login.php            # Seller authentication gateway
├── simulator.php               # CLUP-gated policy and scenario testing simulator
├── voting-dashboard.php       # Community demand signaling & voting dashboard
├── api/                        # RESTful JSON API endpoints
│   ├── _bootstrap.php          # Shared API controller initialization script
│   ├── audit-logs.php          # System audit log retrieval endpoint
│   ├── barangay.php           # Barangay lookup & spatial demographic API
│   ├── bootstrap.php           # Core application state bootstrap API
│   ├── cart.php                # Investor property shortlist & cart manager
│   ├── clup.php                # CLUP compliance evaluation & governance API
│   ├── document-requests.php   # Property document request workflow API
│   ├── due-diligence.php       # Due diligence tracking & status update API
│   ├── external-*.php          # External API proxies (weather, news, market, AI)
│   ├── google-earth.php        # Google Earth KML spatial export service
│   ├── health.php              # Health check endpoint returning HTTP 200 JSON
│   ├── location-search.php     # Spatial location search and geocoding endpoint
│   ├── messages.php            # Threaded user messaging API
│   ├── notifications.php       # User notification delivery API
│   ├── properties.php          # Property listing index, filter, and CRUD API
│   ├── property.php            # Single property detailed payload API
│   ├── scenarios.php           # Scenario simulator saving & loading API
│   ├── seller-profiles.php     # Seller registration and profile review API
│   ├── showcase.php            # Showcase items (Offer Board / Pipeline) API
│   ├── visit-logs.php          # Site visit scheduling & logger API
│   └── votes.php               # Demand voting aggregation API
├── app/                        # Application core logic layer
│   ├── bootstrap.php           # Dependency container and repository wiring
│   ├── config.php              # Default global configuration definitions
│   ├── config.local.php.example# Local environment configuration blueprint
│   ├── Core/                   # Low-level core engines
│   │   ├── Database.php        # PDO database connection factory wrapper
│   │   ├── SchemaManager.php   # Automated base database table migration manager
│   │   └── ClupSchemaManager.php# CLUP governance schema migration manager
│   ├── Repositories/           # Data Access Layer (DAO Pattern)
│   │   ├── ClupGovernanceRepository.php  # CLUP zoning datasets & rules DAO
│   │   ├── PropertyRepository.php        # Property inventory DAO
│   │   ├── SellerProfileRepository.php   # Seller profile records DAO
│   │   ├── UserRepository.php            # User authentication & identity DAO
│   │   ├── VoteOptionRepository.php      # Demand voting options DAO
│   │   └── [Other Repositories...]       # Messages, Audit, Visits, Showcase DAOs
│   └── Support/                # Service Layer & Core Engines
│       ├── ClupComplianceService.php     # CLUP compliance calculation engine
│       ├── DecisionEngineService.php     # Investment scoring & ranking algorithm
│       ├── PropertyCommandCenterService.php # Consolidated dossier aggregator
│       ├── ExternalServices.php          # External API wrappers with cache
│       ├── AutoSeeder.php                # Initial database seed manager
│       ├── NotificationEngine.php        # System notification processor
│       └── helpers.php                   # Helper utilities and sanitizers
├── assets/                     # Front-end static assets
│   ├── css/                    # Custom CSS design system stylesheets
│   ├── js/                     # Client-side JavaScript modules & Leaflet scripts
│   └── images/                 # Image assets, UI icons, and property photo cache
├── data/                       # Static JSON datasets & cache storage
│   ├── cache/                  # Temporary file cache for external API responses
│   ├── clup-source-registry.json # Comprehensive Land Use Plan source registry
│   ├── meta.json               # Platform taxonomy & metadata definitions
│   └── properties.json         # Seed dataset for property inventory
├── database/                   # MySQL DDL & DML scripts
│   ├── schema.sql              # Complete database table structures and indexes
│   ├── seed.sql                # Default initial database records
│   └── setup.sql               # Unified database schema & seed creation script
└── docs/                       # Architectural documentation & ERD diagrams
    ├── CLUP_GOVERNANCE.md      # Detailed CLUP engine policy specifications
    ├── CODEX_CONTEXT.md        # Technical architecture reference manual
    ├── system-manuscript-documentation.md # Comprehensive academic manuscript
    └── *.drawio                # ERD and system flowchart design source files
```

## 8. Academic and Author Credits

This project was developed as an undergraduate capstone project in Information Technology.

* **Researchers / Engineering Team:**
  * Academic Research and Software Engineering Team
* **Degree Program:** Bachelor of Science in Information Technology (BSIT)
* **Academic Department:** College of Information Technology
* **Institution:** Don Mariano Marcos Memorial State University - Mid La Union Campus (DMMMSU-MLUC)
* **Location:** City of San Fernando, La Union 2500, Philippines
#   l o c u s - s f  
 