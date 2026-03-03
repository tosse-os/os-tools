@extends('layouts.app')

@section('content')

<div class="max-w-6xl mx-auto bg-white shadow-sm rounded p-8 space-y-8">

  <div class="flex justify-between items-center">
    <h1 class="text-2xl font-semibold">Report Archiv</h1>
    <a href="{{ route('dashboard') }}" class="text-sm text-blue-600 hover:underline">
      Zurück zum Dashboard
    </a>
  </div>

  @if($reports->isEmpty())
  <div class="text-gray-500 text-sm">
    Noch keine Reports vorhanden.
  </div>
  @else

  <div class="overflow-x-auto">
    <table class="min-w-full text-sm border">

      <thead class="bg-gray-100 text-left">
        <tr>
          <th class="px-4 py-3 border-b">Datum</th>
          <th class="px-4 py-3 border-b">URL</th>
          <th class="px-4 py-3 border-b">Typ</th>
          <th class="px-4 py-3 border-b">Status</th>
          <th class="px-4 py-3 border-b">Score</th>
          <th class="px-4 py-3 border-b text-right">Aktion</th>
        </tr>
      </thead>

      <tbody>
        @foreach($reports as $report)

        <tr class="hover:bg-gray-50">
          <td class="px-4 py-3 border-b">
            {{ $report->created_at->format('d.m.Y H:i') }}
          </td>

          <td class="px-4 py-3 border-b">
            <div class="truncate max-w-xs">
              {{ $report->url }}
            </div>
          </td>

          <td class="px-4 py-3 border-b">
            {{ ucfirst($report->type) }}
          </td>

          <td class="px-4 py-3 border-b">
            @if($report->status === 'done')
            <span class="text-green-600 font-semibold">Fertig</span>
            @elseif($report->status === 'running')
            <span class="text-yellow-600 font-semibold">Läuft</span>
            @elseif($report->status === 'queued')
            <span class="text-gray-600 font-semibold">In Warteschlange</span>
            @else
            <span class="text-red-600 font-semibold">Fehler</span>
            @endif
          </td>

          <td class="px-4 py-3 border-b">
            @if($report->score !== null)
            <span class="font-semibold">
              {{ $report->score }}
            </span>
            @else
            –
            @endif
          </td>

          <td class="px-4 py-3 border-b text-right">
            <a
              href="{{ route('reports.show', $report->id) }}"
              class="text-blue-600 hover:underline">
              Anzeigen
            </a>
          </td>
        </tr>

        @endforeach
      </tbody>

    </table>
  </div>

  @endif

</div>

@endsection
