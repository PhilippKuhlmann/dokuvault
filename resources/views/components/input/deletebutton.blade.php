@props(['link'])

<form method="POST" action="{{ $link }}">
    @csrf
    @method('DELETE')
    <x-input.button color="red" :label="__('Löschen')" />
</form>
