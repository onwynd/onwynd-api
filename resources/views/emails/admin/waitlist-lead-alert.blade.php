@extends('emails.layouts.main')

@section('content')

@php
    $isPriority  = $priority === 'high';
    $badgeColor  = $isPriority ? '#dc2626' : '#d97706';
    $badgeBg     = $isPriority ? '#fef2f2' : '#fffbeb';
    $badgeLabel  = $isPriority ? 'HIGH PRIORITY — INSTITUTION LEAD' : 'THERAPIST LEAD';

    $roleLabels = [
        'therapist'   => 'Therapist / Coach',
        'institution' => 'Institution',
        'patient'     => 'Patient',
        'other'       => 'Other',
    ];
    $institutionTypeLabels = [
        'company'    => 'Company / Startup',
        'university' => 'University / School',
        'hospital'   => 'Hospital / Clinic',
        'ngo'        => 'NGO / Non-profit',
    ];
@endphp

{{-- Priority badge --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:24px;">
    <tr>
        <td align="center">
            <div style="display:inline-block;background-color:{{ $badgeBg }};border:1px solid {{ $badgeColor }};border-radius:8px;padding:8px 20px;">
                <span style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:12px;font-weight:800;color:{{ $badgeColor }};letter-spacing:1px;text-transform:uppercase;">
                    {{ $badgeLabel }}
                </span>
            </div>
        </td>
    </tr>
</table>

{{-- Headline --}}
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:26px;font-weight:800;line-height:1.2;text-align:center;color:#4b3425;margin-bottom:6px;">
    New waitlist submission
</div>
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:15px;font-weight:500;line-height:1.5;text-align:center;color:#926247;margin-bottom:32px;">
    {{ $submission->created_at->format('D, d M Y · H:i') }} UTC
</div>

{{-- Greeting --}}
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;line-height:28px;color:#1f160f;margin-bottom:24px;">
    Hi <strong style="color:#4b3425;">{{ $recipientName }}</strong>,
</div>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:15px;line-height:26px;color:#3d2e22;margin-bottom:28px;">
    @if($isPriority)
        A <strong>high-value institutional lead</strong> just joined the Onwynd waitlist. This warrants a prompt personal follow-up.
    @else
        A new therapist has joined the Onwynd waitlist. Review and follow up when ready.
    @endif
</div>

{{-- Lead details card --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:28px;">
    <tr>
        <td style="background-color:#f7f4f2;border-radius:16px;border:1px solid #e8ddd9;padding:24px 28px;">
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:#9bb068;margin-bottom:16px;">Lead details</div>

            @php
                $rows = [
                    ['Name',  $submission->first_name . ' ' . $submission->last_name],
                    ['Email', $submission->email],
                    ['Role',  $roleLabels[$submission->role] ?? $submission->role],
                    ['Country', $submission->country ?? '—'],
                    ['Joined via', $submission->referral_source ?? '—'],
                ];

                if ($submission->role === 'therapist') {
                    $rows[] = ['Experience', $submission->years_of_experience ?? '—'];
                    $rows[] = ['Specialty', $submission->specialty ?? '—'];
                }

                if ($submission->role === 'institution') {
                    $rows[] = ['Institution type', $institutionTypeLabels[$submission->institution_type ?? ''] ?? '—'];
                    $rows[] = ['Organisation', $submission->organization_name ?? '—'];
                    if ($submission->institution_type === 'university') {
                        $rows[] = ['Students', $submission->student_count ?? '—'];
                    } else {
                        $rows[] = ['Size', $submission->company_size ?? '—'];
                    }
                }

                if ($submission->message) {
                    $rows[] = ['Message', $submission->message];
                }
            @endphp

            @foreach($rows as [$label, $value])
            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:10px;">
                <tr>
                    <td style="width:130px;vertical-align:top;">
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
            <a href="{{ rtrim(config('frontend.dashboard_url'), '/') }}/admin/waitlist" style="display:inline-block;background-color:#122420;color:#ffffff;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:15px;font-weight:700;padding:14px 32px;border-radius:50px;text-decoration:none;letter-spacing:0.3px;">
                View in dashboard →
            </a>
        </td>
    </tr>
</table>

{{-- Note --}}
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;line-height:22px;text-align:center;color:#bda193;">
    This is an automated internal alert from Onwynd. Do not reply — manage leads from the admin dashboard.
</div>

@endsection
