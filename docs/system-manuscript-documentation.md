# Title Page

**Project Title:** LOCUS-SF

**Document Type:** System Audit and Manuscript-Ready Technical Documentation

**Basis of Documentation:** Verified from the current repository only, including PHP source files, JavaScript modules, database schema, configuration files, JSON seed data, and implemented API routes.

**Audit Date:** April 12, 2026

**Source Scope:** `api/`, `app/`, `assets/`, `database/`, `data/`, and the top-level PHP pages in the repository root.

# Table of Contents

1. Title Page
2. Table of Contents
3. List of Figures
4. List of Tables
5. System Overview
6. System Architecture
7. Low-Level Design
8. Program Documentation
9. API Documentation
10. System Integration Explanation
11. Tools and Technologies
12. Testing and Results
13. References
14. Appendices

# List of Figures

Figure 1. Overall System Architecture Diagram. [TO BE PROVIDED MANUALLY]

Figure 2. Entity Relationship Diagram. [TO BE PROVIDED MANUALLY]

Figure 3. Admin Dashboard Screenshot. [TO BE PROVIDED MANUALLY]

Figure 4. Property Explorer Screenshot. [TO BE PROVIDED MANUALLY]

Figure 5. Property Command Center Screenshot. [TO BE PROVIDED MANUALLY]

Figure 6. Offer Board Screenshot. [TO BE PROVIDED MANUALLY]

Figure 7. City Pipeline Screenshot. [TO BE PROVIDED MANUALLY]

# List of Tables

Table 1. Implemented Pages and Access Scope

Table 2. Role Capabilities Summary

Table 3. Core Application Components

Table 4. Verified Database Tables

Table 5. API Inventory

Table 6. Tools and Technologies

Table 7. Testing Summary

# System Overview

LOCUS-SF is a PHP and MySQL web application for property discovery, seller listing management, investor evaluation, voting, document requests, messaging, site-visit coordination, showcase publishing, and operational monitoring. The repository implements a multi-page web interface backed by JSON API endpoints under `api/`.

The codebase verifies four effective user states: guest, investor, seller, and admin. Access-sensitive pages and route behaviors are enforced through PHP session state stored in `$_SESSION['sfc_user']`.

Seed and metadata files confirm the presence of 10 seeded properties, 59 barangays, 10 due diligence checklist items, and 11 vote presets. The application also ships with seeded demo accounts for admin, seller, and investor use.

The shared web shell includes both dynamic modules and some static presentation content. One example is the shared city brief modal in the layout; its current copy is static interface content and should not be described as live analytics.

Table 1. Implemented Pages and Access Scope

| Page | Access Scope | Verified Purpose |
| --- | --- | --- |
| `index.php` | Public | Landing page and entry point into the platform |
| `admin-login.php` | Public | Admin login screen |
| `investor-login.php` | Public | Investor login and investor account creation |
| `seller-login.php` | Public | Seller login and seller account creation |
| `admin-dashboard.php` | Admin only | Admin operations dashboard, seller review, activity monitoring |
| `admin-properties.php` | Admin only | Property CRUD workspace |
| `admin-showcase.php` | Admin only | Showcase CRUD studio for Offer Board and City Pipeline |
| `investor-dashboard.php` | Investor only | Investor workspace with shortlist, recommendations, market and news panels |
| `seller-dashboard.php` | Seller only | Seller workspace for listings, messages, document requests, and visit handling |
| `property-ranking.php` | Public | Ranked opportunity board with filters and comparison actions |
| `property-explorer.php` | Public | Map-based property exploration and filtering |
| `property-details.php` | Public | Property detail page and property command center shell |
| `compare-decision.php` | Public | Property comparison and recommendation page |
| `voting-dashboard.php` | Public | Voting dashboard with investor vote casting and admin vote-option management |
| `offer-board.php` | Public | Public display of published Offer Board items |
| `city-pipeline.php` | Public | Public display of published City Pipeline items |
| `logout.php` | Logged-in user flow | Session termination and redirect to landing page |
| `m.php` | Public | Redirect helper to messaging anchor on a property detail page |

Table 2. Role Capabilities Summary

| Role | Verified Capabilities |
| --- | --- |
| Guest | View landing page, rankings, explorer, property details, comparison page, voting dashboard, Offer Board, City Pipeline, and property-level public conversation state |
| Investor | Create account, log in, maintain shortlist cart, cast votes, send messages, request documents, propose visits, receive notifications, and access investor dashboard |
| Seller | Create account, submit verification details, manage own listings, update due diligence on own listings, reply to messages, update document requests, manage visit transitions, and access seller dashboard |
| Admin | Manage all listings, review seller applications, manage vote options, manage showcase items, access audit logs, and monitor operational activity from admin pages |

# System Architecture

The implemented architecture is a server-rendered PHP multi-page application with a JavaScript-enhanced frontend and a JSON API backend. Frontend pages are rendered by top-level PHP files, while interactive behaviors are handled by `assets/js/portal.js`, `assets/js/api.js`, and `assets/js/notifications.js`.

At runtime, the browser loads a page shell rendered by PHP. The page shell publishes `window.SFC_APP_CONFIG`, which includes the base path, API base path, asset base path, map tile configuration, current user role, and current user payload. JavaScript modules then call the API layer using `fetch`.

