@php($company = config('site.company'))

<x-site.legal title="Privacy Policy" updated="[date]">
    <p>This policy explains what personal data {{ $company }} collects when you use {{ config('app.name') }}, why, and what you can do about it.</p>

    <h2>What we collect</h2>
    <ul>
        <li><strong>Your account:</strong> your name, e-mail address and a hashed password; the two-factor secret if you turn it on.</li>
        <li><strong>Your workspaces:</strong> what you and your team add, who is a member and with which role, and invitations.</li>
        <li><strong>Activity:</strong> a log of membership, role and plan changes in each workspace, kept for [365 days].</li>
        <li><strong>Billing:</strong> your plan and subscription. Card details go to our payment provider, Stripe, and never reach our servers.</li>
        <li><strong>Technical data:</strong> a session cookie that keeps you signed in, and server logs.</li>
    </ul>

    <h2>Why we use it</h2>
    <p>To provide the service, to bill for it, to keep it secure and to answer you when you write to us. [Add any other purpose, such as product e-mails or analytics, and its legal basis.]</p>

    <h2>Who else sees it</h2>
    <p>The service providers that help us run the service: [hosting], Stripe for payments, [e-mail delivery]. [List each, with where it stores data.]</p>

    <h2>Your rights</h2>
    <ul>
        <li>Download everything we hold about you from your Profile page, as one file.</li>
        <li>Delete your account from your Profile page; workspace owners can export or delete a workspace from its settings.</li>
        <li>[Describe how to ask for a correction, object to a use, or complain to a supervisory authority.]</li>
    </ul>

    <h2>Cookies</h2>
    <p>We use only the cookies the service needs to work: one for your session and one that protects forms. [List any others.]</p>

    <h2>Contact</h2>
    <p>Questions about your data: <a href="mailto:{{ config('site.contact_email') }}">{{ config('site.contact_email') }}</a>.</p>
</x-site.legal>
