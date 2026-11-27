@props(['preview' => null])
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<title>{{ config('app.brand.name') }}</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="color-scheme" content="light dark">
<meta name="supported-color-schemes" content="light dark">
<style>
@media only screen and (max-width: 640px) {
.content-cell {
padding: 16px 12px !important;
}
.legal-footer {
padding: 12px 12px 16px !important;
}
}

@media only screen and (max-width: 420px) {
.footer-brand,
.footer-logo {
width: 76px !important;
}
.footer-logo {
height: auto !important;
}
}

@media (prefers-color-scheme: dark) {
body { background-color: #111827 !important; }
.content-cell, .content-cell p, .content-cell h1, .content-cell h2,
.content-cell h3, .content-cell td, .footer-details, .environment-label {
color: #f3f6fa !important;
}
.content-cell a { color: #a9ceff !important; }
.content-cell .button { color: #001d4e !important; }
.panel-content { background-color: #253248 !important; }
.subcopy { border-color: #526179 !important; }
}
</style>
{!! $head ?? '' !!}
</head>
<body>
@if (filled($preview))
<div style="display:none;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;">{{ $preview }}</div>
@endif

<table class="content" width="100%" cellpadding="0" cellspacing="0" role="presentation">
{!! $header ?? '' !!}
<tr>
<td class="content-cell" align="left">
{!! Illuminate\Mail\Markdown::parse($slot) !!}
{!! $subcopy ?? '' !!}
</td>
</tr>
{!! $footer ?? '' !!}
</table>
</body>
</html>
