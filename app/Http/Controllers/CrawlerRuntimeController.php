<?php

namespace App\Http\Controllers;

use App\Services\Crawler\CrawlerEventProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CrawlerRuntimeController extends Controller
{
    public function event(Request $request, CrawlerEventProcessor $processor): JsonResponse
    {
        $payload = $request->validate([
            'crawl_id' => ['required', 'string'],
            'type' => ['required', 'string'],
            'payload' => ['nullable', 'array'],
        ]);

        $processed = $processor->process($payload['crawl_id'], $payload);

        return response()->json(['processed' => $processed]);
    }

    public function nextTask(Request $request): JsonResponse
    {
        $data = $request->validate([
            'crawl_id' => ['required', 'string'],
        ]);

        $next = DB::transaction(function () use ($data) {
            $record = DB::table('crawl_queue')
                ->where('crawl_id', $data['crawl_id'])
                ->where('status', 'pending')
                ->orderBy('created_at')
                ->lockForUpdate()
                ->first();

            if (!$record) {
                return null;
            }

            DB::table('crawl_queue')
                ->where('crawl_id', $data['crawl_id'])
                ->where('url', $record->url)
                ->update(['status' => 'processing']);

            return $record;
        });

        if (!$next) {
            return response()->json(['task' => null]);
        }

        return response()->json([
            'task' => [
                'crawl_id' => $next->crawl_id,
                'url' => $next->url,
                'depth' => (int) $next->depth,
            ],
        ]);
    }
}
