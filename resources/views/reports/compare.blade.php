@extends('layouts.app')

@section('content')

<div class="max-w-7xl mx-auto bg-white shadow-sm rounded p-8 space-y-6">
  <div class="flex justify-between items-center">
    <h1 class="text-2xl font-semibold">Report Vergleich</h1>
    <a href="{{ url()->previous() }}" class="text-sm text-blue-600 hover:underline">Zurück</a>
  </div>

  <div class="flex items-center gap-3 text-sm">
    <a
      href="{{ route('reports.compare', array_merge($compareQuery, ['mode' => 'modules'])) }}"
      class="rounded px-3 py-1.5 {{ $mode === 'modules' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' }}">
      Module Comparison
    </a>

    <a
      href="{{ route('reports.compare', array_merge($compareQuery, ['mode' => 'delta'])) }}"
      class="rounded px-3 py-1.5 {{ $mode === 'delta' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700' }}">
      Score Difference
    </a>
  </div>

  @if($mode === 'delta')
  <div class="overflow-x-auto">
    <table class="min-w-full text-sm border">
      <thead class="bg-gray-100 text-left">
        <tr>
          <th class="px-4 py-3 border-b">Module</th>
          @foreach($reports as $report)
          <th class="px-4 py-3 border-b">
            <div class="font-semibold">{{ $report->url }}</div>
            <div class="text-xs font-normal text-gray-500">{{ $report->created_at->format('d.m.Y H:i') }}</div>
          </th>
          @endforeach
          <th class="px-4 py-3 border-b">Difference</th>
        </tr>
      </thead>
      <tbody>
        @foreach($comparisonModules as $module)
        <tr>
          <td class="px-4 py-3 border-b font-semibold align-top">{{ ucfirst($module) }}</td>
          @foreach($reports as $report)
          <td class="px-4 py-3 border-b align-top">
            {{ $scoreDifferences[$module]['scores'][$report->id] ?? '–' }}
          </td>
          @endforeach
          <td class="px-4 py-3 border-b align-top">
            <span class="font-semibold {{ $scoreDifferences[$module]['difference_class'] }}">
              {{ $scoreDifferences[$module]['difference_text'] }}
            </span>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  @else
  <div class="overflow-x-auto">
    <table class="min-w-full text-sm border">
      <thead class="bg-gray-100 text-left">
        <tr>
          <th class="px-4 py-3 border-b">Module</th>
          @foreach($reports as $report)
          <th class="px-4 py-3 border-b">
            <div class="font-semibold">{{ $report->url }}</div>
            <div class="text-xs font-normal text-gray-500">{{ $report->created_at->format('d.m.Y H:i') }}</div>
          </th>
          @endforeach
        </tr>
      </thead>
      <tbody>
        @foreach($comparisonModules as $module)
        <tr>
          <td class="px-4 py-3 border-b font-semibold align-top">{{ ucfirst($module) }}</td>
          @foreach($reports as $report)
          <td class="px-4 py-3 border-b align-top">
            <div class="space-y-2">
              <div>
                <div class="text-xs uppercase text-gray-500">Score</div>
                <div class="font-semibold">
                  {{ $comparisonData[$module][$report->id]['score'] ?? '–' }} / {{ $comparisonData[$module][$report->id]['max_score'] ?? '–' }}
                </div>
              </div>

              <div>
                <div class="text-xs uppercase text-gray-500">Missing</div>
                <ul class="list-disc list-inside text-gray-700">
                  @forelse($comparisonData[$module][$report->id]['missing'] ?? [] as $missing)
                  <li>{{ $missing }}</li>
                  @empty
                  <li class="list-none text-gray-500">–</li>
                  @endforelse
                </ul>
              </div>

              <div>
                <div class="text-xs uppercase text-gray-500">Checks</div>
                <ul class="space-y-1 text-gray-700">
                  @forelse($comparisonData[$module][$report->id]['checks'] ?? [] as $check)
                  <li>
                    <span class="{{ $check['passed'] ? 'text-green-600' : 'text-gray-500' }}">
                      {{ $check['passed'] ? '✓' : '•' }}
                    </span>
                    {{ $check['label'] }}
                  </li>
                  @empty
                  <li class="text-gray-500">–</li>
                  @endforelse
                </ul>
              </div>
            </div>
          </td>
          @endforeach
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  @endif
</div>

@endsection
