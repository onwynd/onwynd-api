@component('mail::message')

{{-- Logo: partner logo → Onwynd branding logo → styled Onwynd text mark --}}
@if ($partner->logo)
<div style="text-align:center;margin-bottom:24px;">
  <img src="{{ $partner->logo }}" alt="{{ $partner->name }}" style="max-height:60px;max-width:200px;display:inline-block;">
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

# Biweekly Performance Report

**{{ $partner->name }}** · {{ $periodStart->format('F j') }} – {{ $periodEnd->format('F j, Y') }}

---

## User Activity

| Metric | Value |
|---|---|
| **Total Users** | {{ number_format($stats['total_users']) }} |
| **Active This Period** | {{ number_format($stats['active_users']) }} |
| **New Users Joined** | {{ number_format($stats['new_users']) }} |
| **Engagement Rate** | {{ $stats['engagement_rate'] }}% |

## Sessions

| Metric | Value |
|---|---|
| **Completed Sessions** | {{ number_format($stats['sessions_completed']) }} |
| **Cancelled / No-Show** | {{ number_format($stats['sessions_cancelled']) }} |

---

@if ($stats['engagement_rate'] >= 70)
Your team engagement is **excellent** this period. Keep up the great work!
@elseif ($stats['engagement_rate'] >= 40)
Your team engagement is **moderate**. Consider encouraging more employees to log in and use the platform.
@else
Engagement is **lower than usual** this period. Reach out to your Onwynd account manager for tips on boosting utilisation.
@endif

@component('mail::button', ['url' => rtrim(config('frontend.dashboard_url'), '/') . '/partner/dashboard', 'color' => 'primary'])
View Full Dashboard
@endcomponent

This report covers activity from **{{ $periodStart->format('D, M j Y') }}** to **{{ $periodEnd->format('D, M j Y') }}** for all users linked to your organisation on Onwynd.

Questions? Reply to this email or contact your dedicated account manager.

Thanks,
**The Onwynd Team** via {{ config('app.name', 'Onwynd') }}

@endcomponent
