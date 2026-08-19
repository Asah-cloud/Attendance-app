<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subject }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#1e293b;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:32px 16px;background-color:#f1f5f9;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;background-color:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e2e8f0;">
                    <tr>
                        <td align="center" style="padding:28px 32px 0 32px;">
                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    @if($companyLogoUrl)
                                        <td style="padding-right:8px;"><img src="{{ $companyLogoUrl }}" alt="{{ $organizationName }}" style="height:24px;width:24px;object-fit:contain;border-radius:6px;border:1px solid #e2e8f0;"></td>
                                    @endif
                                    <td style="font-size:13px;font-weight:700;color:#64748b;">{{ $organizationName }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:14px 32px 0 32px;">
                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    @if($eventLogoUrl)
                                        <td style="padding-right:12px;"><img src="{{ $eventLogoUrl }}" alt="{{ $event->title }}" style="height:52px;width:52px;object-fit:contain;border-radius:10px;border:1px solid #e2e8f0;"></td>
                                    @endif
                                    <td style="font-size:19px;font-weight:800;color:#0f172a;text-align:left;">{{ $event->title }}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:24px 32px 32px 32px;">
                            <h2 style="margin:0 0 18px 0;font-size:20px;font-weight:800;color:#0f172a;">{{ $greeting }}</h2>
                            @foreach($lines as $line)
                                <p style="margin:0 0 14px 0;font-size:14px;line-height:1.6;color:#334155;">{{ $line }}</p>
                            @endforeach
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:26px 0;">
                                <tr>
                                    <td style="border-radius:10px;background-color:#2563eb;">
                                        <a href="{{ $actionUrl }}" style="display:inline-block;padding:14px 28px;font-size:13px;font-weight:800;color:#ffffff;text-decoration:none;">{{ $actionLabel }}</a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:0;font-size:13px;color:#64748b;">{{ $salutation }}</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
