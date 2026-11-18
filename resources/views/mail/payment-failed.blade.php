<x-mail::message :workspace="$workspace" :logo="$logo">
# Your payment did not go through

Stripe could not collect the latest payment for **{{ $workspace }}**. It will try again over the next few days, and the workspace keeps its plan in the meantime.

Update the card on the Billing page to settle it now.

<x-mail::button :url="$url">
Open Billing
</x-mail::button>

You receive this as an owner of {{ $workspace }}.
</x-mail::message>
