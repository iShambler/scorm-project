<?php
/**
 * ImageHelper — Fase 4: Búsqueda y descarga de imágenes
 * Fuente: Unsplash API (gratis, 50 req/hora)
 * Fallback: imágenes del Word extraídas
 */

namespace ScormConverter;

class ImageHelper
{
    private string $unsplashKey;
    private string $apiUrl = 'https://api.unsplash.com/search/photos';
    private array $cache = [];  // keyword -> image data
    
    public function __construct(string $unsplashKey = '')
    {
        $this->unsplashKey = $unsplashKey;
    }
    
    /**
     * ¿Está configurada la API de Unsplash?
     */
    public function isAvailable(): bool
    {
        return !empty($this->unsplashKey) && $this->unsplashKey !== 'tu-unsplash-key-aqui';
    }
    
    /**
     * Busca y descarga una imagen por keyword
     * @return array|null {filename, data, mime, url, credit}
     */
    public function searchAndDownload(string $keyword, int $width = 800): ?array
    {
        if (!$this->isAvailable()) return null;
        
        // Cache: no repetir búsquedas
        $cacheKey = strtolower(trim($keyword));
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }
        
        try {
            // Buscar en Unsplash
            $url = $this->apiUrl . '?' . http_build_query([
                'query' => $keyword,
                'per_page' => 1,
                'orientation' => 'landscape',
                'content_filter' => 'high'
            ]);
            
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Client-ID ' . $this->unsplashKey,
                    'Accept-Version: v1'
                ],
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200) {
                logError("Unsplash API error", ['code' => $httpCode, 'keyword' => $keyword]);
                return null;
            }
            
            $data = json_decode($response, true);
            if (empty($data['results'][0])) return null;
            
            $photo = $data['results'][0];
            $imageUrl = $photo['urls']['regular'] ?? $photo['urls']['small'];
            
            // Añadir parámetros de tamaño
            $imageUrl .= '&w=' . $width . '&h=400&fit=crop&q=80';
            
            // Descargar la imagen
            $imageData = $this->downloadImage($imageUrl);
            if (!$imageData) return null;
            
            $filename = 'unsplash_' . $cacheKey . '_' . substr(md5($photo['id']), 0, 8) . '.jpg';
            
            $result = [
                'filename' => $filename,
                'data' => $imageData,
                'mime' => 'image/jpeg',
                'url' => $imageUrl,
                'credit' => $photo['user']['name'] ?? 'Unsplash',
                'credit_url' => $photo['user']['links']['html'] ?? 'https://unsplash.com',
                'source' => 'unsplash'
            ];
            
            $this->cache[$cacheKey] = $result;
            
            // Trigger download tracking (Unsplash API guidelines)
            if (!empty($photo['links']['download_location'])) {
                $this->triggerDownload($photo['links']['download_location']);
            }
            
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
            CURLOPT_TIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        $data = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return ($httpCode === 200 && $data !== false && strlen($data) > 1000) ? $data : null;
    }
    
    /**
     * Notifica descarga a Unsplash (requerido por API guidelines)
     */
    private function triggerDownload(string $downloadUrl): void
    {
        $ch = curl_init($downloadUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Client-ID ' . $this->unsplashKey],
            CURLOPT_TIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
    
    /**
     * Guarda imágenes en un directorio del SCORM
     * @param array $images Lista de imágenes [{filename, data}]
     * @param string $dir Directorio destino
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
