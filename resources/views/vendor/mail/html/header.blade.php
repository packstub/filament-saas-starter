{{-- The name at the top of every e-mail, with the workspace's logo when the e-mail is about one. --}}
@props(['url', 'logo' => null])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if ($logo)
<img src="{{ $logo }}" class="logo" alt="">
@endif
<span class="brand">{!! $slot !!}</span>
</a>
</td>
</tr>
