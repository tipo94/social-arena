#!/bin/bash

# Wrapper script for Laravel Artisan commands in Docker

if [ $# -eq 0 ]; then
    echo "🎨 Laravel Artisan Command Wrapper"
    echo "Usage: $0 [artisan_command]"
    echo "Example: $0 migrate"
    echo "Example: $0 make:controller PostController"
    echo "Example: $0 queue:work"
    exit 1
fi

echo "🎨 Running: php artisan $*"
docker-compose exec app php artisan "$@" 