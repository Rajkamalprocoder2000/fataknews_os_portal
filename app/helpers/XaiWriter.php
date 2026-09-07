<?php
// app/helpers/XaiWriter.php

class XaiWriter {
    public static function isConfigured(): bool {
        $provider = self::provider();
        if ($provider === 'groq') {
            return trim(GROQ_API_KEY) !== '';
        }

        if ($provider === 'xai') {
            return trim(XAI_API_KEY) !== '';
        }

        return false;
    }

    public static function generateArticle(array $input): array {
        if (!self::isConfigured()) {
            throw new RuntimeException('No supported AI API key is configured on the server. Set XAI_API_KEY or GROQ_API_KEY.');
        }

        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL extension is required for AI integration.');
        }

        $topic = trim((string)($input['topic'] ?? ''));
        if ($topic === '') {
            throw new InvalidArgumentException('Topic is required.');
        }

        $angle = trim((string)($input['angle'] ?? ''));
        $tone = trim((string)($input['tone'] ?? 'standard')) ?: 'standard';
        $language = trim((string)($input['language'] ?? 'english')) ?: 'english';
        $wordCount = max(250, min(1500, (int)($input['word_count'] ?? 700)));
        $preferredType = trim((string)($input['preferred_type'] ?? 'news')) ?: 'news';
        $selectedCategory = trim((string)($input['selected_category_slug'] ?? ''));
        $categoryOptions = $input['category_options'] ?? [];

        $categoryMap = [];
        $categorySlugs = [];
        if (is_array($categoryOptions)) {
            foreach ($categoryOptions as $option) {
                $slug = trim((string)($option['slug'] ?? ''));
                $name = trim((string)($option['name'] ?? ''));
                if ($slug === '' || $name === '') {
                    continue;
                }
                $categoryMap[$slug] = $name;
                $categorySlugs[] = $slug;
            }
        }

        if ($selectedCategory !== '' && !in_array($selectedCategory, $categorySlugs, true)) {
            $selectedCategory = '';
        }

        $systemPrompt = implode("\n", [
            'You are the newsroom drafting assistant for FatakNews.in.',
            'Write clean, publication-ready article drafts for a digital news platform.',
            'Optimise for search intent and click-through rate without using fake urgency, false promises, or misleading clickbait.',
            'Do not invent direct quotes, eyewitness claims, hard statistics, or named sources unless the user explicitly provides them.',
            'If the topic is broad or underspecified, write a plausible draft with cautious, generic framing and no fabricated facts.',
            'Stay tightly aligned to the user topic and brief. Do not drift into unrelated national, political, or generic filler.',
            'If exact facts are missing, write a service-oriented or context-first draft that clearly stays within the likely scope of the topic.',
            'Front-load the main keyword, city, beat, or year when it genuinely improves discoverability.',
            'The article body must be HTML using only <p>, <h2>, <h3>, <ul>, <ol>, <li>, <blockquote>, <strong>, and <em> tags.',
            'Use short paragraphs, clear sectioning, and scannable structure suitable for Google Search and mobile readers.',
            'Headlines should be high-interest but credible. Avoid ALL CAPS, fake numbers, and unsupported superlatives.',
            'Articles should usually answer: what happened, why it matters, key takeaways, and what comes next.',
            'Return only content that fits the requested JSON/object shape.',
        ]);

        $categoryList = [];
        foreach ($categoryMap as $slug => $name) {
            $categoryList[] = $slug . ' = ' . $name;
        }

        $userPrompt = implode("\n", array_filter([
            'Create a publishable first draft for this newsroom request.',
            'Topic: ' . $topic,
            $angle !== '' ? 'Angle / brief: ' . $angle : null,
            'Preferred tone: ' . $tone,
            'Preferred language: ' . $language,
            'Target word count: ' . $wordCount,
            'Preferred post type: ' . $preferredType,
            $selectedCategory !== '' ? 'Selected category slug: ' . $selectedCategory : null,
            !empty($categoryList) ? 'Available top-level category slugs: ' . implode('; ', $categoryList) : null,
            self::languageGuidance($language),
            self::toneGuidance($tone),
            self::seoGuidance(),
            self::articleGuidance($preferredType, $wordCount),
            'Keep every section tightly connected to the topic. Do not replace the topic with a broader theme.',
            'Tags should be short, lowercase, and useful for publishing workflows.',
            'Also produce 4 headline_variants: one search-first, one high-CTR but credible, one explainer-style, and one short mobile-friendly option.',
            'Also produce 4 seo_notes for the editor. Each note must be practical, short, and action-oriented.',
            'Also produce a share_blurb for social/WhatsApp sharing in 1-2 sentences without hashtags.',
            'SEO title should stay under 65 characters when possible.',
            'SEO description should stay around 150-160 characters when possible.',
            'Return only a JSON object with these keys: title, excerpt, content_html, tags, seo_title, seo_description, post_type_hint, category_slug_hint, headline_variants, seo_notes, share_blurb.',
            'Do not wrap the JSON in markdown fences.',
        ]));

