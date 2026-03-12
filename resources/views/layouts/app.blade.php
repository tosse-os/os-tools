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
      <div
        class="flex h-16 items-center justify-between"
        x-data="{ mobileMenuOpen: false }">
        <div class="flex items-center space-x-3">
          <a href="/">
            <img src="{{ asset('images/os-logo.png') }}" alt="Logo" class="h-10 w-auto">
            <!-- <span class="text-xl font-semibold text-gray-800">Orange Tools</span> -->
          </a>
        </div>

        <button
          type="button"
          class="inline-flex items-center justify-center rounded-md p-2 text-gray-700 hover:bg-gray-100 md:hidden"
          @click="mobileMenuOpen = !mobileMenuOpen"
          aria-label="Toggle menu">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </button>

        <nav class="hidden md:flex md:flex-1 md:items-center md:text-sm md:font-medium md:ml-8">

          <div class="flex items-center space-x-2">
            @foreach(config('reports.types') as $type => $report)
            <a href="{{ route($report['route']) }}"
              class="inline-flex items-center rounded-lg px-3 py-2 {{ request()->routeIs($report['route']) ? 'bg-orange-100 text-orange-700 font-semibold ring-1 ring-orange-200' : 'text-gray-700 hover:bg-gray-100' }} transition">
              {{ $report['label'] }}
            </a>
            @endforeach

            <a href="{{ route('projects.index') }}"
              class="inline-flex items-center rounded-lg px-3 py-2 {{ request()->routeIs('projects.*') || request()->routeIs('analyses.*') ? 'bg-orange-100 text-orange-700 font-semibold ring-1 ring-orange-200' : 'text-gray-700 hover:bg-gray-100' }} transition">
              Projects
            </a>


            <a href="{{ route('reports.index') }}"
              class="inline-flex items-center rounded-lg px-3 py-2 {{ request()->routeIs('reports.index') ? 'bg-orange-100 text-orange-700 font-semibold ring-1 ring-orange-200' : 'text-gray-700 hover:bg-gray-100' }} transition">
              Reports
            </a>

            <a href="{{ route('crawls.index') }}"
              class="inline-flex items-center rounded-lg px-3 py-2 {{ request()->routeIs('crawls.*') ? 'bg-orange-100 text-orange-700 font-semibold ring-1 ring-orange-200' : 'text-gray-700 hover:bg-gray-100' }} transition">
              Crawls
            </a>
          </div>

          <div
            class="relative ml-auto"
            x-data="{ open: false }"
            @click.outside="open = false">
            <button
              type="button"
              class="inline-flex items-center rounded-lg px-3 py-2 transition {{ request()->routeIs('logs.index') || request()->routeIs('logs.live') || request()->routeIs('queues.index') || request()->routeIs('admin.settings.*') ? 'bg-orange-100 text-orange-700 font-semibold ring-1 ring-orange-200' : 'text-gray-700 hover:bg-gray-100' }}"
              @click="open = !open"
              aria-haspopup="true"
              :aria-expanded="open.toString()">
              System
              <svg xmlns="http://www.w3.org/2000/svg" class="ml-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>

            <div
              x-cloak
              x-show="open"
              x-transition
              class="absolute right-0 mt-2 w-52 rounded-lg border border-gray-200 bg-white py-1 shadow-lg">
              <a href="{{ route('logs.index') }}"
                class="block px-4 py-2 text-sm {{ request()->routeIs('logs.index') ? 'bg-orange-50 text-orange-700 font-semibold' : 'text-gray-700 hover:bg-gray-50' }}">
                Logs
              </a>
              <a href="{{ route('logs.live') }}"
                class="block px-4 py-2 text-sm {{ request()->routeIs('logs.live') ? 'bg-orange-50 text-orange-700 font-semibold' : 'text-gray-700 hover:bg-gray-50' }}">
                Live Logs
              </a>
              <a href="{{ route('queues.index') }}"
                class="block px-4 py-2 text-sm {{ request()->routeIs('queues.index') ? 'bg-orange-50 text-orange-700 font-semibold' : 'text-gray-700 hover:bg-gray-50' }}">
                Queue Monitor
              </a>
              <a href="{{ route('admin.settings.index') }}"
                class="block px-4 py-2 text-sm {{ request()->routeIs('admin.settings.*') ? 'bg-orange-50 text-orange-700 font-semibold' : 'text-gray-700 hover:bg-gray-50' }}">
                Settings
              </a>
            </div>
          </div>

        </nav>

      </div>

      <div
        x-cloak
        x-show="mobileMenuOpen"
        x-transition
        class="border-t border-gray-200 pb-3 pt-2 md:hidden"
        x-data="{ mobileSystemOpen: false }">
        <div class="space-y-1 text-sm font-medium">
          @foreach(config('reports.types') as $type => $report)
          <a href="{{ route($report['route']) }}"
            class="block rounded-lg px-3 py-2 {{ request()->routeIs($report['route']) ? 'bg-orange-100 text-orange-700 font-semibold ring-1 ring-orange-200' : 'text-gray-700 hover:bg-gray-100' }} transition">
            {{ $report['label'] }}
          </a>
          @endforeach

          <a href="{{ route('projects.index') }}"
            class="block rounded-lg px-3 py-2 {{ request()->routeIs('projects.*') || request()->routeIs('analyses.*') ? 'bg-orange-100 text-orange-700 font-semibold ring-1 ring-orange-200' : 'text-gray-700 hover:bg-gray-100' }} transition">
            Projects
          </a>
          <a href="{{ route('reports.index') }}"
            class="block rounded-lg px-3 py-2 {{ request()->routeIs('reports.index') ? 'bg-orange-100 text-orange-700 font-semibold ring-1 ring-orange-200' : 'text-gray-700 hover:bg-gray-100' }} transition">
            Reports
          </a>
          <a href="{{ route('crawls.index') }}"
            class="block rounded-lg px-3 py-2 {{ request()->routeIs('crawls.*') ? 'bg-orange-100 text-orange-700 font-semibold ring-1 ring-orange-200' : 'text-gray-700 hover:bg-gray-100' }} transition">
            Crawls
          </a>

          <div class="pt-1">
            <button
              type="button"
              class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left {{ request()->routeIs('logs.index') || request()->routeIs('logs.live') || request()->routeIs('queues.index') || request()->routeIs('admin.settings.*') ? 'bg-orange-100 text-orange-700 font-semibold ring-1 ring-orange-200' : 'text-gray-700 hover:bg-gray-100' }} transition"
              @click="mobileSystemOpen = !mobileSystemOpen"
              :aria-expanded="mobileSystemOpen.toString()">
              <span>System</span>
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>

            <div x-show="mobileSystemOpen" x-transition class="mt-1 space-y-1 pl-4">
              <a href="{{ route('logs.index') }}"
                class="block rounded-lg px-3 py-2 {{ request()->routeIs('logs.index') ? 'bg-orange-50 text-orange-700 font-semibold' : 'text-gray-700 hover:bg-gray-100' }}">
                Logs
              </a>
              <a href="{{ route('logs.live') }}"
                class="block rounded-lg px-3 py-2 {{ request()->routeIs('logs.live') ? 'bg-orange-50 text-orange-700 font-semibold' : 'text-gray-700 hover:bg-gray-100' }}">
                Live Logs
              </a>
              <a href="{{ route('queues.index') }}"
                class="block rounded-lg px-3 py-2 {{ request()->routeIs('queues.index') ? 'bg-orange-50 text-orange-700 font-semibold' : 'text-gray-700 hover:bg-gray-100' }}">
                Queue Monitor
              </a>
              <a href="{{ route('admin.settings.index') }}"
                class="block rounded-lg px-3 py-2 {{ request()->routeIs('admin.settings.*') ? 'bg-orange-50 text-orange-700 font-semibold' : 'text-gray-700 hover:bg-gray-100' }}">
                Settings
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </header>

  <main class="py-10 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
    @if (session('system_warnings'))
    <div class="mb-6 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
      <div class="font-semibold mb-1">System warnings</div>
      <ul class="list-disc ml-5 space-y-1">
        @foreach ((array) session('system_warnings') as $warning)
        <li>{{ $warning }}</li>
        @endforeach
      </ul>
    </div>
    @endif

    @yield('content')
  </main>

  @yield('scripts')

</body>

</html>
