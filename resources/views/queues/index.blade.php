@extends('layouts.app')

@section('content')

<div class="space-y-8">

  <div class="bg-white shadow rounded-lg p-6">

    <div class="flex items-center justify-between mb-2">

      <div>
        <h1 class="text-2xl font-semibold text-gray-900">Queue Monitor</h1>
        <p class="text-sm text-gray-500 mt-1">
          Operational overview of queued and failed background jobs.
        </p>
      </div>

      <div class="flex gap-2">
        <span class="inline-flex items-center rounded-full bg-blue-100 px-3 py-1 text-xs font-medium text-blue-700">
          Queued: {{ isset($jobs) ? count($jobs) : 0 }}
        </span>

        <span class="inline-flex items-center rounded-full bg-red-100 px-3 py-1 text-xs font-medium text-red-700">
          Failed: {{ isset($failedJobs) ? count($failedJobs) : 0 }}
        </span>
      </div>

    </div>

  </div>


  <div class="bg-white shadow rounded-lg p-6">

    <div class="flex items-center justify-between mb-4">
      <h2 class="text-lg font-semibold text-gray-900">Queued Jobs</h2>

      <span class="inline-flex items-center rounded-full bg-blue-100 px-3 py-1 text-xs font-medium text-blue-700">
        Pending
      </span>
    </div>

    <div class="overflow-x-auto">

      <table class="min-w-full divide-y divide-gray-200 text-sm">

        <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
          <tr>
            <th class="px-4 py-3">ID</th>
            <th class="px-4 py-3">Queue</th>
            <th class="px-4 py-3">Attempts</th>
            <th class="px-4 py-3">Status</th>
          </tr>
        </thead>

        <tbody class="divide-y divide-gray-200">

          @forelse($jobs ?? [] as $job)

          <tr class="odd:bg-white even:bg-gray-50">

            <td class="py-3 px-4 text-gray-700">
              {{ $job->id ?? '-' }}
            </td>

            <td class="py-3 px-4 text-gray-700">
              {{ $job->queue ?? '-' }}
            </td>

            <td class="py-3 px-4 text-gray-700">
              {{ $job->attempts ?? 0 }}
            </td>

            <td class="py-3 px-4 text-gray-700">
              queued
            </td>

          </tr>

          @empty

          <tr>
            <td colspan="4" class="py-6 text-center text-gray-500">
              No queued jobs found.
            </td>
          </tr>

          @endforelse

        </tbody>

      </table>

    </div>

  </div>


  <div class="bg-white shadow rounded-lg p-6">

    <div class="flex items-center justify-between mb-4">
      <h2 class="text-lg font-semibold text-gray-900">Failed Jobs</h2>

      <span class="inline-flex items-center rounded-full bg-red-100 px-3 py-1 text-xs font-medium text-red-700">
        {{ isset($failedJobs) ? count($failedJobs) : 0 }}
      </span>
    </div>

    <div class="overflow-x-auto">

      <table class="min-w-full divide-y divide-gray-200 text-sm">

        <thead class="bg-gray-50 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
          <tr>
            <th class="px-4 py-3">ID</th>
            <th class="px-4 py-3">Job</th>
            <th class="px-4 py-3">Error</th>
            <th class="px-4 py-3">Failed At</th>
          </tr>
        </thead>

        <tbody class="divide-y divide-gray-200">

          @forelse($failedJobs ?? [] as $job)

          @php

          $payload = json_decode($job->payload ?? '{}', true);

          $jobName =
          $payload['displayName']
          ?? $payload['job']
          ?? 'unknown';

          $exception = $job->exception ?? '';

          $lines = explode("\n", $exception);

          $message = $lines[0] ?? '';

          $trace = array_slice($lines, 1);

          @endphp

          <tr class="align-top odd:bg-white even:bg-gray-50 hover:bg-orange-50 transition-colors">

            <td class="py-3 px-4 text-gray-700">
              {{ $job->id ?? '-' }}
            </td>

            <td class="py-3 px-4 text-gray-700">
              {{ $jobName }}
            </td>

            <td class="py-3 px-4">

              <div class="font-semibold text-red-600">
                {{ $message }}
              </div>

              @if(!empty($trace))

              <details class="mt-2 text-xs text-gray-600">

                <summary class="cursor-pointer text-orange-600 hover:underline">
                  Stacktrace anzeigen
                </summary>

                <pre class="mt-2 whitespace-pre-wrap bg-gray-50 p-3 rounded border border-gray-200 overflow-x-auto">
                {{ implode("\n", $trace) }}
                </pre>

              </details>

              @endif

            </td>

            <td class="py-3 px-4 text-gray-700">
              {{ $job->failed_at ?? '-' }}
            </td>

          </tr>

          @empty

          <tr>
            <td colspan="4" class="py-6 text-center text-gray-500">
              No failed jobs.
            </td>
          </tr>

          @endforelse

        </tbody>

      </table>

    </div>

  </div>

</div>

@endsection
