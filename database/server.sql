/*
 * Database schema for The SMS SaaS application
 * This SQL script creates the necessary tables for managing tenants, tenant profiles, and users.
 * 
 * ==========================================
 * TABLE OF CONTENTS:
 * ==========================================
 * SECTION 0 : LARAVEL FRAMEWORK TABLES (Cache, Jobs, Sessions, Notifications, etc.)
 * SECTION 1 : MULTI-TENANCY (Tenants, Profiles, Domains)
 * SECTION 2 : IAM - USERS & RBAC (Users, Spatie Roles/Permissions, Audit Logs)
 * SECTION 2b: TENANT INVITATIONS
 * SECTION 1b: SUBSCRIPTIONS & BILLING
 * SECTION 1b2: FEATURES & FEATURE TOGGLES (Plan→Feature mapping, Tenant on/off switches)
 * SECTION 1c: TAX CONFIGURATION
 * SECTION 3 : MASTER DATA - Suppliers
 * SECTION 3b: MASTER DATA - UOMs
 * SECTION 4 : INVENTORY (Warehouses, Stocks, Stock Batches, Stock Movements, Costing, Stock Takes)
 * SECTION 4b: PURCHASE ORDERS
 * SECTION 4 : INVENTORY (cont.) - Import Receipts, Import Items
 * SECTION 4c: TRANSFER ORDERS (Inter-Warehouse)
 * SECTION 3c: MASTER DATA - Categories & Products (Variants, Prices, Attributes, Images)
 * SECTION 7 : MARKETING & PRICING (Surcharge Catalog, Promotions)
 * SECTION 5a: POS SHIFT MANAGEMENT
 * SECTION 5 : SALES & POS - Orders (Orders, Order Items, Surcharges)
 * SECTION 8 : FINANCE & ACCOUNTING - Invoices
 * SECTION 6 : RETURNS (Return Orders, Credit Notes, Account Receivables, Account Payables)
 * SECTION 7b: PRICING - Price Lists
 * SECTION 3d: MASTER DATA - Customers (Customer Groups)
 * SECTION 5b: SALES - Payments
 * SECTION 8b: FINANCE - Business Expenses
 * SECTION 9 : REPORTING (Sales Summary, Product Summary, Category Summary)
 * SECTION 10: DATA SYNC (Offline/Online Synchronization)
 * SECTION 11: TENANT CONFIGURATION (Document Templates, Printers, Code Sequences)
 * SECTION 11b: API & INTEGRATIONS (API Keys, Webhooks)
 * SECTION 12: DEFERRED FOREIGN KEYS (Forward-reference constraints applied after all tables exist)
 * ==========================================
 */

-- ==========================================
-- SECTION 0: LARAVEL FRAMEWORK DEFAULT TABLES
-- ==========================================

/*
 * CACHE tables: Laravel cache driver (database)
 */
CREATE TABLE cache (
    `key` varchar(255) not null primary key,
    value mediumtext not null,
    expiration int not null,
    index cache_expiration_index (expiration)
);

CREATE TABLE cache_locks (
    `key` varchar(255) not null primary key,
    owner varchar(255) not null,
    expiration int not null,
    index cache_locks_expiration_index (expiration)
);

/*
 * SESSIONS table: Laravel session driver (database)
 */
CREATE TABLE sessions (
    id varchar(255) not null primary key,
    user_id bigint unsigned null,
    ip_address varchar(45) null,
    user_agent text null,
    payload longtext not null,
    last_activity int not null,
    index sessions_user_id_index (user_id),
    index sessions_last_activity_index (last_activity)
);

/*
 * JOBS tables: Laravel queue driver (database)
 */
CREATE TABLE jobs (
    id bigint unsigned auto_increment primary key,
    queue varchar(255) not null,
    payload longtext not null,
    attempts tinyint unsigned not null,
    reserved_at int unsigned null,
    available_at int unsigned not null,
    created_at int unsigned not null,
    index jobs_queue_reserved_at_available_at_index (queue, reserved_at, available_at)
);

CREATE TABLE job_batches (
    id varchar(255) not null primary key,
    name varchar(255) not null,
    total_jobs int not null,
    pending_jobs int not null,
    failed_jobs int not null,
    failed_job_ids longtext not null,
    options mediumtext null,
    cancelled_at int null,
    created_at int not null,
    finished_at int null
);

CREATE TABLE failed_jobs (
    id bigint unsigned auto_increment primary key,
    uuid varchar(255) not null unique,
    connection text not null,
    queue text not null,
    payload longtext not null,
    exception longtext not null,
    failed_at timestamp default current_timestamp
);

/*
 * PASSWORD RESET TOKENS table: Laravel auth password resets
 */
CREATE TABLE password_reset_tokens (
    email varchar(255) not null primary key,
    token varchar(255) not null,
    created_at timestamp null
);

/*
 * NOTIFICATIONS table: Laravel database notification driver
 */
CREATE TABLE notifications (
    id char(36) not null primary key,
    type varchar(255) not null,
    notifiable_type varchar(255) not null,
    notifiable_id bigint unsigned not null,
    data json not null,
    read_at timestamp null,
    created_at timestamp null,
    updated_at timestamp null,
    index notifications_notifiable_type_notifiable_id_index (notifiable_type, notifiable_id)
);

/*
 * MIGRATIONS table: Laravel migration tracking
 */
CREATE TABLE migrations (
    id int unsigned auto_increment primary key,
    migration varchar(255) not null,
    batch int not null
);

-- ==========================================
-- SECTION 1: MULTI-TENANCY (stancl/tenancy)
-- ==========================================

/*
 * TENANTS table: Stores information about each tenant (customer) of the SaaS application.
 */
CREATE TABLE tenants (
    id int auto_increment primary key,
    uuid char(36) not null unique,              -- Public-facing identifier (API, URL, webhook)
    name varchar(255) not null,
    domain varchar(255) not null unique,
    email varchar(255) not null unique,
    status enum('active', 'suspended', 'expired') default 'active',
    data json null,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp
);

/*
 * DOMAINS table: stancl/tenancy domain identification
 */
CREATE TABLE domains (
    id int auto_increment primary key,
    domain varchar(255) not null unique,
    tenant_id int not null,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    foreign key (tenant_id) references tenants(id) on delete cascade
);

CREATE TABLE tenant_profiles (
    id int auto_increment primary key,
    tenant_id int not null,
    company_name varchar(255) not null,
    company_address text,
    company_phone varchar(20),
    company_email varchar(255),
    tax_code varchar(50) null,
    logo_path varchar(255) null,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    foreign key (tenant_id) references tenants(id) on delete cascade
);

-- ==========================================
-- SECTION 2: IAM - USERS & RBAC (Spatie laravel-permission)
-- ==========================================

/*
 * USERS table: Stores information about users who belong to tenants.
 * Removed static enum role → now managed by Spatie roles/permissions.
 */
CREATE TABLE users (
    id int auto_increment primary key,
    tenant_id int not null,
    uuid varchar(255) not null unique,
    name varchar(255) not null,
    email varchar(255) not null unique,
    email_verified_at timestamp null,
    password varchar(255) not null,
    phone varchar(20) null,
    status enum('active', 'inactive', 'suspended') default 'active',
    two_factor_secret text null,
    two_factor_recovery_codes text null,
    two_factor_confirmed_at timestamp null,
    remember_token varchar(100) null,
    last_login_at timestamp null,
    last_login_ip varchar(45) null,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    foreign key (tenant_id) references tenants(id) on delete cascade,
    index users_tenant_id_index (tenant_id),
    index users_status_index (status)
);

/*
 * SPATIE PERMISSIONS tables: Role-Based Access Control
 * These are the standard Spatie laravel-permission tables.
 * Guard name should be 'web' for session-based auth.
 */
CREATE TABLE permissions (
    id bigint unsigned auto_increment primary key,
    name varchar(255) not null,
    guard_name varchar(255) not null,
    created_at timestamp null,
    updated_at timestamp null,
    unique key permissions_name_guard_name_unique (name, guard_name)
);

CREATE TABLE roles (
    id bigint unsigned auto_increment primary key,
    name varchar(255) not null,
    guard_name varchar(255) not null,
    created_at timestamp null,
    updated_at timestamp null,
    unique key roles_name_guard_name_unique (name, guard_name)
);

CREATE TABLE model_has_permissions (
    permission_id bigint unsigned not null,
    model_type varchar(255) not null,
    model_id bigint unsigned not null,
    primary key (permission_id, model_id, model_type),
    foreign key (permission_id) references permissions(id) on delete cascade,
    index model_has_permissions_model_id_model_type_index (model_id, model_type)
);

CREATE TABLE model_has_roles (
    role_id bigint unsigned not null,
    model_type varchar(255) not null,
    model_id bigint unsigned not null,
    primary key (role_id, model_id, model_type),
    foreign key (role_id) references roles(id) on delete cascade,
    index model_has_roles_model_id_model_type_index (model_id, model_type)
);

CREATE TABLE role_has_permissions (
    permission_id bigint unsigned not null,
    role_id bigint unsigned not null,
    primary key (permission_id, role_id),
    foreign key (permission_id) references permissions(id) on delete cascade,
    foreign key (role_id) references roles(id) on delete cascade
);

/*
 * AUDIT LOGS table: General-purpose audit trail (maps to Prisma AuditLog)
 * Also covers user login/logout events. user_id + action='LOGIN'/'LOGOUT' replaces user_audits.
 */
CREATE TABLE audit_logs (
    id int auto_increment primary key,
    tenant_id int not null,
    user_id int not null,
    action varchar(50) not null,
    table_name varchar(100) not null,
    record_id int null,
    old_value json null,
    new_value json null,
    ip_address varchar(45) null,
    user_agent text null,
    created_at timestamp default current_timestamp,
    foreign key (tenant_id) references tenants(id) on delete cascade,
    foreign key (user_id) references users(id) on delete cascade,
    index audit_logs_tenant_id_index (tenant_id),
    index audit_logs_user_id_index (user_id),
    index audit_logs_table_name_index (table_name),
    index audit_logs_record_id_index (record_id),
    index audit_logs_created_at_index (created_at)
);

-- ==========================================
-- SECTION 2b: TENANT INVITATIONS
-- ==========================================

/*
 * TENANT_INVITATIONS table: Invite users to join a tenant workspace.
 * Flow: Owner sends invite → email with token → invitee accepts → user created/linked.
 * Token is hashed (SHA-256) and has an expiry.
 */
CREATE TABLE tenant_invitations (
    id int auto_increment primary key,
    tenant_id int not null,
    email varchar(255) not null,
    role_id bigint unsigned not null,        -- Which role the invitee will get
    token varchar(64) not null unique,       -- Hashed invitation token
    status enum('PENDING', 'ACCEPTED', 'EXPIRED', 'REVOKED') default 'PENDING',
    expires_at timestamp not null,
    accepted_at timestamp null,
    invited_by int not null,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    foreign key (tenant_id) references tenants(id) on delete cascade,
    foreign key (role_id) references roles(id) on delete cascade,
    foreign key (invited_by) references users(id) on delete cascade,
    index tenant_invitations_tenant_id_index (tenant_id),
    index tenant_invitations_email_index (email),
    index tenant_invitations_token_index (token),
    index tenant_invitations_status_index (status)
);

-- ==========================================
-- SECTION 1b: SUBSCRIPTIONS & BILLING
-- ==========================================

