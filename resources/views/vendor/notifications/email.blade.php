@extends('emails.layouts.main')

@php
    $color = match($level ?? 'default') {
        'error'   => '#fe814b',
        'success' => '#9bb068',
        default   => '#9bb068',
    };
@endphp

@section('content')

{{-- Greeting --}}
@if(!empty($greeting))
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:26px;font-weight:800;line-height:1.2;color:#4b3425;margin-bottom:20px;">
    {{ $greeting }}
</div>
@else
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:26px;font-weight:800;line-height:1.2;color:#4b3425;margin-bottom:20px;">
    {{ $level === 'error' ? 'Whoops!' : 'Hello!' }}
</div>
@endif

{{-- Intro lines --}}
@foreach($introLines as $line)
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;line-height:28px;color:#3d2e22;margin-bottom:16px;">
    {{ $line }}
</div>
@endforeach

{{-- Action button --}}
@isset($actionText)
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin:28px 0;">
    <tr>
        <td align="center">
            <a href="{{ $actionUrl }}"
               style="display:inline-block;padding:16px 40px;background-color:{{ $color }};border-radius:50px;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;font-weight:800;color:#ffffff;text-decoration:none;letter-spacing:0.3px;">
                {{ $actionText }} →
            </a>
        </td>
    </tr>
</table>
@endisset

{{-- Outro lines --}}
@foreach($outroLines as $line)
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:15px;line-height:26px;color:#926247;margin-bottom:12px;">
    {{ $line }}
</div>
@endforeach

{{-- Subcopy (URL fallback for action button) --}}
@isset($actionText)
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:12px;line-height:20px;color:#bda193;margin-top:24px;padding-top:16px;border-top:1px solid #e8ddd9;">
    If you're having trouble clicking the "{{ $actionText }}" button, copy and paste the URL below into your browser:<br>
    <span style="word-break:break-all;color:#926247;">{{ $displayableActionUrl }}</span>
</div>
@endisset

@endsection
