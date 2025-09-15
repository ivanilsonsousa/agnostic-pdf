<!doctype html>
<html>
<head><meta charset="utf-8"><title>{{ $title }}</title></head>
<body style="font-family: DejaVu Sans, sans-serif; padding: 48px;">
  <h1 style="margin:0 0 8px 0;">{{ $title }}</h1>
  <p style="margin:0 0 16px 0;">{{ $subtitle ?? '' }}</p>
  <small>Generated at: {{ $generatedAt ?? now() }}</small>
  <hr>
</body>
</html>
