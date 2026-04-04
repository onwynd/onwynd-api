<!doctype html>
<html xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <title>{{ config('app.name') }}</title>
    <!--[if !mso]><!-- -->
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <!--<![endif]-->
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!--[if !mso]><!-->
    <link href="https://fonts.googleapis.com/css2?family=Urbanist:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet" type="text/css">
    <style type="text/css">
        @import url('https://fonts.googleapis.com/css2?family=Urbanist:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&display=swap');
    </style>
    <!--<![endif]-->

    <!--[if mso]>
    <xml>
    <o:OfficeDocumentSettings>
      <o:AllowPNG/>
      <o:PixelsPerInch>96</o:PixelsPerInch>
    </o:OfficeDocumentSettings>
    </xml>
    <![endif]-->

    <style type="text/css">
        #outlook a { padding: 0; }
        .ReadMsgBody { width: 100%; }
        .ExternalClass { width: 100%; }
        .ExternalClass * { line-height: 100%; }
        body {
            margin: 0;
            padding: 0;
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
            background-color: #f7f4f2;
        }
        table, td {
            border-collapse: collapse;
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }
        img {
            border: 0;
            height: auto;
            line-height: 100%;
            outline: none;
            text-decoration: none;
            -ms-interpolation-mode: bicubic;
        }
        p { display: block; margin: 13px 0; }

        @media only screen and (max-width: 620px) {
            .email-container { width: 100% !important; }
            .fluid { width: 100% !important; max-width: 100% !important; }
            .stack-on-mobile { display: block !important; width: 100% !important; }
            .px-mobile { padding-left: 24px !important; padding-right: 24px !important; }
        }
    </style>
</head>

