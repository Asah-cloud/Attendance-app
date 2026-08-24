<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Form QR · {{ $form->title }}</title>
    <style>
        body { min-height: 100vh; margin: 0; display: grid; place-items: center; font-family: Arial, sans-serif; text-align: center; color: #071426; }
        .card { padding: 48px; border: 1px solid #dbeafe; border-radius: 32px; }
        h1 { margin: 28px 0 8px; font-size: 34px; }
        p { color: #475569; }
        button { margin-top: 24px; padding: 12px 20px; border: 0; border-radius: 10px; background: #2563eb; color: white; font-weight: bold; cursor: pointer; }
        @media print { button { display: none; } .card { border: 0; } }
    </style>
</head>
<body>
    <div class="card">
        {!! QrCode::size(420)->generate(route('forms.show', [$event->slug, $form->slug])) !!}
        <h1>{{ $form->title }}</h1>
        <p>Scan to fill out this form</p>
        <p>{{ route('forms.show', [$event->slug, $form->slug]) }}</p>
        <button onclick="window.print()">Print QR</button>
    </div>
</body>
</html>
