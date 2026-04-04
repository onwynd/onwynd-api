@extends('emails.layouts.main')

@section('content')

{{-- Dynamic alert color based on level --}}
@php
    $levelColors = [
        'critical' => ['bg' => '#fff0f0', 'border' => '#e53e3e', 'badge_bg' => '#fed7d7', 'badge_text' => '#c53030', 'icon' => '#e53e3e'],
        'warning'  => ['bg' => '#fffbeb', 'border' => '#d69e2e', 'badge_bg' => '#fefcbf', 'badge_text' => '#975a16', 'icon' => '#d69e2e'],
        'info'     => ['bg' => '#ebf8ff', 'border' => '#3182ce', 'badge_bg' => '#bee3f8', 'badge_text' => '#2b6cb0', 'icon' => '#3182ce'],
    ];
    $level = strtolower($alertLevel ?? 'info');
    $colors = $levelColors[$level] ?? $levelColors['info'];
@endphp

{{-- Icon --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td align="center" style="padding-bottom:20px;">
            <div style="width:72px;height:72px;border-radius:50%;background-color:{{ $colors['bg'] }};border:2px solid {{ $colors['border'] }};display:inline-block;line-height:72px;text-align:center;margin:0 auto;">
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:inline-block;vertical-align:middle;">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z" fill="{{ $colors['icon'] }}"/>
                </svg>
            </div>
        </td>
    </tr>
</table>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:1px;text-align:center;margin-bottom:8px;">
    <span style="display:inline-block;padding:4px 14px;border-radius:999px;background-color:{{ $colors['badge_bg'] }};color:{{ $colors['badge_text'] }};">
        {{ strtoupper($alertLevel) }}
    </span>
</div>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:28px;font-weight:800;line-height:1.2;text-align:center;color:#4b3425;margin-bottom:8px;">
    System Alert
</div>
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;font-weight:500;line-height:1.5;text-align:center;color:#926247;margin-bottom:28px;">
    {{ $timestamp }}
</div>

{{-- Message body --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:28px;">
    <tr>
        <td style="background-color:{{ $colors['bg'] }};border-left:4px solid {{ $colors['border'] }};border-radius:0 12px 12px 0;padding:20px 24px;">
            <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:15px;line-height:26px;color:#3d2e22;">
                {{ $messageBody }}
            </div>
        </td>
    </tr>
</table>

<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td align="center">
            <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td align="center" style="border-radius:123px;background-color:#fe814b;">
                        <a href="{{ config('frontend.dashboard_url') . '/tech/logs' }}" style="display:inline-block;padding:16px 40px;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:123px;">
                            View System Logs
                        </a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

@endsection