On the backend, every API route includes `api/_bootstrap.php`, which loads authentication helpers, creates the service container in `app/bootstrap.php`, and standardizes JSON response handling. `app/bootstrap.php` creates a PDO database connection, ensures the schema exists, auto-seeds empty installations, constructs repositories, and wires services such as the decision engine, notification engine, external service adapters, Google Earth export service, and property command center service.

Architecture Diagram: [TO BE PROVIDED MANUALLY]

Table 3. Core Application Components

| Component | Verified Files | Verified Responsibility |
| --- | --- | --- |
| Page layer | Root `*.php` pages | Server-rendered screens and role-based navigation |
| Web shell | `app/Support/web.php` | Shared head, header, footer, body config injection, and page framing |
| Authentication | `app/Support/auth.php` | Session start, login, logout, current user lookup, role checks, investor registration, seller registration |
| API bootstrap | `api/_bootstrap.php` | Container loading, fatal error handling, JSON response conventions |
| Application bootstrap | `app/bootstrap.php` | Dependency construction and service container assembly |
| Database connection | `app/Core/Database.php` | MySQL connection and database auto-creation |
| Schema management | `app/Core/SchemaManager.php` | Schema verification and column/index normalization |
| Auto seeding | `app/Support/AutoSeeder.php` | Seed users, properties, vote options, showcase items, messages, visits, document requests, scenarios, and audit logs |
| Repositories | `app/Repositories/*.php` | Data access and domain rules for each module |
| Decision support | `app/Support/DecisionEngineService.php` | Property scoring, personas, recommendation states, next actions |
| Command center | `app/Support/PropertyCommandCenterService.php` | Aggregated property-level operational view |
| Notifications | `app/Support/NotificationEngine.php`, `app/Repositories/NotificationRepository.php` | Event-driven notification creation, feed retrieval, preference storage |
| External adapters | `app/Support/ExternalServices.php` | Market, news, weather, geocoding, AI summary, media upload integrations with fallback support |
| Google Earth export | `app/Support/GoogleEarthService.php` | KML/KMZ generation for properties and overlays |
| Frontend API client | `assets/js/api.js` | Browser-side API wrapper and Google Earth link generation |
| Frontend interactions | `assets/js/portal.js` | Page initialization and feature workflows |
| Notification UI | `assets/js/notifications.js` | Notification feed polling and read/cadence actions |

The verified high-level request path is:

Browser page -> `assets/js/api.js` -> `api/*.php` route -> `api/_bootstrap.php` -> repository/service layer from `app/bootstrap.php` -> MySQL and JSON seed/config data -> JSON response or file download.

# Low-Level Design

## Request and Session Design

The application uses PHP sessions for authentication. `sfc_login()` authenticates against database-backed users, `sfc_current_user()` refreshes the stored session payload from the database, and protected pages enforce role checks using `sfc_require_role()`.

Investor registration creates a new `users` row with role `investor`. Seller registration creates a seller user, creates or updates the seller profile, sets the identity verification status to `pending`, stores the session, and notifies admin users.

## Visibility and Ownership Rules

The property repository applies role-aware visibility:

- Admin can view all properties.
- Seller can view approved listings plus their own listings.
- Guest and investor can view approved listings only.

Other verified ownership rules include:

- Sellers can edit, delete, and update due diligence only for their own properties.
- Sellers must be identity-verified before they can publish listings through `api/properties.php`.
- Only investors can manage the shortlist cart and cast votes.
- Messaging threads are restricted to authorized participants for inbox and thread access.
- One visit logistics record is enforced per investor-property thread.

## Database Design

The schema file `database/schema.sql` defines 18 tables.

Table 4. Verified Database Tables

| Table | Verified Purpose |
| --- | --- |
| `users` | Core user accounts for admin, seller, and investor roles |
| `user_preferences` | Notification cadence preferences per user |
| `seller_profiles` | Seller verification, identity, and review records |
| `properties` | Main property inventory records |
| `property_media` | Property media assets |
| `property_due_diligence` | Stored due diligence checklist state per property |
| `vote_options` | Admin-managed vote categories with optional images |
| `showcase_items` | Offer Board and City Pipeline items |
| `property_votes` | Investor vote records per property |
| `message_threads` | Investor-seller conversation threads per property |
| `property_messages` | Messages inside a thread or property conversation |
| `property_shortlists` | Investor shortlist cart entries |
| `property_document_requests` | Document request workflow records |
| `visit_logs` | Site visit scheduling, confirmations, and field-audit state |
| `notifications` | In-app notifications and read state |
| `audit_logs` | Operational audit ledger |
| `investment_scenarios` | Saved comparison and scenario records |
| `spatial_overlays` | Overlay geometry and metadata for map/export use |

## Repository and Service Responsibilities

The repository layer holds most domain rules:

- `PropertyRepository` normalizes listing payloads, computes derived fields, manages due diligence state, and hydrates media, document summaries, pricing benchmarks, and verification context.
- `MessageRepository` manages inbox views, property conversations, replies, and access control.
- `DocumentRequestRepository` manages request creation, inbox behavior, and seller/admin status updates.
- `VisitLogRepository` manages proposal, counter-offer, confirmation, in-progress, completion, and field-audit actions.
- `VoteOptionRepository` manages vote options and per-property tallies.
- `ShowcaseRepository` manages Offer Board and City Pipeline content.
- `SellerProfileRepository` manages seller profile drafts, submission validation, uniqueness checks, and admin review outcomes.
- `ScenarioRepository` stores comparison scenarios by property.
- `NotificationRepository` stores notification feed state and cadence preferences.
- `AuditLogRepository` stores and filters the admin audit ledger.

