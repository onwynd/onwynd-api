@extends('emails.layouts.main')
@php $isTransactional = true; @endphp

@section('content')

{{-- Icon --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td align="center" style="padding-bottom:28px;">
            <div style="width:72px;height:72px;border-radius:50%;background-color:#fff3d4;display:inline-block;line-height:72px;text-align:center;margin:0 auto;">
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:inline-block;vertical-align:middle;">
                    <path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67V7z" fill="#c89b00"/>
                </svg>
            </div>
        </td>
    </tr>
</table>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:32px;font-weight:800;line-height:1.2;text-align:center;color:#4b3425;margin-bottom:8px;">
    We've been thinking of you
</div>
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;font-weight:500;line-height:1.5;text-align:center;color:#926247;margin-bottom:32px;">
    Your wellbeing matters — even on quiet days.
</div>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;line-height:28px;color:#3d2e22;margin-bottom:28px;">
    Hey <strong style="color:#4b3425;">{{ $user->first_name }}</strong>,<br><br>
    It's been <strong style="color:#4b3425;">{{ $daysInactive }} days</strong> since your last activity on {{ config('app.name') }}. Life gets busy — we get it. No judgment here. But your mental health journey is worth returning to, even just for a few minutes.
</div>

{{-- Suggestions --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:28px;border:1px solid #e8ddd9;border-radius:16px;overflow:hidden;">
    <tr>
        <td style="padding:20px 24px;background-color:#f7f4f2;">
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:#9bb068;margin-bottom:16px;">Easy ways to restart</div>

            @foreach($suggestedActivities as $activity => $description)
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:12px;">
                <tr>
                    <td style="vertical-align:top;width:20px;padding-top:3px;">
                        <div style="width:8px;height:8px;border-radius:50%;background-color:#9bb068;margin-top:4px;"></div>
                    </td>
                    <td style="padding-left:12px;">
                        <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;font-weight:700;color:#4b3425;">{{ $activity }}</div>
                        <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;color:#926247;margin-top:2px;">{{ $description }}</div>
                    </td>
                </tr>
            </table>
            @endforeach

            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                    <td style="vertical-align:top;width:20px;padding-top:3px;">
                        <div style="width:8px;height:8px;border-radius:50%;background-color:#fe814b;margin-top:4px;"></div>
                    </td>
                    <td style="padding-left:12px;">
                        <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;font-weight:700;color:#4b3425;">Start small</div>
                        <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;color:#926247;margin-top:2px;">Even 2 minutes of mindfulness can shift your whole day.</div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td align="center">
            <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td align="center" style="border-radius:123px;background-color:#9bb068;">
                        <a href="{{ $ctaUrl }}" style="display:inline-block;padding:16px 40px;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:123px;">
                            {{ $ctaText ?? 'Come Back' }}
                        </a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

@endsection
