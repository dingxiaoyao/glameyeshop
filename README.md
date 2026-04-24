# GLAMEYE

GLAMEYE is a simple online storefront and checkout site for premium glass eyewear. This repository includes the public storefront, checkout flow, admin landing page, backend API endpoints, and a sample database setup.

## Structure

- `index.html` — storefront landing page
- `checkout.html` — checkout flow and payment selection
- `admin/index.html` — simple admin dashboard placeholder
- `api/` — backend PHP endpoints for orders, payments, and lead capture
- `database/setup.sql` — sample database schema and initial data
- `.github/workflows/deploy.yml` — GitHub Actions workflow for PHP syntax checks on push

## Local setup

1. Install PHP and MySQL.
2. Import `database/setup.sql` into MySQL.
3. Update `api/config.php` with your local database credentials.
4. Serve the directory with a PHP-capable web server.
