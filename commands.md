# Haasib Development Commands

## 🚀 Server Commands (FrankenPHP - Primary Server)

### Start FrankenPHP Server
```bash
# Development server with file watching (PRIMARY)
php artisan octane:start --server=frankenphp --port=9001 --watch

# Production server
php artisan octane:start --server=frankenphp --port=9001

# Custom port
php artisan octane:start --server=frankenphp --port=8080 --watch

# With additional workers
php artisan octane:start --server=frankenphp --port=9001 --workers=4 --watch
```

### Stop & Restart FrankenPHP
```bash
# Stop server
php artisan octane:stop

# Restart server (in development)
php artisan octane:restart

# Reload workers
php artisan octane:reload
```

### FrankenPHP Status & Info
```bash
# Check server status
php artisan octane:status

# Show server configuration
php artisan octane:info
```

## 🔄 Alternative Servers (Fallback)

### Laravel Development Server (Fallback)
```bash
# Standard Laravel server
php artisan serve --port=9001

# With specific host
php artisan serve --host=0.0.0.0 --port=9001
```

## 📦 Frontend Development

### Vite Development Server
```bash
# Start with hot module replacement
npm run dev

# Build for production
npm run build

# Preview production build
npm run preview
```

### Component Development
```bash
# Add shadcn/vue components
npx shadcn-vue add button
npx shadcn-vue add dialog
npx shadcn-vue add table

# List available components
npx shadcn-vue list
```

## 🗄️ Database Commands

### Migrations
```bash
# Run migrations
php artisan migrate

# Fresh migrations (WARNING: destroys data)
php artisan migrate:fresh

# Migration status
php artisan migrate:status

# Rollback last migration
php artisan migrate:rollback
```

### Database Management
```bash
# Seed database
php artisan db:seed

# Fresh migrate + seed
php artisan migrate:fresh --seed

# Database console
php artisan db

# Show database info
php artisan db:show
```

## 🧹 Cache & Optimization

### Clear Caches
```bash
# Clear all caches
php artisan optimize:clear

# Individual cache clearing
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Clear compiled files
php artisan clear-compiled
```

### Optimize for Production
```bash
# Optimize all
php artisan optimize

# Individual optimizations
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 🔧 Development Tools

### Artisan Commands
```bash
# List all commands
php artisan list

# Generate key
php artisan key:generate

# Create symbolic link to storage
php artisan storage:link

# Queue work (if using queues)
php artisan queue:work
```

### Code Quality
```bash
# Format code (if Pint is installed)
vendor/bin/pint

# Run tests
php artisan test

# Run specific test
php artisan test --filter=ExampleTest
```

## 📊 Monitoring & Debugging

### Process Management
```bash
# Find process using port
lsof -i :9001
sudo lsof -i :9001

# Kill process by PID
sudo kill -9 [PID]

# Kill all PHP processes
sudo pkill -f "php"

# Kill all Octane processes
sudo pkill -f "octane"
```

### Performance Testing
```bash
# Quick response time test
curl -w "Time: %{time_total}s\nStatus: %{http_code}\n" -s -o /dev/null http://127.0.0.1:9001/

# Burst test (5 requests)
for i in {1..5}; do curl -w "Request $i: %{time_total}s\n" -s -o /dev/null http://127.0.0.1:9001/; done

# Load test with ab (if installed)
ab -n 100 -c 10 http://127.0.0.1:9001/
```

### Log Monitoring
```bash
# Tail Laravel logs
tail -f storage/logs/laravel.log

# Follow logs with Laravel Pail (if installed)
php artisan pail

# Clear logs
php artisan log:clear
```

## 🐛 Debugging & Troubleshooting

### Common Issues
```bash
# Permissions fix
sudo chown -R $USER:$USER storage bootstrap/cache
chmod -R 755 storage bootstrap/cache

# Composer issues
composer dump-autoload

# Node modules issues
rm -rf node_modules package-lock.json
npm install
```

### Environment
```bash
# Show environment info
php artisan about

# Check PHP version and extensions
php -v
php -m

# Check Composer version
composer --version

# Check Node/NPM versions
node --version
npm --version
```

## 🔐 Security & Maintenance

### Security Commands
```bash
# Generate new APP_KEY
php artisan key:generate

# Create maintenance mode
php artisan down

# Exit maintenance mode
php artisan up
```

### Backup & Restore (if implemented)
```bash
# Create backup
php artisan backup:run

# List backups
php artisan backup:list

# Clean old backups
php artisan backup:clean
```

## 🚀 Production Deployment

### Production Preparation
```bash
# Build assets
npm run build

# Optimize Laravel
php artisan optimize

# Cache everything
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Update autoloader
composer install --optimize-autoloader --no-dev
```

### FrankenPHP Production
```bash
# Production server
php artisan octane:start --server=frankenphp --port=80

# With SSL (production)
php artisan octane:start --server=frankenphp --host=0.0.0.0 --port=443
```

## 📋 Quick Development Workflow

### Start Development Session
```bash
# 1. Start FrankenPHP server
php artisan octane:start --server=frankenphp --port=9001 --watch

# 2. Start Vite (in separate terminal)
npm run dev

# 3. Access application
# Frontend: http://localhost:5180
# Backend: http://127.0.0.1:9001
```

### Daily Development
```bash
# Morning startup
php artisan optimize:clear
php artisan octane:restart
npm run dev

# Before committing
npm run build
php artisan test
vendor/bin/pint
```

## 📈 Performance Benchmarks

### FrankenPHP vs Laravel Dev Server
- **Homepage**: 72ms (FrankenPHP) vs 200ms (Laravel) = 3x faster
- **Dashboard**: 16ms (FrankenPHP) vs 170ms (Laravel) = 10x faster  
- **Burst Requests**: 22-36ms (FrankenPHP) vs 167-247ms (Laravel) = 5-8x faster

### Recommended Setup
- **Development**: FrankenPHP with `--watch` flag
- **Testing**: FrankenPHP for speed
- **Production**: FrankenPHP with multiple workers

---

*Last updated: Nov 20, 2024*
*FrankenPHP Performance: ⚡ Blazing Fast*