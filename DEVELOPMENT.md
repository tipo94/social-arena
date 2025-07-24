# AI-Book Development Environment

This document describes the complete Docker-based development environment for the AI-Book social networking platform.

## Overview

The development environment uses Docker Compose to orchestrate multiple services:

- **Laravel API** (PHP 8.2-FPM with Redis extension)
- **Vue 3 Frontend** (Node 20 with Vite)
- **MySQL 8.0** Database
- **Redis** for caching, sessions, and queues
- **Nginx** web server
- **MailHog** for email testing
- **phpMyAdmin** for database management (dev only)
- **Redis Commander** for Redis management (dev only)

## Quick Start

```bash
# Start development environment
./docker/scripts/start-dev.sh

# Stop development environment
./docker/scripts/stop-dev.sh
```

## Available Services

Once started, the following services are available:

| Service | URL | Description |
|---------|-----|-------------|
| Frontend (Vue 3) | http://localhost:5173 | Vue 3 development server with hot reload |
| Backend API | http://localhost:8000 | Laravel API endpoints |
| Health Check | http://localhost:8000/api/health | API health status |
| phpMyAdmin | http://localhost:8080 | Database management interface |
| Redis Commander | http://localhost:8081 | Redis management interface |
| MailHog | http://localhost:8025 | Email testing interface |

## Configuration

### Environment Variables

The main configuration is in `.env`:

```bash
# Application
APP_NAME="AI-Book Social Network"
APP_URL=http://localhost:8000

# Database
DB_CONNECTION=mysql
DB_HOST=database
DB_DATABASE=laravel
DB_USERNAME=laravel
DB_PASSWORD=secret

# Redis Configuration
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
REDIS_HOST=redis

# Sanctum (SPA Authentication)
SANCTUM_STATEFUL_DOMAINS="localhost:5173,127.0.0.1:5173,localhost:8000,127.0.0.1:8000"
```

### Development Features

#### Xdebug Support
- Xdebug is enabled in development containers
- Configure your IDE to connect to `host.docker.internal:9003`
- IDE key: `PHPSTORM`

#### Hot Reload
- Vue 3 frontend has hot reload enabled
- Changes to frontend code are reflected immediately
- Vite dev server runs on port 5173

#### Database & Redis Persistence
- Database data persists in `facebook_dbdata` volume
- Redis data persists in `facebook_redis_data` volume
- Node modules cached in `facebook_node_modules` volume

## Utility Scripts

### Development Scripts

```bash
# Start/stop environment
./docker/scripts/start-dev.sh
./docker/scripts/stop-dev.sh

# View logs
./docker/scripts/logs.sh [service] [options]
./docker/scripts/logs.sh app -f     # Follow app logs
./docker/scripts/logs.sh all        # Show all logs

# Run Laravel Artisan commands
./docker/scripts/artisan.sh migrate
./docker/scripts/artisan.sh make:controller PostController
```

### Manual Commands

```bash
# Container management
docker-compose up -d              # Start all services
docker-compose down               # Stop all services
docker-compose ps                 # Show container status

# Laravel commands
docker-compose exec app php artisan migrate
docker-compose exec app php artisan make:model Post
docker-compose exec app composer install

# Frontend commands
docker-compose exec node npm install
docker-compose exec node npm run build

# Database access
docker-compose exec database mysql -u laravel -p laravel

# Redis access
docker-compose exec redis redis-cli
```

## Health Checks

All services include health checks:

- **App**: PHP-FPM status check
- **Database**: MySQL ping
- **Redis**: Redis ping  
- **Webserver**: Nginx health endpoint
- **Node**: Vite dev server status
- **MailHog**: Web interface availability

## Development Workflow

### Backend Development

1. Make changes to PHP files
2. Changes are reflected immediately (volume mount)
3. For new dependencies: `docker-compose exec app composer install`
4. Run migrations: `./docker/scripts/artisan.sh migrate`

### Frontend Development

1. Make changes to Vue files in `resources/js/`
2. Hot reload automatically updates the browser
3. For new dependencies: `docker-compose exec node npm install`

### Database Management

- Use phpMyAdmin at http://localhost:8080
- Credentials: `laravel` / `secret`
- Or use CLI: `docker-compose exec database mysql -u laravel -p`

### Redis Management

- Use Redis Commander at http://localhost:8081
- Or use CLI: `docker-compose exec redis redis-cli`

### Email Testing

- All emails are captured by MailHog
- View emails at http://localhost:8025
- No emails are sent to real addresses in development

## Performance Optimizations

### MySQL Configuration
- Optimized buffer pool size (512MB)
- Query cache disabled (MySQL 8.0 best practice)
- InnoDB settings tuned for social networking workload
- Performance schema enabled for monitoring

### Redis Configuration
- Persistence enabled with AOF
- Keyspace notifications enabled for real-time features
- Separate databases for cache (1) and sessions/queues (0)

### PHP Configuration
- Memory limit: 512MB
- Upload limit: 40MB
- Execution time: 300 seconds
- Redis extension enabled for better performance

## Troubleshooting

### Common Issues

**Containers not starting:**
```bash
docker-compose down
docker-compose up -d --build
```

**Database connection errors:**
```bash
# Check database is healthy
docker-compose ps database
# View database logs
./docker/scripts/logs.sh database
```

**Permission issues:**
```bash
# Fix Laravel permissions
docker-compose exec app chown -R www:www storage bootstrap/cache
```

**Frontend not updating:**
```bash
# Restart node container
docker-compose restart node
# Clear node modules
docker volume rm facebook_node_modules
```

### Platform Compatibility

The configuration includes ARM64 compatibility warnings for:
- MailHog
- phpMyAdmin  
- Redis Commander

These are cosmetic warnings and don't affect functionality on M1/M2 Macs.

## Production Considerations

This development setup includes tools not suitable for production:
- Xdebug (performance impact)
- phpMyAdmin (security risk)
- Redis Commander (security risk)
- Development-level logging

For production deployment, use the base `docker-compose.yml` without the `docker-compose.override.yml` file.

## Additional Resources

- [Laravel Documentation](https://laravel.com/docs)
- [Vue 3 Documentation](https://vuejs.org/)
- [Docker Compose Documentation](https://docs.docker.com/compose/)
- [Sanctum SPA Authentication](https://laravel.com/docs/sanctum#spa-authentication) 