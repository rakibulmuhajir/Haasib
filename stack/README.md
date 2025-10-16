<p align="center">
  <img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo">
</p>

<h1 align="center">Haasib</h1>
<p align="center">
  <strong>Comprehensive Double-Entry Accounting System</strong><br>
  Built with Laravel 12, Vue 3, PostgreSQL 16, and PrimeVue 4
</p>

<p align="center">
  <a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
  <a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
  <a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
  <a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## 🚀 Quick Start

### New to Haasib?
- **[Quickstart Guide](./QUICKSTART.md)** - Complete setup and getting started guide
- **[CLI Cheatsheet](./docs/CLI-CHEATSHEET.md)** - Quick reference for all commands
- **[Documentation](./docs/)** - Detailed documentation and guides

### Prerequisites
- PHP 8.3+
- PostgreSQL 16+  
- Node.js 18+
- Composer & NPM

### Installation
```bash
git clone <repository-url>
cd haasib/stack
composer install
npm install
cp .env.example .env
php artisan key:generate
# Configure database in .env
php artisan migrate
php artisan db:seed
npm run build
php artisan serve
```

## ✨ Features

### 📊 Core Accounting
- **Double-Entry System**: Complete double-entry bookkeeping with automatic validation
- **Chart of Accounts**: Flexible account hierarchy and management
- **Journal Entries**: Manual and automated journal entry creation
- **Audit Trail**: Complete audit history for all transactions

### 🔄 Workflow Management
- **Entry Lifecycle**: Draft → Submit → Approve → Post
- **Batch Processing**: Group and process multiple entries together
- **Recurring Templates**: Schedule recurring journal entries automatically
- **Approval Workflows**: Multi-level approval controls

### 📈 Reporting & Analytics
- **Trial Balance**: Real-time trial balance generation
- **Financial Statements**: Balance sheet and income statement
- **General Ledger**: Detailed transaction history
- **Export Capabilities**: CSV export for all reports

### 🎨 Modern Interface
- **Vue 3 + Inertia**: Responsive and fast user interface
- **PrimeVue Components**: Professional UI component library
- **Real-time Updates**: Live data synchronization
- **Dark Mode Support**: Eye-friendly dark theme

### ⚡ Performance
- **Queue System**: Background processing for heavy operations
- **Caching**: Optimized query performance
- **Database Optimized**: PostgreSQL with RLS support
- **API First**: RESTful APIs for all operations

## 🛠️ Architecture

Built with modern technologies and best practices:

- **Backend**: Laravel 12 with modular architecture
- **Frontend**: Vue 3 + Inertia.js v2 + PrimeVue 4
- **Database**: PostgreSQL 16 with Row Level Security
- **Testing**: Pest 4 with comprehensive test coverage
- **Queue**: Laravel Horizon for job monitoring
- **DevTools**: Laravel Telescope for debugging

## 📚 Resources

### Documentation
- [Quickstart Guide](./QUICKSTART.md) - Get started in minutes
- [CLI Commands](./docs/CLI-CHEATSHEET.md) - Command reference
- [API Documentation](./docs/api/) - REST API guide
- [Feature Guides](./docs/) - Detailed feature documentation

### Development
- [Contributing Guidelines](./CONTRIBUTING.md) - How to contribute
- [Architecture Overview](./docs/architecture.md) - System design
- [Testing Guide](./docs/testing.md) - Testing practices

### Community
- [Issues](https://github.com/your-org/haasib/issues) - Report bugs and request features
- [Discussions](https://github.com/your-org/haasib/discussions) - Community discussions

## 📋 System Requirements

- **PHP**: 8.3+
- **Database**: PostgreSQL 16+
- **Node.js**: 18+
- **Extensions**: BCMath, Ctype, Fileinfo, JSON, Mbstring, OpenSSL, PDO, Tokenizer, XML
- **Memory**: 512MB+ recommended
- **Storage**: 100MB+ disk space

## 🔧 Configuration

Key configuration options in `.env`:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=haasib
DB_USERNAME=your_username
DB_PASSWORD=your_password

QUEUE_CONNECTION=database
CACHE_DRIVER=redis
SESSION_DRIVER=redis
```

## 🧪 Testing

Run the test suite:

```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Feature/Accounting/JournalEntries/JournalAuditTest.php

# Run with coverage
php artisan test --coverage
```

## 📄 License

Haasib is open-sourced software licensed under the [MIT license](LICENSE.md).

## 🤝 Contributing

Thank you for considering contributing to Haasib! Please read our [Contributing Guide](CONTRIBUTING.md) for details.

---

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
