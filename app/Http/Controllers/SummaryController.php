<?php

namespace App\Http\Controllers;

use App\Http\Requests\SummarizeTextRequest;
use Illuminate\Http\JsonResponse;

class SummaryController extends Controller
{
    public function summarizeText(SummarizeTextRequest $request): JsonResponse
    {
        $text = (string) $request->input('text', '');
        $maxSentences = (int) ($request->input('maxSentences') ?? 5);
        $timezone = (string) ($request->input('timezone') ?? config('app.timezone', 'UTC'));

        // Simple deterministic extractive approach: take the first N sentences.
        $sentences = preg_split('/(?<=[.!?])\s+/u', trim($text)) ?: [];
        $sentences = array_values(array_filter($sentences, static fn ($s) => $s !== ''));
        $summarySentences = array_slice($sentences, 0, max(1, $maxSentences));

        $payload = [
            'title' => 'Text Summary',
            'timeframe' => null,
            'metrics' => null,
            'highlights' => $summarySentences,
            'risks' => [],
            'anomalies' => [],
            'nextActions' => [],
            'generatedAt' => now($timezone)->toIso8601String(),
        ];

        return response()->json(['success' => true, 'data' => $payload]);
    }
}
