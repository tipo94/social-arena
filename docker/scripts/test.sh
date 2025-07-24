#!/bin/bash

echo "🧪 Running AI-Book Test Suite"
echo "=============================="

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${2}${1}${NC}"
}

# Check if containers are running
print_status "🔍 Checking Docker containers..." $BLUE
if ! docker-compose ps | grep -q "Up"; then
    print_status "❌ Docker containers are not running. Starting them..." $YELLOW
    docker-compose up -d
    sleep 10
fi

# Run PHP/Laravel tests
print_status "🧪 Running PHPUnit tests..." $BLUE
if docker-compose exec -T app php artisan test --coverage; then
    print_status "✅ PHPUnit tests passed!" $GREEN
else
    print_status "❌ PHPUnit tests failed!" $RED
    EXIT_CODE=1
fi

echo ""

# Install frontend dependencies if needed
print_status "📦 Installing frontend dependencies..." $BLUE
docker-compose exec -T node npm install

# Run Vitest tests
print_status "🧪 Running Vitest tests..." $BLUE
if docker-compose exec -T node npm run test:run; then
    print_status "✅ Vitest tests passed!" $GREEN
else
    print_status "❌ Vitest tests failed!" $RED
    EXIT_CODE=1
fi

echo ""

# Run frontend type checking
print_status "🔍 Running TypeScript type checking..." $BLUE
if docker-compose exec -T node npm run type-check; then
    print_status "✅ TypeScript type checking passed!" $GREEN
else
    print_status "❌ TypeScript type checking failed!" $RED
    EXIT_CODE=1
fi

echo ""

# Option to run Cypress tests
read -p "🤖 Run Cypress E2E tests? (y/n): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    print_status "🧪 Running Cypress E2E tests..." $BLUE
    if docker-compose exec -T node npm run test:e2e:headless; then
        print_status "✅ Cypress E2E tests passed!" $GREEN
    else
        print_status "❌ Cypress E2E tests failed!" $RED
        EXIT_CODE=1
    fi
fi

echo ""
echo "=============================="
if [ "$EXIT_CODE" = "1" ]; then
    print_status "❌ Some tests failed. Check the output above." $RED
    exit 1
else
    print_status "✅ All tests passed successfully!" $GREEN
    exit 0
fi 