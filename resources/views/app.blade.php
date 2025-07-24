<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <title>{{ config('app.name', 'AI-Book Social Network') }}</title>
    
    <!-- Meta tags for social sharing -->
    <meta name="description" content="AI-Book Social Network - Connect with fellow readers through intelligent recommendations and meaningful discussions">
    <meta name="keywords" content="books, reading, social network, AI recommendations, book clubs">
    <meta name="author" content="AI-Book Social Network">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ config('app.url') }}">
    <meta property="og:title" content="{{ config('app.name') }}">
    <meta property="og:description" content="Connect with fellow readers through intelligent recommendations and meaningful discussions">
    
    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="{{ config('app.url') }}">
    <meta property="twitter:title" content="{{ config('app.name') }}">
    <meta property="twitter:description" content="Connect with fellow readers through intelligent recommendations and meaningful discussions">
    
    <!-- Favicon -->
    <link rel="icon" href="/favicon.ico">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    
    <!-- Environment variables for Vue -->
    <script>
        window.config = {
            appName: @json(config('app.name')),
            appUrl: @json(config('app.url')),
            apiUrl: @json(config('app.url') . '/api'),
        };
    </script>
    
    @if(app()->environment('production'))
        <!-- Production assets will be served by Vite build -->
        @vite(['resources/js/main.ts'])
    @else
        <!-- Development mode - Vite dev server -->
        @vite(['resources/js/main.ts'])
    @endif
    
    <!-- Basic CSS for the loading state -->
    <style>
        .loading {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            font-family: 'Figtree', sans-serif;
            color: #6B7280;
        }
        .spinner {
            border: 4px solid #f3f4f6;
            border-top: 4px solid #4F46E5;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin-right: 12px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="antialiased">
    <!-- Vue.js app will mount here -->
    <div id="app">
        <!-- Loading state while Vue is initializing -->
        <div class="loading">
            <div class="spinner"></div>
            <span>Loading AI-Book Social Network...</span>
        </div>
    </div>
</body>
</html> 