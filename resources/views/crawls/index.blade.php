@extends('layouts.app')

@section('content')
<div class="space-y-6">
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-semibold">Crawls</h1>
  </div>

  <div class="rounded-lg bg-white shadow-sm ring-1 ring-gray-200 overflow-hidden">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-50 text-left text-gray-600">
        <tr>
          <th class="px-4 py-3 font-medium">Domain</th>
          <th class="px-4 py-3 font-medium">Pages scanned</th>
          <th class="px-4 py-3 font-medium">Status</th>
          <th class="px-4 py-3 font-medium">Created</th>
          <th class="px-4 py-3 font-medium text-right">Action</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
        @forelse($crawls as $crawl)
          <tr>
            <td class="px-4 py-3 text-gray-900">{{ $crawl->domain }}</td>
            <td class="px-4 py-3 text-gray-700">{{ $crawl->pages_scanned }}</td>
            <td class="px-4 py-3 text-gray-700">{{ $crawl->status }}</td>
            <td class="px-4 py-3 text-gray-700">{{ optional($crawl->created_at)->format('d.m.Y H:i') ?? '—' }}</td>
            <td class="px-4 py-3 text-right">
              <a href="{{ route('crawls.show', $crawl) }}" class="text-blue-600 hover:underline">View</a>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="5" class="px-4 py-6 text-center text-gray-500">No crawls available.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div>
    {{ $crawls->links() }}
  </div>
</div>
@endsection
