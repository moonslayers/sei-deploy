# AGENTS.md

This file provides guidelines for agentic coding agents working on this Laravel project.

## Build & Development Commands

### Development
```bash
php artisan serve          # Start development server
php artisan queue:listen   # Process queued jobs
php artisan pail           # Real-time log monitoring
npm run dev                # Watch and build frontend assets
composer dev               # Start all services
```

### Testing
```bash
php artisan test
```

### Linting & Database
```bash
./vendor/bin/pint          # Auto-fix code style issues (Laravel Pint)
./vendor/bin/pint --test   # Check without fixing
php artisan migrate        # Run database migrations
php artisan migrate:fresh  # Reset and re-run migrations
php artisan migrate:rollback # Rollback last migration
```

## Code Style Guidelines

### General PHP Rules
- **PHP Version**: 8.2+
- **Indentation**: 4 spaces (no tabs)
- **Line Endings**: LF only
- **No closing PHP tags**: Omit `?>` at end of files
- **Single quotes**: Use for strings unless interpolation needed
- **Short array syntax**: Use `[]` instead of `array()`