The service layer aggregates and enriches data:

- `DecisionEngineService` decorates properties with financial, demand, readiness, location, risk, personal-fit, confidence, and recommendation state.
- `PropertyCommandCenterService` combines property, voting, messaging, visit, document, notification, and audit information into one property-level operational payload.
- `ExternalServices` provides live-or-fallback market data, business news, weather, geocoding, AI summaries, and upload behavior.
- `GoogleEarthService` generates KML and KMZ exports and optionally includes spatial overlays.

ER Diagram: [TO BE PROVIDED MANUALLY]

Flowcharts: [TO BE PROVIDED MANUALLY]

Sequence Diagrams: [TO BE PROVIDED MANUALLY]

## Design Notes and Constraints

- `api/barangay.php` validates barangay names against `data/meta.json` but does not enforce a role check at the route level.
- `api/scenarios.php` validates input but does not enforce authentication or role checks at the route level.
- Current external service keys in `app/config.local.php` are empty, so the codebase is designed to run with fallbacks and cached local data.

# Program Documentation

## Feature 1

Feature Name: Authentication and Role-Based Access

Description: The system provides separate login pages for admin, investor, and seller users. Investor registration creates a database-backed investor account. Seller registration creates a seller account, stores seller profile details, marks identity verification as pending, and notifies admins.

Process Flow: User opens a role-specific login page -> credentials are checked with `sfc_login()` or a new account is created through `sfc_register_investor()` or `sfc_register_seller()` -> session data is written to `$_SESSION['sfc_user']` -> protected pages enforce `sfc_require_role()` -> `logout.php` clears the session and redirects to the landing page.

Screenshot: [TO BE PROVIDED MANUALLY]

## Feature 2

Feature Name: Seller Registration and Verification Queue

Description: Sellers can submit a verification profile containing identity and business details. Admin users can review queued seller applications and change the application status to verified, rejected, suspended, or pending review.

Process Flow: Seller signs up or opens seller profile workflow -> seller submits profile details through `api/seller-profiles.php` -> profile status becomes `pending_review` -> admins fetch the queue and review a seller profile -> admin updates the review status -> seller identity status is synchronized and a notification is sent to the seller.

Screenshot: [TO BE PROVIDED MANUALLY]

## Feature 3

Feature Name: Property Listing Management

Description: Admin users can create, update, and delete all listings. Verified sellers can create and manage their own listings. Listing payloads include pricing, location, corridor, description, facilities, tags, ownership contact, document status, and verification-related fields.

Process Flow: Admin or seller submits property data to `api/properties.php` or `api/property.php` -> the repository normalizes and stores the payload -> primary media is synchronized -> due diligence state is ensured -> decision data is reattached -> notifications and audit events are generated where applicable.

Screenshot: [TO BE PROVIDED MANUALLY]

## Feature 4

Feature Name: Property Explorer and Map-Based Discovery

Description: The property explorer page provides searchable and filterable property discovery with a Leaflet map, type and corridor filters, search, location lookup, and mobile list-map switching.

Process Flow: User opens `property-explorer.php` -> page bootstrap data is loaded from `api/bootstrap.php` -> `assets/js/portal.js` renders the explorer list and Leaflet map -> optional location search calls `api/location-search.php` -> user selects a property and can proceed to the property detail page.

Screenshot: [TO BE PROVIDED MANUALLY]

## Feature 5

Feature Name: Ranking and Decision Comparison

Description: The system ranks visible properties and supports side-by-side comparison using investment lenses, decision personas, budget filters, and intent filters. The comparison page can evaluate up to three properties and resolve a recommendation state through the decision engine.

Process Flow: User opens `property-ranking.php` or `compare-decision.php` -> visible properties are decorated by the decision engine -> user filters, queues, and compares properties -> the comparison workflow evaluates the selected set and presents recommendation output based on stored property data and score components.

Screenshot: [TO BE PROVIDED MANUALLY]

## Feature 6

Feature Name: Voting and Business Signal Collection

Description: The voting dashboard records investor votes against a property using admin-managed vote options. Admin users can create, update, or deactivate vote options and optionally attach images to them.

Process Flow: Voting page loads vote options and property vote tallies -> investor selects a property and casts a vote through `api/votes.php` -> tally data is updated and notification events are emitted -> admin users manage vote options through `api/vote-options.php`.

Screenshot: [TO BE PROVIDED MANUALLY]

## Feature 7

Feature Name: Messaging and Conversation Threads

Description: Investors, sellers, and admins can participate in property-related conversations. Threads are organized per property and investor, with inbox views for logged-in operational users.

Process Flow: Investor opens a property conversation and sends a message -> the message repository creates or reuses the thread -> seller or admin replies through the same thread -> inbox views are available through `api/messages.php?scope=inbox` -> thread payloads can include linked visit information.

Screenshot: [TO BE PROVIDED MANUALLY]

## Feature 8

