<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invitation Declined</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            padding: 40px;
            max-width: 500px;
            width: 90%;
            text-align: center;
        }
        .icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        .success-icon {
            color: #10b981;
        }
        .error-icon {
            color: #ef4444;
        }
        h1 {
            color: #1f2937;
            margin: 0 0 20px 0;
            font-size: 28px;
            font-weight: 600;
        }
        .message {
            color: #6b7280;
            font-size: 16px;
            line-height: 1.5;
            margin-bottom: 30px;
        }
        .button {
            background: #3b82f6;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.3s;
        }
        .button:hover {
            background: #2563eb;
        }
    </style>
</head>
<body>
    <div class="container">
        @if(session('message'))
            <div class="icon success-icon">✓</div>
            <h1>Invitation Declined</h1>
            <p class="message">{{ session('message') }}</p>
        @else
            <div class="icon error-icon">✗</div>
            <h1>Error</h1>
            <p class="message">{{ session('error') ?? 'Something went wrong while processing your request.' }}</p>
        @endif
        
        <p class="message">
            If you change your mind, please contact the person who sent you the invitation for a new invite.
        </p>
        
        <a href="{{ url('/') }}" class="button">Go to Homepage</a>
    </div>
</body>
</html>