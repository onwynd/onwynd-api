@extends('emails.layouts.main')

@section('content')

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:32px;font-weight:800;line-height:1.2;text-align:center;color:#4b3425;margin-bottom:8px;">
    Weekly Performance Report
</div>
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;font-weight:500;text-align:center;color:#926247;margin-bottom:32px;">
    Period: {{ $startDate }} — {{ $endDate }}
</div>

{{-- Metrics Grid --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:28px;">
    <tr>
        <td width="50%" style="padding:6px 6px 6px 0;">
            <div style="background:#f7f4f2;border:1px solid #e8ddd9;border-radius:16px;padding:18px;text-align:center;">
                <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:#926247;margin-bottom:8px;">New Users</div>
                <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:28px;font-weight:800;color:#4b3425;">{{ $metrics['new_users'] }}</div>
                <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:12px;font-weight:600;color:{{ $metrics['user_growth'] >= 0 ? '#9bb068' : '#fe814b' }};margin-top:4px;">
                    {{ $metrics['user_growth'] >= 0 ? '+' : '' }}{{ $metrics['user_growth'] }}% vs last week
                </div>
            </div>
        </td>
        <td width="50%" style="padding:6px 0 6px 6px;">
            <div style="background:#f7f4f2;border:1px solid #e8ddd9;border-radius:16px;padding:18px;text-align:center;">
                <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:#926247;margin-bottom:8px;">Revenue</div>
                <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:28px;font-weight:800;color:#4b3425;">{{ $metrics['revenue'] }}</div>
                <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:12px;font-weight:600;color:{{ $metrics['revenue_growth'] >= 0 ? '#9bb068' : '#fe814b' }};margin-top:4px;">
                    {{ $metrics['revenue_growth'] >= 0 ? '+' : '' }}{{ $metrics['revenue_growth'] }}% vs last week
                </div>
            </div>
        </td>
    </tr>
    <tr>
        <td width="50%" style="padding:6px 6px 0 0;">
            <div style="background:#f7f4f2;border:1px solid #e8ddd9;border-radius:16px;padding:18px;text-align:center;">
                <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:#926247;margin-bottom:8px;">Active Therapists</div>
                <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:28px;font-weight:800;color:#4b3425;">{{ $metrics['active_therapists'] }}</div>
            </div>
        </td>
        <td width="50%" style="padding:6px 0 0 6px;">
            <div style="background:#f7f4f2;border:1px solid #e8ddd9;border-radius:16px;padding:18px;text-align:center;">
                <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:#926247;margin-bottom:8px;">Sessions Held</div>
                <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:28px;font-weight:800;color:#4b3425;">{{ $metrics['sessions_held'] }}</div>
            </div>
        </td>
    </tr>
</table>

{{-- AI Analysis --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:20px;">
    <tr>
        <td style="padding-bottom:12px;">
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;font-weight:800;color:#4b3425;padding-bottom:10px;border-bottom:2px solid #e8ddd9;">AI Analysis &amp; Insights</div>
        </td>
    </tr>
    <tr>
        <td>
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:15px;line-height:26px;color:#3d2e22;">{{ $aiAnalysis }}</div>
        </td>
    </tr>
</table>

{{-- Forecast --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:20px;">
    <tr>
        <td style="padding-bottom:12px;">
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;font-weight:800;color:#4b3425;padding-bottom:10px;border-bottom:2px solid #e8ddd9;">Weekly Forecast</div>
        </td>
    </tr>
    <tr>
        <td>
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:15px;line-height:26px;color:#3d2e22;">
                Based on current trends, we project <strong style="color:#9bb068;">{{ $forecast['projected_revenue'] }}</strong> in revenue and <strong style="color:#9bb068;">{{ $forecast['projected_users'] }}</strong> new signups next week.
            </div>
        </td>
    </tr>
</table>

{{-- Action Steps --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:32px;">
    <tr>
        <td style="padding-bottom:12px;">
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;font-weight:800;color:#4b3425;padding-bottom:10px;border-bottom:2px solid #e8ddd9;">Recommended Actions</div>
        </td>
    </tr>
    <tr>
        <td style="padding-top:12px;">
            @foreach($actionSteps as $step)
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:10px;">
                <tr>
                    <td style="vertical-align:top;width:20px;padding-top:5px;"><div style="width:8px;height:8px;border-radius:50%;background-color:#9bb068;"></div></td>
                    <td style="padding-left:12px;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;line-height:22px;color:#3d2e22;">{{ $step }}</td>
                </tr>
            </table>
            @endforeach
        </td>
    </tr>
</table>

<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td align="center">
            <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td align="center" style="border-radius:123px;background-color:#9bb068;">
                        <a href="{{ config('frontend.dashboard_url') . '/admin/dashboard' }}" style="display:inline-block;padding:16px 40px;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:123px;">
                            Open Admin Dashboard
                        </a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

@endsection