Feature Name: Document Request Workflow

Description: Investors and admins can request property documents, while sellers and admins can update the request status and response note. Property-level and inbox-level request views are implemented.

Process Flow: Investor or admin submits a request through `api/document-requests.php` -> the request is stored against the property and seller -> seller or admin updates the status through the same endpoint -> the property request list is refreshed and notification events are generated.

Screenshot: [TO BE PROVIDED MANUALLY]

## Feature 9

Feature Name: Site Visit and Field Audit Workflow

Description: Investors can propose site visits using primary and secondary time windows. Sellers or admins can counter-offer, confirm, mark the visit in progress, and complete the visit. After completion, the investor can submit a field audit that updates the ground-truth multiplier.

Process Flow: Investor proposes a visit through `api/visit-logs.php` -> a conversation thread is created or reused -> seller or admin applies visit actions such as `counterOffer`, `confirm`, `markInProgress`, or `markVisited` -> investor submits the field audit with `submitAudit` -> the visit timeline and multiplier are updated.

Screenshot: [TO BE PROVIDED MANUALLY]

## Feature 10

Feature Name: Investor Shortlist Cart and Google Earth Export

Description: Investors can maintain a shortlist cart of property IDs. The frontend also generates Google Earth view links and KML or KMZ export links for selected properties.

Process Flow: Investor adds or removes property IDs through `api/cart.php` -> shortlist state is used by investor-facing pages -> the frontend builds a Google Earth export URL -> `api/google-earth.php` returns a KML or KMZ file containing visible properties and optional overlays.

Screenshot: [TO BE PROVIDED MANUALLY]

## Feature 11

Feature Name: Admin Monitoring and Audit Review

Description: The admin workspace consolidates platform activity, including live listings, seller applications, conversations, voting, investor activity, and the audit ledger. Admin users can also review seller verification requests and manage property inventory.

Process Flow: Admin opens `admin-dashboard.php` or `admin-properties.php` -> client-side code requests operational data from multiple APIs -> seller applications are reviewed through `api/seller-profiles.php` -> audit entries are read from `api/audit-logs.php` -> property records are maintained through listing APIs.

Screenshot: [TO BE PROVIDED MANUALLY]

## Feature 12

Feature Name: Offer Board and City Pipeline Publishing

Description: The system implements two public showcase streams. Offer Board displays curated timed opportunities. City Pipeline displays planned or future project items, including `future_project` and `investment_gap` pipeline modes.

Process Flow: Admin creates or updates showcase items through `api/showcase.php` and `api/showcase-item.php` -> items are stored in `showcase_items` -> public pages fetch published items only -> Offer Board and City Pipeline render the appropriate subset by feature type.

Screenshot: [TO BE PROVIDED MANUALLY]

## Feature 13

Feature Name: Property Command Center

Description: The property detail workflow includes a command center payload that combines listing data, votes, due diligence state, messages, visit state, document requests, notifications, audit logs, blockers, timeline items, trust indicators, and next actions.

Process Flow: User opens `property-details.php?id=<propertyId>` -> client requests `api/property-command-center.php` -> the command center service assembles multi-module data for the target property -> the frontend renders operational sections and next-step guidance according to role and available data.

Screenshot: [TO BE PROVIDED MANUALLY]

## Feature 14

Feature Name: Notifications and Cadence Control

Description: The application stores in-app notifications for operational and transactional events, including listing changes, seller application events, messaging, votes, document requests, due diligence updates, and visit transitions. Users can mark notifications as read and update notification cadence.

Process Flow: Domain events trigger notification creation through `NotificationEngine` and `NotificationRepository` -> logged-in users retrieve the feed from `api/notifications.php` -> users can mark one notification read, mark all as read, or update cadence through action-based write requests.

Screenshot: [TO BE PROVIDED MANUALLY]

# API Documentation

The following API routes were verified directly from the `api/` directory.

Table 5. API Inventory

| Endpoint | Methods | Verified Purpose |
| --- | --- | --- |
| `/api/bootstrap.php` | `GET` | Bootstrapped meta, properties, and stats |
| `/api/health.php` | `GET` | Health status and database connection check |
| `/api/properties.php` | `GET`, `POST` | List properties and create listings |
| `/api/property.php` | `GET`, `PUT`, `PATCH`, `DELETE` | Read, update, or delete one property |
| `/api/barangay.php` | `POST` | Update property barangay |
| `/api/due-diligence.php` | `GET`, `POST` | Read or update due diligence state |
| `/api/cart.php` | `GET`, `POST`, `DELETE` | Investor shortlist cart |
| `/api/votes.php` | `GET`, `POST` | Vote tallies and vote casting |
| `/api/vote-options.php` | `GET`, `POST`, `PUT`, `PATCH`, `DELETE` | Vote option management |
| `/api/messages.php` | `GET`, `POST`, `DELETE` | Conversation inbox, thread read, send, and admin clear |
| `/api/document-requests.php` | `GET`, `POST`, `PUT`, `PATCH` | Document request workflow |
| `/api/visit-logs.php` | `GET`, `POST`, `PATCH` | Visit scheduling and workflow transitions |
| `/api/notifications.php` | `GET`, `POST`, `PATCH`, `PUT` | Notification feed and actions |
| `/api/audit-logs.php` | `GET` | Admin audit ledger |
| `/api/scenarios.php` | `GET`, `POST` | Scenario storage and retrieval |
| `/api/showcase.php` | `GET`, `POST` | Showcase item listing and creation |
| `/api/showcase-item.php` | `GET`, `PUT`, `PATCH`, `DELETE` | Single showcase item read and maintenance |
| `/api/seller-profiles.php` | `GET`, `POST`, `PATCH` | Seller profile queue, submission, and review |
| `/api/property-command-center.php` | `GET` | Aggregated property command center payload |
| `/api/location-search.php` | `GET` | Geocoding and fallback property matching |
| `/api/external-market.php` | `GET` | Market snapshot |
| `/api/external-news.php` | `GET` | Business or investment news digest |
| `/api/external-weather.php` | `GET` | Weather context by property or coordinates |
| `/api/external-ai-summary.php` | `GET` | Property AI summary or structured fallback |
| `/api/google-earth.php` | `GET` | KML or KMZ export |