/*
 * SUBSCRIPTIONS table: Stores tenant subscription plans.
 */
CREATE TABLE subscriptions (
    id int auto_increment primary key,
    tenant_id int not null,
    plan_name varchar(255) not null,
    status enum('active', 'canceled', 'past_due', 'trialing') default 'active',
    start_date date,
    end_date date,
    trial_ends_at date null,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    foreign key (tenant_id) references tenants(id) on delete cascade,
    index subscriptions_tenant_id_index (tenant_id),
    index subscriptions_status_index (status)
);

/*
 * BILLING table: Stores billing/invoice records for tenant payments.
 */
CREATE TABLE billing (
    id int auto_increment primary key,
    tenant_id int not null,
    subscription_id int null,
    amount decimal(14, 2) not null,
    currency varchar(10) not null default 'VND',
    status enum('paid', 'unpaid', 'failed', 'refunded') default 'unpaid',
    invoice_number varchar(255) not null unique,
    payment_method varchar(50) null,
    paid_at timestamp null,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    foreign key (tenant_id) references tenants(id) on delete cascade,
    foreign key (subscription_id) references subscriptions(id) on delete set null,
    index billing_tenant_id_index (tenant_id),
    index billing_status_index (status)
);

-- ==========================================
-- SECTION 1b2: FEATURES & FEATURE TOGGLES
-- ==========================================

/*
 * FEATURES table: System-wide feature catalog.
 * Defines all toggleable modules/capabilities in the platform.
 * Examples: TAX, RETURNS, PROMOTIONS, MULTI_WAREHOUSE, PRICE_LISTS, API_ACCESS, BATCH_TRACKING
 *
 * Features are defined by the platform (SaaS admin), not by tenants.
 * Each feature has a unique code used in application logic checks.
 */
CREATE TABLE features (
    id int auto_increment primary key,
    code varchar(100) not null unique,       -- 'TAX', 'RETURNS', 'MULTI_WAREHOUSE', 'PROMOTIONS'
    name varchar(255) not null,              -- 'Quản lý thuế', 'Đổi trả hàng'
    description text null,
    module varchar(100) null,                -- Logical grouping: 'INVENTORY', 'SALES', 'FINANCE'
    is_active boolean default true,          -- Platform-level kill switch
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    index features_code_index (code),
    index features_module_index (module)
);

/*
 * PLAN_FEATURES table: Maps subscription plans → allowed features.
 * Defines the ceiling of what a plan permits. Tenants cannot enable features
 * that their plan does not include.
 *
 * Example:
 *   Plan "Basic"  → TAX, RETURNS
 *   Plan "Pro"    → TAX, RETURNS, PROMOTIONS, MULTI_WAREHOUSE, BATCH_TRACKING
 *   Plan "Enterprise" → all features
 *
 * Some features may have limits (e.g. max 2 warehouses on Basic, unlimited on Pro).
 * The `limit_value` column stores these numeric caps (null = unlimited).
 */
CREATE TABLE plan_features (
    id int auto_increment primary key,
    subscription_id int not null,
    feature_id int not null,
    limit_value int null,                    -- null = unlimited, e.g. max_warehouses = 2
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    foreign key (subscription_id) references subscriptions(id) on delete cascade,
    foreign key (feature_id) references features(id) on delete cascade,
    unique key uk_plan_feature (subscription_id, feature_id),
    index plan_features_subscription_id_index (subscription_id)
);

/*
 * TENANT_FEATURE_TOGGLES table: Per-tenant feature on/off switches.
 * Even if the plan allows a feature, the tenant can disable it to simplify their UI.
 *
 * Logic flow:
 *   1. Check plan_features → Does the plan include this feature? (ceiling)
 *   2. Check tenant_feature_toggles → Has the tenant explicitly disabled it? (override)
 *   3. Both must be true for the feature to be active.
 *
 * If no row exists in tenant_feature_toggles for a feature, default = enabled (plan allows it → on).
 * Only insert a row when tenant explicitly toggles OFF.
 *
 * Example: Tenant "Tiệm tạp hóa Bình" on Pro plan:
 *   - Pro includes TAX, RETURNS, PROMOTIONS, MULTI_WAREHOUSE
 *   - Tenant disables TAX → row: (tenant_id, feature_id, is_enabled=false)
 *   - Result: TAX module hidden from their UI, tax_config_id ignored on products
 */
CREATE TABLE tenant_feature_toggles (
    id int auto_increment primary key,
    tenant_id int not null,
    feature_id int not null,
    is_enabled boolean default true,
    disabled_at timestamp null,              -- When it was turned off
    disabled_by int null,                    -- User who toggled it
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    foreign key (tenant_id) references tenants(id) on delete cascade,
    foreign key (feature_id) references features(id) on delete cascade,
    foreign key (disabled_by) references users(id) on delete set null,
    unique key uk_tenant_feature (tenant_id, feature_id),
    index tenant_feature_toggles_tenant_id_index (tenant_id),
    index tenant_feature_toggles_is_enabled_index (is_enabled)
);

-- ==========================================
-- SECTION 1c: TAX CONFIGURATION
-- ==========================================

/*
 * TAX_CONFIGS table: Tax rate definitions per tenant.
 * Vietnam VAT rates: 0% (export), 5% (essentials), 8% (reduced), 10% (standard).
 * Each tenant can configure which tax rates they use.
 */
CREATE TABLE tax_configs (
    id int auto_increment primary key,
    tenant_id int not null,
    code varchar(50) not null,              -- 'VAT0', 'VAT5', 'VAT8', 'VAT10', 'NO_TAX'
    name varchar(255) not null,             -- 'GTGT 10%', 'Không chịu thuế'
    rate decimal(5, 2) not null,            -- 0.00, 5.00, 8.00, 10.00
    is_default boolean default false,
    is_active boolean default true,
    description text null,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    foreign key (tenant_id) references tenants(id) on delete cascade,
    unique key uk_tenant_tax_code (tenant_id, code),
    index tax_configs_tenant_id_index (tenant_id),
    index tax_configs_is_active_index (is_active)
);

-- ==========================================
-- SECTION 3: MASTER DATA - Suppliers
-- ==========================================

/*
 * SUPPLIERS table: Matches Prisma Supplier model with debt tracking.
 */
CREATE TABLE suppliers (
    id int auto_increment primary key,
    tenant_id int not null,
    code varchar(50) null,
    name varchar(255) not null,
    contact_name varchar(255),
    contact_email varchar(255),
    contact_phone varchar(20),
    address text null,
    tax_code varchar(50) null,
    debt_amount decimal(14, 2) default 0.00,
    credit_limit decimal(14, 2) default 0.00,
    is_active boolean default true,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    deleted_at timestamp null,
    foreign key (tenant_id) references tenants(id) on delete cascade,
    unique key uk_tenant_supplier_code (tenant_id, code),
    index suppliers_tenant_id_index (tenant_id),
    index suppliers_name_index (name),
    index suppliers_is_active_index (is_active)
);

-- ==========================================
-- SECTION 3b: MASTER DATA - UOMs
-- ==========================================

/*
 * UOM table: Unit of Measure with self-referencing base unit. Matches Prisma UnitType.
 */
CREATE TABLE uoms (
    id bigint auto_increment primary key,
    tenant_id int not null,
    name varchar(255) not null,
    symbol varchar(50) null,
    base_uom_id bigint null,
    conversion_factor decimal(14, 6) default 1.000000 not null,
    is_active boolean default true,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    deleted_at timestamp null,
    foreign key (tenant_id) references tenants(id) on delete cascade,
    foreign key (base_uom_id) references uoms(id) on delete restrict,
    unique key uk_tenant_uom_name (tenant_id, name),
    unique key uk_tenant_uom_symbol (tenant_id, symbol)
);

-- ==========================================
-- SECTION 4: INVENTORY - Warehouses & Stock
-- ==========================================

/*
 * WAREHOUSES table: Physical warehouse/store locations (Prisma: Warehouse).
 * Decoupled from product_variants (was incorrectly coupled before).
 */
CREATE TABLE warehouses (
    id int auto_increment primary key,
    tenant_id int not null,
    code varchar(50) null,
    name varchar(255) not null,
    address text null,
    phone varchar(20) null,
    is_active boolean default true,
    is_default boolean default false,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    deleted_at timestamp null,
    foreign key (tenant_id) references tenants(id) on delete cascade,
    unique key uk_tenant_warehouse_code (tenant_id, code),
    index warehouses_tenant_id_index (tenant_id),
    index warehouses_is_active_index (is_active)
);

/*
 * WAREHOUSE_LOCATIONS table: Hierarchical location within a warehouse (Prisma: WarehouseLocation).
 * Supports: AREA → FLOOR → AISLE → RACK → SHELF → BIN
 */
CREATE TABLE warehouse_locations (
    id int auto_increment primary key,
    warehouse_id int not null,
    code varchar(100) not null,
    name varchar(255) not null,
    type enum('AREA', 'FLOOR', 'AISLE', 'RACK', 'SHELF', 'BIN') not null,
    parent_id int null,
    capacity int null,
    description text null,
    is_active boolean default true,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    foreign key (warehouse_id) references warehouses(id) on delete cascade,
    foreign key (parent_id) references warehouse_locations(id) on delete cascade,
    unique key uk_warehouse_location_code (warehouse_id, code),
    index warehouse_locations_warehouse_id_index (warehouse_id),
    index warehouse_locations_parent_id_index (parent_id),
    index warehouse_locations_type_index (type)
);

/*
 * STOCKS table: Current stock per product per warehouse (Prisma: Stock).
 */
CREATE TABLE stocks (
    id int auto_increment primary key,
    product_variant_id int not null,
    warehouse_id int not null,
    warehouse_location_id int null,
    quantity int default 0,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    -- FK: product_variant_id → product_variants(id) ON DELETE CASCADE [DEFERRED]
    foreign key (warehouse_id) references warehouses(id) on delete cascade,
    foreign key (warehouse_location_id) references warehouse_locations(id) on delete set null,
    unique key uk_product_warehouse (product_variant_id, warehouse_id),
    index stocks_warehouse_id_index (warehouse_id),
    index stocks_quantity_index (quantity)
);

/*
 * STOCK_BATCHES table: Track inventory by import lot/batch.
 * Each import_item creates a batch. Remaining quantity tracks how many units from this batch
 * are still in stock, enabling FIFO/LIFO/Specific cost calculation.
 *
 * Example:
 *   Batch 1: import 10 units @ $10 → remaining_quantity starts at 10
 *   Sell 4 units (FIFO) → remaining_quantity becomes 6
 *   Batch 2: import 20 units @ $9.5 → remaining_quantity starts at 20
 *   Current stock: 6 @ $10 + 20 @ $9.5 = 26 units, value = $250
 */
