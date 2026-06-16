<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>OAuth authorization failed</title>
    <style>
        body { font-family: system-ui, sans-serif; margin: 2rem; line-height: 1.5; }
        h1 { font-size: 1.25rem; margin-bottom: 1rem; }
        ul { color: #b91c1c; padding-left: 1.25rem; }
    </style>
</head>
<body>
    <h1>OAuth authorization failed</h1>
    <ul>
        @foreach ($errors as $messages)
            @foreach ($messages as $message)
                <li>{{ $message }}</li>
            @endforeach
        @endforeach
    </ul>
</body>
</html>
