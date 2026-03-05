@extends('layouts.app')

@section('content')
<div class="space-y-6">
  <section class="bg-white border border-gray-200 rounded-2xl shadow-sm p-6 sm:p-8">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-2xl font-semibold text-gray-900">Queue Monitor</h1>
        <p class="text-sm text-gray-500 mt-1">Operational overview of queued and failed background jobs.</p>
      </div>
      <div class="flex flex-wrap gap-2">
        <span class="inline-flex items-center rounded-full bg-blue-100 px-3 py-1 text-xs font-medium text-blue-700">
          Queued: {{ count($jobs) }}
        </span>
        <span class="inline-flex items-center rounded-full bg-red-100 px-3 py-1 text-xs font-medium text-red-700">
          Failed: {{ count($failedJobs) }}
        </span>
      </div>
    </div>
  </section>

  <section class="bg-white border border-gray-200 rounded-2xl shadow-sm p-6">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-lg font-semibold text-gray-900">Queued Jobs</h2>
      <span class="inline-flex items-center rounded-full bg-blue-100 px-3 py-1 text-xs font-medium text-blue-700">Pending</span>
    </div>

    <div class="overflow-x-auto rounded-xl border border-gray-200">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-100 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
          <tr>
            <th class="py-3 px-4">ID</th>
            <th class="py-3 px-4">Queue</th>
            <th class="py-3 px-4">Attempts</th>
            <th class="py-3 px-4">Status</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          @forelse($jobs as $job)
          <tr class="odd:bg-white even:bg-gray-50 hover:bg-blue-50 transition-colors">
            <td class="py-3 px-4 text-gray-800">{{ $job->id }}</td>
            <td class="py-3 px-4 text-gray-700">{{ $job->queue }}</td>
            <td class="py-3 px-4 text-gray-700">{{ $job->attempts }}</td>
            <td class="py-3 px-4">
              <span class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-1 text-xs font-semibold text-blue-700 ring-1 ring-blue-200">Queued</span>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="4" class="py-8 px-4 text-center text-gray-500">No queued jobs found.</td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>

  <section class="bg-white border border-gray-200 rounded-2xl shadow-sm p-6">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-lg font-semibold text-gray-900">Failed Jobs</h2>
      <span class="inline-flex items-center rounded-full bg-red-100 px-3 py-1 text-xs font-medium text-red-700">Failed</span>
    </div>

    <div class="overflow-x-auto rounded-xl border border-gray-200">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-100 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
          <tr>
            <th class="py-3 px-4">ID</th>
            <th class="py-3 px-4">Status</th>
            <th class="py-3 px-4">Exception</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
          @forelse($failedJobs as $failedJob)
          <tr class="align-top odd:bg-white even:bg-gray-50 hover:bg-red-50 transition-colors">
            <td class="py-3 px-4 text-gray-800">{{ $failedJob->id }}</td>
            <td class="py-3 px-4">
              <span class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-700 ring-1 ring-red-200">Failed</span>
            </td>
            <td class="py-3 px-4">
              <pre class="font-mono text-xs leading-relaxed text-gray-800 whitespace-pre-wrap break-words">{{ $failedJob->exception }}</pre>
            </td>
          </tr>
          @empty
          <tr>
            <td colspan="3" class="py-8 px-4 text-center text-gray-500">No failed jobs found.</td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </section>
</div>
@endsection
