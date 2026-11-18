{{--
    The home page. The copy describes the example product (projects for teams):
    rewrite it for yours. The pricing section is generated from
    config/packstub-features.php (plans, what each includes) and
    config/billing.php (amounts, the yearly switch, the free trial).
--}}
@php
    $registerUrl = filament()->getPanel('app')->getRegistrationUrl();

    $features = [
        ['title' => 'A workspace for every team', 'body' => 'Everything lives in a workspace: your projects, your people, your plan. Belong to as many as you like and switch in one click.', 'icon' => 'M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21'],
        ['title' => 'Bring your team along', 'body' => 'Invite people by e-mail or with a link. Owners, admins and members each see and do what their role allows.', 'icon' => 'M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z'],
        ['title' => 'Plans that grow with you', 'body' => 'Start on the free plan and move up when you need more room. Your usage is always one click away.', 'icon' => 'M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941'],
        ['title' => 'Secure by default', 'body' => 'Verified e-mail addresses, strong passwords and two-factor sign-in with any authenticator app.', 'icon' => 'M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z'],
        ['title' => 'Know who did what', 'body' => 'An activity log shows who joined, who left, who changed roles and when the plan changed.', 'icon' => 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z'],
        ['title' => 'Your data stays yours', 'body' => 'Download everything as one file whenever you like, and close your account or workspace yourself.', 'icon' => 'M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3'],
    ];

    $faqs = [
        ['q' => 'Can I try it for free?', 'a' => 'Yes. Every new workspace starts on the free plan, with no card needed.'.($trialDays ? " Paid plans come with a {$trialDays}-day free trial, so you can try everything before your first payment." : '')],
        ['q' => 'Who is billed: me or my team?', 'a' => 'The workspace is. One subscription covers everyone in it, and owners and admins manage it from the Billing page.'],
        ['q' => 'Can I change plans later?', 'a' => 'Any time. Moving up gives you the new limits straight away; moving down keeps everything you already have.'],
        ['q' => 'How do I cancel?', 'a' => 'From the Billing page, in a couple of clicks. Your workspace moves to the free plan at the end of the period you paid for.'],
        ['q' => 'How is my data protected?', 'a' => 'Each workspace only ever sees its own data. You can turn on two-factor sign-in, and download or delete your data whenever you like.'],
    ];

    // The interval shown first on the pricing cards; the switch shows the others.
    $defaultInterval = array_key_first($intervals);

    // Written out in full so Tailwind finds the classes: an amount shows while its interval is picked.
    $switch = [
        'monthly' => 'group-has-[#interval-monthly:checked]/pricing:block group-has-[#interval-yearly:checked]/pricing:hidden',
        'yearly' => 'group-has-[#interval-yearly:checked]/pricing:block group-has-[#interval-monthly:checked]/pricing:hidden',
    ];
    $columns = [1 => 'lg:grid-cols-1', 2 => 'lg:grid-cols-2', 3 => 'lg:grid-cols-3', 4 => 'lg:grid-cols-4'];
@endphp

<x-site.layout>
    {{-- Hero --}}
    <section class="relative overflow-hidden">
        <div aria-hidden="true" class="pointer-events-none absolute inset-x-0 -top-40 -z-10 flex justify-center blur-3xl">
            <div class="aspect-[3/2] w-[56rem] bg-gradient-to-tr from-indigo-300 via-sky-200 to-fuchsia-200 opacity-40 dark:from-indigo-700 dark:via-sky-800 dark:to-fuchsia-800 dark:opacity-25" style="clip-path: polygon(30% 0, 100% 20%, 80% 90%, 10% 100%, 0 40%)"></div>
        </div>

        <div class="mx-auto grid w-full max-w-6xl items-center gap-16 px-6 pt-20 pb-24 lg:grid-cols-2 lg:pt-28">
            <div>
                @if ($trialDays)
                    <p class="mb-6 inline-flex items-center gap-2 rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-sm font-medium text-indigo-700 dark:border-indigo-900 dark:bg-indigo-950 dark:text-indigo-300">
                        {{ $trialDays }}-day free trial on every paid plan
                    </p>
                @endif

                <h1 class="text-4xl font-semibold tracking-tight text-balance sm:text-6xl">
                    {{ config('site.tagline') }}
                </h1>

                <p class="mt-6 max-w-xl text-lg text-pretty text-slate-600 dark:text-slate-300">
                    {{ config('site.description') }}
                </p>

                <div class="mt-10 flex flex-wrap items-center gap-4">
                    <a href="{{ $registerUrl }}" class="rounded-lg bg-indigo-600 px-5 py-3 font-medium text-white shadow-sm hover:bg-indigo-500 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                        Create your workspace
                    </a>
                    <a href="#pricing" class="rounded-lg px-5 py-3 font-medium text-slate-700 hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800">
                        See pricing <span aria-hidden="true">&rarr;</span>
                    </a>
                </div>
            </div>

            {{-- A picture of the app, drawn in HTML so it follows light and dark mode. Swap it for a screenshot of yours. --}}
            <div aria-hidden="true" class="rounded-2xl border border-slate-200 bg-white/80 p-2 shadow-xl shadow-indigo-500/10 ring-1 ring-slate-900/5 dark:border-slate-800 dark:bg-slate-900/80 dark:ring-white/5">
                <div class="rounded-xl border border-slate-100 bg-slate-50 p-5 dark:border-slate-800 dark:bg-slate-950">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="grid size-7 place-items-center rounded-md bg-indigo-600 text-xs font-bold text-white">A</span>
                            <span class="text-sm font-semibold">Acme</span>
                        </div>
                        <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">Pro plan</span>
                    </div>

                    <div class="mt-5 grid grid-cols-3 gap-3">
                        @foreach ([['Projects', '12 of 50'], ['Seats', '4 of 10'], ['This week', '+3']] as [$label, $value])
                            <div class="rounded-lg border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">
                                <div class="text-xs text-slate-500 dark:text-slate-400">{{ $label }}</div>
                                <div class="mt-1 text-base font-semibold whitespace-nowrap sm:text-lg">{{ $value }}</div>
                            </div>
                        @endforeach
                    </div>

                    <ul class="mt-5 divide-y divide-slate-200 rounded-lg border border-slate-200 bg-white text-sm dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-900">
                        @foreach ([['Website redesign', 'Active', 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'], ['Mobile app', 'Active', 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'], ['Spring campaign', 'On hold', 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300'], ['Office move', 'Archived', 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300']] as [$name, $status, $badge])
                            <li class="flex items-center justify-between px-4 py-3">
                                <span>{{ $name }}</span>
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badge }}">{{ $status }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </section>

    {{-- Features --}}
    <section id="features" aria-labelledby="features-heading" class="scroll-mt-20 border-t border-slate-200 bg-slate-50 py-24 dark:border-slate-800 dark:bg-slate-900/40">
        <div class="mx-auto w-full max-w-6xl px-6">
            <div class="max-w-2xl">
                <h2 id="features-heading" class="text-3xl font-semibold tracking-tight sm:text-4xl">Everything your team needs to get going</h2>
                <p class="mt-4 text-lg text-slate-600 dark:text-slate-300">Sign up, invite your people and get to work in minutes.</p>
            </div>

            <ul class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($features as $feature)
                    <li class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
                        <span class="grid size-10 place-items-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-950 dark:text-indigo-300">
                            <svg aria-hidden="true" class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $feature['icon'] }}" /></svg>
                        </span>
                        <h3 class="mt-4 font-semibold">{{ $feature['title'] }}</h3>
                        <p class="mt-2 text-sm/6 text-slate-600 dark:text-slate-300">{{ $feature['body'] }}</p>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- Pricing: generated from the plans the app sells. The Monthly / Yearly switch needs no JavaScript. --}}
    <section id="pricing" aria-labelledby="pricing-heading" class="group/pricing scroll-mt-20 py-24">
        <div class="mx-auto w-full max-w-6xl px-6">
            <div class="mx-auto max-w-2xl text-center">
                <h2 id="pricing-heading" class="text-3xl font-semibold tracking-tight sm:text-4xl">Simple pricing for every team</h2>
                <p class="mt-4 text-lg text-slate-600 dark:text-slate-300">
                    Start for free and upgrade when you need more.
                    @if ($trialDays)
                        Paid plans start with a {{ $trialDays }}-day free trial.
                    @endif
                </p>
            </div>

            @if (count($intervals) > 1)
                <fieldset class="mt-10 flex justify-center">
                    <legend class="sr-only">Billing interval</legend>
                    <div class="inline-flex rounded-full border border-slate-200 bg-slate-100 p-1 text-sm font-medium dark:border-slate-800 dark:bg-slate-900">
                        @foreach ($intervals as $interval => $label)
                            <label class="cursor-pointer rounded-full px-4 py-1.5 text-slate-600 has-checked:bg-white has-checked:text-slate-900 has-checked:shadow-sm has-focus-visible:outline-2 has-focus-visible:outline-indigo-600 dark:text-slate-300 dark:has-checked:bg-slate-700 dark:has-checked:text-white">
                                <input type="radio" name="interval" value="{{ $interval }}" id="interval-{{ $interval }}" class="sr-only" @checked($interval === $defaultInterval)>
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @endif

            <ul class="mx-auto mt-12 grid max-w-md gap-6 lg:max-w-none {{ $columns[min(count($plans), 4)] }}">
                @foreach ($plans as $plan)
                    @php($featured = $loop->count > 2 && $loop->index === 1)
                    <li data-plan="{{ $plan['key'] }}" @class([
                        'flex flex-col rounded-2xl border p-8',
                        'border-indigo-600 shadow-xl shadow-indigo-500/10 ring-1 ring-indigo-600 dark:border-indigo-400 dark:ring-indigo-400' => $featured,
                        'border-slate-200 dark:border-slate-800' => ! $featured,
                    ])>
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="text-lg font-semibold">{{ $plan['label'] }}</h3>
                            @if ($featured)
                                <span class="rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">Most popular</span>
                            @endif
                        </div>

                        @if ($plan['description'])
                            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ $plan['description'] }}</p>
                        @endif

                        <div class="mt-6 min-h-12">
                            @foreach ($intervals as $interval => $label)
                                {{-- A plan not sold at this interval shows its first price. --}}
                                @php($amount = $plan['amounts'][$interval] ?? collect($plan['amounts'])->first())
                                <p data-interval="{{ $interval }}" @class([
                                    'text-3xl font-semibold tracking-tight',
                                    'hidden' => $interval !== $defaultInterval,
                                    $switch[$interval] => count($intervals) > 1,
                                ])>
                                    {{ $amount ?? 'Contact us' }}
                                    @if ($plan['per_seat'] && $amount)
                                        <span class="text-sm font-normal text-slate-500 dark:text-slate-400">per seat</span>
                                    @endif
                                </p>
                            @endforeach
                        </div>

                        <ul class="mt-6 flex-1 space-y-3 text-sm">
                            @foreach ($plan['includes'] as $line)
                                <li class="flex gap-3">
                                    <svg aria-hidden="true" class="size-5 flex-none text-indigo-600 dark:text-indigo-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" /></svg>
                                    {{ $line }}
                                </li>
                            @endforeach
                        </ul>

                        <a href="{{ $registerUrl }}" @class([
                            'mt-8 rounded-lg px-4 py-2.5 text-center text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600',
                            'bg-indigo-600 text-white shadow-sm hover:bg-indigo-500' => $featured,
                            'bg-slate-100 text-slate-900 hover:bg-slate-200 dark:bg-slate-800 dark:text-white dark:hover:bg-slate-700' => ! $featured,
                        ])>
                            {{ ! $plan['paid'] ? 'Start for free' : ($trialDays ? 'Start your free trial' : 'Get started') }}
                            <span class="sr-only">on the {{ $plan['label'] }} plan</span>
                        </a>
                    </li>
                @endforeach
            </ul>

            <p class="mt-8 text-center text-sm text-slate-500 dark:text-slate-400">
                You choose a paid plan from the Billing page of your workspace, after you sign up.
            </p>
        </div>
    </section>

    {{-- FAQ --}}
    <section id="faq" aria-labelledby="faq-heading" class="scroll-mt-20 border-t border-slate-200 bg-slate-50 py-24 dark:border-slate-800 dark:bg-slate-900/40">
        <div class="mx-auto w-full max-w-3xl px-6">
            <h2 id="faq-heading" class="text-3xl font-semibold tracking-tight sm:text-4xl">Questions and answers</h2>

            <div class="mt-10 divide-y divide-slate-200 dark:divide-slate-800">
                @foreach ($faqs as $faq)
                    <details class="group py-5">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-medium [&::-webkit-details-marker]:hidden">
                            {{ $faq['q'] }}
                            <svg aria-hidden="true" class="size-5 flex-none text-slate-400 transition group-open:rotate-45" viewBox="0 0 20 20" fill="currentColor"><path d="M10.75 4.75a.75.75 0 0 0-1.5 0v4.5h-4.5a.75.75 0 0 0 0 1.5h4.5v4.5a.75.75 0 0 0 1.5 0v-4.5h4.5a.75.75 0 0 0 0-1.5h-4.5v-4.5Z" /></svg>
                        </summary>
                        <p class="mt-3 text-slate-600 dark:text-slate-300">{{ $faq['a'] }}</p>
                    </details>
                @endforeach
            </div>

            <p class="mt-10 text-slate-600 dark:text-slate-300">
                Something else? Write to us at <a href="mailto:{{ config('site.contact_email') }}" class="font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">{{ config('site.contact_email') }}</a>.
            </p>
        </div>
    </section>

    {{-- Call to action --}}
    <section class="py-24">
        <div class="mx-auto w-full max-w-6xl px-6">
            <div class="rounded-3xl bg-indigo-600 px-8 py-16 text-center text-white shadow-xl dark:bg-indigo-700">
                <h2 class="text-3xl font-semibold tracking-tight sm:text-4xl">Ready when you are</h2>
                <p class="mx-auto mt-4 max-w-xl text-lg text-indigo-100">Create your workspace in under a minute and invite your team.</p>
                <a href="{{ $registerUrl }}" class="mt-8 inline-block rounded-lg bg-white px-5 py-3 font-medium text-indigo-700 shadow-sm hover:bg-indigo-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">
                    Get started for free
                </a>
            </div>
        </div>
    </section>
</x-site.layout>
