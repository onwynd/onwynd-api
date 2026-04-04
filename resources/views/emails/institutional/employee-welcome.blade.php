@component('mail::message')

{{-- Institution logo with Onwynd fallback --}}
@if ($institutionLogo)
<div style="text-align:center;margin-bottom:24px;">
  <img src="{{ $institutionLogo }}" alt="{{ $institutionName }}" style="max-height:60px;max-width:200px;display:inline-block;">
</div>
@elseif ($logoUrl)
<div style="text-align:center;margin-bottom:24px;">
  <img src="{{ $logoUrl }}" alt="{{ config('app.name', 'Onwynd') }}" style="max-height:50px;max-width:160px;display:inline-block;">
</div>
@else
<div style="text-align:center;margin-bottom:24px;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:26px;font-weight:800;letter-spacing:-0.5px;color:#2A7A6A;">
  {{ config('app.name', 'Onwynd') }}
</div>
@endif

# Welcome to {{ $institutionName }}, {{ $name }}!

**{{ $institutionName }}** has set up a wellness account for you on {{ config('app.name') }}.
This is a benefit provided by your organisation — for you, with care.

| | |
|---|---|
| **Name** | {{ $name }} |
| **Role** | {{ ucwords(str_replace('_', ' ', $role)) }} |
| **Organisation** | {{ $institutionName }} |
| **Platform** | {{ config('app.name') }} |

@component('mail::button', ['url' => $loginUrl, 'color' => 'primary'])
Set Up Your Password & Log In
@endcomponent

If you did not expect this email, please contact your HR administrator.

Thanks,
**{{ $institutionName }}** via {{ config('app.name') }}

@endcomponent