CREATE TABLE stock_batches (
    id int auto_increment primary key,
    product_variant_id int not null,
    warehouse_id int not null,
    import_item_id int null,                -- Link to import lot (null for manual adjustments)
    
    batch_code varchar(100) null,           -- Optional lot/batch number (e.g. 'LOT-20260311-001')
    
    -- Base unit cost tracking (always stored in base unit for consistency)
    cost_price decimal(14, 2) not null,     -- Cost per BASE UNIT in this batch
    
    -- Quantity tracking (always in base unit)
    initial_quantity int not null,           -- Original qty when batch created (base unit)
    remaining_quantity int not null,         -- Qty still available (base unit)
    
    -- Optional base unit selling price override per batch
    -- If null → use product_variants.price (default)
    selling_price decimal(14, 2) null,
    wholesale_price decimal(14, 2) null,
    
    -- Batch metadata
    received_at timestamp default current_timestamp,  -- When this batch entered stock
    expires_at date null,                              -- Expiry date (for perishables)
    
    is_active boolean default true,         -- false when remaining_quantity = 0
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    -- FK: product_variant_id → product_variants(id) ON DELETE CASCADE [DEFERRED]
    foreign key (warehouse_id) references warehouses(id) on delete cascade,
    -- FK: import_item_id → import_items(id) ON DELETE SET NULL [DEFERRED]
    index stock_batches_product_warehouse_index (product_variant_id, warehouse_id),
    index stock_batches_remaining_index (remaining_quantity),
    index stock_batches_is_active_index (is_active),
    index stock_batches_received_at_index (received_at),
    index stock_batches_expires_at_index (expires_at)
);

/*
 * STOCK_BATCH_PRICES table: Per-UOM pricing for each batch.
 * Allows different cost/selling prices per unit of measure within the same batch.
 *
 * Example: Batch 1 (Coca Cola, imported at 2 UOMs simultaneously):
 *   { batch_id: 1, unit_name: 'Lon',   conversion_rate: 1,  cost_price: 10.00, selling_price: 15.00 }
 *   { batch_id: 1, unit_name: 'Thùng', conversion_rate: 24, cost_price: 230.00, selling_price: 340.00 }
 *
 * Batch 2 (new import, lower prices):
 *   { batch_id: 2, unit_name: 'Lon',   conversion_rate: 1,  cost_price: 9.50, selling_price: 14.50 }
 *   { batch_id: 2, unit_name: 'Thùng', conversion_rate: 24, cost_price: 220.00, selling_price: 330.00 }
 *
 * Note: stock_batches.cost_price remains the BASE UNIT cost for inventory valuation.
 * This table extends it with UOM-specific overrides for pricing at point of sale.
 */
CREATE TABLE stock_batch_prices (
    id int auto_increment primary key,
    batch_id int not null,
    unit_name varchar(50) not null,          -- 'Lon', 'Thùng', 'Hộp', etc.
    conversion_rate int not null default 1,  -- 1 Thùng = 24 Lon → conversion_rate = 24
    
    -- Cost per this UOM
    cost_price decimal(14, 2) not null,      -- e.g. Thùng: $230 (may != base * 24 due to volume discount)
    
    -- Selling prices per this UOM (null → auto-calculate from base * conversion_rate)
    selling_price decimal(14, 2) null,       -- Retail price for this UOM in this batch
    wholesale_price decimal(14, 2) null,     -- Wholesale price for this UOM in this batch
    
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    foreign key (batch_id) references stock_batches(id) on delete cascade,
    unique key uk_batch_unit (batch_id, unit_name),
    index stock_batch_prices_batch_id_index (batch_id)
);

/*
 * INVENTORY_COSTING_CONFIGS table: Per-tenant inventory valuation method.
 * Controls how cost_price is calculated when selling.
 *
 * Methods:
 *   FIFO           - First In, First Out (sell oldest batch first)
 *   LIFO           - Last In, First Out (sell newest batch first)
 *   WEIGHTED_AVG   - Weighted Average Cost (recalculate on each import)
 *   SPECIFIC       - Manual batch selection (staff picks which batch to sell from)
 */
CREATE TABLE inventory_costing_configs (
    id int auto_increment primary key,
    tenant_id int not null,
    method enum('FIFO', 'LIFO', 'WEIGHTED_AVG', 'SPECIFIC') default 'FIFO',
    
    -- Pricing strategy: how selling price relates to batches
    -- GLOBAL       → All batches use product_variants.price (your option 1: all at $15)
    -- PER_BATCH    → Each batch can have its own selling_price (your option 2: old $15, new $14.5)
    -- MARGIN_BASED → Auto-calculate selling price = cost_price * (1 + margin_percent/100)
    pricing_strategy enum('GLOBAL', 'PER_BATCH', 'MARGIN_BASED') default 'GLOBAL',
    default_margin_percent decimal(5, 2) null,  -- Used when pricing_strategy = 'MARGIN_BASED'
    
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    foreign key (tenant_id) references tenants(id) on delete cascade,
    unique key uk_tenant_costing (tenant_id)
);

/*
 * STOCK_MOVEMENTS table: Full audit of every stock change (Prisma: StockMovement).
 * Tracks import, export, return, adjustment, transfer, damaged, expired.
 */
CREATE TABLE stock_movements (
    id int auto_increment primary key,
    product_variant_id int not null,
    warehouse_id int not null,
    batch_id int null,                      -- Which batch this movement affects
    movement_type enum('IMPORT', 'EXPORT', 'RETURN_IN', 'RETURN_OUT', 'ADJUST_IN', 'ADJUST_OUT', 'TRANSFER_IN', 'TRANSFER_OUT', 'DAMAGED', 'EXPIRED') not null,
    quantity int not null,
    balance_after int not null,
    cost_price_at_move decimal(14, 2) null,  -- Snapshot of cost price at time of movement
    order_id int null,                       -- FK → orders(id) ON DELETE SET NULL [DEFERRED]
    import_item_id int null,                 -- FK → import_items(id) ON DELETE SET NULL [DEFERRED]
    stock_take_id int null,                  -- FK → stock_takes(id) ON DELETE SET NULL [DEFERRED]
    note text,
    created_by int null,
    created_at timestamp default current_timestamp,
    -- FK: product_variant_id → product_variants(id) ON DELETE CASCADE [DEFERRED]
    foreign key (warehouse_id) references warehouses(id) on delete cascade,
    foreign key (batch_id) references stock_batches(id) on delete set null,
    foreign key (created_by) references users(id) on delete set null,
    index stock_movements_product_variant_id_index (product_variant_id),
    index stock_movements_warehouse_id_index (warehouse_id),
    index stock_movements_movement_type_index (movement_type),
    index stock_movements_created_at_index (created_at),
    index stock_movements_order_id_index (order_id)
);

/*
 * STOCK_TAKES table: Inventory count sessions (Prisma: StockTake).
 */
