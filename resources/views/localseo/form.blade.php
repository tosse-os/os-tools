@extends('layouts.app')

@section('content')

@php($page = 'local-seo')

<div class="max-w-3xl mx-auto bg-white shadow-sm rounded p-6">

  <h1 class="text-xl font-semibold mb-6">
    Local SEO Analyse
  </h1>

  <form id="local-seo-form" class="space-y-4">

    <input
      type="url"
      name="url"
      required
      placeholder="https://example.com"
      value="https://glas-service-muenchen.de"
      class="w-full border border-gray-300 px-4 py-2 rounded focus:ring-2 focus:ring-orange-500">

    <input
      type="text"
      name="keyword"
      required
      placeholder="Keyword (z.B. Glaserei)"
      value="Glaserei"
      class="w-full border border-gray-300 px-4 py-2 rounded focus:ring-2 focus:ring-orange-500">

    <input
      type="text"
      name="city"
      required
      placeholder="Stadt (z.B. München)"
      value="München"
      class="w-full border border-gray-300 px-4 py-2 rounded focus:ring-2 focus:ring-orange-500">

    <button
      type="submit"
      class="bg-orange-600 text-white px-6 py-2 rounded hover:bg-orange-700 transition">
      Analyse starten
    </button>

  </form>

</div>

@endsection
