@extends('emails.layouts.main')

@section('content')

{{-- Icon --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td align="center" style="padding-bottom:28px;">
            <div style="width:72px;height:72px;border-radius:50%;background-color:#e5ead7;display:inline-block;line-height:72px;text-align:center;margin:0 auto;">
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:inline-block;vertical-align:middle;">
                    <path d="M17 12h-5v5h5v-5zM16 1v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-1V1h-2zm3 18H5V8h14v11z" fill="#9bb068"/>
                </svg>
            </div>
        </td>
    </tr>
</table>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:32px;font-weight:800;line-height:1.2;text-align:center;color:#4b3425;margin-bottom:8px;">
    One week in
</div>
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;font-weight:500;line-height:1.5;text-align:center;color:#926247;margin-bottom:32px;">
    You're building something real, {{ $user->first_name }}.
</div>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;line-height:28px;color:#3d2e22;margin-bottom:28px;">
    Every check-in, every breath, every journal entry is a vote for the version of you that feels better. One week is worth celebrating. Keep going.
</div>

{{-- Next steps card --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:28px;border:1px solid #e8ddd9;border-radius:16px;overflow:hidden;">
    <tr>
        <td style="padding:20px 24px;background-color:#f7f4f2;">
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:#9bb068;margin-bottom:16px;">What's waiting for you this week</div>

            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:12px;">
                <tr>
                    <td style="vertical-align:top;width:20px;padding-top:5px;">
                        <div style="width:8px;height:8px;border-radius:50%;background-color:#9bb068;"></div>
                    </td>
                    <td style="padding-left:12px;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;color:#4b3425;font-weight:600;">Browse our soundscape library</td>
                </tr>
            </table>
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:12px;">
                <tr>
                    <td style="vertical-align:top;width:20px;padding-top:5px;">
                        <div style="width:8px;height:8px;border-radius:50%;background-color:#fe814b;"></div>
                    </td>
                    <td style="padding-left:12px;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;color:#4b3425;font-weight:600;">Try the AI Companion chat — 100% private</td>
                </tr>
            </table>
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                    <td style="vertical-align:top;width:20px;padding-top:5px;">
                        <div style="width:8px;height:8px;border-radius:50%;background-color:#ffce5c;"></div>
                    </td>
                    <td style="padding-left:12px;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;color:#4b3425;font-weight:600;">Book your first therapy session</td>
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
                            Go to My Dashboard
                        </a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

@endsection
