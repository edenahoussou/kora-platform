@props(['logo'])
<tr>
<td class="legal-footer">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="footer-brand" width="100" valign="middle">
<img src="{{ $logo }}" width="100" height="40" alt="{{ config('app.brand.name') }}" class="footer-logo">
</td>
<td class="footer-details" valign="middle">
{{ $slot }}
</td>
</tr>
</table>
</td>
</tr>
