@component('mail::message')

{{-- Onwynd internal employee welcome — always Onwynd-branded --}}
@php
    $onwyndLogo = \App\Models\Setting::getValue('logo_url')
        ?? (config('app.url') . '/img/logo.png');
@endphp
<div style="text-align:center;margin-bottom:24px;">
  <img src="{{ $onwyndLogo }}" alt="{{ config('app.name', 'Onwynd') }}" style="max-height:50px;max-width:160px;display:inline-block;"
       onerror="this.style.display='none';this.nextSibling.style.display='block'">
  <span style="display:none;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:26px;font-weight:800;letter-spacing:-0.5px;color:#2A7A6A;">
    {{ config('app.name', 'Onwynd') }}
  </span>
</div>

# Welcome to the team, {{ $name }}!

You've been added to **{{ config('app.name') }}** as a team member. Your account is ready.

| | |
|---|---|
| **Name** | {{ $name }} |
| **Role** | {{ ucwords(str_replace('_', ' ', $role)) }} |

@component('mail::button', ['url' => $loginUrl, 'color' => 'primary'])
Set Up Your Password & Log In
@endcomponent

If you did not expect this email, please contact HR.

Thanks,
**The {{ config('app.name') }} People Team**

@endcomponent