<body style="margin:0;padding:0;background-color:#f7f4f2;font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;">

    <!-- Outer wrapper -->
    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:#f7f4f2;">
        <tr>
            <td align="center" style="padding: 32px 16px 40px;">

                <!-- Email container -->
                <table class="email-container" role="presentation" border="0" cellpadding="0" cellspacing="0" width="600" style="max-width:600px;">

                    <!-- ═══ HEADER ═══ -->
                    <tr>
                        <td style="border-radius:20px 20px 0 0;background-color:#4b3425;padding:0;overflow:hidden;">

                            <!-- Top accent line -->
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td style="height:4px;background:linear-gradient(90deg,#9bb068 0%,#fe814b 50%,#ffce5c 100%);font-size:0;line-height:0;">&nbsp;</td>
                                </tr>
                            </table>

                            <!-- Logo area -->
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td align="center" style="padding:32px 40px 28px;">
                                        <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td align="center">
                                                    <!--[if mso]>
                                                    <div style="font-family:'Helvetica Neue',Arial,sans-serif;font-size:26px;font-weight:800;letter-spacing:-0.5px;color:#ffffff;line-height:1;text-align:center;">
                                                        ONWYND
                                                    </div>
                                                    <![endif]-->
                                                    <!--[if !mso]><!-->
                                                    <img
                                                        src="{{ config('app.url') }}/onwynd-logo.png"
                                                        width="160"
                                                        height="40"
                                                        alt="Onwynd"
                                                        style="display:block;margin:0 auto;border:0;filter:brightness(0) invert(1);-ms-filter:'progid:DXImageTransform.Microsoft.BasicImage(invert=1)';"
                                                    />
                                                    <!--<![endif]-->
                                                    @if(isset($previewText))
                                                    <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:12px;font-weight:500;color:rgba(255,255,255,0.55);margin-top:10px;letter-spacing:0.5px;text-transform:uppercase;">
                                                        {{ $previewText }}
                                                    </div>
                                                    @endif
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                        </td>
                    </tr>

                    <!-- ═══ BODY CARD ═══ -->
                    <tr>
                        <td style="background-color:#ffffff;border-left:1px solid #e8ddd9;border-right:1px solid #e8ddd9;">
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td class="px-mobile" style="padding:44px 48px 36px;">
                                        @yield('content')
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- ═══ SIGN-OFF ═══ -->
                    <tr>
                        <td style="background-color:#faf8f6;border:1px solid #e8ddd9;border-top:0;border-radius:0 0 20px 20px;padding:0;">

                            <!-- Divider with dot -->
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td style="padding:0 48px;">
                                        <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                                            <tr>
                                                <td style="height:1px;background-color:#e8ddd9;font-size:0;line-height:0;"></td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td class="px-mobile" style="padding:28px 48px 36px;">
                                        @php
                                            $closings = [
                                                'With care & curiosity',
                                                'With love and warmth',
                                                'Rooting for you',
                                                'Warmly',
                                                'Cheering you on',
                                                'With care & coffee',
                                                'Here for you, always',
                                                'With heart',
                                            ];
                                            $closing = $closings[array_rand($closings)];
                                        @endphp
                                        <div style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:15px;line-height:24px;color:#6d4b36;">
                                            {{ $closing }},<br>
                                            <strong style="color:#4b3425;">The {{ config('app.name') }} Team</strong>
                                        </div>

                                        <!-- Footer logo + link -->
                                        <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="margin-top:20px;">
                                            <tr>
                                                <td style="vertical-align:middle;">
                                                    <!--[if !mso]><!-->
                                                    <img
                                                        src="{{ config('app.url') }}/onwynd-logo.png"
                                                        width="72"
                                                        height="18"
                                                        alt="Onwynd"
                                                        style="display:block;border:0;filter:brightness(0) sepia(1) hue-rotate(60deg) saturate(3);-ms-filter:'progid:DXImageTransform.Microsoft.BasicImage()';"
                                                    />
                                                    <!--<![endif]-->
                                                    <!--[if mso]>
                                                    <span style="font-family:'Helvetica Neue',Arial,sans-serif;font-size:13px;font-weight:700;color:#9bb068;">ONWYND</span>
                                                    <![endif]-->
                                                </td>
                                                <td style="padding-left:10px;vertical-align:middle;">
                                                    <a href="{{ config('frontend.url') }}" style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:13px;font-weight:700;color:#9bb068;text-decoration:none;">onwynd.com</a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                        </td>
                    </tr>

                    <!-- ═══ SPACER ═══ -->
                    <tr>
                        <td style="height:16px;font-size:0;line-height:0;">&nbsp;</td>
                    </tr>

                    <!-- ═══ FOOTER ═══ -->
                    @php
                        // Determine footer mode:
                        //  'transactional' → required account email, no opt-out
                        //  'newsletter'    → marketing email with token-based unsubscribe
                        //  'default'       → general, links to notification preferences
                        $emailFooterType = isset($isTransactional) && $isTransactional
                            ? 'transactional'
                            : (isset($unsubscribeUrl) && $unsubscribeUrl ? 'newsletter' : 'default');
                    @endphp
                    <tr>
                        <td align="center" style="padding:0 16px 8px;">
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td align="center" style="font-family:'Urbanist','Helvetica Neue',Arial,sans-serif;font-size:12px;font-weight:500;color:#bda193;line-height:20px;">
                                        <!-- Dot nav -->
                                        <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="margin:0 auto 10px;">
                                            <tr>
                                                <td style="padding:0 10px;">
                                                    <a href="{{ config('frontend.url') }}" style="color:#926247;text-decoration:none;font-size:12px;font-weight:600;">Website</a>
                                                </td>
                                                <td style="color:#e8ddd9;font-size:14px;">·</td>
                                                <td style="padding:0 10px;">
                                                    <a href="{{ config('frontend.url') . '/privacy' }}" style="color:#926247;text-decoration:none;font-size:12px;font-weight:600;">Privacy</a>
                                                </td>
                                                @if($emailFooterType === 'newsletter')
                                                    <td style="color:#e8ddd9;font-size:14px;">·</td>
                                                    <td style="padding:0 10px;">
                                                        <a href="{{ $unsubscribeUrl }}" style="color:#926247;text-decoration:none;font-size:12px;font-weight:600;">Unsubscribe</a>
                                                    </td>
                                                @elseif($emailFooterType === 'default')
                                                    <td style="color:#e8ddd9;font-size:14px;">·</td>
                                                    <td style="padding:0 10px;">
                                                        <a href="{{ config('frontend.url') . '/profile/notifications' }}" style="color:#926247;text-decoration:none;font-size:12px;font-weight:600;">Manage Preferences</a>
                                                    </td>
                                                @endif
                                                {{-- transactional: no third link shown --}}
                                            </tr>
                                        </table>

                                        @if($emailFooterType === 'transactional')
                                        <div style="color:#bda193;font-size:11px;margin-bottom:6px;">
                                            This is a required notification about your {{ config('app.name') }} account and cannot be unsubscribed from.
                                        </div>
                                        @endif

                                        <div style="color:#bda193;font-size:11px;">
                                            &copy; {{ date('Y') }} {{ config('app.name') }} Inc. &nbsp;·&nbsp; Made with care, somewhere thoughtful.
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Bottom spacer -->
                    <tr>
                        <td style="height:24px;font-size:0;line-height:0;">&nbsp;</td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>

</body>
</html>
