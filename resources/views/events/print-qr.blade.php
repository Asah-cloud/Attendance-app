<!DOCTYPE html>
<html>
<head>
    <title>Print QR - {{ $event->title }}</title>
    <style>
        body { 
            display: flex; flex-direction: column; align-items: center; 
            justify-content: center; height: 100vh; margin: 0; 
            font-family: sans-serif; text-align: center; 
        }
        .card { padding: 50px; border: 1px solid #eee; border-radius: 40px; }
        h1 { margin-top: 30px; font-size: 36px; font-weight: 900; color: #1e3a8a; }
        p { font-size: 20px; color: #6b7280; font-weight: bold; text-transform: uppercase; letter-spacing: 2px; }
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="card">
        {!! QrCode::size(450)->color(30, 58, 138)->generate(URL::signedRoute('scan.events', ['event' => $event->id, 'day' => $currentDay])) !!}
        <h1>{{ $event->title }}</h1>
        <p>Day {{ $currentDay }} Check-in</p>
    </div>

    <div class="no-print" style="margin-top: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; cursor: pointer;">Print Now</button>
    </div>

    <script>
        // Auto-open print dialog when page loads
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>
