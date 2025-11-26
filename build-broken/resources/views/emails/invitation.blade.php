<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Company Invitation</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .email-container {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #3b82f6;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .company-name {
            color: #3b82f6;
            font-size: 24px;
            font-weight: bold;
            margin: 0;
        }
        .invitation-details {
            background-color: #f8fafc;
            padding: 20px;
            border-radius: 6px;
            margin: 20px 0;
        }
        .button-container {
            text-align: center;
            margin: 30px 0;
        }
        .button {
            display: inline-block;
            padding: 12px 30px;
            margin: 0 10px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
            text-align: center;
            transition: background-color 0.3s;
        }
        .accept-button {
            background-color: #10b981;
            color: white;
        }
        .accept-button:hover {
            background-color: #059669;
        }
        .decline-button {
            background-color: #ef4444;
            color: white;
        }
        .decline-button:hover {
            background-color: #dc2626;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            font-size: 14px;
            color: #6b7280;
            text-align: center;
        }
        .expiry-notice {
            background-color: #fef3c7;
            color: #92400e;
            padding: 10px;
            border-radius: 4px;
            margin: 15px 0;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <h1 class="company-name">{{ $company->name }}</h1>
            <p>You've been invited to join our team!</p>
        </div>

        <p>Hello,</p>

        <p><strong>{{ $inviter->name }}</strong> has invited you to join <strong>{{ $company->name }}</strong> as a <strong>{{ ucfirst(str_replace('_', ' ', $invitation->role)) }}</strong>.</p>

        <div class="invitation-details">
            <h3>Invitation Details:</h3>
            <ul>
                <li><strong>Company:</strong> {{ $company->name }}</li>
                <li><strong>Role:</strong> {{ ucfirst(str_replace('_', ' ', $invitation->role)) }}</li>
                <li><strong>Invited by:</strong> {{ $inviter->name }} ({{ $inviter->email }})</li>
                <li><strong>Industry:</strong> {{ ucfirst($company->industry) }}</li>
            </ul>
        </div>

        <div class="expiry-notice">
            ⚠️ This invitation will expire on {{ $invitation->expires_at->format('F j, Y \a\t g:i A') }}
        </div>

        <p>To get started with {{ $company->name }}, please accept or decline this invitation:</p>

        <div class="button-container">
            <a href="{{ $acceptUrl }}" class="button accept-button">Accept Invitation</a>
            <a href="{{ $declineUrl }}" class="button decline-button">Decline Invitation</a>
        </div>

        <p>If you're unable to click the buttons above, you can copy and paste these links into your browser:</p>
        <p><strong>Accept:</strong> <a href="{{ $acceptUrl }}">{{ $acceptUrl }}</a></p>
        <p><strong>Decline:</strong> <a href="{{ $declineUrl }}">{{ $declineUrl }}</a></p>

        <div class="footer">
            <p>This invitation was sent by {{ $inviter->name }} from {{ $company->name }}.</p>
            <p>If you weren't expecting this invitation, you can safely ignore this email.</p>
            <p>© {{ date('Y') }} {{ $company->name }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>