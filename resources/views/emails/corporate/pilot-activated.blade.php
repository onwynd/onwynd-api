<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Your Onwynd Pilot is Now Live</title>
</head>
<body style="font-family:sans-serif;color:#333;max-width:600px;margin:0 auto;padding:20px;background:#fff;">

  <div style="margin-bottom:24px;">
    <img src="{{ config('app.url') }}/img/logo.png" alt="Onwynd" style="height:36px;" onerror="this.style.display='none'">
  </div>

  <h2 style="color:#122420;margin-bottom:8px;">Hi {{ $hrName }},</h2>
  <p>Great news — your Onwynd pilot for <strong>{{ $orgName }}</strong> is now live and ready for your employees.</p>

  <table style="width:100%;border-collapse:collapse;margin:24px 0;font-size:14px;">
    <tr>
      <td style="padding:10px 0;border-bottom:1px solid #eee;color:#555;width:50%;">Pilot Period</td>
      <td style="padding:10px 0;border-bottom:1px solid #eee;font-weight:500;">{{ $pilotStart->format('d M Y') }} – {{ $pilotEnd->format('d M Y') }}</td>
    </tr>
    <tr>
      <td style="padding:10px 0;border-bottom:1px solid #eee;color:#555;">Session Quota</td>
      <td style="padding:10px 0;border-bottom:1px solid #eee;font-weight:500;">{{ $sessionQuota }} sessions</td>
    </tr>
    @if(!empty($amounts['session_fee']))
    <tr>
      <td style="padding:10px 0;border-bottom:1px solid #eee;color:#555;">Session Fee</td>
      <td style="padding:10px 0;border-bottom:1px solid #eee;">{{ $amounts['session_fee'] }}</td>
    </tr>
    @endif
    @if(!empty($amounts['booking_fee']))
    <tr>
      <td style="padding:10px 0;border-bottom:1px solid #eee;color:#555;">Service Fee</td>
      <td style="padding:10px 0;border-bottom:1px solid #eee;">{{ $amounts['booking_fee'] }}</td>
    </tr>
    @endif
    @if(!empty($amounts['vat_line']))
    <tr>
      <td style="padding:10px 0;border-bottom:1px solid #eee;color:#555;">{{ $amounts['vat_label'] }}</td>
      <td style="padding:10px 0;border-bottom:1px solid #eee;">{{ $amounts['vat_line'] }}</td>
    </tr>
    @endif
    @if($amounts['vat_enabled'] && !empty($amounts['total']))
    <tr>
      <td style="padding:10px 0;color:#555;font-weight:600;">Total per Session</td>
      <td style="padding:10px 0;font-weight:600;">{{ $amounts['total'] }}</td>
    </tr>
    @endif
  </table>

  <p style="margin-bottom:8px;">Head to your dashboard to invite your employees and get them set up:</p>
  <p>
    <a href="{{ rtrim(config('frontend.dashboard_url'), '/') }}/hr/employees"
       style="display:inline-block;background:#2A7A6A;color:#fff;text-decoration:none;padding:12px 24px;border-radius:6px;font-size:14px;font-weight:600;">
      Invite Employees
    </a>
  </p>

  <p style="margin-top:32px;font-size:13px;color:#666;">
    Need help? Contact us at
    <a href="mailto:{{ config('onwynd.support_email', 'hello@onwynd.com') }}" style="color:#2A7A6A;">
      {{ config('onwynd.support_email', 'hello@onwynd.com') }}
    </a>
  </p>

  <hr style="border:none;border-top:1px solid #eee;margin:24px 0;">
  <p style="font-size:12px;color:#999;">
    &copy; {{ date('Y') }} Onwynd · This email was sent regarding your corporate pilot agreement.
  </p>

</body>
</html>
