@extends('emails.layouts.main')

@section('content')

{{-- Icon --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td align="center" style="padding-bottom:28px;">
            <div style="width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,#e5ead7 0%,#c8d5aa 100%);display:inline-block;line-height:72px;text-align:center;margin:0 auto;">
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:inline-block;vertical-align:middle;">
                    <path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z" fill="#9bb068"/>
                </svg>
            </div>
        </td>
    </tr>
</table>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:32px;font-weight:800;line-height:1.2;text-align:center;color:#4b3425;margin-bottom:8px;">
    Your payslip is ready
</div>
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;font-weight:500;line-height:1.5;text-align:center;color:#926247;margin-bottom:32px;">
    {{ $month }} payroll has been processed.
</div>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;line-height:28px;color:#3d2e22;margin-bottom:28px;">
    Hello <strong style="color:#4b3425;">{{ $employeeName }}</strong>,<br><br>
    Your payslip for <strong style="color:#4b3425;">{{ $month }}</strong> is now available. Log in to the employee portal to download your detailed statement.
</div>

<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:28px;border:1px solid #e8ddd9;border-radius:16px;overflow:hidden;">
    <tr>
        <td style="padding:20px 24px;background-color:#f7f4f2;">
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                    <td style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;color:#926247;">Period</td>
                    <td style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;font-weight:700;color:#4b3425;text-align:right;">{{ $month }}</td>
                </tr>
                <tr>
                    <td style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;color:#926247;padding-top:10px;">Net Amount</td>
                    <td style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:20px;font-weight:800;color:#9bb068;text-align:right;padding-top:10px;">{{ $netAmount }}</td>
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
                        <a href="{{ $link }}" style="display:inline-block;padding:16px 40px;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:123px;">
                            Download Payslip
                        </a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

@endsection
