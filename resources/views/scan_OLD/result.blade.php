@extends('layouts.app')

@section('title', 'Scan-Ergebnis')

@section('content')
<h1 class="text-2xl font-bold mb-4">Scan-Ergebnis: {{ $data['url'] ?? '–' }}</h1>

@if (!empty($data['status']))
<section class="mb-6">
  <h2 class="text-xl font-semibold">HTTP-Status</h2>
  <p><strong>Statuscode:</strong> {{ $data['status']['code'] ?? '–' }}</p>
  <p><strong>Finale URL:</strong> {{ $data['status']['finalUrl'] ?? '–' }}</p>
  <p><strong>Weitergeleitet?</strong> {{ $data['status']['redirected'] ? 'Ja' : 'Nein' }}</p>
</section>
@endif

@if (!empty($data['headingCheck']))
<section class="mb-6">
  <h2 class="text-xl font-semibold">Überschriften</h2>
  <p><strong>H1:</strong> {{ $data['headingCheck']['count']['h1'] ?? 0 }}</p>
  <p><strong>H2:</strong> {{ $data['headingCheck']['count']['h2'] ?? 0 }}</p>
  <p><strong>H3:</strong> {{ $data['headingCheck']['count']['h3'] ?? 0 }}</p>

  @if (!empty($data['headingCheck']['errors']))
  <h4 class="mt-4">⚠️ Probleme:</h4>
  <ul class="list-disc pl-5">
    @foreach ($data['headingCheck']['errors'] as $err)
    <li>{{ $err }}</li>
    @endforeach
  </ul>
  @endif

  <h4 class="mt-4">Beispielüberschriften (max. 10)</h4>
  <ul class="list-disc pl-5">
    @foreach ($data['headingCheck']['list'] as $heading)
    <li><strong>{{ strtoupper($heading['tag']) }}</strong>: {{ $heading['text'] ?: '🛑 Leer' }}</li>
    @endforeach
  </ul>
</section>
@endif

@if (!empty($data['altCheck']))
<section class="mb-6">
  <h2 class="text-xl font-semibold">ALT-Texte</h2>
  <p><strong>Gesamtanzahl Bilder:</strong> {{ $data['altCheck']['imageCount'] ?? 0 }}</p>
  <p><strong>Fehlende ALT-Attribute:</strong> {{ $data['altCheck']['altMissing'] ?? 0 }}</p>
  <p><strong>Leere ALT-Attribute:</strong> {{ $data['altCheck']['altEmpty'] ?? 0 }}</p>

  <h4 class="mt-4">Beispielbilder (max. 10)</h4>
  <ul class="list-disc pl-5">
    @foreach ($data['altCheck']['preview'] as $img)
    <li>
      <code>{{ $img['src'] }}</code> →
      <em>
        @if (is_null($img['alt']))
        ❌ Kein ALT
        @elseif (trim($img['alt']) === '')
        ⚠️ Leeres ALT
        @else
        ✅ {{ $img['alt'] }}
        @endif
      </em>
    </li>
    @endforeach
  </ul>
</section>
@endif
@endsection
<div class="mt-6">
  <a href="{{ route('scans.index') }}" class="inline-block bg-orange-600 text-white px-4 py-2 rounded hover:bg-orange-700">
    Zur Scan-Übersicht
  </a>
</div>
