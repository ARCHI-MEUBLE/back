# ArchiMeuble Backend

## Overview

ArchiMeuble is a custom furniture manufacturing company based in Lille, France. This backend powers their e-commerce platform, allowing customers to:

- Design custom furniture (bookcases, dressings, buffets) using a 3D configurator
- Visualize their furniture in real-time with parametric 3D generation
- Order material samples before purchasing
- Purchase ready-made items from the catalogue
- Order custom facade panels for existing furniture

The platform handles the complete order lifecycle: configuration, quotation, payment (full or deposit), production tracking, and delivery. A separate Next.js frontend (`../front`) consumes this API and expects the exact URLs, status codes and JSON shapes described below.

## Stack

- PHP 8.2, no framework — PSR-4 domain architecture under `src/Domain/<X>/`
- PostgreSQL
- Python 3 (parametric 3D generation via `python/procedure_real.py`)
- Docker (local dev and Railway production)

## Requirements

- Docker and Docker Compose (local development), or PHP 8.2 + PostgreSQL installed natively
- Railway account (production deployment)

## Quick Start

```bash
# Clone and configure
git clone <repository-url>
cd back
cp .env.example .env

# Start Postgres + backend (installs Composer deps, runs migrations, serves on :8000)
docker compose up -d

# Verify
curl http://localhost:8000/health
```

The server is available at `http://localhost:8000`. An Adminer instance is available at `http://localhost:8080` to browse the database.

### Without Docker

```bash
composer install
cp .env.example .env   # point DATABASE_URL at a local PostgreSQL instance
php bin/console migrate
php -S 127.0.0.1:8000 public/index.php
```

## Environment Variables

Copy `.env.example` to `.env` and fill in the values — see that file for the full list and defaults. The essentials:

| Variable | Description |
|----------|--------------|
| `DATABASE_URL` | PostgreSQL connection string (required) |
| `FRONTEND_URL` | Frontend origin, used for CORS and email links |
| `STRIPE_SECRET_KEY` / `STRIPE_PUBLISHABLE_KEY` / `STRIPE_WEBHOOK_SECRET` | Stripe payment processing |
| `RESEND_API_KEY` | Resend transactional email API key |
| `BACKUP_API_KEY` | Shared secret for the confidential admin-creation and database backup endpoints |
| `CRON_SECRET` | Token protecting the scheduled-task HTTP endpoints (Calendly reminders, installments) |
| `CALENDLY_API_TOKEN` / `CALENDLY_PHONE_URL` / `CALENDLY_VISIO_URL` | Calendly integration |
| `CRISP_WEBSITE_ID` | Crisp live chat widget |

## Project Structure

```
back/
├── public/index.php            # Single HTTP entry point
├── bin/console                 # CLI: migrate, migrate:status, cron:installments
├── src/
│   ├── App.php                 # Composition root (settings, DB, routes, middleware)
│   ├── *RouteRegistry.php      # Wires each domain's routes into the router
│   ├── Config/                 # Env parsing, Settings value objects
│   ├── Http/                   # Kernel, Router, Request/Response, middleware, guards
│   ├── Db/                     # Connection, Migrator, versioned migrations/
│   ├── Lib/                    # Logger, Clock, validation
│   ├── Infrastructure/         # Stripe, Mail (Resend), Invoice (PDF), RateLimit
│   └── Domain/<Name>/          # One folder per business domain (routes, service,
│                                # repository) — Admin, Auth, Customer, Category, Model,
│                                # Showroom, Review, Realisation, Notification,
│                                # EmailTemplate, Catalogue, Sample, Pricing, Facade,
│                                # QuoteRequest, Configuration, Cart, Order, Payment,
│                                # Calendly, Email, System
├── legacy/                     # Two not-yet-rewritten legacy PHP services, bridged
│                                # via thin gateways: InvoiceService.php (PDF invoices)
│                                # and calendly/EmailService.php (Calendly emails)
├── python/                     # Parametric 3D furniture generation (procedure_real.py)
├── backend/                    # Runtime data only, not code: uploads/ and data/showrooms.json
├── storage/                    # Local dev storage (models, uploads, sessions, backups)
├── tests/
│   ├── Unit/                   # Unit tests for services
│   ├── Contract/                # Golden HTTP contract tests + __snapshots__/
│   └── Support/                 # Test harness (ServerProcess, DbSeeder, ApiClient...)
├── docker-compose.yml           # Local dev: Postgres + backend + Adminer
├── Dockerfile                   # Production image
├── start.sh                     # Container entrypoint: migrate, cron, serve
└── railway.toml                 # Railway deploy config (healthcheck: /health)
```

## API Overview

All endpoints are reachable both as `/backend/api/<path>.php` (legacy form, used by the Next.js server-side proxy) and `/api/<path>` (direct browser calls). Both resolve to the same route.

### Authentication

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/backend/api/customers/register` | Customer registration |
| POST | `/backend/api/customers/login` | Customer login |
| GET | `/backend/api/customers/session` | Check session |
| POST | `/backend/api/admin-auth/login` | Admin login |

### Configurations

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/backend/api/generate` | Generate 3D model from prompt |
| POST | `/backend/api/configurations/save` | Save configuration |
| GET | `/backend/api/configurations/list` | List customer configurations |

### Orders

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/backend/api/orders/create` | Create order from cart |
| GET | `/backend/api/orders/list` | List customer orders |
| GET | `/backend/api/orders/[id]` | Get order details |

### Cart

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/backend/api/cart/add` | Add item to cart |
| GET | `/backend/api/cart/items` | Get cart items |
| DELETE | `/backend/api/cart/remove` | Remove item from cart |

