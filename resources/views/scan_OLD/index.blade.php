@extends('layouts.app')

@section('content')
<h1 class="text-2xl font-bold mb-6">Alle Scans</h1>

<table class="w-full table-auto border">
  <thead>
    <tr class="bg-gray-100 text-left text-sm">
      <th class="p-2">ID</th>
      <th class="p-2">URL</th>
      <th class="p-2">Status</th>
      <th class="p-2">Datum</th>
      <th class="p-2">Aktion</th>
    </tr>
  </thead>
  <tbody>
    @foreach ($scans as $scan)
    <tr class="border-t text-sm">
      <td class="p-2">{{ $scan->id }}</td>
      <td class="p-2 break-words">{{ $scan->url }}</td>
      <td class="p-2">{{ $scan->status }}</td>
      <td class="p-2">{{ $scan->created_at->format('d.m.Y H:i') }}</td>
      <td class="p-2">
        <a href="{{ route('scans.show', $scan) }}" class="text-blue-600 hover:underline">Details</a>
      </td>
    </tr>
    @endforeach
  </tbody>
</table>

<div class="mt-6">
  {{ $scans->links() }}
</div>
@endsection
