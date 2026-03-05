@extends('layouts.app')

@section('content')
<div class="space-y-8">
  <div class="bg-white shadow-sm rounded p-6">
    <h1 class="text-2xl font-semibold mb-6">Queue Monitor</h1>

    <h2 class="text-lg font-semibold mb-3">Queued Jobs</h2>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm mb-4">
        <thead>
          <tr class="border-b text-left text-gray-600">
            <th class="py-2 pr-4">ID</th>
            <th class="py-2 pr-4">Queue</th>
            <th class="py-2">Attempts</th>
          </tr>
        </thead>
        <tbody>
          @forelse($jobs as $job)
          <tr class="border-b">
            <td class="py-2 pr-4">{{ $job->id }}</td>
            <td class="py-2 pr-4">{{ $job->queue }}</td>
            <td class="py-2">{{ $job->attempts }}</td>
          </tr>
          @empty
          <tr>
            <td colspan="3" class="py-4 text-gray-500">No queued jobs found.</td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>

    <h2 class="text-lg font-semibold mb-3">Failed Jobs</h2>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm">
        <thead>
          <tr class="border-b text-left text-gray-600">
            <th class="py-2 pr-4">ID</th>
            <th class="py-2">Exception</th>
          </tr>
        </thead>
        <tbody>
          @forelse($failedJobs as $failedJob)
          <tr class="border-b align-top">
            <td class="py-2 pr-4">{{ $failedJob->id }}</td>
            <td class="py-2 break-all">{{ $failedJob->exception }}</td>
          </tr>
          @empty
          <tr>
            <td colspan="2" class="py-4 text-gray-500">No failed jobs found.</td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection
