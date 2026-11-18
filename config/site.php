<?php

return [

    /*
    |--------------------------------------------------------------------------
    | The public site
    |--------------------------------------------------------------------------
    |
    | What the home page, the Terms and Privacy pages and the meta tags say
    | about your product. The copy itself is in resources/views/site; these
    | are the few facts used in more than one place.
    |
    | `description` is the meta description and the text search engines and
    | link previews show (about 150 characters). `company` and `contact_email`
    | appear in the footer and on the legal pages. `twitter` is the X/Twitter
    | handle for link previews, without the @, or null.
    |
    */

    'tagline' => env('SITE_TAGLINE', 'Projects for your whole team, in one place'),

    'description' => env('SITE_DESCRIPTION', 'Plan, track and ship projects together. Invite your team, pick the plan that fits, and start for free.'),

    'company' => env('SITE_COMPANY', env('APP_NAME', 'Laravel')),

    'contact_email' => env('SITE_CONTACT_EMAIL', env('MAIL_FROM_ADDRESS', 'hello@example.com')),

    'twitter' => env('SITE_TWITTER'),

    /*
    | While true, the Terms and Privacy pages are the kit's placeholders: they
    | carry a "Replace with your own" notice and `noindex`, and stay out of the
    | sitemap. Set it to false once both pages hold your own text.
    */

    'legal_placeholders' => (bool) env('SITE_LEGAL_PLACEHOLDERS', true),

];
