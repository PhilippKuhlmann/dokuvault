{{-- HTML that only belongs in the HTML part of a mail. The text part
     shows the "plain" slot instead (see text/rich.blade.php) - otherwise
     the raw tags would land in the plain-text mail. --}}
{!! $slot !!}
