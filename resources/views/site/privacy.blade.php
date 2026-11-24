@php($company = config('site.company'))

<x-site.legal :title="__('ui_site.privacy.title')" :updated="__('ui_site.legal.date')">
    <p>{{ __('ui_site.privacy.intro', ['company' => $company, 'app' => config('app.name')]) }}</p>

    <h2>{{ __('ui_site.privacy.collect.heading') }}</h2>
    <ul>
        <li>{!! __('ui_site.privacy.collect.account') !!}</li>
        <li>{!! __('ui_site.privacy.collect.workspaces') !!}</li>
        <li>{!! __('ui_site.privacy.collect.activity') !!}</li>
        <li>{!! __('ui_site.privacy.collect.billing') !!}</li>
        <li>{!! __('ui_site.privacy.collect.technical') !!}</li>
    </ul>

    <h2>{{ __('ui_site.privacy.why.heading') }}</h2>
    <p>{{ __('ui_site.privacy.why.body') }}</p>

    <h2>{{ __('ui_site.privacy.who.heading') }}</h2>
    <p>{{ __('ui_site.privacy.who.body') }}</p>

    <h2>{{ __('ui_site.privacy.rights.heading') }}</h2>
    <ul>
        <li>{{ __('ui_site.privacy.rights.download') }}</li>
        <li>{{ __('ui_site.privacy.rights.delete') }}</li>
        <li>{{ __('ui_site.privacy.rights.placeholder') }}</li>
    </ul>

    <h2>{{ __('ui_site.privacy.cookies.heading') }}</h2>
    <p>{{ __('ui_site.privacy.cookies.body') }}</p>

    <h2>{{ __('ui_site.legal.contact') }}</h2>
    <p>{!! __('ui_site.legal.contact_privacy', ['email' => '<a href="mailto:'.e(config('site.contact_email')).'">'.e(config('site.contact_email')).'</a>']) !!}</p>
</x-site.legal>
