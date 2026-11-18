@php($company = config('site.company'))

<x-site.legal :title="__('site.terms.title')" :updated="__('site.legal.date')">
    <p>{{ __('site.terms.intro', ['app' => config('app.name'), 'company' => $company]) }}</p>

    <h2>{{ __('site.terms.account.heading') }}</h2>
    <p>{{ __('site.terms.account.body') }}</p>

    <h2>{{ __('site.terms.workspaces.heading') }}</h2>
    <p>{{ __('site.terms.workspaces.body') }}</p>

    <h2>{{ __('site.terms.plans.heading') }}</h2>
    <ul>
        <li>{{ __('site.terms.plans.billing') }}</li>
        <li>{{ __('site.terms.plans.placeholder') }}</li>
        <li>{{ __('site.terms.plans.cancel') }}</li>
    </ul>

    <h2>{{ __('site.terms.content.heading') }}</h2>
    <p>{{ __('site.terms.content.body') }}</p>

    <h2>{{ __('site.terms.use.heading') }}</h2>
    <p>{{ __('site.terms.use.body') }}</p>

    <h2>{{ __('site.terms.availability.heading') }}</h2>
    <p>{{ __('site.terms.availability.body') }}</p>

    <h2>{{ __('site.terms.liability.heading') }}</h2>
    <p>{{ __('site.terms.liability.body') }}</p>

    <h2>{{ __('site.legal.contact') }}</h2>
    <p>{!! __('site.legal.contact_terms', ['email' => '<a href="mailto:'.e(config('site.contact_email')).'">'.e(config('site.contact_email')).'</a>']) !!}</p>
</x-site.legal>
