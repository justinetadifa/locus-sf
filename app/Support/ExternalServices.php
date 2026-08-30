<?php
declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class ExternalServices
{
    private array $services;
    private array $seedMeta;
    private SimpleCache $cache;

    public function __construct(private array $config, ?array $seedMeta = null)
    {
        $this->services = is_array($config['services'] ?? null) ? $config['services'] : [];
        $this->seedMeta = $seedMeta ?? JsonData::meta();
        $cachePath = (string) ($this->services['cache']['path'] ?? (dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'external'));
        $this->cache = new SimpleCache($cachePath);
    }

    public function describeServices(array $seedServices = []): array
    {
        $locationKey = $this->locationIqKey();
        $alphaKey = trim((string) ($this->services['market']['alpha_vantage_key'] ?? ''));
        $newsKey = trim((string) ($this->services['news']['newsapi_key'] ?? ''));
        $weatherKey = trim((string) ($this->services['weather']['openweather_key'] ?? ''));
        $cloudinary = $this->hasCloudinaryConfig();
        $aiProvider = $this->configuredAiProvider();

        return array_replace_recursive($seedServices, [
            'maps' => [
                'provider' => 'Leaflet + OpenStreetMap',
                'mode' => 'live-map',
                'enabled' => true,
                'note' => $locationKey !== ''
                    ? 'Interactive map is live with server-side LocationIQ geocoding.'
                    : 'Interactive map is live. Add a LocationIQ key for live geocoding.',
                'capabilities' => [
                    'geocode' => $locationKey !== '',
                    'staticMaps' => false,
                    'propertyMarkers' => true,
                ],
            ],
            'location' => [
                'provider' => $locationKey !== '' ? 'LocationIQ' : 'Local property search',
                'mode' => $locationKey !== '' ? 'live-geocoding' : 'fallback-search',
                'enabled' => $locationKey !== '',
                'note' => $locationKey !== '' ? 'Location search is powered by server-side LocationIQ requests.' : 'Location search currently falls back to local property matching.',
            ],
            'media' => [
                'provider' => $cloudinary ? 'Cloudinary' : 'Local property uploads',
                'mode' => $cloudinary ? 'optional-cloudinary' : 'local-storage',
                'enabled' => $cloudinary,
                'note' => $cloudinary ? 'Cloudinary is ready for property uploads while local assets stay supported.' : 'Property media is currently stored locally until Cloudinary is configured.',
            ],
            'marketData' => [
                'provider' => $alphaKey !== '' ? 'Alpha Vantage' : 'Seeded market context',
                'mode' => $alphaKey !== '' ? 'live-cached' : 'fallback',
                'enabled' => $alphaKey !== '',
                'note' => $alphaKey !== '' ? 'Market indicators are fetched through cached Alpha Vantage calls.' : 'Add an Alpha Vantage key to replace seeded market context with live data.',
            ],
            'news' => [
                'provider' => $newsKey !== '' ? 'NewsAPI' : 'Seeded investment digest',
                'mode' => $newsKey !== '' ? 'live-cached' : 'fallback',
                'enabled' => $newsKey !== '',
                'note' => $newsKey !== '' ? 'Business headlines are fetched through cached NewsAPI requests.' : 'Add a NewsAPI key to replace the seeded investment digest with live headlines.',
            ],
            'weather' => [
                'provider' => $weatherKey !== '' ? 'OpenWeather' : 'Climate note',
                'mode' => $weatherKey !== '' ? 'live-cached' : 'fallback',
                'enabled' => $weatherKey !== '',
                'note' => $weatherKey !== '' ? 'Weather context is available through OpenWeather.' : 'Add an OpenWeather key to enable live property-level weather context.',
            ],
            'ai' => [
                'provider' => $aiProvider ?? 'Structured fallback summary',
                'mode' => $aiProvider !== null ? 'live-orchestrated' : 'fallback',
                'enabled' => $aiProvider !== null,
                'note' => $aiProvider !== null ? sprintf('Property summaries are generated through %s.', $aiProvider) : 'Add Gemini or OpenRouter credentials to enable live AI summaries.',
            ],
        ]);
    }

    public function marketSnapshot(): array
    {
        $fallback = $this->fallbackMarketSnapshot();
        $apiKey = trim((string) ($this->services['market']['alpha_vantage_key'] ?? ''));
        if ($apiKey === '') {
            return $fallback;
        }

        try {
            return $this->cache->remember('alpha-market-snapshot', 21600, function () use ($apiKey, $fallback): array {
                $status = $this->httpJson('GET', 'https://www.alphavantage.co/query', [
                    'function' => 'MARKET_STATUS',
                    'apikey' => $apiKey,
                ]);
                $movers = $this->httpJson('GET', 'https://www.alphavantage.co/query', [
                    'function' => 'TOP_GAINERS_LOSERS',
                    'apikey' => $apiKey,
                ]);

                $markets = is_array($status['markets'] ?? null) ? $status['markets'] : [];
                $us = $this->firstMarket($markets, 'US');
                $fx = $this->firstMarket($markets, 'Forex');
                $crypto = $this->firstMarket($markets, 'Crypto');
                $gainer = is_array(($movers['top_gainers'] ?? [])[0] ?? null) ? $movers['top_gainers'][0] : null;
                $active = is_array(($movers['most_actively_traded'] ?? [])[0] ?? null) ? $movers['most_actively_traded'][0] : null;

                $metrics = [
                    ['label' => 'US equities', 'value' => $this->marketLabel($us['current_status'] ?? null, 'Unknown')],
                    ['label' => 'Forex', 'value' => $this->marketLabel($fx['current_status'] ?? null, 'Unknown')],
                    ['label' => 'Crypto', 'value' => $this->marketLabel($crypto['current_status'] ?? null, 'Unknown')],
                ];
                if ($gainer !== null) {
                    $metrics[] = ['label' => 'Top gainer', 'value' => trim(((string) ($gainer['ticker'] ?? '')) . ' ' . ((string) ($gainer['change_percentage'] ?? '')))];
                }
                if ($active !== null) {
                    $metrics[] = ['label' => 'Most active', 'value' => trim(((string) ($active['ticker'] ?? '')) . ' ' . ((string) ($active['volume'] ?? '')))];
                }

                return [
                    'provider' => 'Alpha Vantage',
                    'mode' => 'live-cached',
                    'live' => true,
                    'summary' => [
                        'headline' => 'Live market context for investor briefings',
                        'subline' => 'Directional global signals surfaced through cached Alpha Vantage calls.',
                    ],
                    'metrics' => $metrics,
                    'highlights' => array_values(array_filter([
                        $us !== null ? 'US equities status is visible for timing context.' : null,
                        $fx !== null ? 'Forex availability is surfaced for cross-border discussions.' : null,
                        $gainer !== null ? sprintf('Top gainer: %s %s.', (string) ($gainer['ticker'] ?? ''), (string) ($gainer['change_percentage'] ?? '')) : null,
                        $active !== null ? sprintf('Most active symbol: %s.', (string) ($active['ticker'] ?? '')) : null,
                    ])),
                    'fetchedAt' => gmdate(DATE_ATOM),
                ];
            });
        } catch (Throwable $e) {
            $fallback['note'] = 'Live Alpha Vantage data could not be loaded right now.';
            $fallback['error'] = $e->getMessage();
            return $fallback;
        }
    }

    public function newsDigest(int $limit = 6): array
    {
        $limit = max(1, min(12, $limit));
        $fallback = $this->fallbackNewsDigest($limit);
        $apiKey = trim((string) ($this->services['news']['newsapi_key'] ?? ''));
        if ($apiKey === '') {
            return $fallback;
        }

        $query = trim((string) ($this->services['news']['query'] ?? '("San Fernando" OR "La Union" OR Philippines) AND (business OR investment OR property OR infrastructure)'));

        try {
            return $this->cache->remember('news-' . sha1($query . '|' . $limit), 3600, function () use ($apiKey, $query, $limit, $fallback): array {
                $payload = $this->httpJson('GET', 'https://newsapi.org/v2/everything', [
                    'q' => $query,
                    'language' => 'en',
                    'sortBy' => 'publishedAt',
                    'searchIn' => 'title,description',
                    'pageSize' => $limit,
                ], null, ['X-Api-Key' => $apiKey]);

                $items = [];
                foreach ((array) ($payload['articles'] ?? []) as $article) {
                    if (!is_array($article)) {
                        continue;
                    }
                    $title = trim((string) ($article['title'] ?? ''));
                    if ($title === '') {
                        continue;
                    }
                    $items[] = [
                        'title' => $title,
                        'source' => (string) (($article['source']['name'] ?? null) ?: 'NewsAPI'),
                        'publishedAt' => (string) ($article['publishedAt'] ?? ''),
                        'url' => string_or_null($article['url'] ?? null),
                        'description' => trim((string) ($article['description'] ?? '')),
                    ];
                }

                if ($items === []) {
                    return $fallback;
                }

                return [
                    'provider' => 'NewsAPI',
                    'mode' => 'live-cached',
                    'live' => true,
                    'note' => 'Developer mode can return delayed articles.',
                    'items' => array_slice($items, 0, $limit),
                    'fetchedAt' => gmdate(DATE_ATOM),
                ];
            });
        } catch (Throwable $e) {
            $fallback['note'] = 'Live NewsAPI data could not be loaded right now.';
            $fallback['error'] = $e->getMessage();
            return $fallback;
        }
    }

    public function weatherContext(float $lat, float $lng, string $label = 'San Fernando, La Union'): array
    {
        $fallback = $this->fallbackWeather($label);
        $apiKey = trim((string) ($this->services['weather']['openweather_key'] ?? ''));
        if ($apiKey === '') {
            return $fallback;
        }

        $units = trim((string) ($this->services['weather']['units'] ?? 'metric')) ?: 'metric';

        try {
            return $this->cache->remember('weather-' . round($lat, 3) . '-' . round($lng, 3) . '-' . $units, 1800, function () use ($apiKey, $units, $lat, $lng, $label): array {
                $payload = $this->httpJson('GET', 'https://api.openweathermap.org/data/2.5/weather', [
                    'lat' => $lat,
                    'lon' => $lng,
                    'appid' => $apiKey,
                    'units' => $units,
                ]);

                return [
                    'provider' => 'OpenWeather',
                    'mode' => 'live-cached',
                    'live' => true,
                    'location' => $label,
                    'summary' => ucfirst(trim((string) ($payload['weather'][0]['description'] ?? $payload['weather'][0]['main'] ?? 'Current weather available'))),
                    'temperatureC' => isset($payload['main']['temp']) ? (float) $payload['main']['temp'] : null,
                    'humidity' => isset($payload['main']['humidity']) ? (int) $payload['main']['humidity'] : null,
                    'windSpeed' => isset($payload['wind']['speed']) ? (float) $payload['wind']['speed'] : null,
                    'note' => 'Useful for hospitality, outdoor, and climate-sensitive opportunities.',
                    'fetchedAt' => gmdate(DATE_ATOM),
                ];
            });
        } catch (Throwable $e) {
            $fallback['note'] = 'Live weather could not be loaded right now.';
            $fallback['error'] = $e->getMessage();
            return $fallback;
        }
    }

    public function geocodeSearch(string $query, array $localProperties = []): array
    {
        $query = trim($query);
        $fallback = $this->fallbackSearch($query, $localProperties);
        if ($query === '' || mb_strlen($query) < 2) {
            return $fallback;
        }

        $apiKey = $this->locationIqKey();
        if ($apiKey === '') {
            return $fallback;
        }

        try {
            return $this->cache->remember('location-' . sha1(mb_strtolower($query)), 86400, function () use ($apiKey, $query, $fallback): array {
                $payload = $this->httpJson('GET', 'https://us1.locationiq.com/v1/search.php', [
                    'key' => $apiKey,
                    'q' => $query,
                    'format' => 'json',
                    'limit' => 5,
                    'countrycodes' => 'ph',
                    'normalizecity' => 1,
                ]);

                $results = [];
                foreach ((array) $payload as $entry) {
                    if (!is_array($entry)) {
                        continue;
                    }
                    $lat = float_or_null($entry['lat'] ?? null);
                    $lng = float_or_null($entry['lon'] ?? null);
                    if ($lat === null || $lng === null) {
                        continue;
                    }
                    $results[] = [
                        'kind' => 'geocode',
                        'label' => (string) ($entry['display_name'] ?? 'San Fernando, La Union'),
                        'subtitle' => (string) (($entry['type'] ?? 'Location') . ' | ' . ($entry['class'] ?? 'LocationIQ')),
                        'lat' => $lat,
                        'lng' => $lng,
                    ];
                }

                return [
                    'provider' => 'LocationIQ',
                    'mode' => 'live-cached',
                    'live' => true,
                    'results' => array_merge($fallback['results'], $results),
                    'fetchedAt' => gmdate(DATE_ATOM),
                ];
            });
        } catch (Throwable $e) {
            $fallback['note'] = 'Live geocoding could not be loaded right now.';
            $fallback['error'] = $e->getMessage();
            return $fallback;
        }
    }

    public function propertySummary(array $property, array $votes = []): array
    {
        $fallback = $this->fallbackSummary($property, $votes);
        $provider = $this->configuredAiProvider();
        if ($provider === null) {
            return $fallback;
        }

        try {
            return $this->cache->remember('summary-' . sha1(json_encode([$property['id'] ?? 0, $votes], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)), 43200, function () use ($provider, $property, $votes): array {
                $prompt = $this->summaryPrompt($property, $votes);
                $raw = $provider === 'Gemini' ? $this->fetchGemini($prompt) : $this->fetchOpenRouter($prompt);
                return $this->normalizeSummary($raw, $provider, true);
            });
        } catch (Throwable $e) {
            $fallback['note'] = sprintf('%s summary request failed, so the structured fallback is shown.', $provider);
            $fallback['error'] = $e->getMessage();
            return $fallback;
        }
    }

    public function uploadPropertyImage(array $file): ?string
    {
        if (!$this->hasCloudinaryConfig()) {
            return null;
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_file($tmpName)) {
            throw new InvalidArgumentException('Uploaded image is invalid.');
        }

        $cloud = $this->services['media']['cloudinary'];
        $folder = trim((string) ($cloud['folder'] ?? 'sfcelerate-bizstart/properties'));
        $timestamp = time();
        $signature = sha1(sprintf('folder=%s&timestamp=%d%s', $folder, $timestamp, (string) $cloud['api_secret']));

        $response = $this->httpMultipart(
            sprintf('https://api.cloudinary.com/v1_1/%s/image/upload', rawurlencode((string) $cloud['cloud_name'])),
            [
                'api_key' => (string) $cloud['api_key'],
                'timestamp' => (string) $timestamp,
                'signature' => $signature,
                'folder' => $folder,
                'file' => curl_file_create($tmpName, (string) ($file['type'] ?? 'application/octet-stream'), (string) ($file['name'] ?? 'property-image')),
            ]
        );

        $secureUrl = string_or_null($response['secure_url'] ?? null);
        if ($secureUrl === null) {
            throw new RuntimeException('Cloudinary did not return a secure asset URL.');
        }

        return $secureUrl;
    }

    private function fallbackMarketSnapshot(): array
    {
        $seed = (array) ($this->seedMeta['services']['marketData'] ?? []);
        $benchmarks = is_array($seed['benchmarks'] ?? null) ? $seed['benchmarks'] : [];

        return [
            'provider' => 'Seeded market context',
            'mode' => 'fallback',
            'live' => false,
            'summary' => [
                'headline' => 'Seeded market context for local demos',
                'subline' => 'Live market data can be enabled with Alpha Vantage while the interface remains stable.',
            ],
            'metrics' => [
                ['label' => 'Debt rate', 'value' => isset($benchmarks['debtRate']) ? sprintf('%.1f%%', (float) $benchmarks['debtRate'] * 100) : 'n/a'],
                ['label' => 'Exit cap', 'value' => isset($benchmarks['exitCapRate']) ? sprintf('%.1f%%', (float) $benchmarks['exitCapRate'] * 100) : 'n/a'],
                ['label' => 'Tourism growth', 'value' => isset($benchmarks['tourismGrowth']) ? sprintf('%.1f%%', (float) $benchmarks['tourismGrowth'] * 100) : 'n/a'],
            ],
            'highlights' => is_array($seed['highlights'] ?? null) ? $seed['highlights'] : [],
            'note' => 'Add an Alpha Vantage key in config.local.php to replace this seeded context.',
            'fetchedAt' => gmdate(DATE_ATOM),
        ];
    }

    private function fallbackNewsDigest(int $limit): array
    {
        $highlights = is_array(($this->seedMeta['services']['marketData']['highlights'] ?? null))
            ? $this->seedMeta['services']['marketData']['highlights']
            : [];
        $items = [];
        foreach (array_slice($highlights, 0, $limit) as $highlight) {
            $items[] = [
                'title' => (string) $highlight,
                'source' => 'SFCelerate Research',
                'publishedAt' => gmdate(DATE_ATOM),
                'url' => null,
                'description' => 'Seeded investor digest shown until a NewsAPI key is configured.',
            ];
        }

        return [
            'provider' => 'Seeded investment digest',
            'mode' => 'fallback',
            'live' => false,
            'note' => 'Add a NewsAPI key in config.local.php to surface live business headlines.',
            'items' => $items,
            'fetchedAt' => gmdate(DATE_ATOM),
        ];
    }

    private function fallbackWeather(string $label): array
    {
        return [
            'provider' => 'Climate note',
            'mode' => 'fallback',
            'live' => false,
            'location' => $label,
            'summary' => 'Warm coastal climate with seasonal rain exposure.',
            'temperatureC' => null,
            'humidity' => null,
            'windSpeed' => null,
            'note' => 'Configure OpenWeather to show live weather context.',
            'fetchedAt' => gmdate(DATE_ATOM),
        ];
    }

    private function fallbackSearch(string $query, array $properties): array
    {
        $needle = mb_strtolower($query);
        $results = [];
        foreach ($properties as $property) {
            if (!is_array($property)) {
                continue;
            }
            $label = (string) ($property['name'] ?? 'Property');
            $barangay = (string) ($property['barangay'] ?? 'San Fernando');
            $haystack = mb_strtolower($label . ' ' . $barangay . ' ' . (string) ($property['city'] ?? 'San Fernando, La Union'));
            if ($needle !== '' && !str_contains($haystack, $needle)) {
                continue;
            }
            $lat = float_or_null($property['lat'] ?? null);
            $lng = float_or_null($property['lng'] ?? null);
            if ($lat === null || $lng === null) {
                continue;
            }
            $results[] = [
                'kind' => 'property',
                'label' => $label,
                'subtitle' => trim(sprintf('%s | %s', (string) ($property['city'] ?? 'San Fernando, La Union'), $barangay), ' |'),
                'lat' => $lat,
                'lng' => $lng,
                'propertyId' => (int) ($property['id'] ?? 0),
            ];
        }

        return [
            'provider' => 'Local property search',
            'mode' => 'fallback',
            'live' => false,
            'note' => 'Configure LocationIQ to add live geocoding and external location lookup.',
            'results' => array_slice($results, 0, 6),
            'fetchedAt' => gmdate(DATE_ATOM),
        ];
    }

    private function fallbackSummary(array $property, array $votes): array
    {
        $sortedVotes = $this->sortedVotes($votes);
        $topVote = $sortedVotes[0][0] ?? 'No clear demand signal yet';
        $topVoteCount = $sortedVotes[0][1] ?? 0;

        return [
            'provider' => 'Structured fallback summary',
            'mode' => 'fallback',
            'live' => false,
            'headline' => sprintf('%s shows strong %s fit', (string) ($property['name'] ?? 'This property'), (string) ($property['corridor'] ?? 'corridor')),
            'summary' => sprintf(
                '%s combines %s ha, PHP %s pricing, and %d%% road access in %s.',
                (string) ($property['name'] ?? 'This property'),
                number_format((float) ($property['area'] ?? 0), 1),
                number_format((float) ($property['price'] ?? 0)),
                (int) ($property['roadAccess'] ?? 0),
                (string) ($property['barangay'] ?? 'San Fernando')
            ),
            'takeaways' => [
                sprintf('Corridor fit: %s positioning.', (string) ($property['corridor'] ?? 'Strategic')),
                sprintf('Demand pulse: %s%s.', $topVote, $topVoteCount > 0 ? sprintf(' with %d votes', $topVoteCount) : ''),
                sprintf('Seller narrative: %s', trim((string) ($property['description'] ?? 'No seller description available.'))),
            ],
            'note' => 'Configure Gemini or OpenRouter to replace this structured summary with a live AI briefing.',
            'fetchedAt' => gmdate(DATE_ATOM),
        ];
    }

    private function configuredAiProvider(): ?string
    {
        $provider = strtolower(trim((string) ($this->services['ai']['provider'] ?? '')));
        $geminiKey = trim((string) ($this->services['ai']['gemini_key'] ?? ''));
        $openRouterKey = trim((string) ($this->services['ai']['openrouter_key'] ?? ''));
        $openRouterModel = trim((string) ($this->services['ai']['openrouter_model'] ?? ''));

        if ($provider === 'gemini' && $geminiKey !== '') {
            return 'Gemini';
        }
        if ($provider === 'openrouter' && $openRouterKey !== '' && $openRouterModel !== '') {
            return 'OpenRouter';
        }

        return null;
    }

    private function locationIqKey(): string
    {
        return trim((string) (($this->services['location']['locationiq_key'] ?? '') ?: ($this->services['maps']['locationiq_key'] ?? '')));
    }

    private function hasCloudinaryConfig(): bool
    {
        $cloud = is_array($this->services['media']['cloudinary'] ?? null) ? $this->services['media']['cloudinary'] : [];
        return trim((string) ($cloud['cloud_name'] ?? '')) !== ''
            && trim((string) ($cloud['api_key'] ?? '')) !== ''
            && trim((string) ($cloud['api_secret'] ?? '')) !== '';
    }

    private function firstMarket(array $markets, string $needle): ?array
    {
        foreach ($markets as $market) {
            if (!is_array($market)) {
                continue;
            }
            $label = mb_strtolower((string) ($market['market_type'] ?? $market['region'] ?? ''));
            if (str_contains($label, mb_strtolower($needle))) {
                return $market;
            }
        }

        return null;
    }

    private function marketLabel(mixed $value, string $fallback): string
    {
        $text = trim((string) $value);
        return $text === '' ? $fallback : ucwords(str_replace('_', ' ', strtolower($text)));
    }

    private function sortedVotes(array $votes): array
    {
        $entries = [];
        foreach ($votes as $label => $count) {
            $entries[] = [(string) $label, (int) $count];
        }
        usort($entries, static fn (array $a, array $b): int => $b[1] <=> $a[1]);
        return $entries;
    }

    private function summaryPrompt(array $property, array $votes): string
    {
        return <<<PROMPT
You are writing a short investment briefing for an international real estate platform.
Write one short headline, one concise summary sentence, and three short takeaways.
Keep the tone calm, premium, and analytical.
Do not oversell.

PROPERTY JSON:
{$this->jsonForPrompt([
    'name' => $property['name'] ?? null,
    'city' => $property['city'] ?? null,
    'barangay' => $property['barangay'] ?? null,
    'type' => $property['type'] ?? null,
    'corridor' => $property['corridor'] ?? null,
    'area' => $property['area'] ?? null,
    'price' => $property['price'] ?? null,
    'roadAccess' => $property['roadAccess'] ?? null,
    'description' => $property['description'] ?? null,
    'tags' => $property['tags'] ?? null,
])}

VOTE SIGNAL JSON:
{$this->jsonForPrompt($votes)}
PROMPT;
    }

    private function fetchGemini(string $prompt): string
    {
        $apiKey = trim((string) ($this->services['ai']['gemini_key'] ?? ''));
        $model = trim((string) ($this->services['ai']['gemini_model'] ?? 'gemini-2.5-flash'));
        $payload = $this->httpJson(
            'POST',
            sprintf('https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent', rawurlencode($model)),
            ['key' => $apiKey],
            [
                'contents' => [[
                    'parts' => [['text' => $prompt]],
                ]],
                'generationConfig' => ['temperature' => 0.4],
            ]
        );
        $parts = $payload['candidates'][0]['content']['parts'] ?? [];
        $text = [];
        foreach ((array) $parts as $part) {
            if (is_array($part) && isset($part['text'])) {
                $text[] = (string) $part['text'];
            }
        }
        return trim(implode("\n", $text));
    }

    private function fetchOpenRouter(string $prompt): string
    {
        $apiKey = trim((string) ($this->services['ai']['openrouter_key'] ?? ''));
        $model = trim((string) ($this->services['ai']['openrouter_model'] ?? ''));
        if ($model === '') {
            throw new RuntimeException('OpenRouter model is required.');
        }
        $payload = $this->httpJson(
            'POST',
            'https://openrouter.ai/api/v1/chat/completions',
            [],
            [
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => 'You produce concise, premium investment briefings.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.4,
            ],
            [
                'Authorization' => 'Bearer ' . $apiKey,
                'X-Title' => (string) ($this->config['app']['name'] ?? 'SFCelerate'),
            ]
        );
        return trim((string) ($payload['choices'][0]['message']['content'] ?? ''));
    }

    private function normalizeSummary(string $raw, string $provider, bool $live): array
    {
        $lines = array_values(array_filter(array_map(
            static fn (string $line): string => trim(ltrim($line, "-*0123456789. \t")),
            preg_split('/\r\n|\r|\n/', $raw) ?: []
        )));

        $headline = $lines[0] ?? 'AI-assisted investment summary';
        $summary = $lines[1] ?? ($lines[0] ?? 'Summary unavailable.');
        $takeaways = array_slice($lines, 2, 3);
        if ($takeaways === []) {
            $takeaways = [$summary];
        }

        return [
            'provider' => $provider,
            'mode' => $live ? 'live-cached' : 'fallback',
            'live' => $live,
            'headline' => $headline,
            'summary' => $summary,
            'takeaways' => $takeaways,
            'fetchedAt' => gmdate(DATE_ATOM),
        ];
    }

    private function jsonForPrompt(array $payload): string
    {
        return (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function httpJson(string $method, string $url, array $query = [], mixed $body = null, array $headers = []): array
    {
        $finalUrl = $query !== [] ? $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($query) : $url;
        $raw = $this->httpRequest($method, $finalUrl, $body, $headers);
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('External API returned invalid JSON.');
        }
        if (isset($decoded['error']) && is_string($decoded['error']) && trim($decoded['error']) !== '') {
            throw new RuntimeException($decoded['error']);
        }
        if (isset($decoded['Note']) && is_string($decoded['Note'])) {
            throw new RuntimeException($decoded['Note']);
        }
        return $decoded;
    }

    private function httpMultipart(string $url, array $fields): array
    {
        $raw = $this->httpRequest('POST', $url, $fields, [], true);
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('External upload returned invalid JSON.');
        }
        if (isset($decoded['error']['message'])) {
            throw new RuntimeException((string) $decoded['error']['message']);
        }
        return $decoded;
    }

    private function httpRequest(string $method, string $url, mixed $body = null, array $headers = [], bool $multipart = false): string
    {
        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                throw new RuntimeException('Unable to initialize cURL.');
            }
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 25);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));

            if ($body !== null) {
                if ($multipart) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                } else {
                    $lines[] = 'Content-Type: application/json';
                    curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($body) ? $body : json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                }
            }
            if ($lines !== []) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, $lines);
            }

            $response = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($response === false) {
                throw new RuntimeException($error !== '' ? $error : 'Unable to reach external service.');
            }
            if ($status >= 400) {
                throw new RuntimeException(sprintf('External service returned HTTP %d.', $status));
            }

            return (string) $response;
        }

        if ($multipart) {
            throw new RuntimeException('Multipart uploads require cURL in this PHP environment.');
        }

        $content = $body === null ? null : (is_string($body) ? $body : json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        if ($content !== null) {
            $lines[] = 'Content-Type: application/json';
        }
        $context = stream_context_create([
            'http' => [
                'method' => strtoupper($method),
                'header' => implode("\r\n", $lines),
                'content' => $content,
                'timeout' => 25,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            throw new RuntimeException('Unable to reach external service.');
        }

        return (string) $response;
    }
}
