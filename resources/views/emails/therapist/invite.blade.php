@component('mail::message')

@php
    $onwyndLogo = \App\Models\Setting::getValue('logo_url') ?? (config('app.url') . '/img/logo.png');
@endphp
<div style="text-align:center;margin-bottom:24px;">
  <img src="{{ $onwyndLogo }}" alt="{{ config('app.name', 'Onwynd') }}" style="max-height:50px;max-width:160px;display:inline-block;">
</div>

# You're Invited to Join as a Therapist

Hi there,

**{{ $inviterName }}** from the {{ config('app.name') }} team has personally invited you to join our platform as a licensed therapist.

{{ config('app.name') }} connects therapists with individuals and corporate organisations across Africa who need mental health support. You set your own rates, manage your schedule, and get paid weekly.

---

**Why join {{ config('app.name') }}?**

- Set your own session rate — any amount, no minimum
- Founding Therapists pay 3% less commission, locked for 24 months
- Weekly payouts, no minimum threshold
- Individual and corporate clients on one platform

@if ($invite->notes)
> *Personal note from {{ $inviterName }}:* {{ $invite->notes }}
@endif

---

This invite is for **{{ $invite->email }}** and expires on **{{ $invite->expires_at->format('D, M j Y') }}**.

@component('mail::button', ['url' => $signupUrl, 'color' => 'primary'])
Accept Invite & Create Your Profile
@endcomponent

If you have any questions before joining, reply to this email — we'd love to chat.

Thanks,
**The {{ config('app.name') }} Team**

*Didn't expect this? You can safely ignore this email.*

@endcomponent
