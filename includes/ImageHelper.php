<?php
/**
 * ImageHelper — Búsqueda y descarga de imágenes
 * Fuente: Pexels API (gratis, 200 req/hora, 20.000/mes)
 */

namespace ScormConverter;

class ImageHelper
{
    private string $pexelsKey;
    private string $apiUrl = 'https://api.pexels.com/v1/search';
    private array $cache = [];  // keyword -> image data

    public function __construct(string $pexelsKey = '')
    {
        $this->pexelsKey = $pexelsKey;
    }

    /**
     * ¿Está configurada la API de Pexels?
     */
    public function isAvailable(): bool
    {
        return !empty($this->pexelsKey);
    }

    /**
     * Busca y descarga una imagen por keyword
     * @return array|null {filename, data, mime, url, credit}
     */
    public function searchAndDownload(string $keyword, int $width = 800): ?array
    {
        if (!$this->isAvailable()) return null;

        $cacheKey = strtolower(trim($keyword));
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        try {
            $url = $this->apiUrl . '?' . http_build_query([
                'query'       => $keyword,
                'per_page'    => 1,
                'orientation' => 'landscape',
            ]);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => ['Authorization: ' . $this->pexelsKey],
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                logError("Pexels API error", ['code' => $httpCode, 'keyword' => $keyword]);
                return null;
            }

            $data = json_decode($response, true);
            if (empty($data['photos'][0])) return null;

            $photo    = $data['photos'][0];
            $imageUrl = $photo['src']['large'] ?? $photo['src']['medium'];

            $imageData = $this->downloadImage($imageUrl);
            if (!$imageData) return null;

            $filename = 'pexels_' . preg_replace('/[^a-z0-9]/', '_', $cacheKey) . '_' . substr(md5($photo['id']), 0, 8) . '.jpg';

            $result = [
                'filename'   => $filename,
                'data'       => $imageData,
                'mime'       => 'image/jpeg',
                'url'        => $imageUrl,
                'credit'     => $photo['photographer'] ?? 'Pexels',
                'credit_url' => $photo['photographer_url'] ?? 'https://www.pexels.com',
                'source'     => 'pexels',
            ];

            $this->cache[$cacheKey] = $result;
            return $result;

        } catch (\Exception $e) {
            logError("ImageHelper error", ['error' => $e->getMessage(), 'keyword' => $keyword]);
            return null;
        }
    }

    /**
     * Descarga una imagen desde URL
     */
    private function downloadImage(string $url): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $data     = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($httpCode === 200 && $data !== false && strlen($data) > 1000) ? $data : null;
    }

    /**
     * Guarda imágenes en un directorio del SCORM
     * @return array Mapa filename => relative path
     */
    public static function saveToDir(array $images, string $dir): array
    {
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $saved = [];
        foreach ($images as $img) {
            if (empty($img['data']) || empty($img['filename'])) continue;
            $path = $dir . '/' . $img['filename'];
            file_put_contents($path, $img['data']);
            $saved[$img['filename']] = '../img/' . $img['filename'];
        }
        return $saved;
    }
}
