#!/bin/bash

echo "🚀 Starting AI-Book Development Environment..."

# Stop any existing containers
echo "📦 Stopping existing containers..."
docker-compose down

# Build and start containers
echo "🔨 Building and starting containers..."
docker-compose up -d --build

# Wait for services to be healthy
echo "⏳ Waiting for services to be ready..."
sleep 10

# Check container health
echo "🏥 Checking container health..."
docker-compose ps

# Setup Laravel application
echo "🔧 Setting up Laravel application..."
docker-compose exec -T app php artisan key:generate --ansi
docker-compose exec -T app php artisan config:clear
docker-compose exec -T app php artisan cache:clear
docker-compose exec -T app php artisan route:clear
docker-compose exec -T app php artisan view:clear

# Run migrations
echo "📊 Running database migrations..."
docker-compose exec -T app php artisan migrate --force

# Seed database
echo "🌱 Seeding database..."
docker-compose exec -T app php artisan db:seed --force

echo "✅ Development environment is ready!"
echo ""
echo "🌐 Available services:"
echo "   • Frontend (Vue 3): http://localhost:5173"
echo "   • Backend API: http://localhost:8000"
echo "   • Health Check: http://localhost:8000/api/health"
echo "   • phpMyAdmin: http://localhost:8080"
echo "   • Redis Commander: http://localhost:8081"
echo "   • MailHog: http://localhost:8025"
echo ""
echo "📝 Useful commands:"
echo "   • View logs: docker-compose logs -f [service]"
echo "   • Run artisan: docker-compose exec app php artisan [command]"
echo "   • Run npm: docker-compose exec node npm [command]"
echo "   • Access shell: docker-compose exec app bash" 