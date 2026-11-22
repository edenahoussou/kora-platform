@props(['logo'])
<tr>
<td class="legal-footer">
<div class="footer-brand">
<img src="{{ $logo }}" width="100" height="40" alt="{{ config('app.brand.name') }}" class="footer-logo">
</div>
{{ $slot }}
</td>
</tr>
