# ArchiMeuble Backend

## Overview

ArchiMeuble is a custom furniture manufacturing company based in Lille, France. This backend powers their e-commerce platform, allowing customers to:

- Design custom furniture (bookcases, dressings, buffets) using a 3D configurator
- Visualize their furniture in real-time with parametric 3D generation
- Order material samples before purchasing
- Purchase ready-made items from the catalogue
- Order custom facade panels for existing furniture

The platform handles the complete order lifecycle: configuration, quotation, payment (full or deposit), production tracking, and delivery.

## Stack

- PHP 8.2
- SQLite
- Python 3 (3D generation)
- Docker

## Requirements

- Docker and Docker Compose (local development)
- Railway account (production deployment)

## Quick Start

```bash
# Clone and configure
git clone <repository-url>
cd back
cp .env.example .env

# Start services
docker compose up -d

# Verify
curl http://localhost:8000/backend/api/test.php
```

The server will be available at `http://localhost:8000`.

## Environment Variables

Copy `.env.example` to `.env` and configure the following:

### Required

| Variable | Description |
|----------|-------------|
| `DB_PATH` | SQLite database path |
| `STRIPE_SECRET_KEY` | Stripe API secret key |
| `STRIPE_PUBLISHABLE_KEY` | Stripe publishable key |
| `STRIPE_WEBHOOK_SECRET` | Stripe webhook secret |
| `RESEND_API_KEY` | Resend email API key |

### Optional

| Variable | Description |
|----------|-------------|
| `SMTP_HOST` | SMTP server host |
| `SMTP_PORT` | SMTP server port |
| `SMTP_USERNAME` | SMTP username |
| `SMTP_PASSWORD` | SMTP password |
| `CALENDLY_API_TOKEN` | Calendly integration token |
| `CRISP_WEBSITE_ID` | Crisp chat widget ID |
| `BACKUP_API_KEY` | Secret key for backup API access |

## Project Structure

```
back/
├── backend/
│   ├── api/                    # REST API endpoints
│   │   ├── admin/              # Admin endpoints
│   │   ├── admin-auth/         # Admin authentication
│   │   ├── customers/          # Customer management
│   │   ├── configurations/     # Furniture configurations
│   │   ├── orders/             # Order management
│   │   ├── cart/               # Shopping cart
│   │   ├── samples/            # Material samples
│   │   ├── system/             # System utilities
│   │   └── ...
│   ├── config/                 # Configuration files
│   ├── core/                   # Core classes (Database, Router)
│   ├── models/                 # Data models
│   ├── services/               # Business services
│   └── python/                 # 3D generation scripts
├── database/                   # Local SQLite database
├── docker-compose.yml
├── Dockerfile
├── router.php                  # Request router
├── start.sh                    # Production startup script
└── railway.json                # Railway deployment config
```

## API Overview

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

SQLite database with the following main tables:

- `customers` - Customer accounts
- `admins` - Admin accounts
- `configurations` - Saved furniture configurations
- `orders` - Customer orders
- `order_items` - Order line items (furniture)
- `order_catalogue_items` - Order line items (catalogue)
- `order_sample_items` - Order line items (samples)
- `cart_items` - Shopping cart
- `sample_types` - Material types
- `sample_colors` - Material colors/finishes
- `catalogue_items` - Catalogue products
- `models` - Furniture templates
- `categories` - Product categories
- `realisations` - Portfolio projects

### Database Access

```bash
# Local
docker compose exec backend sqlite3 /app/database/archimeuble.db

# Production (Railway)
railway run sqlite3 /data/archimeuble_dev.db
```

## Deployment

### Railway (Production)

The application is deployed on Railway with the following configuration:

1. **Builder**: Dockerfile
2. **Volume**: Mounted at `/data` for persistent storage
3. **Database**: `/data/archimeuble_dev.db`

Environment variables must be configured in the Railway dashboard.

### Deployment Process

Push to the `dev` branch triggers automatic deployment:

```bash
git push origin dev
```

## Backup System

Automated backups run daily at 3:00 AM (configured in `start.sh`).

### Backup Storage

- Location: `/data/backups/` on Railway
- Retention: 30 most recent backups
- Format: `database-backup-YYYY-MM-DD_HH-MM-SS.db`

### Backup API

A secured API endpoint is available for backup management:

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/backend/api/system/db-maintenance?key=XXX` | List backups |
| GET | `/backend/api/system/db-maintenance/download/[file]?key=XXX` | Download backup |
| POST | `/backend/api/system/db-maintenance?key=XXX` | Restore backup |

Requires `BACKUP_API_KEY` environment variable.

### Local Backup Download

1. Create `.backup-config.json` (not tracked by git):
```json
{
  "apiUrl": "https://api-dev.archimeuble.com",
  "apiKey": "YOUR_BACKUP_API_KEY"
}
```

2. Run:
```bash
node download-backup.js
```

Backups are downloaded to `./local-backups/`.

## Development

### Local Development

```bash
# Start
docker compose up -d

# View logs
docker logs -f archimeuble-backend

# Stop
docker compose down
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

PHP changes are reflected immediately (volume mounted). Python changes require container restart.

## Default Admin Credentials

- Email: `admin@archimeuble.com`
- Password: `admin123`

Change these credentials in production.

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
# Find and kill process
lsof -ti:8000 | xargs kill -9

# Or change port in docker-compose.yml
ports:
  - "8001:8000"
```

### Database locked

```bash
docker compose down
docker compose up
```

## External Services

| Service | Purpose |
|---------|---------|
| Stripe | Payment processing |
| Resend | Transactional emails |
| Calendly | Appointment scheduling |
| Crisp | Live chat support |
