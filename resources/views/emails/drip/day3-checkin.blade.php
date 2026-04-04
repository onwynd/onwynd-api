@extends('emails.layouts.main')

@section('content')

{{-- Icon --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td align="center" style="padding-bottom:28px;">
            <div style="width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,#e5ead7 0%,#c8d5aa 100%);display:inline-block;line-height:72px;text-align:center;margin:0 auto;">
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:inline-block;vertical-align:middle;">
                    <path d="M12 2a10 10 0 1 0 0 20A10 10 0 0 0 12 2zm0 18a8 8 0 1 1 0-16 8 8 0 0 1 0 16zm-1-11h2v2h-2V9zm0 4h2v6h-2v-6z" fill="#9bb068"/>
                </svg>
            </div>
        </td>
    </tr>
</table>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:32px;font-weight:800;line-height:1.2;text-align:center;color:#4b3425;margin-bottom:8px;">
    Day 3 — how are you doing?
</div>
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;font-weight:500;line-height:1.5;text-align:center;color:#926247;margin-bottom:32px;">
    A quick check-in from your {{ config('app.name') }} team.
</div>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;line-height:28px;color:#3d2e22;margin-bottom:28px;">
    Hey <strong style="color:#4b3425;">{{ $user->first_name }}</strong> 👋<br><br>
    It's been 3 days since you joined {{ config('app.name') }} — and we wanted to check in. How are you feeling so far? A quick mood log takes less than 30 seconds and helps us tailor your experience to what you actually need.
</div>

{{-- Mood check card --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:28px;border:1px solid #e8ddd9;border-radius:16px;overflow:hidden;">
    <tr>
        <td style="padding:20px 24px;background-color:#f7f4f2;text-align:center;">
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;color:#926247;margin-bottom:4px;">Today's check-in takes just</div>
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:32px;font-weight:800;color:#9bb068;">30 seconds</div>
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
                            Log Today's Mood
                        </a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

@endsection
