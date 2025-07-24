@extends('layouts.app')

@section('title', 'Mehrseitenscan')

@section('content')
<h1 class="text-2xl font-bold mb-4">Mehrseitiger Website-Scan</h1>

<form method="POST" action="{{ route('multiscan.run') }}" class="space-y-4">
  @csrf
  <input type="text" name="url" value="https://orange-services.de" class="border px-3 py-2 w-full rounded">
  <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Scan starten</button>
</form>
@endsection
