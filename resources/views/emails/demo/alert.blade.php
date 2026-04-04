@extends('emails.layouts.main')

@section('content')

@php
    $orgTypeLabel = $orgType === 'university' ? 'University / School' : 'Company / Organisation';
    $rows = [
        ['Company',     $lead->company],
        ['Contact',     $lead->first_name . ' ' . $lead->last_name],
        ['Email',       $lead->email],
        ['Phone',       $lead->phone ?? '—'],
        ['Org type',    $orgTypeLabel],
        ['Size',        $companySize ?: '—'],
    ];
    if ($message) $rows[] = ['Message', $message];
@endphp

{{-- Priority badge --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:24px;">
    <tr>
        <td align="center">
            <div style="display:inline-block;background-color:#fef2f2;border:1px solid #dc2626;border-radius:8px;padding:8px 20px;">
                <span style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:12px;font-weight:800;color:#dc2626;letter-spacing:1px;text-transform:uppercase;">
                    🔴 &nbsp;NEW DEMO REQUEST
                </span>
            </div>
        </td>
    </tr>
</table>

{{-- Headline --}}
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:26px;font-weight:800;line-height:1.2;text-align:center;color:#4b3425;margin-bottom:6px;">
    New demo request
</div>
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:15px;font-weight:500;line-height:1.5;text-align:center;color:#926247;margin-bottom:32px;">
    {{ now()->format('D, d M Y · H:i') }} UTC
</div>

{{-- Greeting --}}
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;line-height:28px;color:#1f160f;margin-bottom:16px;">
    Hi <strong style="color:#4b3425;">{{ $recipientName }}</strong>,
</div>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:15px;line-height:26px;color:#3d2e22;margin-bottom:28px;">
    <strong style="color:#4b3425;">{{ $lead->company }}</strong> just requested a demo. Follow up within 1 business day.
</div>

{{-- Lead details --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:28px;">
    <tr>
        <td style="background-color:#f7f4f2;border-radius:16px;border:1px solid #e8ddd9;padding:24px 28px;">
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:#9bb068;margin-bottom:16px;">Lead details</div>

            @foreach($rows as [$label, $value])
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:10px;">
                <tr>
                    <td style="width:110px;vertical-align:top;">
                        <span style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:12px;font-weight:700;color:#926247;text-transform:uppercase;letter-spacing:0.5px;">{{ $label }}</span>
                    </td>
                    <td style="vertical-align:top;padding-left:12px;">
                        <span style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;color:#3d2e22;font-weight:{{ $label === 'Email' ? '700' : '500' }};">{{ $value }}</span>
                    </td>
                </tr>
            </table>
            @endforeach
        </td>
    </tr>
</table>

{{-- CTA --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:32px;">
    <tr>
        <td align="center">
            <a href="{{ rtrim(config('frontend.dashboard_url'), '/') }}/sales/leads/{{ $lead->id }}" style="display:inline-block;background-color:#122420;color:#ffffff;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:15px;font-weight:700;padding:14px 32px;border-radius:50px;text-decoration:none;letter-spacing:0.3px;">
                View lead in dashboard →
            </a>
        </td>
    </tr>
</table>

{{-- Note --}}
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;line-height:22px;text-align:center;color:#bda193;">
    Automated internal alert from Onwynd. Manage this lead from the sales dashboard.
</div>

@endsection
