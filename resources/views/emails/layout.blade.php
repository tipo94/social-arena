<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>{{ $subject ?? config('mail.templates.company_name') }}</title>
    <style>
        /* Reset and base styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #374151;
            background-color: #f9fafb;
        }
        
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .header {
            background: linear-gradient(135deg, {{ config('mail.templates.brand_color', '#4f46e5') }}, #6366f1);
            padding: 32px 24px;
            text-align: center;
        }
        
        .logo {
            max-width: 200px;
            height: auto;
            margin-bottom: 16px;
        }
        
        .company-name {
            color: #ffffff;
            font-size: 28px;
            font-weight: bold;
            margin: 0;
        }
        
        .content {
            padding: 32px 24px;
        }
        
        .greeting {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 16px;
            color: #1f2937;
        }
        
        .message {
            font-size: 16px;
            line-height: 1.7;
            margin-bottom: 24px;
            color: #4b5563;
        }
        
        .button {
            display: inline-block;
            padding: 12px 24px;
            background-color: {{ config('mail.templates.brand_color', '#4f46e5') }};
            color: #ffffff;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            margin: 16px 0;
            transition: background-color 0.2s;
        }
        
        .button:hover {
            background-color: #4338ca;
        }
        
        .button-secondary {
            background-color: #6b7280;
        }
        
        .button-secondary:hover {
            background-color: #4b5563;
        }
        
        .divider {
            height: 1px;
            background-color: #e5e7eb;
            margin: 24px 0;
        }
        
        .footer {
            background-color: #f3f4f6;
            padding: 24px;
            text-align: center;
            font-size: 14px;
            color: #6b7280;
        }
        
        .footer-links {
            margin: 16px 0;
        }
        
        .footer-links a {
            color: {{ config('mail.templates.brand_color', '#4f46e5') }};
            text-decoration: none;
            margin: 0 8px;
        }
        
        .social-links {
            margin: 16px 0;
        }
        
        .social-links a {
            display: inline-block;
            margin: 0 8px;
            color: #6b7280;
            text-decoration: none;
        }
        
        .unsubscribe {
            font-size: 12px;
            color: #9ca3af;
            margin-top: 16px;
        }
        
        .unsubscribe a {
            color: #9ca3af;
            text-decoration: underline;
        }
        
        /* Responsive design */
        @media (max-width: 640px) {
            .container {
                margin: 0;
                border-radius: 0;
            }
            
            .header,
            .content,
            .footer {
                padding: 24px 16px;
            }
            
            .greeting {
                font-size: 20px;
            }
            
            .message {
                font-size: 14px;
            }
        }
        
        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            .container {
                background-color: #1f2937;
            }
            
            .content {
                color: #e5e7eb;
            }
            
            .greeting {
                color: #f3f4f6;
            }
            
            .message {
                color: #d1d5db;
            }
            
            .footer {
                background-color: #111827;
                color: #9ca3af;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            @if(config('mail.templates.logo_url'))
                <img src="{{ config('mail.templates.logo_url') }}" alt="{{ config('mail.templates.company_name') }}" class="logo">
            @else
                <h1 class="company-name">{{ config('mail.templates.company_name') }}</h1>
            @endif
        </div>
        
        <!-- Content -->
        <div class="content">
            @yield('content')
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <div class="footer-links">
                <a href="{{ config('app.url') }}">Home</a>
                <a href="{{ config('app.url') }}/about">About</a>
                <a href="{{ config('app.url') }}/contact">Contact</a>
                <a href="{{ config('app.url') }}/privacy">Privacy</a>
            </div>
            
            <div class="social-links">
                <a href="#">Twitter</a>
                <a href="#">Facebook</a>
                <a href="#">LinkedIn</a>
            </div>
            
            @if(config('mail.templates.company_address'))
                <p>{{ config('mail.templates.company_address') }}</p>
            @endif
            
            <div class="unsubscribe">
                <p>
                    Don't want to receive these emails? 
                    <a href="{{ config('mail.templates.unsubscribe_url') }}">Unsubscribe</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html> 