## Core APIs

Endpoint: `/api/bootstrap.php`

Method: `GET`

Description: Returns bootstrapped metadata, visible properties for the current role, service descriptions, decision personas, and dashboard summary statistics.

Payload: None.

Response: `meta`, `properties`, `stats.activeInquiries`, `stats.globalReach`, `stats.marketSnapshot`, `generatedAt`.

Endpoint: `/api/health.php`

Method: `GET`

Description: Returns application health status and confirms database connectivity.

Payload: None.

Response: `status`, `database.connected`, `database.name`, `timestamp`.

## Property and Listing APIs

Endpoint: `/api/properties.php`

Method: `GET`

Description: Returns visible properties for the current user after decision-engine decoration.

Payload: None.

Response: `properties`.

Endpoint: `/api/properties.php`

Method: `POST`

Description: Creates a new property listing. Only admin and seller roles are accepted. Sellers must already be identity-verified.

Payload: JSON or multipart form data. Verified fields include `property_name` or `name`, `city`, `barangay`, `property_type` or `type`, `corridor`, `description`, `price`, `land_area` or `area`, `score`, `road_access`, `lat`, `lng`, `image_file` or `image_path`, `tags`, `facilities`, owner-contact fields, document-status fields, `seller_user_id`, and admin-only verification-related fields.

Response: `201` with `property`.

Endpoint: `/api/property.php?id=<id>`

Method: `GET`

Description: Returns a single property by ID after decision-engine decoration.

Payload: Query parameter `id`.

Response: `property`.

Endpoint: `/api/property.php?id=<id>`

Method: `PUT` or `PATCH`

Description: Updates an existing property. Only admin and seller roles are accepted. Sellers can update only their own listings.

Payload: JSON or multipart form data. Verified fields match the property create workflow. Multipart updates can include `image_file`.

Response: `property`.

Endpoint: `/api/property.php?id=<id>`

Method: `DELETE`

Description: Deletes a property. Only admin and seller roles are accepted. Sellers can delete only their own listings.

Payload: Query parameter `id`.

Response: `propertyId`, `deleted`.

Endpoint: `/api/barangay.php`

Method: `POST`

Description: Updates the `barangay` field of a property after validating it against the approved barangay list from `data/meta.json`. The route does not implement a role check.

Payload: `propertyId`, `barangay` or `null`.

Response: `property`.

Endpoint: `/api/due-diligence.php`

Method: `GET`

Description: Returns the saved due diligence state for a property.

Payload: Query parameter `propertyId`.

Response: `state`.

Endpoint: `/api/due-diligence.php`

Method: `POST`

Description: Updates due diligence state for a property. Only admin and the assigned seller are allowed.

Payload: `propertyId`, `state` object.

Response: `state`.

## Investor Workflow APIs

Endpoint: `/api/cart.php`

Method: `GET`

Description: Returns the current investor shortlist cart.

Payload: None. Investor login is required.

Response: `propertyIds`.

Endpoint: `/api/cart.php`

Method: `POST`

Description: Adds a property to the investor shortlist cart.

Payload: `propertyId`.

Response: `propertyIds`, `added`.

Endpoint: `/api/cart.php`

Method: `DELETE`

Description: Removes a property from the investor shortlist cart.

Payload: `propertyId`.

Response: `propertyIds`, `removed`.

Endpoint: `/api/votes.php`

Method: `GET`

Description: Returns vote tallies for one property or a map of tallies for multiple property IDs.

Payload: Query parameter `propertyId` for a single property, or `ids` or `propertyIds` as a comma-separated list.

Response: For a single property, `votes` and `selectedVoteOptionId`. For multiple properties, `tallies`.

Endpoint: `/api/votes.php`

Method: `POST`

Description: Casts or updates an investor vote for a property.

Payload: `propertyId` and either `voteOptionId` or `label`.

Response: `votes`, `selectedVoteOptionId`.

Endpoint: `/api/vote-options.php`

Method: `GET`

Description: Returns vote options. Admin users receive inactive options as well.

Payload: None.

Response: `voteOptions`.

Endpoint: `/api/vote-options.php`

Method: `POST`

Description: Creates a vote option. Admin only.

Payload: JSON or multipart form data. Verified fields include `title`, optional `description`, optional `image_url` or `image_file`, optional `sort_order`, and optional `is_active`.

