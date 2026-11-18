@php($company = config('site.company'))

<x-site.legal title="Terms of Service" updated="[date]">
    <p>These terms apply when you use {{ config('app.name') }} (the "service"), provided by {{ $company }}. By creating an account you agree to them.</p>

    <h2>Your account</h2>
    <p>You are responsible for keeping your sign-in details safe and for what happens under your account. Tell us straight away if you think someone else has used it.</p>

    <h2>Workspaces and your team</h2>
    <p>A workspace belongs to its owners. Owners and admins decide who joins it and what each member may do, and they are responsible for the people they invite.</p>

    <h2>Plans and payment</h2>
    <ul>
        <li>Paid plans are billed per workspace, in advance, monthly or yearly. Payments are handled by our payment provider.</li>
        <li>[Describe free trials, renewals, refunds, taxes and price changes.]</li>
        <li>You can cancel at any time from the Billing page; the plan runs until the end of the period you paid for.</li>
    </ul>

    <h2>Your content</h2>
    <p>What you put into the service stays yours. You give us permission to store and process it only to provide the service to you. You can download it or delete it at any time.</p>

    <h2>Acceptable use</h2>
    <p>[Describe what customers may not do with the service.]</p>

    <h2>Availability and changes</h2>
    <p>[Describe the service level you offer, how you announce changes to the service and to these terms, and how either side can end the agreement.]</p>

    <h2>Liability</h2>
    <p>[Your limitation of liability and governing law.]</p>

    <h2>Contact</h2>
    <p>Questions about these terms: <a href="mailto:{{ config('site.contact_email') }}">{{ config('site.contact_email') }}</a>.</p>
</x-site.legal>
