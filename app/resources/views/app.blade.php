<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <link rel="icon" type="image/png" href="{{ asset('images/monogram.png') }}">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/Pages/{$page['component']}.tsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        <div id="app" data-page="{{ json_encode($page) }}">
            <div style="min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #f8fafc; padding: 40px 16px; color: #020617; font-family: Figtree, ui-sans-serif, system-ui, sans-serif;">
                <div style="width: 100%; max-width: 448px; border: 1px solid #e2e8f0; border-radius: 8px; background: #ffffff; padding: 32px; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08); text-align: center;">
                    <div style="display: inline-flex; height: 56px; width: 56px; align-items: center; justify-content: center; border-radius: 9999px; background: #eff6ff; color: #1d4ed8;">
                        <div style="height: 28px; width: 28px; border-radius: 9999px; border: 2px solid #bfdbfe; border-top-color: #1d4ed8; animation: dius-static-spin 0.8s linear infinite;"></div>
                    </div>
                    <h1 style="margin: 20px 0 0; font-size: 18px; line-height: 28px; font-weight: 600;">Setting up your workspace...</h1>
                    <p style="margin: 8px 0 0; color: #475569; font-size: 14px; line-height: 24px;">Please wait while DIUS Management Portal prepares your session.</p>
                    <p style="margin: 12px 0 0; color: #1e40af; font-size: 12px; line-height: 18px; font-weight: 600;">Starting the workspace... Free prototype services may take a few seconds to wake up.</p>
                </div>
            </div>
        </div>
        <style>
            @keyframes dius-static-spin { to { transform: rotate(360deg); } }
        </style>
    </body>
</html>