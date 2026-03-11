@extends('layouts.app')

@section('content')
<div class="space-y-6">
  <div class="flex items-center justify-between">
    <div>
      <h1 class="text-2xl font-semibold">Crawl details</h1>
      <p class="text-sm text-gray-600">{{ $crawl->start_url }}</p>
    </div>
    <div class="flex items-center gap-4">
      <form method="POST" action="{{ route('crawls.rerun', $crawl) }}">
        @csrf
        <button type="submit" class="rounded bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">
          Run Crawl Again
        </button>
      </form>
      <a href="{{ route('crawls.index') }}" class="text-sm text-blue-600 hover:underline">← Back to Crawls</a>
    </div>
  </div>

  <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
    <div class="rounded border p-3 bg-white"><strong>Domain:</strong> {{ $crawl->domain }}</div>
    <div class="rounded border p-3 bg-white"><strong>Status:</strong> {{ $crawl->status }}</div>
    <div class="rounded border p-3 bg-white"><strong>Pages scanned:</strong> {{ $crawl->pages_scanned }}</div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-3 text-sm">
    <div class="rounded border p-3 bg-white"><strong>Pages crawled:</strong> {{ $summary['pages_crawled'] }}</div>
    <div class="rounded border p-3 bg-white"><strong>Internal links:</strong> {{ $summary['internal_links'] }}</div>
    <div class="rounded border p-3 bg-white"><strong>External links:</strong> {{ $summary['external_links'] }}</div>
    <div class="rounded border p-3 bg-white"><strong>Broken links:</strong> {{ $summary['broken_links'] }}</div>
    <div class="rounded border p-3 bg-white"><strong>Redirects:</strong> {{ $summary['redirects'] }}</div>
    <div class="rounded border p-3 bg-white"><strong>Duplicate pages:</strong> {{ $summary['duplicate_pages'] }}</div>
  </div>

  <div class="rounded border bg-white p-4">
    <h2 class="text-lg font-semibold mb-3">Crawl issue reports</h2>
    <div class="grid grid-cols-2 md:grid-cols-3 gap-3 text-sm">
      <div class="rounded border p-2"><strong>Missing ALT text:</strong> {{ $issueReports['missing_alt'] }}</div>
      <div class="rounded border p-2"><strong>Missing H1:</strong> {{ $issueReports['missing_h1'] }}</div>
      <div class="rounded border p-2"><strong>Broken links:</strong> {{ $issueReports['broken_links'] }}</div>
      <div class="rounded border p-2"><strong>Redirect chains:</strong> {{ $issueReports['redirect_chains'] }}</div>
      <div class="rounded border p-2"><strong>Duplicate pages:</strong> {{ $issueReports['duplicate_pages'] }}</div>
      <div class="rounded border p-2"><strong>Orphan pages:</strong> {{ $issueReports['orphan_pages'] }}</div>
    </div>
  </div>

  <div class="rounded-lg bg-white shadow-sm ring-1 ring-gray-200 overflow-hidden">
    <table class="min-w-full text-sm">
      <thead class="bg-gray-50 text-left text-gray-600">
        <tr>
          <th class="px-4 py-3 font-medium">URL</th>
          <th class="px-4 py-3 font-medium">Status</th>
          <th class="px-4 py-3 font-medium">Canonical</th>
          <th class="px-4 py-3 font-medium">Depth</th>
          <th class="px-4 py-3 font-medium">Internal In</th>
          <th class="px-4 py-3 font-medium">Internal Out</th>
          <th class="px-4 py-3 font-medium">ALT count</th>
          <th class="px-4 py-3 font-medium">Heading count</th>
          <th class="px-4 py-3 font-medium">Errors</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
        @forelse($pages as $page)
          <tr>
            <td class="px-4 py-3 break-all text-gray-900">{{ $page->url }}</td>
            <td class="px-4 py-3 text-gray-700">{{ $page->status ?? '—' }}</td>
            <td class="px-4 py-3 break-all text-gray-700">{{ $page->canonical_url ?? '—' }}</td>
            <td class="px-4 py-3 text-gray-700">{{ $page->depth }}</td>
            <td class="px-4 py-3 text-gray-700">{{ $page->internal_links_in }}</td>
            <td class="px-4 py-3 text-gray-700">{{ $page->internal_links_out }}</td>
            <td class="px-4 py-3 text-gray-700">{{ $page->alt_count }}</td>
            <td class="px-4 py-3 text-gray-700">{{ $page->heading_count }}</td>
            <td class="px-4 py-3 text-red-600">{{ $page->error ?? '—' }}</td>
          </tr>
        @empty
          <tr>
            <td colspan="9" class="px-4 py-6 text-center text-gray-500">No crawl pages stored.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div>
    {{ $pages->links() }}
  </div>

  <div class="rounded border bg-white p-4">
    <h2 class="text-lg font-semibold mb-3">Broken links report</h2>
    <div class="space-y-2 text-sm">
      @forelse($brokenLinks as $link)
        <div class="border rounded p-2">
          <div><strong>Source:</strong> {{ $link->source_url }}</div>
          <div><strong>Target:</strong> {{ $link->target_url }}</div>
          <div><strong>Status:</strong> {{ $link->status_code }}</div>
        </div>
      @empty
        <p class="text-gray-500">No broken links detected.</p>
      @endforelse
    </div>
  </div>

  <div class="rounded border bg-white p-4">
    <h2 class="text-lg font-semibold mb-3">Redirect chains report</h2>
    <div class="space-y-2 text-sm">
      @forelse($redirectChains as $link)
        <div class="border rounded p-2 {{ $link->redirect_chain_length > 2 ? 'border-amber-500' : '' }}">
          <div><strong>Source:</strong> {{ $link->source_url }}</div>
          <div><strong>Redirect chain:</strong> {{ collect($link->redirect_chain ?? [])->pluck('url')->join(' → ') }}</div>
          <div><strong>Final target:</strong> {{ $link->redirect_target ?? $link->target_url }}</div>
          <div><strong>Chain length:</strong> {{ $link->redirect_chain_length }}</div>
        </div>
      @empty
        <p class="text-gray-500">No redirects detected.</p>
      @endforelse
    </div>
  </div>

  <div class="rounded border bg-white p-4">
    <h2 class="text-lg font-semibold mb-3">Orphan pages report</h2>
    <div class="space-y-1 text-sm">
      @forelse($orphanPages as $page)
        <div class="border rounded p-2">{{ $page->url }}</div>
      @empty
        <p class="text-gray-500">No orphan pages detected.</p>
      @endforelse
    </div>
  </div>
</div>
@endsection
