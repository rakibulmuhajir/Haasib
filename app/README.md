# Haasib Application

## Project Overview
Haasib is a modular accounting and business management platform built on the Laravel framework. It combines accounting, inventory, CRM, and payroll modules to support small and medium enterprises.

## Setup
1. **Install PHP dependencies**
   ```bash
   composer install
   ```
2. **Install JavaScript dependencies**
   ```bash
   npm install
   ```
3. **Configure environment**
   Copy `.env.example` to `.env` and update database and service credentials.
4. **Generate application key**
   ```bash
   php artisan key:generate
   ```
5. **Run database migrations**
   ```bash
   php artisan migrate
   ```
6. **Start the development servers**
   ```bash
   php artisan serve
   npm run dev
   ```

## Contributing
1. Fork the repository and create a feature branch.
2. Run the test suite with `composer test` and ensure all tests pass.
3. Submit a pull request describing your changes.

## License
The application is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
