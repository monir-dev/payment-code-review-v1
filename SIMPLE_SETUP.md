# Quick Setup Guide

## 1. Install Dependencies
```bash
composer install
```

## 2. Environment Setup
Set Environment variable
```bash
NMI_API_KEY=your-nmi-api-key-here
```

## 3. Database Setup
```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

## 4. Start Server
```bash
symfony server:start
```

## 5. Access the Application

### Main Pages
- **Dashboard**: http://localhost:8000/
- **Payment Checkout**: http://localhost:8000/checkout
- **Process Refund**: http://localhost:8000/refund
- **Manage Plans**: http://localhost:8000/plans
- **Manage Subscriptions**: http://localhost:8000/subscriptions

### API Endpoints
- **Transaction History**: `GET /api/transactions`
- **Create Plan**: `POST /api/plan/create`
- **Create Subscription**: `POST /api/subscription/create`
- **Cancel Subscription**: `POST /api/subscription/{id}/cancel`
- **Rebill Subscription**: `POST /api/subscription/{id}/rebill`

## 6. Test the Payment Flow
** First create plan if you want to check subscription
1. Go to `/checkout`
2. Fill in billing information
3. Submit to get NMI payment form
4. Complete payment on NMI
5. Get redirected back with result

## Quick Commands
```bash
# Clear cache
php bin/console cache:clear

# Run tests
php bin/phpunit

# Process automatic rebilling (dry run)
php bin/console app:process-automatic-rebilling --dry-run

# Process rebilling with gateway
php bin/console app:process-automatic-rebilling --gateway

# Process specific subscription
php bin/console app:process-automatic-rebilling --subscription-id=sub_123 --gateway
```

## Docker Alternative
```bash
docker-compose up -d
docker-compose exec php composer install
docker-compose exec php php bin/console doctrine:migrations:migrate
```

That's it! 🚀