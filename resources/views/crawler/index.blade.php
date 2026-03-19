@extends('layouts.app')

@section('content')
<div class="space-y-6">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-semibold">Crawls</h1>
      <p class="text-sm text-gray-600">Run a new crawler job for a single URL.</p>
    </div>
  </div>

  <div class="rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200">
    <form method="POST" action="{{ route('crawler.run') }}" class="space-y-4">
      @csrf

      <div>
        <label for="url" class="mb-1 block text-sm font-medium text-gray-700">Domain / URL</label>
        <input
          id="url"
          name="url"
          type="url"
          value="{{ old('url') }}"
          placeholder="https://example.com"
          required
          class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-orange-500 focus:outline-none focus:ring-2 focus:ring-orange-200"
        >
        @error('url')
          <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
      </div>

      <div class="flex items-center gap-3">
        <button type="submit" class="rounded-lg bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700">
          Start crawl
        </button>
      </div>
    </form>
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