        $schema = [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'title' => [
                    'type' => 'string',
                    'description' => 'Compelling article headline.',
                ],
                'excerpt' => [
                    'type' => 'string',
                    'description' => 'Two-sentence summary for feed previews.',
                ],
                'content_html' => [
                    'type' => 'string',
                    'description' => 'Full article body in safe HTML only.',
                ],
                'tags' => [
                    'type' => 'array',
                    'description' => 'Short publishing tags.',
                    'items' => [
                        'type' => 'string',
                    ],
                ],
                'seo_title' => [
                    'type' => 'string',
                    'description' => 'SEO title.',
                ],
                'seo_description' => [
                    'type' => 'string',
                    'description' => 'SEO meta description.',
                ],
                'post_type_hint' => [
                    'type' => 'string',
                    'enum' => ['news', 'article', 'breaking'],
                    'description' => 'Best-fit post type.',
                ],
                'category_slug_hint' => [
                    'type' => 'string',
                    'description' => 'Best-fit top-level category slug from the provided list.',
                ],
                'headline_variants' => [
                    'type' => 'array',
                    'description' => 'Alternate title ideas with different editorial angles.',
                    'items' => [
                        'type' => 'string',
                    ],
                ],
                'seo_notes' => [
                    'type' => 'array',
                    'description' => 'Short practical SEO/editorial suggestions for this draft.',
                    'items' => [
                        'type' => 'string',
                    ],
                ],
                'share_blurb' => [
                    'type' => 'string',
                    'description' => 'Short social/share blurb for increasing opens without misleading readers.',
                ],
            ],
            'required' => [
                'title',
                'excerpt',
                'content_html',
                'tags',
                'seo_title',
                'seo_description',
                'post_type_hint',
                'category_slug_hint',
                'headline_variants',
                'seo_notes',
                'share_blurb',
            ],
        ];

        [$path, $payload, $mode] = self::buildRequest($systemPrompt, $userPrompt, $schema);
        try {
            $response = self::request($path, $payload);
        } catch (RuntimeException $e) {
            if (!self::shouldRetryWithGroqJsonObject($e)) {
                throw $e;
            }

            [$path, $payload, $mode] = self::buildRequest($systemPrompt, $userPrompt, $schema, 'json_object');
            $response = self::request($path, $payload);
        }
        $content = self::extractOutputText($response, $mode);
        $decoded = self::decodeJsonPayload($content);

        if (!is_array($decoded)) {
            throw new RuntimeException('AI provider returned an invalid structured response.');
        }

        $categoryHint = trim((string)($decoded['category_slug_hint'] ?? ''));
        if ($categoryHint !== '' && !empty($categoryMap) && !isset($categoryMap[$categoryHint])) {
            $categoryHint = $selectedCategory !== '' ? $selectedCategory : array_key_first($categoryMap);
        }

        $tags = [];
        foreach (($decoded['tags'] ?? []) as $tag) {
            $tag = Helper::slug((string)$tag);
            if ($tag !== '') {
                $tags[] = $tag;
            }
        }
        $tags = array_values(array_unique(array_slice($tags, 0, 8)));

        $title = self::normalizeLine((string)($decoded['title'] ?? ''));
        $excerpt = self::normalizeLine((string)($decoded['excerpt'] ?? ''));
        $contentHtml = self::sanitizeGeneratedHtml((string)($decoded['content_html'] ?? ''));
        $seoTitle = self::normalizeLine((string)($decoded['seo_title'] ?? ''));
        $seoDescription = self::normalizeLine((string)($decoded['seo_description'] ?? ''));
        $headlineVariants = self::normalizeList($decoded['headline_variants'] ?? [], 4, 110);
        $seoNotes = self::normalizeList($decoded['seo_notes'] ?? [], 4, 180);
        $shareBlurb = self::normalizeLine((string)($decoded['share_blurb'] ?? ''));

        if ($title === '') {
            $title = self::normalizeLine($topic);
        }
        if ($excerpt === '') {
            $excerpt = Helper::excerpt($contentHtml, 170);
        }
        if ($seoTitle === '') {
            $seoTitle = $title;
        }
        if ($seoDescription === '') {
            $seoDescription = $excerpt !== '' ? $excerpt : Helper::excerpt($contentHtml, 160);
        }
        if (empty($headlineVariants)) {
            $headlineVariants = array_values(array_unique(array_filter([
                $title,
                self::normalizeLine($title . ' - What it means'),
                self::normalizeLine($topic),
            ])));
        }
        if (empty($seoNotes)) {
            $seoNotes = [
                'Opening paragraph me main keyword naturally rakho.',
                'Kam se kam ek H2 me city/topic/year mention karo.',
                'Cover image aur excerpt ko search intent ke hisaab se align rakho.',
                'Title ko credible rakho, overclaim ya fake urgency avoid karo.',
            ];
        }
        if ($shareBlurb === '') {
            $shareBlurb = $excerpt !== '' ? $excerpt : $title;
        }

        return [
            'title' => $title,
            'excerpt' => $excerpt,
            'content_html' => $contentHtml,
            'tags' => $tags,
            'seo_title' => mb_substr($seoTitle, 0, 70),
            'seo_description' => mb_substr($seoDescription, 0, 170),
            'post_type_hint' => trim((string)($decoded['post_type_hint'] ?? 'news')),
            'category_slug_hint' => $categoryHint,
            'headline_variants' => $headlineVariants,
            'seo_notes' => $seoNotes,
            'share_blurb' => mb_substr($shareBlurb, 0, 220),
        ];
    }

    private static function languageGuidance(string $language): string {
        switch (strtolower($language)) {
            case 'hindi':
                return 'Write in natural Hindi (Devanagari), with newsroom-style clarity. Use everyday journalistic Hindi, not stiff translation Hindi.';
            case 'hinglish':
                return 'Write in clean Hinglish for digital readers, but keep the headline and SEO copy highly readable and search-friendly.';
            default:
                return 'Write in clear English suitable for Indian digital news readers.';
        }
    }

    private static function toneGuidance(string $tone): string {
        switch (strtolower($tone)) {
            case 'breaking':
                return 'Use urgent, crisp news writing. Lead with the update immediately, keep sentences tighter, and prioritise speed plus clarity.';
            case 'explainer':
                return 'Use explanatory framing with context, key takeaways, and reader-friendly subheads.';
            case 'analytical':
                return 'Use analytical framing with implications, tradeoffs, and likely impact, while keeping the copy readable.';
            default:
                return 'Use standard digital newsroom tone: clear, direct, credible, and mobile-friendly.';
        }
    }

    private static function seoGuidance(): string {
        return 'SEO rules: include the main search phrase naturally in the title, excerpt, opening paragraph, and at least one subheading; avoid keyword stuffing; make the headline curiosity-rich but accurate; prefer entity-rich wording (city, topic, year, impact) when relevant.';
    }

    private static function articleGuidance(string $preferredType, int $wordCount): string {
        switch ($preferredType) {
            case 'breaking':
                return 'Article rules: open with the strongest update, follow with why it matters, then add a short key-points list if useful. Keep it tight and high-signal.';
            case 'article':
                return 'Article rules: use a stronger narrative flow, include 3-5 useful sub-sections, and make the piece feel feature-quality while still search-friendly.';
            default:
                return 'Article rules: structure the piece for news searchers. Cover what happened, why it matters, key takeaways, and what happens next. Target around ' . $wordCount . ' words unless the topic clearly needs less.';
        }
    }

    private static function normalizeLine(string $value): string {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        return trim($value, " \t\n\r\0\x0B-");
    }

    private static function normalizeList($values, int $limit, int $maxLength = 180): array {
        if (!is_array($values)) {
            return [];
        }

        $result = [];
        foreach ($values as $value) {
            $line = self::normalizeLine((string)$value);
            if ($line === '') {
                continue;
            }

            $result[] = mb_substr($line, 0, $maxLength);
        }

        return array_values(array_unique(array_slice($result, 0, $limit)));
    }

    private static function sanitizeGeneratedHtml(string $html): string {
        $html = trim($html);
        $html = preg_replace('/<\s*(script|style)[^>]*>.*?<\s*\/\s*\1\s*>/is', '', $html) ?? $html;
        $html = preg_replace('/\son[a-z]+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/i', '', $html) ?? $html;
        $html = strip_tags($html, '<p><h2><h3><ul><ol><li><blockquote><strong><em>');
        return trim($html);
    }

    private static function provider(): string {
        $provider = strtolower(trim((string)AI_PROVIDER));
        if (in_array($provider, ['groq', 'xai'], true)) {
            return $provider;
        }

        if (trim(GROQ_API_KEY) !== '') {
            return 'groq';
        }

        if (trim(XAI_API_KEY) !== '') {
            return 'xai';
        }

        return 'none';
    }

    private static function buildRequest(string $systemPrompt, string $userPrompt, array $schema, string $format = 'schema'): array {
        switch (self::provider()) {
            case 'groq':
                if ($format === 'json_object') {
                    return [
                        '/chat/completions',
                        [
                            'model' => GROQ_MODEL,
                            'messages' => [
                                [
                                    'role' => 'system',
                                    'content' => $systemPrompt . "\nReturn a single valid JSON object only. No markdown, no prose, no code fences.",
                                ],
                                [
                                    'role' => 'user',
                                    'content' => $userPrompt . "\nReturn a single valid JSON object with exactly these keys: title, excerpt, content_html, tags, seo_title, seo_description, post_type_hint, category_slug_hint, headline_variants, seo_notes, share_blurb.",
                                ],
                            ],
                            'temperature' => 0.2,
                            'include_reasoning' => false,
                            'response_format' => [
                                'type' => 'json_object',
                            ],
                        ],
                        'chat',
                    ];
                }

                $jsonSchema = [
                    'name' => 'fataknews_article_draft',
                    'schema' => $schema,
                ];

                if (self::groqSupportsStrictStructuredOutputs()) {
                    $jsonSchema['strict'] = true;
                }

                return [
                    '/chat/completions',
                    [
                        'model' => GROQ_MODEL,
                        'messages' => [
                            ['role' => 'system', 'content' => $systemPrompt],
                            ['role' => 'user', 'content' => $userPrompt],
                        ],
                        'temperature' => 0.2,
                        'include_reasoning' => false,
                        'response_format' => [
                            'type' => 'json_schema',
                            'json_schema' => $jsonSchema,
                        ],
                    ],
                    'chat',
                ];
            case 'xai':
                return [
                    '/chat/completions',
                    [
                        'model' => XAI_MODEL,
                        'messages' => [
                            ['role' => 'system', 'content' => $systemPrompt],
                            ['role' => 'user', 'content' => $userPrompt],
                        ],
                        'temperature' => 0.2,
                        'response_format' => [
                            'type' => 'json_schema',
                            'json_schema' => [
                                'name' => 'fataknews_article_draft',
                                'schema' => $schema,
                                'strict' => true,
                            ],
                        ],
                    ],
                    'chat',
                ];
            default:
                throw new RuntimeException('No supported AI provider is configured on the server.');
        }
    }

    private static function request(string $path, array $payload): array {
        $provider = self::provider();
        switch ($provider) {
            case 'groq':
                $url = rtrim(GROQ_BASE_URL, '/') . $path;
                $apiKey = GROQ_API_KEY;
                break;
            case 'xai':
                $url = rtrim(XAI_BASE_URL, '/') . $path;
                $apiKey = XAI_API_KEY;
                break;
            default:
                throw new RuntimeException('No supported AI provider is configured on the server.');
        }

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => AI_TIMEOUT,
        ]);

        if (defined('AI_CA_BUNDLE') && is_file(AI_CA_BUNDLE)) {
            curl_setopt($ch, CURLOPT_CAINFO, AI_CA_BUNDLE);
        }

        $raw = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException('AI request failed: ' . ($error ?: 'unknown cURL error'));
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('AI provider returned a non-JSON response.');
        }

        if ($status >= 400) {
            $message = is_array($decoded['error'] ?? null)
                ? ($decoded['error']['message'] ?? $decoded['error']['code'] ?? 'Unknown API error')
                : ($decoded['error'] ?? $decoded['message'] ?? $decoded['code'] ?? 'Unknown API error');
            $failedGeneration = '';
            if (is_array($decoded['error'] ?? null) && isset($decoded['error']['failed_generation'])) {
                $failedGeneration = trim((string)$decoded['error']['failed_generation']);
            } elseif (isset($decoded['failed_generation'])) {
                $failedGeneration = trim((string)$decoded['failed_generation']);
            }

            if ($failedGeneration !== '') {
                $message .= ' | failed_generation=' . mb_substr($failedGeneration, 0, 500);
            }
            throw new RuntimeException('AI provider error: ' . $message);
        }

        return $decoded;
    }

    private static function groqSupportsStrictStructuredOutputs(): bool {
        $model = strtolower(trim((string)GROQ_MODEL));
        return in_array($model, ['openai/gpt-oss-20b', 'openai/gpt-oss-120b'], true);
    }

    private static function shouldRetryWithGroqJsonObject(Throwable $e): bool {
        if (self::provider() !== 'groq') {
            return false;
        }

        $message = strtolower(trim($e->getMessage()));
        return str_contains($message, 'failed to validate json')
            || str_contains($message, 'failed_generation')
            || str_contains($message, 'json_schema');
    }

    private static function extractOutputText(array $response, string $mode): string {
        if ($mode === 'chat') {
            $message = $response['choices'][0]['message'] ?? [];
            $content = $message['content'] ?? null;

            if (is_string($content) && trim($content) !== '') {
                return trim($content);
            }

            if (is_array($content)) {
                $parts = [];
                foreach ($content as $part) {
                    if (is_string($part) && trim($part) !== '') {
                        $parts[] = trim($part);
                        continue;
                    }

                    if (is_array($part)) {
                        $text = trim((string)($part['text'] ?? $part['content'] ?? ''));
                        if ($text !== '') {
                            $parts[] = $text;
                        }
                    }
                }

                if (!empty($parts)) {
                    return trim(implode("\n", $parts));
                }
            }

            $refusal = trim((string)($message['refusal'] ?? ''));
            if ($refusal !== '') {
                throw new RuntimeException('AI provider refused the request: ' . $refusal);
            }

            $finishReason = trim((string)($response['choices'][0]['finish_reason'] ?? ''));
            if ($finishReason !== '') {
                throw new RuntimeException('No usable chat content was returned by the AI provider. finish_reason=' . $finishReason);
            }

            throw new RuntimeException('No usable chat content was returned by the AI provider.');
        }

        if (!empty($response['output_text']) && is_string($response['output_text'])) {
            return trim($response['output_text']);
        }

        foreach (($response['output'] ?? []) as $item) {
            if (($item['type'] ?? '') !== 'message') {
                continue;
            }
            foreach (($item['content'] ?? []) as $content) {
                if (($content['type'] ?? '') === 'output_text' && is_string($content['text'] ?? null)) {
                    return trim($content['text']);
                }
            }
        }

        throw new RuntimeException('No usable text content was returned by the AI provider.');
    }

    private static function decodeJsonPayload(string $content): ?array {
        $trimmed = trim($content);
        $decoded = self::decodeCandidateJson($trimmed);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/is', $trimmed, $matches)) {
            $decoded = self::decodeCandidateJson($matches[1]);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $start = strpos($trimmed, '{');
        $end = strrpos($trimmed, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $decoded = self::decodeCandidateJson(substr($trimmed, $start, $end - $start + 1));
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private static function decodeCandidateJson(string $candidate): ?array {
        $candidate = trim($candidate);
        if ($candidate === '') {
            return null;
        }

        $decoded = json_decode($candidate, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $repaired = self::repairJsonLikePayload($candidate);
        if ($repaired !== $candidate) {
            $decoded = json_decode($repaired, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private static function repairJsonLikePayload(string $payload): string {
        $payload = preg_replace('/^\xEF\xBB\xBF/', '', $payload) ?? $payload;

        $result = '';
        $inString = false;
        $escaped = false;
        $length = strlen($payload);

        for ($i = 0; $i < $length; $i++) {
            $char = $payload[$i];

            if ($inString) {
                if ($escaped) {
                    $result .= $char;
                    $escaped = false;
                    continue;
                }

                if ($char === '\\') {
                    $result .= $char;
                    $escaped = true;
                    continue;
                }

                if ($char === '"') {
                    $result .= $char;
                    $inString = false;
                    continue;
                }

                if ($char === "\r") {
                    $result .= '\r';
                    continue;
                }

                if ($char === "\n") {
                    $result .= '\n';
                    continue;
                }

                if ($char === "\t") {
                    $result .= '\t';
                    continue;
                }

                if (ord($char) < 32) {
                    $result .= sprintf('\u%04x', ord($char));
                    continue;
                }

                $result .= $char;
                continue;
            }

            if ($char === '"') {
                $inString = true;
            }

            $result .= $char;
        }

        return $result;
    }
}
