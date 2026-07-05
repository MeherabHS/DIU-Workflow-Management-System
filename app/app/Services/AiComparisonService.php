<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiComparisonService
{
    public function isConfigured(): bool
    {
        return config('ai_comparison.enabled', false)
            && ! empty(config('ai_comparison.api_key', ''));
    }

    public function extractRequirements(string $text): array
    {
        $prompt = <<<'PROMPT'
Extract the requirements from the following document text.
Return ONLY a valid JSON array of objects. Each object must have:
- "text": the requirement description (string)
- "page": the page or section number if available (integer or null)

Do not include any text outside the JSON array.
Do not use markdown code fences.

Document text:
{text}
PROMPT;

        return $this->sendToAi(str_replace('{text}', $text, $prompt));
    }

    public function extractDeliverables(string $text): array
    {
        $prompt = <<<'PROMPT'
Extract the deliverables from the following document text.
Return ONLY a valid JSON array of objects. Each object must have:
- "text": the deliverable description (string)
- "page": the page or section number if available (integer or null)

Do not include any text outside the JSON array.
Do not use markdown code fences.

Document text:
{text}
PROMPT;

        return $this->sendToAi(str_replace('{text}', $text, $prompt));
    }

    public function compareRequirements(array $requirements, array $deliverables): array
    {
        $reqText = json_encode($requirements, JSON_PRETTY_PRINT);
        $delText = json_encode($deliverables, JSON_PRETTY_PRINT);

        $prompt = <<<'PROMPT'
Compare the following requirements against the deliverables.
Requirements may be unstructured letters or memos. Deliverables may be partial reports, evidence notes, or progress files.
Infer implied major requirements when the requirement text is not written as a checklist.
For each requirement, determine if it is:
- "completed": the deliverable fully satisfies the requirement
- "partially_completed": the deliverable partially satisfies the requirement
- "missing": no deliverable addresses this requirement
- "unclear": cannot determine if the requirement is met from the deliverable
Do not mark 100% complete unless every major requirement is clearly satisfied by evidence.

Return ONLY a valid JSON object with this exact structure:
{
  "summary": "short overall summary (2-3 sentences)",
  "completion_percentage": 0.00,
  "status": "completed|partially_completed|missing|unclear",
  "expected_items": ["requirement item inferred from the requirement file"],
  "completed_items": ["requirement/evidence item that is clearly complete"],
  "partial_items": ["requirement/evidence item that is partly satisfied"],
  "pending_items": ["missing, incomplete, or review-needed item"],
  "recommendations": ["practical next step"],
  "items": [
    {
      "requirement": "requirement text",
      "status": "completed|partially_completed|missing|unclear",
      "matched_deliverable": "matched deliverable text or null",
      "notes": "brief explanation"
    }
  ]
}

Requirements:
{requirements}

Deliverables:
{deliverables}
PROMPT;

        $prompt = str_replace(
            ['{requirements}', '{deliverables}'],
            [$reqText, $delText],
            $prompt
        );

        return $this->sendToAi($prompt);
    }

    protected function sendToAi(string $prompt): array
    {
        $response = Http::timeout(config('ai_comparison.timeout', 120))
            ->withHeaders([
                'Authorization' => 'Bearer ' . config('ai_comparison.api_key'),
                'Content-Type' => 'application/json',
            ])
            ->post(config('ai_comparison.base_url') . '/chat/completions', [
                'model' => config('ai_comparison.model'),
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a requirements analysis assistant. Always return valid JSON.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.1,
                'max_tokens' => 4000,
            ]);

        if (! $response->successful()) {
            Log::warning('AI API request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return ['error' => "AI API request failed with status {$response->status()}"];
        }

        $data = $response->json();
        $content = $data['choices'][0]['message']['content'] ?? '';

        // Try to parse JSON from the response
        $parsed = $this->parseJsonFromResponse($content);

        if ($parsed === null) {
            Log::warning('AI returned invalid JSON', ['content' => substr($content, 0, 500)]);

            return ['error' => 'AI returned invalid JSON response', 'raw' => $content];
        }

        return $parsed;
    }

    protected function parseJsonFromResponse(string $content): ?array
    {
        // Try direct JSON decode
        $decoded = json_decode($content, true);
        if ($decoded !== null) {
            return $decoded;
        }

        // Try to find JSON block (strip markdown code fences)
        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $content, $matches)) {
            $decoded = json_decode($matches[1], true);
            if ($decoded !== null) {
                return $decoded;
            }
        }

        // Try to find top-level JSON object
        if (preg_match('/(\{.*\})/s', $content, $matches)) {
            $decoded = json_decode($matches[1], true);
            if ($decoded !== null) {
                return $decoded;
            }
        }

        return null;
    }
}