Response: `201` with `voteOption` and `voteOptions`.

Endpoint: `/api/vote-options.php`

Method: `PUT` or `PATCH`

Description: Updates a vote option. Admin only.

Payload: Vote option `id` plus the same payload fields used during creation.

Response: `voteOption`, `voteOptions`.

Endpoint: `/api/vote-options.php`

Method: `DELETE`

Description: Deactivates a vote option. Admin only.

Payload: Vote option `id`.

Response: `voteOptionId`, `deleted`, `voteOptions`.

Endpoint: `/api/scenarios.php`

Method: `GET`

Description: Returns saved scenarios for a property.

Payload: Query parameter `propertyId`.

Response: `scenarios`.

Endpoint: `/api/scenarios.php`

Method: `POST`

Description: Creates a saved scenario for a property. The route validates input but does not implement an authentication or role check.

Payload: `propertyId`, `name`, optional `createdBy`, optional `budget`, optional `sector`, optional `size`, optional `weights`, optional `assumptions`, optional `results`.

Response: `scenario`.

## Collaboration and Operations APIs

Endpoint: `/api/messages.php`

Method: `GET`

Description: Returns either the conversation inbox for logged-in operational users, a specific thread payload for a logged-in user, or a property conversation payload for the supplied property ID. Visit data is attached when available.

Payload: Either `scope=inbox`, `threadId`, or `propertyId`.

Response: For inbox requests, `threads`. For thread or property requests, conversation payload plus `visit`.

Endpoint: `/api/messages.php`

Method: `POST`

Description: Sends a message to an existing thread or starts a property conversation. Logged-in investor, seller, or admin roles are required.

Payload: Either `threadId` and `text`, or `propertyId` and `text`.

Response: Conversation payload plus `visit`.

Endpoint: `/api/messages.php`

Method: `DELETE`

Description: Clears a conversation thread. Admin only.

Payload: `threadId`.

Response: `threadId`, `cleared`.

Endpoint: `/api/document-requests.php`

Method: `GET`

Description: Returns either the document request inbox for a logged-in user or the request list for a specific property.

Payload: Either `scope=inbox` or `propertyId`.

Response: `requests`.

Endpoint: `/api/document-requests.php`

Method: `POST`

Description: Creates a property document request. Investor or admin login is required.

Payload: `propertyId`, `documentName`, optional `note`.

Response: `201` with `request` and `requests`.

Endpoint: `/api/document-requests.php`

Method: `PUT` or `PATCH`

Description: Updates a document request status. Seller or admin login is required.

Payload: `requestId`, `status`, optional `responseNote`.

Response: `request`, `requests`.

Endpoint: `/api/visit-logs.php`

Method: `GET`

Description: Returns visit information by thread or latest visit information for a property. Visibility depends on role-aware repository access.

Payload: `threadId` or `propertyId`.

Response: `visit`.

Endpoint: `/api/visit-logs.php`

Method: `POST`

Description: Creates a site visit proposal. The route requires a logged-in operational user, but the repository only allows investors to propose visits.

Payload: `propertyId`, `investmentPurpose`, `primaryStartAt`, `primaryEndAt`, `secondaryStartAt`, `secondaryEndAt` or equivalent primary and secondary window fields accepted by the visit repository.

Response: `visit`, `thread`, `messages`.

Endpoint: `/api/visit-logs.php`

Method: `PATCH`

Description: Applies a visit action such as counter-offer, accept counter-offer, confirm, mark in progress, mark visited, or submit audit.

Payload: `visitId` or `threadId`, `action`, and action-specific fields such as counter window, `selection`, or field-audit values.

Response: `visit`, `thread`, `messages`.

Endpoint: `/api/notifications.php`

Method: `GET`

Description: Returns the notification feed, unread count, and notification preferences for the logged-in user.

Payload: Optional query parameter `limit`.

Response: `notifications`, `unreadCount`, `preferences`, `generatedAt`.

Endpoint: `/api/notifications.php`

Method: `POST`, `PATCH`, or `PUT`

Description: Executes notification actions for the logged-in user.

Payload: `action=markRead` with `notificationId`, or `action=markAllRead`, or `action=updateCadence` with `notificationCadence` or `cadence`.

Response: Action-dependent payload including updated notification state, `unreadCount`, and/or `preferences`.

Endpoint: `/api/audit-logs.php`

Method: `GET`

Description: Returns the admin audit ledger. Admin only.

Payload: Optional `limit`, optional `scope`, optional `afterId`.

Response: `logs`, `latestId`, `scope`.

Endpoint: `/api/seller-profiles.php`

Method: `GET`

Description: Returns the seller's own profile for seller users, or the admin queue or a specific seller profile for admin users.

Payload: Optional `scope=queue`, optional `status`, or `userId` or `sellerUserId` for single-profile admin lookups.

Response: Seller requests return `profile` and `generatedAt`. Admin queue requests return `profiles`, `summary`, and `generatedAt`. Single-profile admin requests return `profile` and `generatedAt`.

Endpoint: `/api/seller-profiles.php`

Method: `POST`

Description: Saves or submits seller verification details. Seller only.

Payload: Seller profile fields such as `seller_type`, `legal_name`, `display_name`, `phone`, `company_name`, `business_registration_no`, `government_id_no`, `address_line`, `barangay`, `city`, `authorization_basis`, and either `submit=true` or `action=submit` to enter review.

