@extends('emails.layouts.main')

@section('content')

{{-- Icon --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td align="center" style="padding-bottom:28px;">
            <div style="width:72px;height:72px;border-radius:50%;background-color:#ffd2c2;display:inline-block;line-height:72px;text-align:center;margin:0 auto;">
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:inline-block;vertical-align:middle;">
                    <path d="M13 3a9 9 0 0 0-9 9H1l3.89 3.89.07.14L9 12H6c0-3.87 3.13-7 7-7s7 3.13 7 7-3.13 7-7 7c-1.93 0-3.68-.79-4.94-2.06l-1.42 1.42C8.27 19.99 10.51 21 13 21a9 9 0 0 0 0-18z" fill="#fe814b"/>
                </svg>
            </div>
        </td>
    </tr>
</table>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:32px;font-weight:800;line-height:1.2;text-align:center;color:#4b3425;margin-bottom:8px;">
    We've been thinking of you
</div>
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;font-weight:500;line-height:1.5;text-align:center;color:#926247;margin-bottom:32px;">
    Life gets full. Caring for yourself matters too.
</div>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;line-height:28px;color:#3d2e22;margin-bottom:28px;">
    Hey <strong style="color:#4b3425;">{{ $user->first_name }}</strong>,<br><br>
    You joined {{ config('app.name') }} for a reason — and that reason still stands. Sometimes self-care slips to the bottom of the list. We're not here to add pressure, just to gently say: even a 2-minute check-in can shift your entire day.
</div>

<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:28px;border:1px solid #e8ddd9;border-radius:16px;overflow:hidden;">
    <tr>
        <td style="padding:20px 24px;background-color:#f7f4f2;">
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:#9bb068;margin-bottom:16px;">Quick ways to start again</div>

            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:12px;">
                <tr>
                    <td style="vertical-align:top;width:20px;padding-top:5px;"><div style="width:8px;height:8px;border-radius:50%;background-color:#9bb068;"></div></td>
                    <td style="padding-left:12px;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;color:#4b3425;">Talk to your AI Companion — no judgement, no agenda</td>
                </tr>
            </table>
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:12px;">
                <tr>
                    <td style="vertical-align:top;width:20px;padding-top:5px;"><div style="width:8px;height:8px;border-radius:50%;background-color:#fe814b;"></div></td>
                    <td style="padding-left:12px;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;color:#4b3425;">Do a 5-minute breathing exercise</td>
                </tr>
            </table>
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                    <td style="vertical-align:top;width:20px;padding-top:5px;"><div style="width:8px;height:8px;border-radius:50%;background-color:#ffce5c;"></div></td>
                    <td style="padding-left:12px;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;color:#4b3425;">Log how you're feeling right now</td>
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
                    <td align="center" style="border-radius:123px;background-color:#fe814b;">
                        <a href="{{ $ctaUrl }}" style="display:inline-block;padding:16px 40px;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:123px;">
                            Start Fresh Now
                        </a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

@endsection
