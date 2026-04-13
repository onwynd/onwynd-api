@extends('emails.layouts.main')

@section('content')
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td align="center" style="padding-bottom:24px;">
            <div style="width:64px;height:64px;border-radius:50%;background:#e8f0da;display:inline-block;line-height:64px;text-align:center;font-size:28px;">📅</div>
        </td>
    </tr>
    <tr>
        <td align="center" style="padding-bottom:8px;">
            <span style="display:inline-block;background:#e8f0da;color:#4a6a1a;font-size:11px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;padding:4px 14px;border-radius:100px;">New Session Booked</span>
        </td>
    </tr>
    <tr>
        <td align="center" style="padding-bottom:28px;">
            <h1 style="margin:0;font-size:22px;font-weight:700;color:#1f160f;">Session Booking Confirmed</h1>
        </td>
    </tr>
</table>

<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background:#f7f4f2;border-radius:16px;margin-bottom:24px;">
    <tr>
        <td style="padding:20px 24px;">
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                    <td style="padding:8px 0;border-bottom:1px solid rgba(31,22,15,0.08);">
                        <span style="font-size:12px;color:rgba(31,22,15,0.5);font-weight:600;">Patient</span><br>
                        <span style="font-size:15px;color:#1f160f;font-weight:700;">
                            {{ $patient ? trim(($patient->first_name ?? '') . ' ' . ($patient->last_name ?? '')) : 'Unknown' }}
                            @if($patient?->email)
                                &lt;{{ $patient->email }}&gt;
                            @endif
                        </span>
                    </td>
                </tr>
                <tr>
                    <td style="padding:8px 0;border-bottom:1px solid rgba(31,22,15,0.08);">
                        <span style="font-size:12px;color:rgba(31,22,15,0.5);font-weight:600;">Therapist</span><br>
                        <span style="font-size:15px;color:#1f160f;font-weight:700;">{{ $therapistName }}</span>
                    </td>
                </tr>
                <tr>
                    <td style="padding:8px 0;border-bottom:1px solid rgba(31,22,15,0.08);">
                        <span style="font-size:12px;color:rgba(31,22,15,0.5);font-weight:600;">Date &amp; Time</span><br>
                        <span style="font-size:15px;color:#1f160f;font-weight:700;">{{ $dateTime }}</span>
                    </td>
                </tr>
                <tr>
                    <td style="padding:8px 0;">
                        <span style="font-size:12px;color:rgba(31,22,15,0.5);font-weight:600;">Amount Paid</span><br>
                        <span style="font-size:18px;color:#7a9b35;font-weight:800;">
                            {{ $currency === 'USD' ? '$' : '₦' }}{{ number_format($amount, 2) }} {{ $currency }}
                        </span>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td align="center">
            <a href="{{ config('app.url') }}/admin/sessions"
               style="display:inline-block;background:#4b3425;color:#ffffff;font-weight:700;font-size:14px;padding:14px 32px;border-radius:100px;text-decoration:none;">
                View in Admin Panel →
            </a>
        </td>
    </tr>
</table>
@endsection
