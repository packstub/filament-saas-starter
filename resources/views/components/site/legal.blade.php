@props(['title', 'updated'])

{{-- The frame of the Terms and Privacy pages, with the notice that they are placeholders. --}}
<x-site.layout :title="$title" :description="$title.' of '.config('app.name').'.'">
    {{-- Until the text is your own (config/site.php, `legal_placeholders`): kept out of search engines. --}}
    @if (config('site.legal_placeholders'))
        <x-slot:head>
            <meta name="robots" content="noindex">
        </x-slot:head>
    @endif

    <article class="mx-auto w-full max-w-3xl px-6 py-16">
        @if (config('site.legal_placeholders'))
        <div role="note" class="mb-10 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">
            <strong class="font-semibold">Replace with your own.</strong>
            This page is a placeholder that came with the starter kit, not legal advice. Write it with someone who knows the law where you and your customers are.
        </div>
        @endif

        <h1 class="text-4xl font-semibold tracking-tight">{{ $title }}</h1>
        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Last updated {{ $updated }}</p>

        <div class="mt-10 space-y-6 text-slate-700 dark:text-slate-300 [&_a]:font-medium [&_a]:text-indigo-600 dark:[&_a]:text-indigo-400 [&_h2]:mt-10 [&_h2]:text-xl [&_h2]:font-semibold [&_h2]:text-slate-900 dark:[&_h2]:text-white [&_ul]:list-disc [&_ul]:space-y-2 [&_ul]:pl-6">
            {{ $slot }}
        </div>
    </article>
</x-site.layout>
