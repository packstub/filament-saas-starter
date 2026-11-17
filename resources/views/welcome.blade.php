<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name') }}</title>

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="flex min-h-screen flex-col bg-white font-sans text-slate-900 antialiased">
        <header class="mx-auto flex w-full max-w-5xl items-center justify-between px-6 py-6">
            <span class="text-lg font-semibold">{{ config('app.name') }}</span>

            <nav class="flex items-center gap-2 text-sm font-medium">
                @auth
                    <a href="{{ filament()->getPanel('app')->getUrl() }}" class="rounded-lg bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-500">Open the app</a>
                @else
                    <a href="{{ filament()->getPanel('app')->getLoginUrl() }}" class="rounded-lg px-4 py-2 hover:bg-slate-100">Log in</a>
                    <a href="{{ filament()->getPanel('app')->getRegistrationUrl() }}" class="rounded-lg bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-500">Sign up</a>
                @endauth
            </nav>
        </header>

        <main class="mx-auto flex w-full max-w-5xl flex-1 flex-col justify-center px-6 py-16">
            <h1 class="max-w-2xl text-4xl font-semibold tracking-tight sm:text-5xl">
                Your product's home page starts here.
            </h1>

            <p class="mt-6 max-w-xl text-lg text-slate-600">
                Sign up to get a workspace of your own, invite your team and pick a plan.
                This page is <code class="rounded bg-slate-100 px-1.5 py-0.5 text-base">resources/views/welcome.blade.php</code>.
            </p>

            <div class="mt-10">
                <a href="{{ filament()->getPanel('app')->getRegistrationUrl() }}" class="rounded-lg bg-indigo-600 px-5 py-3 font-medium text-white hover:bg-indigo-500">
                    Create your workspace
                </a>
            </div>
        </main>
    </body>
</html>