### Samples (Materials)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/backend/api/samples/list-public` | List available samples |
| POST | `/backend/api/cart/add-sample` | Add sample to cart |

## Furniture Prompt Format

The 3D generation uses a specific prompt format:

```
M[type](width,depth,height)[flags][zones]
```

### Model Types

| Type | Parameters | Description |
|------|------------|-------------|
| M1 | `(width,depth,height)` | Standard furniture |
| M2 | `(width,depth,height_left,height_right)` | Asymmetric heights (buffet/mansard) |
| M3 | `(width,depth,height)` | Large buffet |

### Flags

| Flag | Description |
|------|-------------|
| E | Exposed back |
| F | Finish type |
| H | Horizontal layout |
| S | Metal base |
| S2 | Wood base |
| b | Base configuration |

### Examples

```
M1(1000,400,2000)bFS           # Standard cabinet with metal base
M2(1200,400,1200,2000)EFH      # Asymmetric cabinet (left: 1200mm, right: 2000mm)
M1(1500,450,2200)bFS2          # Cabinet with wood base
```

## Database

PostgreSQL, schema managed by versioned migrations in `src/Db/migrations/` (applied via `php bin/console migrate`, tracked in the `schema_migrations` table). Main tables:

- `customers`, `admins` — accounts
- `configurations`, `saved_configurations` — furniture configurator state
- `orders`, `order_items`, `order_catalogue_items`, `order_sample_items`, `order_facade_items` — order lines
- `cart_items`, `cart_sample_items`, `cart_catalogue_items`, `facade_cart_items` — shopping cart
- `catalogue_items`, `catalogue_item_variations`, `categories`, `models`, `templates` — catalogue and furniture templates
- `sample_types`, `sample_colors` — material samples
- `facades`, `facade_materials`, `facade_drilling_types`, `facade_settings` — facade panels
- `payment_links`, `payment_installments`, `stripe_payment_intents` — payments
- `calendly_appointments`, `email_verifications`, `password_resets`, `notifications`, `admin_notifications`, `realisations`, `realisation_images`, `avis` — support tables

### Database Access

```bash
# Local (Docker Compose)
docker compose exec postgres psql -U archimeuble -d archimeuble
# or open http://localhost:8080 (Adminer)

# Production (Railway)
railway connect postgres
```

## Deployment

### Railway (Production)

1. **Builder**: Dockerfile
2. **Volume**: mounted at `/data` for persistent uploads, generated models, sessions and database backups
3. **Database**: PostgreSQL, provisioned as a Railway plugin (`DATABASE_URL` injected automatically)
4. **Healthcheck**: `GET /health`

Environment variables (see `.env.example`) must be configured in the Railway dashboard.

### Deployment Process

Push to the `dev` branch triggers automatic deployment on Railway.

## Backup System

`start.sh` installs a daily cron (3:00 AM) running `backup-database.sh` inside the container, and `.github/workflows/daily-backup.yml` separately triggers a `pg_dump` backup once a day on the DEV and STAGING deployments via the confidential HTTP endpoint below.

### Backup Storage

- Location: `/data/backups/` on Railway
- Format: `database-backup-YYYY-MM-DD_HH-MM-SS.sql` (plain `pg_dump` output)

### Backup API

A confidential endpoint (never linked from the admin UI) manages backups, protected by `BACKUP_API_KEY` and a 10-requests-per-hour-per-IP rate limit:

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/backend/api/system/db-maintenance?key=XXX` | List backups |
| GET | `/backend/api/system/db-maintenance/download/[file]?key=XXX` | Download a backup |
| POST | `/backend/api/system/db-maintenance/create?key=XXX` | Create a backup (`pg_dump`) |
| POST | `/backend/api/system/db-maintenance?key=XXX` | Restore a backup (`psql`), with a JSON body `{"filename": "..."}` |

## Development

```bash
# Start
docker compose up -d

# View logs
docker logs -f archimeuble-backend

# Stop
docker compose down

# Static analysis, style, file rules, unit + contract tests
composer check
composer test:contract
```

### Adding Dependencies

PHP (Composer):
```bash
docker exec -it archimeuble-backend composer require package-name
```

Python:
```bash
# Add to requirements.txt, then rebuild
docker compose build --no-cache
```

### Code Changes

PHP changes are reflected immediately (volume mounted). Python changes require a container restart.

## Bootstrapping the First Admin

There is no default admin account. Create one via the confidential admin-creation endpoint (protected by `BACKUP_API_KEY`):

```bash
curl -X POST "http://localhost:8000/backend/api/system/create-admin.php?key=$BACKUP_API_KEY" \
  -H 'Content-Type: application/json' \
  -d '{"email": "admin@archimeuble.com", "password": "change-me", "username": "admin"}'
```

## Troubleshooting

### Container fails to start

```bash
docker logs archimeuble-backend
docker compose down
docker compose build --no-cache
docker compose up
```

### Port 8000 in use

```bash
lsof -ti:8000 | xargs kill -9
# Or change the published port in docker-compose.yml
```

### Migrations fail on startup

`start.sh` runs `php bin/console migrate` before serving traffic and aborts if it fails. Check `php bin/console migrate:status` and the container logs for the failing statement.

## External Services

| Service | Purpose |
|---------|---------|
| Stripe | Payment processing |
| Resend | Transactional emails |
| Calendly | Appointment scheduling |
| Crisp | Live chat support |
