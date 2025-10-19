<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Download</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 10px 10px 0 0;
        }
        .content {
            background: #f9fafb;
            padding: 30px;
            border-radius: 0 0 10px 10px;
        }
        .report-info {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #667eea;
        }
        .download-button {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            margin: 20px 0;
        }
        .download-button:hover {
            background: #5a67d8;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            color: #6b7280;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📊 Report Ready for Download</h1>
    </div>

    <div class="content">
        @if($message)
            <p>{{ $message }}</p>
        @else
            <p>Your report is ready for download. You can access it using the link below.</p>
        @endif

        <div class="report-info">
            <h3>{{ $report['name'] }}</h3>
            <p><strong>Type:</strong> {{ Str::title(str_replace('_', ' ', $report['report_type'])) }}</p>
            <p><strong>Generated:</strong> {{ \Carbon\Carbon::parse($report['generated_at'])->format('M j, Y \a\t g:i A') }}</p>
            @if(isset($report['file_size']))
                <p><strong>Size:</strong> {{ number_format($report['file_size'] / 1024 / 1024, 2) }} MB</p>
            @endif
            @if(isset($report['mime_type']))
                <p><strong>Format:</strong> {{ Str::upper(pathinfo($report['file_name'] ?? '', PATHINFO_EXTENSION) ?? 'Unknown') }}</p>
            @endif
        </div>

        @if($downloadUrl)
            <div style="text-align: center;">
                <a href="{{ $downloadUrl }}" class="download-button">
                    Download Report
                </a>
            </div>
            
            <p style="color: #6b7280; font-size: 14px; text-align: center;">
                ⏰ This link will expire in 1 hour for security reasons.
            </p>
        @else
            <p style="color: #dc2626; text-align: center;">
                ❌ Download link could not be generated. Please contact support.
            </p>
        @endif
    </div>

    <div class="footer">
        <p>This is an automated message from the Haasib Reporting System.</p>
        <p>If you didn't request this report, please disregard this email.</p>
    </div>
</body>
</html>