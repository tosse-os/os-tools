@extends('layouts.app')

@section('content')
<div class="space-y-6">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-semibold">Crawl details</h1>
      <p class="text-sm text-gray-600">{{ $crawl->start_url }}</p>
    </div>
    <a href="{{ route('crawls.index') }}" class="text-sm text-blue-600 hover:underline">← Back to Crawls</a>
  </div>

  <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
    <div class="rounded border p-3 bg-white"><strong>Domain:</strong> {{ $crawl->domain }}</div>
    <div class="rounded border p-3 bg-white"><strong>Status:</strong> {{ $crawl->status }}</div>
    <div class="rounded border p-3 bg-white"><strong>Pages scanned:</strong> {{ $crawl->pages_scanned }}</div>
  </div>

  <div class="rounded-lg bg-white shadow-sm ring-1 ring-gray-200 overflow-hidden">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-50 text-left text-gray-600">
        <tr>
          <th class="px-4 py-3 font-medium">URL</th>
          <th class="px-4 py-3 font-medium">Status</th>
          <th class="px-4 py-3 font-medium">ALT count</th>
          <th class="px-4 py-3 font-medium">Heading count</th>
          <th class="px-4 py-3 font-medium">Errors</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
        @forelse($pages as $page)
          <tr>
            <td class="px-4 py-3 break-all text-gray-900">{{ $page->url }}</td>
            <td class="px-4 py-3 text-gray-700">{{ $page->status ?? '—' }}</td>
            <td class="px-4 py-3 text-gray-700">{{ $page->alt_count }}</td>
            <td class="px-4 py-3 text-gray-700">{{ $page->heading_count }}</td>
            <td class="px-4 py-3 text-red-600">{{ $page->error ?? '—' }}</td>
          </tr>
        @empty
          <tr>
            <td colspan="5" class="px-4 py-6 text-center text-gray-500">No crawl pages stored.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div>
    {{ $pages->links() }}
  </div>
</div>
@endsection
