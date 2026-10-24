<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class LinkScraperController extends Controller
{
    /**
     * Scrape icon / favicon and metadata from a given URL.
     */
    public function scrape(Request $request): JsonResponse
    {
        $request->validate([
            'url' => 'required|string|max:2048',
        ]);

        $rawUrl = trim($request->input('url'));
        if (!preg_match('#^https?://#i', $rawUrl)) {
            $rawUrl = 'https://' . $rawUrl;
        }

        $parts = parse_url($rawUrl);
        if (!$parts || empty($parts['host'])) {
            return response()->json([
                'success' => false,
                'message' => 'Format URL tidak valid.',
            ], 422);
        }

        $scheme = $parts['scheme'] ?? 'https';
        $host = strtolower($parts['host']);
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $origin = "{$scheme}://{$host}{$port}";

        $cleanDomain = preg_replace('/^www\./i', '', $host);
        $iconUrl = null;
        $title = null;
        $categorySuggestion = $this->guessCategory($host, $rawUrl);

        try {
            // Attempt HTTP GET to scrape HTML headers
            $response = Http::withoutVerifying()
                ->timeout(3.5)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language' => 'id,en-US;q=0.9,en;q=0.8',
                ])
                ->get($rawUrl);

            if ($response->successful()) {
                $html = $response->body();

                // Extract HTML <title>
                if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
                    $extractedTitle = trim(html_entity_decode(strip_tags($matches[1])));
                    if (!empty($extractedTitle)) {
                        $title = mb_substr($extractedTitle, 0, 100);
                    }
                }

                // Extract icon/favicon link tags: apple-touch-icon, icon, shortcut icon
                $iconPatterns = [
                    '/<link[^>]+rel=["\'](?:apple-touch-icon(?:-precomposed)?|shortcut icon|icon)["\'][^>]+href=["\']([^"\']+)["\']/i',
                    '/<link[^>]+href=["\']([^"\']+)["\'][^>]+rel=["\'](?:apple-touch-icon(?:-precomposed)?|shortcut icon|icon)["\']/i',
                ];

                foreach ($iconPatterns as $pattern) {
                    if (preg_match($pattern, $html, $matches)) {
                        $candidate = trim($matches[1]);
                        if (!empty($candidate)) {
                            $resolved = $this->resolveUrl($candidate, $origin, $rawUrl);
                            if ($resolved) {
                                $iconUrl = $resolved;
                                break;
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // Log or ignore network timeout
        }

        // If no icon found from HTML scraping, use Google Favicon Service with 128px high res
        if (!$iconUrl) {
            $iconUrl = "https://www.google.com/s2/favicons?domain=" . urlencode($host) . "&sz=128";
        }

        return response()->json([
            'success' => true,
            'data' => [
                'url' => $rawUrl,
                'domain' => $cleanDomain,
                'icon_url' => $iconUrl,
                'title' => $title,
                'category_suggestion' => $categorySuggestion,
            ],
        ]);
    }

    /**
     * Guess link category from host or url keywords.
     */
    private function guessCategory(string $host, string $url): string
    {
        $lower = strtolower($host . ' ' . $url);

        if (str_contains($lower, 'wa.me') || str_contains($lower, 'whatsapp.com')) {
            return 'WhatsApp';
        }
        if (str_contains($lower, 'sheets.google.com') || str_contains($lower, 'spreadsheet') || str_contains($lower, 'excel')) {
            return 'Spreadsheet';
        }
        if (str_contains($lower, 'forms.google.com') || str_contains($lower, 'form') || str_contains($lower, 'typeform')) {
            return 'Form';
        }
        if (str_contains($lower, 'portal') || str_contains($lower, 'intranet') || str_contains($lower, 'dashboard')) {
            return 'Portal';
        }
        if (str_contains($lower, 'eskalasi') || str_contains($lower, 'jira') || str_contains($lower, 'ticket') || str_contains($lower, 'trello')) {
            return 'Eskalasi';
        }

        return 'Eskalasi';
    }

    /**
     * Resolve relative icon url to absolute url.
     */
    private function resolveUrl(string $href, string $origin, string $currentUrl): string
    {
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }

        if (str_starts_with($href, '//')) {
            $scheme = parse_url($currentUrl, PHP_URL_SCHEME) ?: 'https';
            return "{$scheme}:{$href}";
        }

        if (str_starts_with($href, '/')) {
            return rtrim($origin, '/') . '/' . ltrim($href, '/');
        }

        // Relative path
        $path = parse_url($currentUrl, PHP_URL_PATH) ?: '/';
        $dir = rtrim(dirname($path), '/\\');
        return rtrim($origin, '/') . ($dir ? '/' . ltrim($dir, '/') : '') . '/' . ltrim($href, '/');
    }
}
