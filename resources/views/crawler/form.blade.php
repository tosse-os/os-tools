@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-semibold">Start Crawl</h1>
      <p class="text-sm text-gray-600">Run a new crawler job for a single URL.</p>
    </div>
    <a href="{{ route('crawls.index') }}" class="text-sm text-blue-600 hover:underline">View all crawls</a>
  </div>

  <div class="rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200">
    <form method="POST" action="{{ route('crawl.run') }}" class="space-y-4">
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
        <a href="{{ route('crawls.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Cancel</a>
      </div>
    </form>
  </div>
</div>
@endsection
