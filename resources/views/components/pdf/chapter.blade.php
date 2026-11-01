@props(['title', 'first' => false])

{{-- Eines der zwölf Kapitel, nach denen das Blatt geordnet ist. data-toc-chapter
     wird beim Rendern gelesen - daraus entstehen die fetten Zeilen im
     Inhaltsverzeichnis und der linke Teil der Kopfzeile. --}}
<div class="chapter {{ $first ? 'chapter-first' : '' }}" data-toc-chapter="{{ $title }}">
    <div class="chapter-rule"></div>
    <div class="chapter-title">{{ $title }}</div>
</div>
