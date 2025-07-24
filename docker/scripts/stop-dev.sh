#!/bin/bash

echo "🛑 Stopping AI-Book Development Environment..."

# Stop all containers
docker-compose down

# Clean up unused resources (optional)
read -p "🧹 Clean up unused Docker resources? (y/n): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    echo "🧹 Cleaning up Docker resources..."
    docker system prune -f
    docker volume prune -f
fi

echo "✅ Development environment stopped!" 