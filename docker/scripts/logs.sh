#!/bin/bash

# Script to view Docker logs for AI-Book development environment

if [ $# -eq 0 ]; then
    echo "📋 Available services:"
    echo "   • app (Laravel PHP-FPM)"
    echo "   • webserver (Nginx)"
    echo "   • database (MySQL)"
    echo "   • redis"
    echo "   • node (Vue.js/Vite)"
    echo "   • mailhog"
    echo "   • phpmyadmin"
    echo "   • redis-commander"
    echo ""
    echo "Usage: $0 [service_name] [options]"
    echo "Example: $0 app -f    # Follow app logs"
    echo "Example: $0 all       # Show all logs"
    exit 1
fi

SERVICE=$1
shift

if [ "$SERVICE" = "all" ]; then
    echo "📋 Showing logs for all services..."
    docker-compose logs "$@"
else
    echo "📋 Showing logs for $SERVICE..."
    docker-compose logs "$@" "$SERVICE"
fi 