Response: `profile`, `user`, `generatedAt`.

Endpoint: `/api/seller-profiles.php`

Method: `PATCH`

Description: Reviews a seller application. Admin only.

Payload: `userId` or `sellerUserId`, `status`, optional `reviewNotes`.

Response: `profile`, `user`, `profiles`, `summary`, `generatedAt`.

## Showcase APIs

Endpoint: `/api/showcase.php`

Method: `GET`

Description: Returns showcase items filtered by visibility and optional feature type.

Payload: Optional `featureType` or `feature_type`.

Response: `items`.

Endpoint: `/api/showcase.php`

Method: `POST`

Description: Creates a showcase item. Admin only.

Payload: JSON or multipart form data. Verified fields include `feature_type` or `featureType`, `title`, `slug`, `partner_label`, `summary`, `description`, `category`, `location_label`, `barangay`, `status`, `pipeline_mode`, `supply_signal`, `cover_image_url` or `image_file`, metric labels and values, `investor_thesis`, `ideal_operator`, `avoidance_note`, `countdown_at`, `completion_target`, `related_property_id`, `is_published`, `is_featured`, and `sort_order`.

Response: `201` with `item` and `items`.

Endpoint: `/api/showcase-item.php?id=<id>`

Method: `GET`

Description: Returns one showcase item by ID.

Payload: Query parameter `id`.

Response: `item`.

Endpoint: `/api/showcase-item.php?id=<id>`

Method: `PUT` or `PATCH`

Description: Updates one showcase item. Admin only.

Payload: JSON or multipart form data matching the showcase create workflow, with optional `image_file`.

Response: `item`, `items`.

Endpoint: `/api/showcase-item.php?id=<id>`

Method: `DELETE`

Description: Deletes one showcase item. Admin only.

Payload: Query parameter `id`.

Response: `itemId`, `deleted`, `items`.

## Aggregation and External Data APIs

Endpoint: `/api/property-command-center.php`

Method: `GET`

Description: Returns the aggregated property command center payload.

Payload: `id` or `propertyId`.

Response: `commandCenter`.

Endpoint: `/api/location-search.php`

Method: `GET`

Description: Performs geocoding or fallback location matching through the external service adapter.

Payload: Query parameter `q`.

Response: `search`.

Endpoint: `/api/external-market.php`

Method: `GET`

Description: Returns the current market snapshot from the external service layer or its fallback.

Payload: None.

Response: `snapshot`.

Endpoint: `/api/external-news.php`

Method: `GET`

Description: Returns the current business or investment news digest from the external service layer or its fallback.

Payload: Optional query parameter `limit`.

Response: `feed`.

Endpoint: `/api/external-weather.php`

Method: `GET`

Description: Returns weather context for a property or for a supplied coordinate pair.

Payload: Either `propertyId`, or `lat` and `lng`, with optional `label`.

Response: `weather`.

Endpoint: `/api/external-ai-summary.php`

Method: `GET`

Description: Returns an AI-generated or structured fallback summary for a property.

Payload: Query parameter `propertyId`.

Response: `summary`.

Endpoint: `/api/google-earth.php`

Method: `GET`

Description: Exports one or more visible properties as a KML or KMZ file and can optionally include spatial overlays.

Payload: `propertyId` or `ids`, optional `scope`, optional `format` of `kml` or `kmz`, optional `includeOverlays`, optional `overlayTypes`.

Response: File download in KML or KMZ format.

# System Integration Explanation

The implemented system integrates its modules through shared repositories and services rather than through separate microservices. The same database-backed entities are reused across ranking, property details, dashboards, document requests, visits, and notifications.

The verified integration paths are:

- The page layer integrates with the API layer through `assets/js/api.js`, which wraps `fetch` calls and supports both JSON and multipart requests.
- `app/Support/web.php` injects routing and current-user context into every page through `window.SFC_APP_CONFIG`.
- `app/bootstrap.php` constructs shared repositories so that multiple endpoints operate on the same normalized property, message, vote, showcase, and seller-profile rules.
- `DecisionEngineService` decorates listing data for ranking, explorer, property detail, and comparison workflows.
- `PropertyCommandCenterService` integrates property data, votes, due diligence, messages, visits, document requests, notifications, and audit logs into one property-level operational payload.
- `NotificationEngine` is triggered by listing creation, listing update, messaging, document requests, due diligence updates, visit transitions, and vote casting.
- `GoogleEarthService` integrates property data and spatial overlays into portable KML or KMZ exports.

The external integration layer is optional and fallback-driven. Verified integrations include:

- LocationIQ for geocoding and location search
- Alpha Vantage for market context
- NewsAPI for business and investment headlines
- OpenWeather for weather context
- Gemini or OpenRouter for AI summaries
- Cloudinary for media upload offloading

At the time of this audit, `app/config.local.php` stores empty keys for the external services above. Because of this, the implemented code relies on fallback behavior and cache-backed local responses when live credentials are absent.

# Tools and Technologies

Table 6. Tools and Technologies

