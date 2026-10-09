{{--
    The expiry mail in the shared mail frame (vendor/mail/html). The rows
    are HTML, not a markdown table: each needs a coloured status, and a
    markdown table cannot carry one.

    No indentation in the HTML below - the frame runs the content through
    markdown, and four leading spaces turn a line into a code block.
--}}
@php
    $badge = function (array $item) {
        if ($item['days'] < 0) {
            return ['#fee2e2', '#991b1b', $item['days'] === -1 ? __('seit 1 Tag abgelaufen') : __('seit :tage Tagen abgelaufen', ['tage' => -$item['days']])];
        }
        if ($item['days'] === 0) {
            return ['#fee2e2', '#991b1b', __('läuft heute ab')];
        }
        $text = $item['days'] === 1 ? __('morgen') : __('in :tage Tagen', ['tage' => $item['days']]);

        return $item['days'] <= 7 ? ['#ffedd5', '#9a3412', $text] : ['#fef9c3', '#854d0e', $text];
    };
    $pill = 'display:inline-block; padding:5px 12px; border-radius:999px; font-size:13px; font-weight:600;';
@endphp
<x-mail::message>
# {{ __('Ablaufende Einträge') }}

<x-mail::rich>
<table role="presentation" cellpadding="0" cellspacing="0" style="margin-bottom:8px;"><tr>
@if ($expired > 0)
<td style="padding-right:8px;"><span style="{{ $pill }} background:#fee2e2; color:#991b1b;">{{ $expired === 1 ? __('1 abgelaufen') : __(':anzahl abgelaufen', ['anzahl' => $expired]) }}</span></td>
@endif
@if ($soon > 0)
<td><span style="{{ $pill }} background:#fef9c3; color:#854d0e;">{{ $soon === 1 ? __('1 läuft bald ab') : __(':anzahl laufen bald ab', ['anzahl' => $soon]) }}</span></td>
@endif
</tr></table>

@foreach ($groups as $customer => $items)
<p style="margin:24px 0 0; padding-bottom:8px; border-bottom:2px solid #e5e7eb; font-size:15px; font-weight:700; color:#122748;">{{ $customer }}</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0">
@foreach ($items as $item)
@php([$bg, $fg, $text] = $badge($item))
<tr>
<td style="padding:12px 0; border-bottom:1px solid #f1f5f9; vertical-align:top;"><a href="{{ $item['url'] }}" style="font-size:15px; font-weight:600; color:#111827; text-decoration:none; word-break:normal;">{{ $item['name'] }}</a><br><span style="font-size:13px; color:#6b7280;">{{ $item['label'] }} · {{ $item['date']->format('d.m.Y') }}</span></td>
<td align="right" style="padding:12px 0 12px 12px; border-bottom:1px solid #f1f5f9; vertical-align:middle; white-space:nowrap;"><span style="display:inline-block; padding:4px 10px; border-radius:6px; background:{{ $bg }}; color:{{ $fg }}; font-size:12px; font-weight:600;">{{ $text }}</span></td>
</tr>
@endforeach
</table>
@endforeach

<x-slot:plain>
@foreach ($groups as $customer => $items)
{{ $customer }}
@foreach ($items as $item)
- {{ $item['name'] }} ({{ $item['label'] }}), {{ $item['date']->format('d.m.Y') }}{{ $item['days'] < 0 ? ' – '.__('abgelaufen') : '' }}: {{ $item['url'] }}
@endforeach

@endforeach
</x-slot:plain>
</x-mail::rich>

@if ($rest > 0)
{{ __('… und :anzahl weitere. Die vollständige Liste steht auf den Dashboards der Kunden.', ['anzahl' => $rest]) }}
@endif

<x-mail::button :url="$home">
{{ __(':anwendung öffnen', ['anwendung' => $app]) }}
</x-mail::button>

<x-slot:subcopy>
{{ __('Jeder Eintrag kommt einmal, wenn er in die Vorwarnzeit fällt, und noch einmal, wenn er abgelaufen ist.') }}
{{ __('Abbestellen:') }} [{{ __('Profil → Benachrichtigungen') }}]({{ $profile }})
</x-slot:subcopy>
</x-mail::message>
