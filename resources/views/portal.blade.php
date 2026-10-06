<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Your company's everyday tools, together in one place.">
    <title>{{ $portal['name'] }} · {{ $portal['product']['name'] ?? 'Your company workspace' }}</title>
    @vite('resources/js/app.ts')
</head>
<body>
    <script>
        window.portal = @js($portal);
    </script>
    <div id="app"></div>
</body>
</html>
