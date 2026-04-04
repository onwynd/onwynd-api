@extends('emails.layouts.main')

@section('content')

{{-- Icon --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td align="center" style="padding-bottom:28px;">
            <div style="width:72px;height:72px;border-radius:50%;background-color:#e5ead7;display:inline-block;line-height:72px;text-align:center;margin:0 auto;">
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:inline-block;vertical-align:middle;">
                    <path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z" fill="#9bb068"/>
                </svg>
            </div>
        </td>
    </tr>
</table>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:32px;font-weight:800;line-height:1.2;text-align:center;color:#4b3425;margin-bottom:8px;">
    Your wellness data is ready
</div>
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;font-weight:500;line-height:1.5;text-align:center;color:#926247;margin-bottom:32px;">
    Your data belongs to you — always.
</div>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;line-height:28px;color:#3d2e22;margin-bottom:28px;">
    Hi <strong style="color:#4b3425;">{{ $userName }}</strong>,<br><br>
    As requested, we've exported your wellness data from <strong style="color:#4b3425;">{{ $fromDate }}</strong> to <strong style="color:#4b3425;">{{ $toDate }}</strong>. It's attached to this email as a JSON file.
</div>

<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:20px;border:1px solid #e8ddd9;border-radius:16px;overflow:hidden;">
    <tr>
        <td style="padding:20px 24px;background-color:#f7f4f2;">
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.6px;color:#9bb068;margin-bottom:16px;">What's inside</div>
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                    <td style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;color:#3d2e22;padding-bottom:10px;border-bottom:1px solid #e8ddd9;">Mood Logs</td>
                    <td style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;font-weight:700;color:#9bb068;text-align:right;padding-bottom:10px;border-bottom:1px solid #e8ddd9;">{{ count($exportData['mood_logs'] ?? []) }} entries</td>
                </tr>
                <tr>
                    <td style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;color:#3d2e22;padding-bottom:10px;border-bottom:1px solid #e8ddd9;padding-top:10px;">Sleep Logs</td>
                    <td style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;font-weight:700;color:#9bb068;text-align:right;padding-bottom:10px;border-bottom:1px solid #e8ddd9;padding-top:10px;">{{ count($exportData['sleep_logs'] ?? []) }} entries</td>
                </tr>
                <tr>
                    <td style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;color:#3d2e22;padding-bottom:10px;border-bottom:1px solid #e8ddd9;padding-top:10px;">Journal Entries</td>
                    <td style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;font-weight:700;color:#9bb068;text-align:right;padding-bottom:10px;border-bottom:1px solid #e8ddd9;padding-top:10px;">{{ count($exportData['journal_entries'] ?? []) }} entries</td>
                </tr>
                <tr>
                    <td style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;color:#3d2e22;padding-top:10px;">Assessment Results</td>
                    <td style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;font-weight:700;color:#9bb068;text-align:right;padding-top:10px;">{{ count($exportData['assessment_results'] ?? []) }} results</td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:20px;">
    <tr>
        <td style="background-color:#faf8f6;border-left:3px solid #e8ddd9;border-radius:0 8px 8px 0;padding:16px 20px;">
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;line-height:22px;color:#926247;">
                Open the attached <strong style="color:#4b3425;">onwynd-wellness-export.json</strong> file in any text editor or import it into a spreadsheet tool for further analysis. Didn't request this? Contact us immediately by replying to this email.
            </div>
        </td>
    </tr>
</table>

@endsection
