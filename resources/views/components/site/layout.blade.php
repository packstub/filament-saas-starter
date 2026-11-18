@props([
    // The page's own title; the home page leaves it out and gets "App name: tagline".
    'title' => null,
    'description' => config('site.description'),
])

@php
    $fullTitle = $title ? $title.' · '.config('app.name') : config('app.name').': '.config('site.tagline');
    $canonical = url()->current();
    // Link previews (Slack, X, LinkedIn…) show this 1200×630 picture. Replace it with your own.
    $image = file_exists(public_path('images/og.png')) ? asset('images/og.png') : null;
    $panel = filament()->getPanel('app');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="light dark">

        <title>{{ $fullTitle }}</title>
        <meta name="description" content="{{ $description }}">
        <link rel="canonical" href="{{ $canonical }}">

        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ config('app.name') }}">
        <meta property="og:title" content="{{ $fullTitle }}">
        <meta property="og:description" content="{{ $description }}">
        <meta property="og:url" content="{{ $canonical }}">
        @if ($image)
            <meta property="og:image" content="{{ $image }}">
            <meta property="og:image:width" content="1200">
            <meta property="og:image:height" content="630">
        @endif
        <meta name="twitter:card" content="{{ $image ? 'summary_large_image' : 'summary' }}">
        <meta name="twitter:title" content="{{ $fullTitle }}">
        <meta name="twitter:description" content="{{ $description }}">
        @if ($image)
            <meta name="twitter:image" content="{{ $image }}">
        @endif
        @if (config('site.twitter'))
            <meta name="twitter:site" content="{{ '@'.config('site.twitter') }}">
        @endif

        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
        <link rel="sitemap" type="application/xml" href="{{ route('sitemap') }}">

        {{ $head ?? '' }}

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="flex min-h-screen flex-col bg-white font-sans text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
        <a href="#main" class="sr-only rounded-lg bg-indigo-600 px-4 py-2 text-white focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-50">Skip to content</a>

        <header class="sticky top-0 z-40 border-b border-slate-200/70 bg-white/85 backdrop-blur dark:border-slate-800/70 dark:bg-slate-950/85">
            <div class="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-6 py-4">
                <a href="{{ route('home') }}" class="flex items-center gap-2 text-lg font-semibold tracking-tight">
                    <span aria-hidden="true" class="grid size-8 place-items-center rounded-lg bg-indigo-600 text-sm font-bold text-white">{{ mb_substr(config('app.name'), 0, 1) }}</span>
                    {{ config('app.name') }}
                </a>

                <nav aria-label="Main" class="flex items-center gap-1 text-sm font-medium">
                    <a href="{{ route('home') }}#features" class="hidden rounded-lg px-3 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900 sm:block dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white">Features</a>
                    <a href="{{ route('home') }}#pricing" class="hidden rounded-lg px-3 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900 sm:block dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white">Pricing</a>
                    <a href="{{ route('home') }}#faq" class="hidden rounded-lg px-3 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900 sm:block dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white">FAQ</a>

                    @auth
                        <a href="{{ $panel->getUrl() }}" class="ml-2 rounded-lg bg-indigo-600 px-4 py-2 text-white shadow-sm hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Open the app</a>
                    @else
                        <a href="{{ $panel->getLoginUrl() }}" class="rounded-lg px-3 py-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white">Log in</a>
                        <a href="{{ $panel->getRegistrationUrl() }}" class="ml-1 rounded-lg bg-indigo-600 px-4 py-2 text-white shadow-sm hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">Sign up</a>
                    @endauth
                </nav>
            </div>
        </header>

        <main id="main" class="flex-1">
            {{ $slot }}
        </main>

        <footer class="border-t border-slate-200 dark:border-slate-800">
            <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 px-6 py-10 text-sm text-slate-600 sm:flex-row sm:items-center sm:justify-between dark:text-slate-400">
                <p>&copy; {{ now()->year }} {{ config('site.company') }}. All rights reserved.</p>

                <nav aria-label="Footer" class="flex flex-wrap gap-x-6 gap-y-2">
                    <a href="{{ route('home') }}#pricing" class="hover:text-slate-900 dark:hover:text-white">Pricing</a>
                    <a href="{{ route('home') }}#faq" class="hover:text-slate-900 dark:hover:text-white">FAQ</a>
                    <a href="{{ route('terms') }}" class="hover:text-slate-900 dark:hover:text-white">Terms</a>
                    <a href="{{ route('privacy') }}" class="hover:text-slate-900 dark:hover:text-white">Privacy</a>
                    <a href="mailto:{{ config('site.contact_email') }}" class="hover:text-slate-900 dark:hover:text-white">Contact</a>
                </nav>
            </div>
        </footer>
    </body>
</html>
