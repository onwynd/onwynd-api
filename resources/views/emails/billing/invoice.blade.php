@extends('emails.layouts.main')
@php $isTransactional = true; @endphp

@section('content')

{{-- Icon --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td align="center" style="padding-bottom:28px;">
            <div style="width:72px;height:72px;border-radius:50%;background-color:#e5ead7;display:inline-block;line-height:72px;text-align:center;margin:0 auto;">
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display:inline-block;vertical-align:middle;">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm4 18H6V4h7v5h5v11zM9 13h6v2H9zm0 3h6v2H9zm0-6h3v2H9z" fill="#9bb068"/>
                </svg>
            </div>
        </td>
    </tr>
</table>

{{-- Headline --}}
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:32px;font-weight:800;line-height:1.2;text-align:center;color:#4b3425;margin-bottom:8px;">
    Your Invoice
</div>
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;font-weight:500;line-height:1.5;text-align:center;color:#926247;margin-bottom:32px;">
    Thanks for supporting {{ config('app.name') }}.
</div>

{{-- Greeting --}}
<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;line-height:28px;color:#3d2e22;margin-bottom:24px;">
    Hi <strong style="color:#4b3425;">{{ $name }}</strong>,<br><br>
    Here's your invoice for your recent purchase. We appreciate you — every plan helps us build better tools for mental wellbeing.
</div>

{{-- Invoice table --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:8px;border-radius:12px;overflow:hidden;border:1px solid #e8ddd9;">
    {{-- Table header --}}
    <tr style="background-color:#f7f4f2;">
        <td style="padding:12px 16px;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:#926247;">Item</td>
        <td style="padding:12px 16px;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:#926247;text-align:center;">Qty</td>
        <td style="padding:12px 16px;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:#926247;text-align:right;">Price</td>
    </tr>
    {{-- Items --}}
    @foreach($items as $item)
    <tr style="border-top:1px solid #e8ddd9;">
        <td style="padding:14px 16px;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;color:#3d2e22;">{{ $item['name'] }}</td>
        <td style="padding:14px 16px;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;color:#3d2e22;text-align:center;">{{ $item['quantity'] }}</td>
        <td style="padding:14px 16px;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:14px;color:#3d2e22;text-align:right;">{{ $item['price'] }}</td>
    </tr>
    @endforeach
    {{-- Tax row --}}
    <tr style="border-top:1px solid #e8ddd9;">
        <td style="padding:12px 16px;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;color:#926247;">Tax</td>
        <td></td>
        <td style="padding:12px 16px;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;color:#926247;text-align:right;">{{ $tax ?? '$0.00' }}</td>
    </tr>
    {{-- Total --}}
    <tr style="background-color:#4b3425;">
        <td style="padding:16px;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:15px;font-weight:800;color:#ffffff;">Total</td>
        <td></td>
        <td style="padding:16px;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:18px;font-weight:800;color:#9bb068;text-align:right;">{{ $total }}</td>
    </tr>
</table>

<div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:12px;line-height:18px;color:#bda193;margin-bottom:28px;margin-top:8px;">
    Questions about this invoice? Reply to this email and we'll sort it right away.
</div>

{{-- CTA --}}
<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:8px;">
    <tr>
        <td align="center">
            <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                <tr>
                    <td align="center" style="border-radius:123px;background-color:#9bb068;">
                        <a href="{{ $statusUrl ?? config('frontend.url') . '/profile/subscription' }}" style="display:inline-block;padding:16px 40px;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:16px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:123px;">
                            View My Account
                        </a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

@endsection