| Tool or Technology | Verified Use in the Project |
| --- | --- |
| PHP | Main server-side language for pages, APIs, repositories, and services |
| MySQL | Primary relational database defined in `database/schema.sql` |
| PDO | Database access layer used throughout the repository layer |
| JavaScript | Frontend behavior, API access, notification polling, and page initialization |
| Fetch API | Browser-side HTTP requests in `assets/js/api.js` |
| HTML and CSS | Server-rendered UI and styling in page templates and `assets/css/portal.css` |
| PHP Sessions | Authentication and role persistence |
| JSON seed data | Metadata and sample records in `data/meta.json`, `data/properties.json`, and `data/sample-data.json` |
| Leaflet 1.9.4 | Interactive map rendering on property explorer and property detail pages |
| OpenStreetMap | Map tile source and attribution |
| Google Fonts | Font loading in the shared page head |
| XAMPP | Verified local setup target from `README.md` |
| Apache | Verified local web server target from `README.md` |
| LocationIQ | Optional geocoding integration |
| Alpha Vantage | Optional market-data integration |
| NewsAPI | Optional news-digest integration |
| OpenWeather | Optional weather integration |
| Gemini or OpenRouter | Optional AI summary integration |
| Cloudinary | Optional media upload integration |
| ZipArchive | Optional KMZ packaging support when available in the PHP runtime |

# Testing and Results

This documentation was produced from a code audit rather than from a full executed QA run.

Table 7. Testing Summary

| Verification Area | Result |
| --- | --- |
| Repository structure audit | Completed |
| Top-level page audit | Completed |
| API route audit | Completed |
| Database schema audit | Completed |
| Configuration audit | Completed |
| Seed data audit | Completed |
| Automated test suite discovery | No automated test files were found in the repository during text search |
| PHP CLI syntax lint | Not executed because `php` was not available in the current terminal `PATH` |
| Browser screenshots | [TO BE PROVIDED MANUALLY] |
| Architecture, ERD, flowchart, and sequence diagrams | [TO BE PROVIDED MANUALLY] |

Observed result set from the repository audit:

- The implemented system is internally consistent as a PHP and MySQL application with a clear page, API, repository, and service split.
- Role-based access is enforced at the page layer and on most state-changing API routes.
- External services are designed to degrade gracefully when live keys are not configured.
- No repository-native automated test suite is currently present.

# References

The following repository sources were used directly in preparing this documentation:

- `README.md`
- `app/bootstrap.php`
- `app/config.php`
- `app/config.local.php`
- `app/Core/Database.php`
- `app/Core/SchemaManager.php`
- `app/Support/auth.php`
- `app/Support/web.php`
- `app/Support/AutoSeeder.php`
- `app/Support/ExternalServices.php`
- `app/Support/DecisionEngineService.php`
- `app/Support/NotificationEngine.php`
- `app/Support/PropertyCommandCenterService.php`
- `app/Support/GoogleEarthService.php`
- `app/Repositories/PropertyRepository.php`
- `app/Repositories/MessageRepository.php`
- `app/Repositories/DocumentRequestRepository.php`
- `app/Repositories/VisitLogRepository.php`
- `app/Repositories/VoteOptionRepository.php`
- `app/Repositories/ShowcaseRepository.php`
- `app/Repositories/SellerProfileRepository.php`
- `app/Repositories/ScenarioRepository.php`
- `app/Repositories/NotificationRepository.php`
- `app/Repositories/AuditLogRepository.php`
- `api/*.php`
- `database/schema.sql`
- `database/setup.sql`
- `data/meta.json`
- `data/properties.json`
- `data/sample-data.json`
- Root page files such as `index.php`, `property-explorer.php`, `property-details.php`, `compare-decision.php`, `voting-dashboard.php`, `offer-board.php`, `city-pipeline.php`, `admin-dashboard.php`, `admin-properties.php`, `admin-showcase.php`, `investor-dashboard.php`, and `seller-dashboard.php`
- `assets/js/api.js`
- `assets/js/portal.js`
- `assets/js/notifications.js`
- `assets/css/portal.css`

# Appendices

## Appendix A. Seeded Data Summary

- Seeded properties in `data/properties.json`: 10
- Barangays in `data/meta.json`: 59
- Due diligence checklist items in `data/meta.json`: 10
- Vote presets in `data/meta.json`: 11

## Appendix B. Demo Credentials Verified from the Codebase

- Admin: `admin@sfcelerate.local` / `Admin123!`
- Seller: `seller@sfcelerate.local` / `Seller123!`
- Investor: `investor@sfcelerate.local` / `Investor123!`
- Additional seeded investor in auto-seeding logic: `maria.santos@sfcelerate.local` / `Investor123!`

## Appendix C. Manual Artifact Checklist

- Overall system architecture diagram: [TO BE PROVIDED MANUALLY]
- Entity relationship diagram: [TO BE PROVIDED MANUALLY]
- Process flowcharts: [TO BE PROVIDED MANUALLY]
- Sequence diagrams: [TO BE PROVIDED MANUALLY]
- User interface screenshots: [TO BE PROVIDED MANUALLY]

## Appendix D. Verified Implementation Notes

- Public users can open `property-details.php`, but some operational data in the command center is role-sensitive.
- Public showcase pages return published items only, while admins can see unpublished showcase content.
- `api/google-earth.php` exports only properties visible to the current user.
- `m.php` is a redirect helper and not a standalone application screen.
