@extends('layouts.app')

@section('title', 'Scan-Ergebnisse')

@section('content')
<h1 class="text-2xl font-bold mb-4">Ergebnisse für: {{ $url }}</h1>

<table class="w-full text-sm border border-gray-300">
  <thead class="bg-gray-100">
    <tr>
      <th class="text-left p-2 border-b">URL</th>
      <th class="text-left p-2 border-b">Status</th>
      <th class="text-left p-2 border-b">Titel</th>
      <th class="text-left p-2 border-b">H1</th>
      <th class="text-left p-2 border-b">ALT fehlt</th>
      <th class="text-left p-2 border-b">ALT leer</th>
    </tr>
  </thead>
  <tbody>
    @foreach ($results as $result)
    <tr>
      <td class="p-2 border-b">{{ $result['url'] }}</td>
      <td class="p-2 border-b">{{ $result['status']['code'] ?? '–' }}</td>
      <td class="p-2 border-b">{{ $result['title'] ?? '–' }}</td>
      <td class="p-2 border-b">{{ $result['headingCheck']['count']['h1'] ?? 0 }}</td>
      <td class="p-2 border-b">{{ $result['altCheck']['altMissing'] ?? 0 }}</td>
      <td class="p-2 border-b">{{ $result['altCheck']['altEmpty'] ?? 0 }}</td>
    </tr>
    @endforeach
  </tbody>
</table>
@endsection
