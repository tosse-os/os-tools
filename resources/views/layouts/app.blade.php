<!DOCTYPE html>
<html lang="de">

<head>
  <meta charset="UTF-8">
  <title>{{ $title ?? 'Orange Tools' }}</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  @vite('resources/css/app.css')
</head>

<body class="bg-gray-50 text-gray-900 antialiased">

  <header class="bg-white border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex h-16 items-center justify-between">
        <div class="flex items-center space-x-3">
          <img src="{{ asset('images/os-logo.png') }}" alt="Logo" class="h-10 w-auto">
          <!-- <span class="text-xl font-semibold text-gray-800">Orange Tools</span> -->
        </div>
        <!-- <nav class="flex space-x-6 text-sm font-medium">
          <a href="{{ route('scan.form') }}"
            class="flex items-center {{ request()->routeIs('scan.form') ? 'text-orange-600 font-semibold' : 'text-gray-700' }} hover:text-orange-600 transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 mr-2 stroke-current" fill="none" viewBox="0 0 24 24" stroke-width="2">
              <circle cx="11" cy="11" r="8" />
              <line x1="21" y1="21" x2="16.65" y2="16.65" />
            </svg>
            Einzelseitenscan
          </a> -->

        <a href="{{ route('scan.form') }}"
          class="flex items-center {{ request()->routeIs('multiscan.form') ? 'text-orange-600 font-semibold' : 'text-gray-700' }} hover:text-orange-600 transition">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 mr-2 stroke-current" fill="none" viewBox="0 0 24 24" stroke-width="2">
            <polygon points="12 2 2 7 12 12 22 7 12 2" />
            <polyline points="2 17 12 22 22 17" />
            <polyline points="2 12 12 17 22 12" />
          </svg>
          Seitencrawler
        </a>

        <a href="{{ route('scans.index') }}"
          class="flex items-center {{ request()->routeIs('scans.index') ? 'text-orange-600 font-semibold' : 'text-gray-700' }} hover:text-orange-600 transition">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 mr-2 stroke-current" fill="none" viewBox="0 0 24 24" stroke-width="2">
            <path d="M3 6h18M3 12h18M3 18h18" />
          </svg>
          Scans
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