CREATE TABLE stock_takes (
    id int auto_increment primary key,
    tenant_id int not null,
    code varchar(50) not null unique,
    warehouse_id int not null,
    creator_id int not null,
    status enum('DRAFT', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED') default 'DRAFT',
    note text,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    completed_at timestamp null,
    foreign key (tenant_id) references tenants(id) on delete cascade,
    foreign key (warehouse_id) references warehouses(id) on delete cascade,
    foreign key (creator_id) references users(id) on delete cascade,
    index stock_takes_warehouse_id_index (warehouse_id),
    index stock_takes_status_index (status)
);

/*
 * STOCK_TAKE_ITEMS table: Line items for inventory count (Prisma: StockTakeItem).
 */
CREATE TABLE stock_take_items (
    id int auto_increment primary key,
    stock_take_id int not null,
    product_variant_id int not null,
    system_qty int not null,
    actual_qty int not null,
    diff_qty int not null,
    reason text null,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    foreign key (stock_take_id) references stock_takes(id) on delete cascade,
    -- FK: product_variant_id → product_variants(id) ON DELETE CASCADE [DEFERRED]
    index stock_take_items_stock_take_id_index (stock_take_id)
);

-- ==========================================
-- SECTION 4b: PURCHASE ORDERS
-- ==========================================

/*
 * PURCHASE_ORDERS table: Purchase order header sent to suppliers.
 * Flow: DRAFT → APPROVED → SENT → PARTIALLY_RECEIVED → FULLY_RECEIVED.
 * Links to import_receipts when goods arrive.
 */
CREATE TABLE purchase_orders (
    id int auto_increment primary key,
    tenant_id int not null,
    code varchar(50) not null unique,
    supplier_id int not null,
    warehouse_id int not null,
    status enum('DRAFT', 'APPROVED', 'SENT', 'PARTIALLY_RECEIVED', 'FULLY_RECEIVED', 'CANCELLED') default 'DRAFT',
    expected_date date null,
    total_amount decimal(14, 2) default 0.00,
    tax_amount decimal(14, 2) default 0.00,
    note text null,
    approved_by int null,
    approved_at timestamp null,
    created_by int not null,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    deleted_at timestamp null,
    foreign key (tenant_id) references tenants(id) on delete cascade,
    foreign key (supplier_id) references suppliers(id) on delete cascade,
    foreign key (warehouse_id) references warehouses(id) on delete cascade,
    foreign key (approved_by) references users(id) on delete set null,
    foreign key (created_by) references users(id) on delete cascade,
    index purchase_orders_tenant_id_index (tenant_id),
    index purchase_orders_supplier_id_index (supplier_id),
    index purchase_orders_status_index (status),
    index purchase_orders_expected_date_index (expected_date)
);

/*
 * PURCHASE_ORDER_ITEMS table: Line items for purchase orders.
 */
CREATE TABLE purchase_order_items (
    id int auto_increment primary key,
    purchase_order_id int not null,
    product_variant_id int not null,
    unit_name varchar(50) default 'Cái',
    conversion_rate int default 1,
    quantity int not null,
    received_quantity int default 0,
    unit_price decimal(14, 2) not null,
    tax_rate decimal(5, 2) default 0.00,
    tax_amount decimal(14, 2) default 0.00,
    total decimal(14, 2) not null,
    note text null,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    foreign key (purchase_order_id) references purchase_orders(id) on delete cascade,
    -- FK: product_variant_id → product_variants(id) ON DELETE CASCADE [DEFERRED]
    index purchase_order_items_purchase_order_id_index (purchase_order_id),
    index purchase_order_items_product_variant_id_index (product_variant_id)
);

/*
 * IMPORT_RECEIPTS table: Purchase receipts from suppliers (Prisma: ImportReceipt).
 */
CREATE TABLE import_receipts (
    id int auto_increment primary key,
    tenant_id int not null,
    code varchar(50) not null unique,
    purchase_order_id int null,
    supplier_id int null,
    warehouse_id int not null,
    creator_id int not null,
    total_amount decimal(14, 2) default 0.00,
    paid_amount decimal(14, 2) default 0.00,
    debt_amount decimal(14, 2) default 0.00,
    status enum('DRAFT', 'PENDING', 'APPROVED', 'COMPLETED', 'CANCELLED') default 'DRAFT',
    import_date timestamp default current_timestamp,
    note text,
    parent_id int null,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    foreign key (tenant_id) references tenants(id) on delete cascade,
    foreign key (purchase_order_id) references purchase_orders(id) on delete set null,
    foreign key (supplier_id) references suppliers(id) on delete set null,
    foreign key (warehouse_id) references warehouses(id) on delete cascade,
    foreign key (creator_id) references users(id) on delete cascade,
    foreign key (parent_id) references import_receipts(id) on delete set null,
    index import_receipts_purchase_order_id_index (purchase_order_id),
    index import_receipts_supplier_id_index (supplier_id),
    index import_receipts_warehouse_id_index (warehouse_id),
    index import_receipts_status_index (status),
    index import_receipts_import_date_index (import_date)
);

/*
 * IMPORT_ITEMS table: Line items for import receipts (Prisma: ImportItem).
 */
CREATE TABLE import_items (
    id int auto_increment primary key,
    import_receipt_id int not null,
    product_variant_id int not null,
    unit_name varchar(50) default 'Cái',
    conversion_rate int default 1,
    quantity int not null,
    quantity_in_base int not null,
    import_price decimal(14, 2) not null,
    tax_rate decimal(5, 2) default 0.00,     -- Import tax rate
    tax_amount decimal(14, 2) default 0.00,  -- Import tax per item
    total decimal(14, 2) not null,            -- (import_price * quantity) + tax_amount
    note text null,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    foreign key (import_receipt_id) references import_receipts(id) on delete cascade,
    -- FK: product_variant_id → product_variants(id) ON DELETE CASCADE [DEFERRED]
    index import_items_import_receipt_id_index (import_receipt_id),
    index import_items_product_variant_id_index (product_variant_id)
);

/*
 * IMPORT_RECEIPT_AUDITS table: Audit trail for import receipts (Prisma: ImportReceiptAudit).
 */
CREATE TABLE import_receipt_audits (
    id int auto_increment primary key,
    import_receipt_id int not null,
    action varchar(50) not null,
    before_json json null,
    after_json json null,
    change_note text null,
    field_changed varchar(100) null,
    items_affected int null,
    created_by int not null,
    created_at timestamp default current_timestamp,
    foreign key (import_receipt_id) references import_receipts(id) on delete cascade,
    foreign key (created_by) references users(id) on delete cascade,
    index import_receipt_audits_import_receipt_id_index (import_receipt_id),
    index import_receipt_audits_action_index (action)
);

-- ==========================================
-- SECTION 4c: TRANSFER ORDERS (Inter-Warehouse)
-- ==========================================

/*
 * TRANSFER_ORDERS table: Inter-warehouse stock transfer header.
 * Flow: DRAFT → APPROVED → IN_TRANSIT → RECEIVED → completed.
 * Source warehouse ships, destination warehouse receives and confirms quantities.
 */
CREATE TABLE transfer_orders (
    id int auto_increment primary key,
    tenant_id int not null,
    code varchar(50) not null unique,
    source_warehouse_id int not null,
    destination_warehouse_id int not null,
    status enum('DRAFT', 'APPROVED', 'IN_TRANSIT', 'RECEIVED', 'CANCELLED') default 'DRAFT',
    total_items int default 0,
    note text null,
    approved_by int null,
    approved_at timestamp null,
    shipped_at timestamp null,
    received_at timestamp null,
    created_by int not null,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    deleted_at timestamp null,
    foreign key (tenant_id) references tenants(id) on delete cascade,
    foreign key (source_warehouse_id) references warehouses(id) on delete cascade,
    foreign key (destination_warehouse_id) references warehouses(id) on delete cascade,
    foreign key (approved_by) references users(id) on delete set null,
    foreign key (created_by) references users(id) on delete cascade,
    index transfer_orders_tenant_id_index (tenant_id),
    index transfer_orders_status_index (status),
    index transfer_orders_source_warehouse_id_index (source_warehouse_id),
    index transfer_orders_destination_warehouse_id_index (destination_warehouse_id)
);

/*
 * TRANSFER_ORDER_ITEMS table: Line items for transfer orders.
 * Tracks shipped vs received quantity for discrepancy detection.
 */
CREATE TABLE transfer_order_items (
    id int auto_increment primary key,
    transfer_order_id int not null,
    product_variant_id int not null,
    batch_id int null,                       -- Specific batch being transferred (for FIFO/SPECIFIC)
    quantity int not null,                   -- Quantity shipped
    received_quantity int default 0,         -- Quantity confirmed at destination
    note text null,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    foreign key (transfer_order_id) references transfer_orders(id) on delete cascade,
    -- FK: product_variant_id → product_variants(id) ON DELETE CASCADE [DEFERRED]
    foreign key (batch_id) references stock_batches(id) on delete set null,
    index transfer_order_items_transfer_order_id_index (transfer_order_id),
    index transfer_order_items_product_variant_id_index (product_variant_id)
);

-- ==========================================
-- SECTION 3c: MASTER DATA - Categories & Products
-- ==========================================

/*
 * CATEGORIES table: Hierarchical product categories (Prisma: Category).
 * Added: code, is_active, proper self-referencing FK.
 */
CREATE TABLE categories (
    id int auto_increment primary key,
    tenant_id int not null,
    code varchar(50) null,
    name varchar(255) not null,
    description text,
    parent_id int null,
    is_active boolean default true,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    deleted_at timestamp null,
    foreign key (tenant_id) references tenants(id) on delete cascade,
    foreign key (parent_id) references categories(id) on delete restrict,
    unique key uk_tenant_category_code (tenant_id, code),
    index categories_tenant_id_index (tenant_id),
    index categories_parent_id_index (parent_id),
    index categories_is_active_index (is_active)
);

create table category_images (
    id int auto_increment primary key,
    category_id int not null,
    image_path varchar(255) not null,
    is_default boolean default false,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    foreign key (category_id) references categories(id) on delete cascade,
    index category_images_category_id_index (category_id)
);

/*
 * PRODUCTS table: Core product definition (Prisma: Product).
 * 3NF normalized: pricing lives on product_variants, stock thresholds on product_storage,
 * location mapping on product_storage/stocks. Only product-identity columns remain here.
 */
CREATE TABLE products (
    id int auto_increment primary key,
    tenant_id int not null,
    category_id int null,
    barcode varchar(255) null unique,
    sku varchar(100) null unique,
    name varchar(255) not null,
    description text,
    base_unit varchar(50) default 'Cái',
    origin varchar(100) null,
    brand varchar(100) null,
    tax_config_id int null,                  -- Default tax rate for this product (e.g. VAT 10%, VAT 5%, exempt)
    is_active boolean default true,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    deleted_at timestamp null,
    foreign key (tenant_id) references tenants(id) on delete cascade,
    foreign key (category_id) references categories(id) on delete set null,
    foreign key (tax_config_id) references tax_configs(id) on delete set null,
    unique key uk_tenant_product_barcode (tenant_id, barcode),
    unique key uk_tenant_product_sku (tenant_id, sku),
    index products_tenant_id_index (tenant_id),
    index products_category_id_index (category_id),
    index products_name_index (name),
    index products_is_active_index (is_active)
);

/*
 * STORAGE_LOCATIONS table: Named storage zones (Prisma: StorageLocation).
 */
CREATE TABLE storage_locations (
    id int auto_increment primary key,
    tenant_id int not null,
    code varchar(50) not null,
    name varchar(255) not null,
    zone varchar(100) null,
    description text null,
    is_active boolean default true,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    foreign key (tenant_id) references tenants(id) on delete cascade,
    unique key uk_tenant_storage_code (tenant_id, code)
);

/*
 * PRODUCT_VARIANTS table: SKU-level variants (Prisma equivalent spread across Product + ProductUnit).
 */
CREATE TABLE product_variants (
    id int auto_increment primary key,
    product_id int not null,
    name varchar(255) not null,
    sku varchar(255) null,
    barcode varchar(255) null,
    base_unit varchar(50) not null,
    price decimal(14, 2) not null,
    discount_price decimal(14, 2) null,
    cost_price decimal(14, 2) default 0.00,
    wholesale_price decimal(14, 2) default 0.00,
    currency varchar(10) not null default 'VND',
    is_active boolean default true,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    deleted_at timestamp null,
    foreign key (product_id) references products(id) on delete cascade,
    index product_variants_product_id_index (product_id),
    index product_variants_sku_index (sku),
    index product_variants_barcode_index (barcode)
);

/*
 * UNIT_PRICE_HISTORY table: Price tracking over time (Prisma: UnitPriceHistory).
 */
CREATE TABLE unit_price_history (
    id int auto_increment primary key,
    product_variant_id int not null,
    product_unit_id int null,
    unit_name varchar(50) not null,
    conversion_rate int not null,
    cost_price decimal(14, 2) not null,
    retail_price decimal(14, 2) not null,
    wholesale_price decimal(14, 2) not null,
    effective_date timestamp default current_timestamp,
    import_receipt_id int null,
    change_reason varchar(50) null,
    changed_by int null,
    created_at timestamp default current_timestamp,
    foreign key (product_variant_id) references product_variants(id) on delete cascade,
    foreign key (import_receipt_id) references import_receipts(id) on delete set null,
    foreign key (changed_by) references users(id) on delete set null,
    index unit_price_history_product_effective_index (product_variant_id, unit_name, effective_date),
    index unit_price_history_effective_date_index (effective_date)
);

create table product_prices (
    id int auto_increment primary key,
    product_variant_id int not null,
    cost_price decimal(14, 2) not null,
    retail_price decimal(14, 2) not null,
    wholesale_price decimal(14, 2) not null,
    uom_id bigint not null,
    currency varchar(10) not null,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    foreign key (uom_id) references uoms(id) on delete cascade,
    foreign key (product_variant_id) references product_variants(id) on delete cascade,
    unique key uk_variant_uom_price (product_variant_id, uom_id),
    index product_prices_variant_id_index (product_variant_id)
);

create table product_units (
    id int auto_increment primary key,
    product_variant_id int not null,
    uom_id bigint not null,
    conversion_factor decimal(14, 6) default 1.000000 not null,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    foreign key (uom_id) references uoms(id) on delete cascade,
    foreign key (product_variant_id) references product_variants(id) on delete cascade,
    unique key uk_variant_uom (product_variant_id, uom_id),
    index product_units_variant_id_index (product_variant_id)
);

create table product_storage (
    id int auto_increment primary key,
    product_variant_id int not null,
    warehouse_location_id int not null,
    stock_level int default 0,
    min_stock_level int default 0,
    max_stock_level int default 0,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    foreign key (product_variant_id) references product_variants(id) on delete cascade,
    foreign key (warehouse_location_id) references warehouse_locations(id) on delete cascade,
    unique key uk_variant_location (product_variant_id, warehouse_location_id),
    index product_storage_variant_id_index (product_variant_id)
);

/*
 * ATTRIBUTES table: Product attribute definitions (Prisma: Attribute).
 * Added: code, unit, data_type, is_active.
 */
CREATE TABLE attributes (
    id int auto_increment primary key,
    tenant_id int not null,
    code varchar(50) not null,
    name varchar(255) not null,
    unit varchar(50) null,
    data_type enum('NUMBER', 'TEXT', 'DATE', 'BOOLEAN') default 'TEXT',
    is_active boolean default true,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    foreign key (tenant_id) references tenants(id) on delete cascade,
    unique key uk_tenant_attribute_code (tenant_id, code)
);

/*
 * PRODUCT_VARIANT_ATTRIBUTES table: Attribute values per variant (Prisma: ProductAttribute).
 * Supports typed values: number, text, date, boolean.
 */
CREATE TABLE product_variant_attributes (
    id int auto_increment primary key,
    product_variant_id int not null,
    attribute_id int not null,
    description text null,
    value_number decimal(14, 4) null,
    value_text varchar(500) null,
    value_date date null,
    value_bool boolean null,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    foreign key (product_variant_id) references product_variants(id) on delete cascade,
    foreign key (attribute_id) references attributes(id) on delete cascade,
    unique key uk_variant_attribute (product_variant_id, attribute_id)
);

create table product_images (
    id int auto_increment primary key,
    product_id int not null,
    image_path varchar(255) not null,
    is_default boolean default false,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    foreign key (product_id) references products(id) on delete cascade,
    index product_images_product_id_index (product_id)
);

create table product_suppliers (
    id int auto_increment primary key,
    product_id int not null,
    supplier_id int not null,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    foreign key (product_id) references products(id) on delete cascade,
    foreign key (supplier_id) references suppliers(id) on delete cascade,
    unique key uk_product_supplier (product_id, supplier_id),
    index product_suppliers_product_id_index (product_id)
);

create table product_variant_suppliers (
    id int auto_increment primary key,
    product_variant_id int not null,
    supplier_id int not null,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    foreign key (product_variant_id) references product_variants(id) on delete cascade,
    foreign key (supplier_id) references suppliers(id) on delete cascade,
    unique key uk_variant_supplier (product_variant_id, supplier_id),
    index product_variant_suppliers_variant_id_index (product_variant_id)
);

create table product_category_suppliers (
    id int auto_increment primary key,
    category_id int not null,
    supplier_id int not null,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    foreign key (category_id) references categories(id) on delete cascade,
    foreign key (supplier_id) references suppliers(id) on delete cascade,
    unique key uk_category_supplier (category_id, supplier_id),
    index product_category_suppliers_category_id_index (category_id)
);

-- ==========================================
-- SECTION 7: MARKETING & PRICING
-- ==========================================

/*
 * SURCHARGE_CATALOG table: Reusable surcharge definitions (Prisma: SurchargeCatalog).
 * Renamed from 'surchagers' (typo fix).
 */
CREATE TABLE surcharge_catalog (
    id int auto_increment primary key,
    tenant_id int not null,
    code varchar(50) not null,
    name varchar(255) not null,
    description text null,
    type enum('PERCENT', 'FIXED_AMOUNT') not null,
    value decimal(14, 2) not null,
    is_auto_apply boolean default false,
    is_refundable boolean default true,
    is_active boolean default true,
    category varchar(50) null,
    created_by int not null,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    deleted_at timestamp null,
    foreign key (tenant_id) references tenants(id) on delete cascade,
    foreign key (created_by) references users(id) on delete cascade,
    unique key uk_tenant_surcharge_code (tenant_id, code),
    index surcharge_catalog_is_active_index (is_active),
    index surcharge_catalog_category_index (category)
);

/*
 * PROMOTIONS table: Enhanced with promotion types from Prisma.
 */
CREATE TABLE promotions (
    id int auto_increment primary key,
    tenant_id int not null,
    code varchar(50) not null,
    name varchar(255) not null,
    description text,
    type enum('DISCOUNT_ITEM', 'DISCOUNT_ORDER', 'BUY_X_GET_Y', 'FREE_SHIPPING', 'GIFT') not null,
    discount_type enum('PERCENT', 'FIXED_AMOUNT') not null,
    value decimal(14, 2) not null,
    min_order_value decimal(14, 2) null,
    max_discount decimal(14, 2) null,
    start_date datetime not null,
    end_date datetime not null,
    usage_limit int null,
    used_count int default 0,
    target_customer_type varchar(50) null,
    is_active boolean default true,
    created_by int not null,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    deleted_at timestamp null,
    foreign key (tenant_id) references tenants(id) on delete cascade,
    foreign key (created_by) references users(id) on delete cascade,
    unique key uk_tenant_promotion_code (tenant_id, code),
    index promotions_is_active_index (is_active),
    index promotions_start_date_index (start_date),
    index promotions_end_date_index (end_date)
);

/*
 * PROMOTION_TARGETS table: Link promotions to products/categories (Prisma: PromotionTarget).
 */
CREATE TABLE promotion_targets (
    id int auto_increment primary key,
    promotion_id int not null,
    product_variant_id int null,
    category_id int null,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    foreign key (promotion_id) references promotions(id) on delete cascade,
    foreign key (product_variant_id) references product_variants(id) on delete cascade,
    foreign key (category_id) references categories(id) on delete cascade,
    index promotion_targets_promotion_id_index (promotion_id)
);

-- ==========================================
-- SECTION 5a: POS SHIFT MANAGEMENT
-- ==========================================

/*
 * POS_SHIFTS table: Cashier shift tracking for POS operations.
 * Each shift captures opening/closing cash, sales totals, and discrepancies.
 * Flow: OPEN → (sales happen) → CLOSED. SUSPENDED for break/handover.
 * Orders created during a shift should reference the active shift_id.
 */
CREATE TABLE pos_shifts (
    id int auto_increment primary key,
    tenant_id int not null,
    warehouse_id int not null,
    user_id int not null,
    status enum('OPEN', 'SUSPENDED', 'CLOSED') default 'OPEN',
    
    -- Cash tracking
    opening_cash decimal(14, 2) default 0.00,
    closing_cash decimal(14, 2) null,
    expected_cash decimal(14, 2) null,       -- Calculated: opening + cash_sales - cash_refunds
    cash_difference decimal(14, 2) null,     -- closing_cash - expected_cash
    
    -- Shift totals (aggregated on close)
    total_sales decimal(14, 2) default 0.00,
    total_returns decimal(14, 2) default 0.00,
    total_transactions int default 0,
    
    opened_at timestamp default current_timestamp,
    closed_at timestamp null,
    note text null,
    closed_by int null,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    foreign key (tenant_id) references tenants(id) on delete cascade,
    foreign key (warehouse_id) references warehouses(id) on delete cascade,
    foreign key (user_id) references users(id) on delete cascade,
    foreign key (closed_by) references users(id) on delete set null,
    index pos_shifts_tenant_id_index (tenant_id),
    index pos_shifts_warehouse_id_index (warehouse_id),
    index pos_shifts_user_id_index (user_id),
    index pos_shifts_status_index (status),
    index pos_shifts_opened_at_index (opened_at)
);

-- ==========================================
-- SECTION 5: SALES & POS - Orders
-- ==========================================

/*
 * ORDERS table: Sales orders with revision chain, lock mechanism (Prisma: Order).
 */
CREATE TABLE orders (
    id int auto_increment primary key,
    tenant_id int not null,
    code varchar(50) not null unique,
    
    -- Order Revision Chain (ERP standard)
    parent_order_id int null,
    revision_number int default 0,
    revision_reason varchar(50) null,
    
    -- Invoice link (1-1 after COMPLETED)
    invoice_id int null unique,              -- FK → invoices(id) ON DELETE SET NULL [DEFERRED]
    
    customer_id int null,
    user_id int not null,
    warehouse_id int not null,
    
    type enum('RETAIL', 'WHOLESALE', 'ONLINE', 'RETURN') default 'RETAIL',
    status enum('PREORDER', 'DRAFT', 'CONFIRMED', 'PROCESSING', 'COMPLETED', 'CANCELLED') default 'DRAFT',
    
    -- Lock mechanism
    is_locked boolean default false,
    locked_at timestamp null,
    locked_by int null,
    locked_reason varchar(50) null,
    
    -- Financials
    total_amount decimal(14, 2) default 0.00,
    discount decimal(14, 2) default 0.00,
    surcharge decimal(14, 2) default 0.00,
    final_amount decimal(14, 2) default 0.00,
    paid_amount decimal(14, 2) default 0.00,
    debt_amount decimal(14, 2) default 0.00,
    
    promotion_id int null,
    promo_discount decimal(14, 2) default 0.00,
    
    note text,
    order_date timestamp default current_timestamp,
    completed_at timestamp null,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    deleted_at timestamp null,
    
    foreign key (tenant_id) references tenants(id) on delete cascade,
    -- FK: customer_id → customers(id) ON DELETE SET NULL [DEFERRED]
    foreign key (user_id) references users(id) on delete cascade,
    foreign key (warehouse_id) references warehouses(id) on delete cascade,
    foreign key (promotion_id) references promotions(id) on delete set null,
    foreign key (parent_order_id) references orders(id) on delete set null,
    foreign key (locked_by) references users(id) on delete set null,
    
    index orders_tenant_id_index (tenant_id),
    index orders_customer_id_index (customer_id),
    index orders_user_id_index (user_id),
    index orders_warehouse_id_index (warehouse_id),
    index orders_type_index (type),
    index orders_status_index (status),
    index orders_order_date_index (order_date),
    index orders_is_locked_index (is_locked),
    index orders_parent_order_id_index (parent_order_id)
);

/*
 * ORDER_ITEMS table: Line items for orders (Prisma: OrderItem).
 */
CREATE TABLE order_items (
    id int auto_increment primary key,
    order_id int not null,
    product_variant_id int not null,
    batch_id int null,                      -- Which stock batch this sale comes from (for FIFO/SPECIFIC)
    unit_name varchar(50) default 'Cái',
    conversion_rate int default 1,
    quantity int not null,
    returned_quantity int default 0,
    price decimal(14, 2) not null,
    cost_price decimal(14, 2) default 0.00,  -- Actual cost from the batch at time of sale
    discount decimal(14, 2) default 0.00,
    promo_discount decimal(14, 2) default 0.00,
    tax_rate decimal(5, 2) default 0.00,     -- Tax rate snapshot at time of sale
    tax_amount decimal(14, 2) default 0.00,  -- Calculated tax per item
    total decimal(14, 2) not null,
    promotion_id int null,
    note text,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    foreign key (order_id) references orders(id) on delete cascade,
    foreign key (product_variant_id) references product_variants(id) on delete cascade,
    foreign key (batch_id) references stock_batches(id) on delete set null,
    foreign key (promotion_id) references promotions(id) on delete set null,
    index order_items_order_id_index (order_id),
    index order_items_product_variant_id_index (product_variant_id),
    index order_items_batch_id_index (batch_id)
);

/*
 * ORDER_SURCHARGES table: Per-order surcharge entries (Prisma: OrderSurcharge).
 */
CREATE TABLE order_surcharges (
    id int auto_increment primary key,
    order_id int not null,
    name varchar(255) not null,
    type enum('PERCENT', 'FIXED_AMOUNT') not null,
    value decimal(14, 2) not null,
    amount decimal(14, 2) not null,
    is_refundable boolean default true,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    foreign key (order_id) references orders(id) on delete cascade,
    index order_surcharges_order_id_index (order_id)
);

/*
 * ORDER_AUDITS table: Full audit trail for orders (Prisma: OrderAudit).
 */
CREATE TABLE order_audits (
    id int auto_increment primary key,
    order_id int not null,
    action varchar(50) not null,
    before_json json null,
    after_json json null,
    change_note text null,
    created_by int null,
    created_at timestamp default current_timestamp,
    foreign key (order_id) references orders(id) on delete cascade,
    foreign key (created_by) references users(id) on delete set null,
    index order_audits_order_id_index (order_id),
    index order_audits_action_index (action),
    index order_audits_created_at_index (created_at)
);

-- ==========================================
-- SECTION 8: FINANCE & ACCOUNTING - Invoices
-- ==========================================

/*
 * INVOICES table: Tax invoice / sales receipt (Prisma: Invoice).
 * With version chain: rootInvoice → parentInvoice → revisions.
 */
CREATE TABLE invoices (
    id int auto_increment primary key,
    tenant_id int not null,
    code varchar(50) not null unique,
    order_id int null unique,
    root_invoice_id int null,
    parent_invoice_id int null,
    version int default 0,
    customer_id int not null,
    
    -- Amounts
    subtotal decimal(14, 2) not null,       -- Sum of line items before tax
    tax_config_id int null,                  -- Default tax applied
    tax_rate decimal(5, 2) default 0.00,     -- Snapshot of tax rate at time of invoice
    tax_amount decimal(14, 2) default 0.00,  -- Calculated tax amount
    total_amount decimal(14, 2) not null,    -- subtotal + tax_amount (final)
    
    status enum('ISSUED', 'REPLACED', 'CANCELLED') default 'ISSUED',
    change_reason text null,
    sale_at timestamp not null,
    created_by int not null,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    deleted_at timestamp null,
    foreign key (tenant_id) references tenants(id) on delete cascade,
    foreign key (order_id) references orders(id) on delete set null,
    -- FK: customer_id → customers(id) ON DELETE CASCADE [DEFERRED]
    foreign key (root_invoice_id) references invoices(id) on delete restrict,
    foreign key (parent_invoice_id) references invoices(id) on delete restrict,
    foreign key (created_by) references users(id) on delete cascade,
    index invoices_tenant_id_index (tenant_id),
    index invoices_order_id_index (order_id),
    index invoices_customer_id_index (customer_id),
    index invoices_status_index (status),
    index invoices_sale_at_index (sale_at)
);

/*
 * INVOICE_ITEMS table: Line items per invoice (Prisma: InvoiceItem).
 */
CREATE TABLE invoice_items (
    id int auto_increment primary key,
    invoice_id int not null,
    product_variant_id int not null,
    product_name varchar(255) not null,
    unit_name varchar(50) default 'Cái',
    quantity decimal(14, 2) not null,
    price decimal(14, 2) not null,
    discount decimal(14, 2) default 0.00,
    tax_rate decimal(5, 2) default 0.00,     -- Per-item tax rate snapshot
    tax_amount decimal(14, 2) default 0.00,  -- Per-item tax amount
    total decimal(14, 2) not null,            -- (price * quantity - discount) + tax_amount
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    foreign key (invoice_id) references invoices(id) on delete cascade,
    foreign key (product_variant_id) references product_variants(id) on delete cascade,
    index invoice_items_invoice_id_index (invoice_id)
);

/*
 * INVOICE_AUDITS table: Audit trail for invoices (Prisma: InvoiceAudit).
 */
CREATE TABLE invoice_audits (
    id int auto_increment primary key,
    invoice_id int not null,
    action varchar(50) not null,
    before_json json null,
    after_json json null,
    created_by int not null,
    created_at timestamp default current_timestamp,
    foreign key (invoice_id) references invoices(id) on delete cascade,
    foreign key (created_by) references users(id) on delete set null,
    index invoice_audits_invoice_id_index (invoice_id),
    index invoice_audits_created_at_index (created_at)
);

-- ==========================================
-- SECTION 6: RETURNS
-- ==========================================

/*
 * RETURN_ORDERS table: Return order header (Prisma: ReturnOrder).
 */
CREATE TABLE return_orders (
    id int auto_increment primary key,
    tenant_id int not null,
    code varchar(50) not null unique,
    order_id int not null,
    customer_id int null,
    warehouse_id int null,
    type enum('FULL', 'PARTIAL') default 'PARTIAL',
    status enum('PENDING', 'APPROVED', 'REJECTED', 'COMPLETED') default 'PENDING',
    total_amount decimal(14, 2) default 0.00,
    refund_method enum('CASH', 'BANK_TRANSFER', 'STORE_CREDIT') null,
    reason text null,
    note text null,
    return_date timestamp default current_timestamp,
    approved_by int null,
    approved_at timestamp null,
    created_by int not null,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    foreign key (tenant_id) references tenants(id) on delete cascade,
    foreign key (order_id) references orders(id) on delete cascade,
    -- FK: customer_id → customers(id) ON DELETE SET NULL [DEFERRED]
    foreign key (warehouse_id) references warehouses(id) on delete set null,
    foreign key (approved_by) references users(id) on delete set null,
    foreign key (created_by) references users(id) on delete cascade,
    index return_orders_order_id_index (order_id),
    index return_orders_customer_id_index (customer_id),
    index return_orders_status_index (status),
    index return_orders_return_date_index (return_date)
);

/*
 * RETURN_ORDER_ITEMS table: Line items for returns (Prisma: ReturnOrderItem).
 */
CREATE TABLE return_order_items (
    id int auto_increment primary key,
    return_order_id int not null,
    product_variant_id int not null,
    order_item_id int null,
    quantity int not null,
    price decimal(14, 2) not null,
    refund_amount decimal(14, 2) not null,
    `condition` varchar(50) null,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    foreign key (return_order_id) references return_orders(id) on delete cascade,
    foreign key (product_variant_id) references product_variants(id) on delete cascade,
    foreign key (order_item_id) references order_items(id) on delete set null,
    index return_order_items_return_order_id_index (return_order_id)
);

/*
 * RETURN_ORDER_AUDITS table: Audit trail for returns (Prisma: ReturnOrderAudit).
 */
CREATE TABLE return_order_audits (
    id int auto_increment primary key,
    return_order_id int not null,
    action varchar(50) not null,
    before_json json null,
    after_json json null,
    change_note text null,
    created_by int not null,
    created_at timestamp default current_timestamp,
    foreign key (return_order_id) references return_orders(id) on delete cascade,
    foreign key (created_by) references users(id) on delete cascade,
    index return_order_audits_return_order_id_index (return_order_id)
);

/*
 * CREDIT_NOTES table: Credit memos for returns/refunds (Prisma: CreditNote).
 */
CREATE TABLE credit_notes (
    id int auto_increment primary key,
    tenant_id int not null,
    code varchar(50) not null unique,
    invoice_id int not null,
    order_id int null,
    return_order_id int null,
    type enum('RETURN', 'REFUND', 'DISCOUNT', 'ERROR') not null,
    amount decimal(14, 2) not null,
    reason text null,
    note text null,
    created_by int not null,
    created_at timestamp default current_timestamp,
    foreign key (tenant_id) references tenants(id) on delete cascade,
    foreign key (invoice_id) references invoices(id) on delete cascade,
    foreign key (order_id) references orders(id) on delete set null,
    foreign key (return_order_id) references return_orders(id) on delete set null,
    foreign key (created_by) references users(id) on delete cascade,
    index credit_notes_invoice_id_index (invoice_id),
    index credit_notes_return_order_id_index (return_order_id),
    index credit_notes_type_index (type)
);

/*
 * CREDIT_NOTE_ITEMS table: Line items for credit notes (Prisma: CreditNoteItem).
 */
CREATE TABLE credit_note_items (
    id int auto_increment primary key,
    credit_note_id int not null,
    product_variant_id int not null,
    quantity int not null,
    price decimal(14, 2) not null,
    total decimal(14, 2) not null,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    foreign key (credit_note_id) references credit_notes(id) on delete cascade,
    foreign key (product_variant_id) references product_variants(id) on delete cascade,
    index credit_note_items_credit_note_id_index (credit_note_id)
);

/*
 * ACCOUNT_RECEIVABLES table: Customer debt ledger.
 * Double-entry style: positive amount = customer owes us, negative = we owe customer.
 * Each row is an immutable transaction. Current balance = SUM(amount) per customer.
 *
 * Flow:
 *   Order completed, partial pay  → +500,000 (INVOICE, remaining debt)
 *   Customer pays 200,000         → -200,000 (PAYMENT)
 *   Customer returns goods        → -150,000 (CREDIT_NOTE)
 *   Balance = 500k - 200k - 150k  = 150,000 still owed
 */
CREATE TABLE account_receivables (
    id int auto_increment primary key,
    tenant_id int not null,
    customer_id int not null,
    
    -- Amount: positive = customer owes, negative = we owe/refund
    amount decimal(14, 2) not null,
    balance_after decimal(14, 2) not null,   -- Running balance snapshot after this transaction
    
    type enum('INVOICE', 'PAYMENT', 'CREDIT_NOTE', 'DEBIT_NOTE', 'ADJUST', 'WRITE_OFF') not null,
    status enum('OPEN', 'PARTIAL', 'PAID', 'OVERDUE', 'WRITTEN_OFF') default 'OPEN',
    
    -- Source document links (exactly one should be set per type)
    order_id int null,                       -- Link to order that generated debt
    invoice_id int null,                     -- Link to invoice
    payment_id int null,                     -- Link to payment that reduced debt
    credit_note_id int null,                 -- Link to credit note (return/refund)
    
    -- Payment terms
    due_date date null,                      -- When this debt is due
    paid_at timestamp null,                  -- When fully paid (status → PAID)
    
    note text null,
    created_by int not null,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    foreign key (tenant_id) references tenants(id) on delete cascade,
    -- FK: customer_id → customers(id) ON DELETE CASCADE [DEFERRED]
    foreign key (order_id) references orders(id) on delete set null,
    foreign key (invoice_id) references invoices(id) on delete set null,
    -- FK: payment_id → payments(id) ON DELETE SET NULL [DEFERRED]
    foreign key (credit_note_id) references credit_notes(id) on delete set null,
    foreign key (created_by) references users(id) on delete cascade,
    index account_receivables_tenant_id_index (tenant_id),
    index account_receivables_customer_id_index (customer_id),
    index account_receivables_type_index (type),
    index account_receivables_status_index (status),
    index account_receivables_due_date_index (due_date),
    index account_receivables_created_at_index (created_at)
);

/*
 * ACCOUNT_PAYABLES table: Supplier debt ledger (mirror of account_receivables).
 * Positive amount = we owe supplier, negative = supplier owes us / refund.
 */
CREATE TABLE account_payables (
    id int auto_increment primary key,
    tenant_id int not null,
    supplier_id int not null,
    
    amount decimal(14, 2) not null,
    balance_after decimal(14, 2) not null,
    
    type enum('IMPORT', 'PAYMENT', 'DEBIT_NOTE', 'CREDIT_NOTE', 'ADJUST', 'WRITE_OFF') not null,
    status enum('OPEN', 'PARTIAL', 'PAID', 'OVERDUE', 'WRITTEN_OFF') default 'OPEN',
    
    import_receipt_id int null,
    payment_id int null,                     -- FK → payments(id) ON DELETE SET NULL [DEFERRED]
    
    due_date date null,
    paid_at timestamp null,
    
    note text null,
    created_by int not null,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    foreign key (tenant_id) references tenants(id) on delete cascade,
    foreign key (supplier_id) references suppliers(id) on delete cascade,
    foreign key (import_receipt_id) references import_receipts(id) on delete set null,
    foreign key (created_by) references users(id) on delete cascade,
    index account_payables_tenant_id_index (tenant_id),
    index account_payables_supplier_id_index (supplier_id),
    index account_payables_type_index (type),
    index account_payables_status_index (status),
    index account_payables_due_date_index (due_date),
    index account_payables_created_at_index (created_at)
);

-- ==========================================
-- SECTION 7b: PRICING - Price Lists
-- ==========================================

/*
 * PRICE_LISTS table: Named price lists with priority (Prisma: PriceList).
 */
CREATE TABLE price_lists (
    id int auto_increment primary key,
    tenant_id int not null,
    code varchar(50) not null,
    name varchar(255) not null,
    is_default boolean default false,
    is_active boolean default true,
    priority int default 0,
    description text null,
    start_date date null,
    end_date date null,
    created_by int null,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    deleted_at timestamp null,
    foreign key (created_by) references users(id) on delete set null,
    foreign key (tenant_id) references tenants(id) on delete cascade,
    unique key uk_tenant_pricelist_code (tenant_id, code),
    index price_lists_is_active_index (is_active),
    index price_lists_priority_index (priority)
);

/*
 * PRICE_LIST_ITEMS table: Product prices per price list (Prisma: PriceListItem).
 */
CREATE TABLE price_list_items (
    id int auto_increment primary key,
    price_list_id int not null,
    product_variant_id int not null,
    unit_name varchar(50) default 'Cái',
    price decimal(14, 2) not null,
    min_quantity int default 1,
    foreign key (price_list_id) references price_lists(id) on delete cascade,
    foreign key (product_variant_id) references product_variants(id) on delete cascade,
    unique key uk_pricelist_product_unit_qty (price_list_id, product_variant_id, unit_name, min_quantity),
    index price_list_items_price_list_id_index (price_list_id)
);

-- ==========================================
-- SECTION 3d: MASTER DATA - Customers
-- ==========================================

/*
 * CUSTOMERS table: Enhanced with types, debt tracking (Prisma: Customer).
 */
CREATE TABLE customers (
    id int auto_increment primary key,
    tenant_id int not null,
    code varchar(50) null,
    name varchar(255) not null,
    email varchar(255) null,
    phone varchar(20) null,
    address text null,
    type varchar(50) not null,
    tax_code varchar(50) null,
    company_name varchar(255) null,
    debt_amount decimal(14, 2) default 0.00,
    total_spent decimal(14, 2) default 0.00,
    credit_limit decimal(14, 2) default 0.00,
    default_due_days int default 0,              -- Default payment terms: 0 = pay now, 30/60/90 = days until due
    description text null,
    group_id int null,
    price_list_id int null,
    is_active boolean default true,
    created_by int null,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    deleted_at timestamp null,
    foreign key (created_by) references users(id) on delete set null,
    foreign key (tenant_id) references tenants(id) on delete cascade,
    foreign key (price_list_id) references price_lists(id) on delete set null,
    unique key uk_tenant_customer_code (tenant_id, code),
    unique key uk_tenant_customer_phone (tenant_id, phone),
    index customers_tenant_id_index (tenant_id),
    index customers_type_index (type),
    index customers_is_active_index (is_active),
    index customers_name_index (name)
);

/*
 * CUSTOMER_GROUPS table: Customer segmentation (Prisma: CustomerGroup).
 */
CREATE TABLE customer_groups (
    id int auto_increment primary key,
    tenant_id int not null,
    code varchar(50) not null,
    name varchar(255) not null,
    description text null,
    default_price_list_id int null,
    is_active boolean default true,
    created_by int null,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    deleted_at timestamp null,
    foreign key (created_by) references users(id) on delete set null,
    foreign key (tenant_id) references tenants(id) on delete cascade,
    foreign key (default_price_list_id) references price_lists(id) on delete set null,
    unique key uk_tenant_cust_group_code (tenant_id, code),
    index customer_groups_tenant_id_index (tenant_id)
);

-- FK for customers.group_id is in SECTION 12: DEFERRED FOREIGN KEYS

/*
 * CUSTOMER_AUDITS table: Customer change tracking (Prisma: CustomerAudit).
 */
CREATE TABLE customer_audits (
    id int auto_increment primary key,
    customer_id int not null,
    action varchar(50) not null,
    before_json json null,
    after_json json null,
    change_note text null,
    field_changed varchar(100) null,
    created_by int not null,
    created_at timestamp default current_timestamp,
    foreign key (customer_id) references customers(id) on delete cascade,
    foreign key (created_by) references users(id) on delete cascade,
    index customer_audits_customer_id_index (customer_id),
    index customer_audits_action_index (action),
    index customer_audits_created_at_index (created_at)
);

-- ==========================================
-- SECTION 5b: SALES - Payments
-- ==========================================

/*
 * PAYMENT_METHOD_CONFIGS table: Payment method setup (Prisma: PaymentMethodConfig).
 */
CREATE TABLE payment_method_configs (
    id int auto_increment primary key,
    tenant_id int not null,
    code varchar(50) not null,
    name varchar(255) not null,
    type varchar(50) null,
    description text null,
    is_active boolean default true,
    is_default boolean default false,
    sort_order int default 0,
    config json null,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    foreign key (tenant_id) references tenants(id) on delete cascade,
    unique key uk_tenant_payment_code (tenant_id, code),
    index payment_method_configs_is_active_index (is_active)
);

/*
 * PAYMENTS table: Payment transactions (Prisma: Payment).
 */
CREATE TABLE payments (
    id int auto_increment primary key,
    tenant_id int not null,
    code varchar(50) not null unique,
    order_id int null,
    customer_id int null,
    amount decimal(14, 2) not null,
    method enum('CASH', 'BANK_TRANSFER', 'COD', 'DEBT', 'OTHER') not null,
    status enum('PENDING', 'PARTIAL', 'COMPLETED', 'REFUNDED', 'CANCELLED') default 'PENDING',
    reference_code varchar(255) null,
    bank_name varchar(100) null,
    account_number varchar(50) null,
    note text null,
    paid_at timestamp null,
    created_by int not null,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    foreign key (tenant_id) references tenants(id) on delete cascade,
    foreign key (order_id) references orders(id) on delete set null,
    foreign key (customer_id) references customers(id) on delete set null,
    foreign key (created_by) references users(id) on delete cascade,
    index payments_order_id_index (order_id),
    index payments_customer_id_index (customer_id),
    index payments_method_index (method),
    index payments_status_index (status),
    index payments_paid_at_index (paid_at)
);

/*
 * PAYMENT_AUDITS table: Audit trail for payments (Prisma: PaymentAudit).
 */
CREATE TABLE payment_audits (
    id int auto_increment primary key,
    payment_id int not null,
    action varchar(50) not null,
    before_json json null,
    after_json json null,
    change_note text null,
    created_by int null,
    created_at timestamp default current_timestamp,
    foreign key (payment_id) references payments(id) on delete cascade,
    foreign key (created_by) references users(id) on delete set null,
    index payment_audits_payment_id_index (payment_id),
    index payment_audits_created_at_index (created_at)
);
-- ==========================================
-- SECTION 8b: FINANCE - Business Expenses
-- ==========================================

/*
 * BUSINESS_EXPENSES table: Operational costs (Prisma: BusinessExpense).
 */
CREATE TABLE business_expenses (
    id int auto_increment primary key,
    tenant_id int not null,
    code varchar(50) not null unique,
    name varchar(255) not null,
    description text null,
    category varchar(50) not null,
    amount decimal(14, 2) not null,
    order_id int null,
    supplier_id int null,
    payment_method varchar(50) null,
    payment_status enum('PENDING', 'PAID', 'CANCELLED') default 'PENDING',
    paid_at timestamp null,
    created_by int not null,
    approved_by int null,
    approved_at timestamp null,
    expense_date timestamp default current_timestamp,
    note text null,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    foreign key (tenant_id) references tenants(id) on delete cascade,
    foreign key (order_id) references orders(id) on delete set null,
    foreign key (supplier_id) references suppliers(id) on delete set null,
    foreign key (created_by) references users(id) on delete cascade,
    foreign key (approved_by) references users(id) on delete set null,
    index business_expenses_category_index (category),
    index business_expenses_payment_status_index (payment_status),
    index business_expenses_expense_date_index (expense_date)
);

/*
 * BUSINESS_EXPENSE_AUDITS table: Audit trail for expenses (Prisma: BusinessExpenseAudit).
 */
CREATE TABLE business_expense_audits (
    id int auto_increment primary key,
    expense_id int not null,
    action varchar(50) not null,
    before_json json null,
    after_json json null,
    change_note text null,
    created_by int not null,
    created_at timestamp default current_timestamp,
    foreign key (expense_id) references business_expenses(id) on delete cascade,
    foreign key (created_by) references users(id) on delete cascade,
    index business_expense_audits_expense_id_index (expense_id)
);

-- ==========================================
-- SECTION 9: REPORTING
-- ==========================================

/*
 * SALES_SUMMARIES table: Daily aggregate sales data (Prisma: SalesSummary).
 */
CREATE TABLE sales_summaries (
    id int auto_increment primary key,
    tenant_id int not null,
    summary_date date not null,
    total_orders int default 0,
    total_items int default 0,
    gross_revenue decimal(14, 2) default 0.00,
    discount_total decimal(14, 2) default 0.00,
    net_revenue decimal(14, 2) default 0.00,
    cost_total decimal(14, 2) default 0.00,
    profit decimal(14, 2) default 0.00,
    retail_revenue decimal(14, 2) default 0.00,
    wholesale_revenue decimal(14, 2) default 0.00,
    created_at timestamp default current_timestamp,
    foreign key (tenant_id) references tenants(id) on delete cascade,
    unique key uk_tenant_summary_date (tenant_id, summary_date),
    index sales_summaries_summary_date_index (summary_date)
);

/*
 * PRODUCT_SALES_SUMMARIES table: Daily per-product sales (Prisma: ProductSalesSummary).
 */
CREATE TABLE product_sales_summaries (
    id int auto_increment primary key,
    tenant_id int not null,
    summary_date date not null,
    product_variant_id int not null,
    quantity int default 0,
    revenue decimal(14, 2) default 0.00,
    cost decimal(14, 2) default 0.00,
    profit decimal(14, 2) default 0.00,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    foreign key (tenant_id) references tenants(id) on delete cascade,
    foreign key (product_variant_id) references product_variants(id) on delete cascade,
    unique key uk_tenant_date_product (tenant_id, summary_date, product_variant_id),
    index product_sales_summaries_summary_date_index (summary_date)
);

/*
 * CATEGORY_SALES_SUMMARIES table: Daily per-category sales (Prisma: CategorySalesSummary).
 */
CREATE TABLE category_sales_summaries (
    id int auto_increment primary key,
    tenant_id int not null,
    summary_date date not null,
    category_id int not null,
    revenue decimal(14, 2) default 0.00,
    profit decimal(14, 2) default 0.00,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    foreign key (tenant_id) references tenants(id) on delete cascade,
    foreign key (category_id) references categories(id) on delete cascade,
    unique key uk_tenant_date_category (tenant_id, summary_date, category_id),
    index category_sales_summaries_summary_date_index (summary_date)
);

-- ==========================================
-- SECTION 10: DATA SYNC (Offline/Online)
-- ==========================================

/*
 * SYNC_LOG table: Track offline→online sync events per tenant.
 * Each device pushes its local changes; server records them here.
 */
CREATE TABLE sync_logs (
    id bigint unsigned auto_increment primary key,
    tenant_id int not null,
    user_id int not null,
    device_id varchar(255) not null,
    sync_type enum('PUSH', 'PULL', 'FULL') not null,
    table_name varchar(100) not null,
    record_count int default 0,
    status enum('PENDING', 'IN_PROGRESS', 'COMPLETED', 'FAILED') default 'PENDING',
    error_message text null,
    started_at timestamp null,
    completed_at timestamp null,
    created_at timestamp default current_timestamp,
    foreign key (tenant_id) references tenants(id) on delete cascade,
    foreign key (user_id) references users(id) on delete cascade,
    index sync_logs_tenant_id_index (tenant_id),
    index sync_logs_device_id_index (device_id),
    index sync_logs_status_index (status),
    index sync_logs_created_at_index (created_at)
);

/*
 * SYNC_CONFLICTS table: Record merge conflicts for manual resolution.
 */
CREATE TABLE sync_conflicts (
    id bigint unsigned auto_increment primary key,
    tenant_id int not null,
    sync_log_id bigint unsigned not null,
    table_name varchar(100) not null,
    record_id int not null,
    local_data json not null,
    server_data json not null,
    resolution enum('LOCAL_WINS', 'SERVER_WINS', 'MANUAL', 'UNRESOLVED') default 'UNRESOLVED',
    resolved_by int null,
    resolved_at timestamp null,
    created_at timestamp default current_timestamp,
    foreign key (tenant_id) references tenants(id) on delete cascade,
    foreign key (sync_log_id) references sync_logs(id) on delete cascade,
    foreign key (resolved_by) references users(id) on delete set null,
    index sync_conflicts_tenant_id_index (tenant_id),
    index sync_conflicts_resolution_index (resolution)
);

-- ==========================================
-- SECTION 11: TENANT CONFIGURATION
-- ==========================================

/*
 * DOCUMENT_TEMPLATES table: Customizable print templates per tenant.
 * Supports: invoices, orders, import receipts, return orders, stock take reports, etc.
 * Each tenant can override the system default with their own Handlebars/HTML template.
 */
CREATE TABLE document_templates (
    id int auto_increment primary key,
    tenant_id int not null,
    code varchar(50) not null,            -- 'INVOICE', 'ORDER', 'IMPORT_RECEIPT', 'RETURN_ORDER', 'STOCK_TAKE', 'CREDIT_NOTE'
    name varchar(255) not null,            -- 'Hóa đơn bán hàng A5', 'Đơn hàng bán sỉ'
    description text null,
    
    -- Template content (Handlebars / HTML)
    body_html longtext not null,           -- The actual template markup
    css text null,                         -- Optional custom CSS
    
    -- Page setup
    paper_size varchar(20) default 'A4',   -- 'A4', 'A5', '80mm', '58mm', 'CUSTOM'
    orientation enum('PORTRAIT', 'LANDSCAPE') default 'PORTRAIT',
    margin_top decimal(5,2) default 10.00, -- mm
    margin_bottom decimal(5,2) default 10.00,
    margin_left decimal(5,2) default 10.00,
    margin_right decimal(5,2) default 10.00,
    
    is_default boolean default false,      -- Default template for this document type
    is_active boolean default true,
    sort_order int default 0,
    
    created_by int not null,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    foreign key (tenant_id) references tenants(id) on delete cascade,
    foreign key (created_by) references users(id) on delete cascade,
    unique key uk_tenant_template_code (tenant_id, code),
    index document_templates_tenant_id_index (tenant_id),
    index document_templates_is_active_index (is_active)
);

/*
 * PRINTER_CONFIGS table: Printer hardware configuration per tenant/station.
 * Maps a logical print job (e.g. "receipt") to a physical printer + template.
 */
CREATE TABLE printer_configs (
    id int auto_increment primary key,
    tenant_id int not null,
    name varchar(255) not null,             -- 'Máy in quầy 1', 'Máy in kho'
    printer_name varchar(255) not null,     -- OS printer name or IP (e.g. 'EPSON TM-T82', '192.168.1.100')
    printer_type enum('THERMAL', 'LASER', 'INKJET', 'DOT_MATRIX') default 'THERMAL',
    connection_type enum('USB', 'NETWORK', 'BLUETOOTH', 'SERIAL') default 'USB',
    ip_address varchar(45) null,
    port int null,
    
    -- Which document types this printer handles
    document_type varchar(50) not null,     -- 'INVOICE', 'ORDER', 'IMPORT_RECEIPT', 'BARCODE_LABEL', 'STOCK_TAKE'
    template_id int null,                   -- Link to specific template (overrides default)
    
    paper_width int null,                   -- mm (e.g. 80, 58 for thermal)
    auto_cut boolean default true,
    copies int default 1,
    
    is_default boolean default false,
    is_active boolean default true,
    
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    foreign key (tenant_id) references tenants(id) on delete cascade,
    foreign key (template_id) references document_templates(id) on delete set null,
    index printer_configs_tenant_id_index (tenant_id),
    index printer_configs_document_type_index (document_type)
);

/*
 * CODE_SEQUENCE_CONFIGS table: Customizable auto-increment code prefixes per tenant.
 * Each tenant can configure their own code format for orders, invoices, etc.
 * Example: system default "ORD-20260311-0001" → tenant customizes to "DH-0001-20260311"
 */
CREATE TABLE code_sequence_configs (
    id int auto_increment primary key,
    tenant_id int not null,
    entity_type varchar(50) not null,       -- 'ORDER', 'INVOICE', 'IMPORT_RECEIPT', 'RETURN_ORDER', 'PAYMENT', 'CREDIT_NOTE', 'STOCK_TAKE', 'PRODUCT'
    
    prefix varchar(20) not null,            -- 'DH', 'HD', 'PN', 'TH', 'TT', 'CN', 'KK', 'SP'
    separator varchar(5) default '-',       -- '-', '/', '.', ''
    
    -- Sequence format components (assembled as: prefix + separator + date_part + separator + sequence)
    date_format varchar(20) null,           -- 'YYYYMMDD', 'YYYYMM', 'YY', null (no date)
    sequence_length int default 4,          -- zero-padded digits: 4 → 0001, 6 → 000001
    reset_period enum('DAILY', 'MONTHLY', 'YEARLY', 'NEVER') default 'DAILY',
    
    -- Current counter (auto-incremented by application)
    current_value int default 0,
    last_reset_at date null,
    
    -- Preview: shows tenant what their codes will look like
    -- e.g. "DH-20260311-0001" generated from prefix='DH', date_format='YYYYMMDD', sequence_length=4
    sample_output varchar(100) null,
    
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    foreign key (tenant_id) references tenants(id) on delete cascade,
    unique key uk_tenant_entity_sequence (tenant_id, entity_type),
    index code_sequence_configs_tenant_id_index (tenant_id)
);

-- ==========================================
-- SECTION 11b: API & INTEGRATIONS
-- ==========================================

/*
 * API_KEYS table: Tenant-scoped API key management for external integrations.
 * Key is stored as SHA-256 hash (never plain text). Prefix shown for identification.
 * Permissions stored as JSON array: ["orders:read", "products:write", ...].
 */
CREATE TABLE api_keys (
    id int auto_increment primary key,
    tenant_id int not null,
    name varchar(255) not null,             -- 'Website Integration', 'Mobile App'
    key_prefix varchar(8) not null,         -- First 8 chars of key, for display: 'sk_live_...'
    key_hash varchar(64) not null unique,   -- SHA-256 hash of the full API key
    permissions json null,                  -- ["orders:read", "products:read", "products:write"]
    last_used_at timestamp null,
    expires_at timestamp null,
    is_active boolean default true,
    created_by int not null,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    foreign key (tenant_id) references tenants(id) on delete cascade,
    foreign key (created_by) references users(id) on delete cascade,
    index api_keys_tenant_id_index (tenant_id),
    index api_keys_key_hash_index (key_hash),
    index api_keys_is_active_index (is_active)
);

/*
 * WEBHOOKS table: Outbound webhook subscriptions per tenant.
 * When events occur (order.created, payment.completed, etc.), system POSTs to the URL.
 * Secret is used for HMAC-SHA256 signature verification on the receiving end.
 */
CREATE TABLE webhooks (
    id int auto_increment primary key,
    tenant_id int not null,
    url varchar(2048) not null,
    events json not null,                   -- ["order.created", "payment.completed", "stock.low"]
    secret varchar(64) not null,            -- HMAC signing secret (stored hashed or encrypted)
    is_active boolean default true,
    last_triggered_at timestamp null,
    last_response_code int null,            -- HTTP status of last delivery attempt
    failure_count int default 0,            -- Consecutive failures (auto-disable after threshold)
    created_by int not null,
    created_at timestamp default current_timestamp,
    updated_at timestamp default current_timestamp on update current_timestamp,
    foreign key (tenant_id) references tenants(id) on delete cascade,
    foreign key (created_by) references users(id) on delete cascade,
    index webhooks_tenant_id_index (tenant_id),
    index webhooks_is_active_index (is_active)
);

-- ==========================================
-- SECTION 12: DEFERRED FOREIGN KEYS
-- ==========================================
/*
 * These ALTER TABLE statements add foreign keys that reference tables defined later in the script.
 * They are deferred to avoid forward-reference errors during sequential execution.
 * Pattern: standard approach used by mysqldump, pg_dump, and enterprise schema migrations.
 */

-- stocks → product_variants
ALTER TABLE stocks ADD FOREIGN KEY (product_variant_id) REFERENCES product_variants(id) ON DELETE CASCADE;

-- stock_batches → product_variants, import_items
ALTER TABLE stock_batches ADD FOREIGN KEY (product_variant_id) REFERENCES product_variants(id) ON DELETE CASCADE;
ALTER TABLE stock_batches ADD FOREIGN KEY (import_item_id) REFERENCES import_items(id) ON DELETE SET NULL;

-- stock_movements → product_variants
ALTER TABLE stock_movements ADD FOREIGN KEY (product_variant_id) REFERENCES product_variants(id) ON DELETE CASCADE;

-- stock_take_items → product_variants
ALTER TABLE stock_take_items ADD FOREIGN KEY (product_variant_id) REFERENCES product_variants(id) ON DELETE CASCADE;

-- import_items → product_variants
ALTER TABLE import_items ADD FOREIGN KEY (product_variant_id) REFERENCES product_variants(id) ON DELETE CASCADE;

-- orders → customers
ALTER TABLE orders ADD FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL;

-- invoices → customers
ALTER TABLE invoices ADD FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE;

-- return_orders → customers
ALTER TABLE return_orders ADD FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL;

-- account_receivables → customers, payments
ALTER TABLE account_receivables ADD FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE;
ALTER TABLE account_receivables ADD FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL;

-- customers → customer_groups
ALTER TABLE customers ADD FOREIGN KEY (group_id) REFERENCES customer_groups(id) ON DELETE SET NULL;

-- purchase_order_items → product_variants
ALTER TABLE purchase_order_items ADD FOREIGN KEY (product_variant_id) REFERENCES product_variants(id) ON DELETE CASCADE;

-- transfer_order_items → product_variants
ALTER TABLE transfer_order_items ADD FOREIGN KEY (product_variant_id) REFERENCES product_variants(id) ON DELETE CASCADE;

-- invoices → tax_configs
ALTER TABLE invoices ADD FOREIGN KEY (tax_config_id) REFERENCES tax_configs(id) ON DELETE SET NULL;

-- account_payables → payments
ALTER TABLE account_payables ADD FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE SET NULL;

-- stock_movements → orders, import_items, stock_takes
ALTER TABLE stock_movements ADD FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL;
ALTER TABLE stock_movements ADD FOREIGN KEY (import_item_id) REFERENCES import_items(id) ON DELETE SET NULL;
ALTER TABLE stock_movements ADD FOREIGN KEY (stock_take_id) REFERENCES stock_takes(id) ON DELETE SET NULL;

-- orders → invoices
ALTER TABLE orders ADD FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL;