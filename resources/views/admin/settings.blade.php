@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto">
  <h1 class="text-2xl font-semibold mb-2">Crawler Settings</h1>
  <p class="text-sm text-gray-600 mb-6">Configure runtime limits for crawler scans.</p>

  @if (session('status'))
  <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
    {{ session('status') }}
  </div>
  @endif

  <form method="POST" action="{{ route('admin.settings.update') }}" class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm space-y-5">
    @csrf

    @foreach ($settingKeys as $key)
    @if ($key === 'crawler_use_redis')
    <div class="rounded-lg border border-gray-200 p-4">
      <label for="crawler_use_redis" class="inline-flex items-center gap-3 text-sm font-medium text-gray-700">
        <input
          type="checkbox"
          id="crawler_use_redis"
          name="crawler_use_redis"
          value="1"
          @checked(old('crawler_use_redis', $settings['crawler_use_redis'] ?? false))
          class="rounded border-gray-300 text-orange-500 focus:ring-orange-400"
        >
        <span>Enable Redis Queue</span>
      </label>
      <p class="mt-2 text-xs text-gray-500">If enabled, the crawler uses Redis queues and Redis-backed event transport.</p>
      @error($key)
      <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
      @enderror
    </div>
    @continue
    @endif

    <div>
      <label for="{{ $key }}" class="block text-sm font-medium text-gray-700 mb-1">
        {{ str_replace('_', ' ', ucfirst($key)) }}
      </label>

      <input
        type="number"
        min="1"
        id="{{ $key }}"
        name="{{ $key }}"
        value="{{ old($key, $settings[$key]) }}"
        class="w-full rounded-lg border-gray-300 focus:border-orange-400 focus:ring-orange-400"
      >

      @error($key)
      <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
      @enderror
    </div>
    @endforeach

    <div class="pt-2">
      <button
        type="submit"
        class="inline-flex items-center rounded-lg bg-orange-500 px-4 py-2 text-white font-medium hover:bg-orange-600 transition"
      >
        Save Settings
      </button>
    </div>
  </form>
</div>
@endsection
