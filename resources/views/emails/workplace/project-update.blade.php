@extends('emails.layouts.main')

@section('content')

{{-- Icon --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td align="center" style="padding-bottom:28px;">
            <div style="width:72px;height:72px;border-radius:50%;background-color:#e5ead7;display:inline-block;line-height:72px;text-align:center;margin:0 auto;">
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:inline-block;vertical-align:middle;">
                    <path d="M3 3h18v2H3zm0 4h12v2H3zm0 4h18v2H3zm0 4h12v2H3zm0 4h18v2H3z" fill="#9bb068"/>
                </svg>
            </div>
        </td>
    </tr>
</table>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:32px;font-weight:800;line-height:1.2;text-align:center;color:#4b3425;margin-bottom:8px;">
    Project update
</div>
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;font-weight:500;line-height:1.5;text-align:center;color:#926247;margin-bottom:32px;">
    {{ $projectName }} has new activity.
</div>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;line-height:28px;color:#3d2e22;margin-bottom:28px;">
    Hi <strong style="color:#4b3425;">{{ $name }}</strong>,<br><br>
    There's been an update to <strong style="color:#4b3425;">{{ $projectName }}</strong>. Here's what changed:
</div>

{{-- Activity card --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:20px;">
    <tr>
        <td style="background-color:#f4f7ee;border-left:3px solid #9bb068;border-radius:0 8px 8px 0;padding:16px 20px;">
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;font-weight:700;color:#9bb068;margin-bottom:6px;text-transform:uppercase;letter-spacing:0.5px;">Latest Activity</div>
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:15px;line-height:24px;color:#3d2e22;">{{ $updateMessage }}</div>
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:12px;color:#926247;margin-top:6px;">Updated by {{ $updaterName }} &nbsp;·&nbsp; {{ $timestamp }}</div>
        </td>
    </tr>
</table>

@if(!empty($nextSteps))
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:28px;border:1px solid #e8ddd9;border-radius:16px;overflow:hidden;">
    <tr>
        <td style="padding:20px 24px;background-color:#f7f4f2;">
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:#9bb068;margin-bottom:12px;">Next Steps</div>
            @foreach($nextSteps as $step)
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:8px;">
                <tr>
                    <td style="vertical-align:top;width:20px;padding-top:5px;"><div style="width:8px;height:8px;border-radius:50%;background-color:#9bb068;"></div></td>
                    <td style="padding-left:12px;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;line-height:22px;color:#3d2e22;">{{ $step }}</td>
                </tr>
            </table>
            @endforeach
        </td>
    </tr>
</table>
@endif

<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td align="center">
            <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td align="center" style="border-radius:123px;background-color:#9bb068;">
                        <a href="{{ $projectLink }}" style="display:inline-block;padding:16px 40px;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:123px;">
                            Go to Project Board
                        </a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

@endsection
