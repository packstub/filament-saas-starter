@php($company = config('site.company'))

<x-site.legal :title="__('site.privacy.title')" :updated="__('site.legal.date')">
    <p>{{ __('site.privacy.intro', ['company' => $company, 'app' => config('app.name')]) }}</p>

    <h2>{{ __('site.privacy.collect.heading') }}</h2>
    <ul>
        <li>{!! __('site.privacy.collect.account') !!}</li>
        <li>{!! __('site.privacy.collect.workspaces') !!}</li>
        <li>{!! __('site.privacy.collect.activity') !!}</li>
        <li>{!! __('site.privacy.collect.billing') !!}</li>
        <li>{!! __('site.privacy.collect.technical') !!}</li>
    </ul>

    <h2>{{ __('site.privacy.why.heading') }}</h2>
    <p>{{ __('site.privacy.why.body') }}</p>

    <h2>{{ __('site.privacy.who.heading') }}</h2>
    <p>{{ __('site.privacy.who.body') }}</p>

    <h2>{{ __('site.privacy.rights.heading') }}</h2>
    <ul>
        <li>{{ __('site.privacy.rights.download') }}</li>
        <li>{{ __('site.privacy.rights.delete') }}</li>
        <li>{{ __('site.privacy.rights.placeholder') }}</li>
    </ul>

    <h2>{{ __('site.privacy.cookies.heading') }}</h2>
    <p>{{ __('site.privacy.cookies.body') }}</p>

    <h2>{{ __('site.legal.contact') }}</h2>
    <p>{!! __('site.legal.contact_privacy', ['email' => '<a href="mailto:'.e(config('site.contact_email')).'">'.e(config('site.contact_email')).'</a>']) !!}</p>
</x-site.legal>
