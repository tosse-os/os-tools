@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto px-4 py-6">
  <h1 class="text-2xl font-semibold mb-6">Meine Scans</h1>

  @if ($scans->isEmpty())
  <p class="text-gray-600">Noch keine Scans vorhanden.</p>
  @else
  <div class="overflow-x-auto bg-white shadow rounded">
    <table class="min-w-full table-auto text-sm text-left border-collapse">
      <thead class="bg-gray-100 border-b">
        <tr>
          <th class="px-4 py-2">#</th>
          <th class="px-4 py-2">Start-URL</th>
          <th class="px-4 py-2">Status</th>
          <th class="px-4 py-2">Ergebnisse</th>
          <th class="px-4 py-2">Erstellt</th>
          <th class="px-4 py-2">Aktion</th>
        </tr>
      </thead>
      <tbody class="divide-y text-gray-800">
        @foreach ($scans as $scan)
        <tr>
          <td class="px-4 py-2">{{ $loop->iteration }}</td>
          <td class="px-4 py-2 break-all">{{ $scan->url }}</td>
          <td class="px-4 py-2">
            <span class="inline-block px-2 py-1 rounded text-white text-xs
                                    @if ($scan->status === 'done') bg-green-500
                                    @elseif ($scan->status === 'queued') bg-gray-400
                                    @elseif ($scan->status === 'running') bg-blue-500
                                    @else bg-red-500 @endif">
              {{ $scan->status }}
            </span>
          </td>
          <td class="px-4 py-2 text-center">{{ $scan->results()->count() }}</td>
          <td class="px-4 py-2 text-gray-500">{{ $scan->created_at->format('d.m.Y H:i') }}</td>
          <td class="px-4 py-2">
            <a href="{{ route('scans.show', $scan) }}" class="text-orange-600 hover:underline text-sm">
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
