<!DOCTYPE html>
<html lang="de">

<head>
  <meta charset="UTF-8">
  <title>{{ $title ?? 'Orange Tools' }}</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  @vite(['resources/css/app.css', 'resources/js/app.js'])
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <script src="//unpkg.com/alpinejs" defer></script>
</head>

<body
  class="bg-gray-50 text-gray-900 antialiased"
  data-page="{{ $page ?? '' }}">

  <header class="bg-white border-b border-gray-200 sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex h-16 items-center justify-between">
        <div class="flex items-center space-x-3">
          <a href="/">
            <img src="{{ asset('images/os-logo.png') }}" alt="Logo" class="h-10 w-auto">
            <!-- <span class="text-xl font-semibold text-gray-800">Orange Tools</span> -->
          </a>
        </div>
        <nav class="flex space-x-6 text-sm font-medium">

          @foreach(config('reports.types') as $type => $report)
          <a href="{{ route($report['route']) }}"
            class="flex items-center {{ request()->routeIs($report['route']) ? 'text-orange-600 font-semibold' : 'text-gray-700' }} hover:text-orange-600 transition">
            {{ $report['label'] }}
          </a>
          @endforeach

          <a href="{{ route('reports.index') }}"
            class="flex items-center {{ request()->routeIs('reports.index') ? 'text-orange-600 font-semibold' : 'text-gray-700' }} hover:text-orange-600 transition">
            Reports
          </a>

        </nav>

      </div>
    </div>
  </header>




  <main class="py-10 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
    @yield('content')
  </main>
  @yield('scripts')

</body>

</html>
