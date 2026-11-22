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
.inner-body {
width: 100% !important;
}
.content-cell {
padding: 24px 18px !important;
}
.legal-footer {
font-size: 11px !important;
padding: 14px 18px 20px !important;
}
}

@media only screen and (max-width: 500px) {
.action table {
width: 100% !important;
}
.button {
display: block !important;
}
}
@media (prefers-color-scheme: dark) {
body, .wrapper { background-color: #111827 !important; }
.inner-body { background-color: #182233 !important; }
.content-cell, .content-cell p, .content-cell h1, .content-cell h2,
.content-cell h3, .content-cell td, .legal-footer, .environment-label {
color: #f3f6fa !important;
}
.content-cell a { color: #a9ceff !important; }
.content-cell .button { color: #001d4e !important; }
.panel-content { background-color: #253248 !important; }
.subcopy, .legal-footer { border-color: #526179 !important; }
}
</style>
{!! $head ?? '' !!}
</head>
<body>
@if (filled($preview))
<div style="display:none;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;">{{ $preview }}</div>
@endif

<table class="wrapper" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="center">
<table class="content" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="shell">
<table class="inner-body" align="center" width="640" cellpadding="0" cellspacing="0" role="presentation">
{!! $header ?? '' !!}
<tr>
<td class="content-cell">
{!! Illuminate\Mail\Markdown::parse($slot) !!}
{!! $subcopy ?? '' !!}
</td>
</tr>
{!! $footer ?? '' !!}
</table>
</td>
</tr>
</table>
</td>
</tr>
</table>
</body>
</html>
