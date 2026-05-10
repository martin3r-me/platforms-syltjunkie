<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login-Link</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #f4f4f5; padding: 40px 20px; margin: 0;">
    <div style="max-width: 480px; margin: 0 auto; background: #ffffff; border-radius: 8px; padding: 32px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <h2 style="margin: 0 0 16px; font-size: 20px; color: #18181b;">Hallo {{ $ownerName }},</h2>

        <p style="color: #3f3f46; font-size: 15px; line-height: 1.6; margin: 0 0 24px;">
            Klicke auf den folgenden Button, um dich bei Syltjunkie anzumelden. Der Link ist 30 Minuten gueltig.
        </p>

        <div style="text-align: center; margin: 0 0 24px;">
            <a href="{{ $magicLink }}" style="display: inline-block; background-color: #2563eb; color: #ffffff; text-decoration: none; padding: 12px 32px; border-radius: 6px; font-size: 15px; font-weight: 500;">
                Jetzt anmelden
            </a>
        </div>

        <p style="color: #71717a; font-size: 13px; line-height: 1.5; margin: 0 0 8px;">
            Falls der Button nicht funktioniert, kopiere diesen Link in deinen Browser:
        </p>
        <p style="color: #71717a; font-size: 12px; word-break: break-all; margin: 0 0 24px;">
            {{ $magicLink }}
        </p>

        <hr style="border: none; border-top: 1px solid #e4e4e7; margin: 24px 0;">

        <p style="color: #a1a1aa; font-size: 12px; margin: 0;">
            Du hast diesen Link nicht angefordert? Dann kannst du diese E-Mail ignorieren.
        </p>
    </div>
</body>
</html>
