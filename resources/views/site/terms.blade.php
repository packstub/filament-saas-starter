@php($company = config('site.company'))

<x-site.legal :title="__('ui_site.terms.title')" :updated="__('ui_site.legal.date')">
    <p>{{ __('ui_site.terms.intro', ['app' => config('app.name'), 'company' => $company]) }}</p>

    <h2>{{ __('ui_site.terms.account.heading') }}</h2>
    <p>{{ __('ui_site.terms.account.body') }}</p>

    <h2>{{ __('ui_site.terms.workspaces.heading') }}</h2>
    <p>{{ __('ui_site.terms.workspaces.body') }}</p>

    <h2>{{ __('ui_site.terms.plans.heading') }}</h2>
    <ul>
        <li>{{ __('ui_site.terms.plans.billing') }}</li>
        <li>{{ __('ui_site.terms.plans.placeholder') }}</li>
        <li>{{ __('ui_site.terms.plans.cancel') }}</li>
    </ul>

    <h2>{{ __('ui_site.terms.content.heading') }}</h2>
    <p>{{ __('ui_site.terms.content.body') }}</p>

    <h2>{{ __('ui_site.terms.use.heading') }}</h2>
    <p>{{ __('ui_site.terms.use.body') }}</p>

    <h2>{{ __('ui_site.terms.availability.heading') }}</h2>
    <p>{{ __('ui_site.terms.availability.body') }}</p>

    <h2>{{ __('ui_site.terms.liability.heading') }}</h2>
    <p>{{ __('ui_site.terms.liability.body') }}</p>

    <h2>{{ __('ui_site.legal.contact') }}</h2>
    <p>{!! __('ui_site.legal.contact_terms', ['email' => '<a href="mailto:'.e(config('site.contact_email')).'">'.e(config('site.contact_email')).'</a>']) !!}</p>
</x-site.legal>
