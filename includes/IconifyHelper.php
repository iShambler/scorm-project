<?php
/**
 * Helper para descargar iconos SVG desde Iconify API
 * API pública, sin key, sin límites significativos
 * https://iconify.design/docs/api/
 */

namespace ScormConverter;

class IconifyHelper
{
    private static string $searchUrl = 'https://api.iconify.design/search';
    private static string $svgUrl = 'https://api.iconify.design';
    private static array $cache = [];
    
    // Prefijos preferidos (iconos más bonitos/consistentes)
    private static array $preferredPrefixes = ['mdi', 'lucide', 'tabler', 'ph', 'carbon', 'material-symbols'];
    
    // Mapa de fallbacks temáticos por si la búsqueda falla
    private static array $fallbackIcons = [
        'default'       => 'mdi:book-open-variant',
        'climate'       => 'mdi:weather-partly-cloudy',
        'environment'   => 'mdi:leaf',
        'technology'    => 'mdi:laptop',
        'data'          => 'mdi:database',
        'security'      => 'mdi:shield-check',
        'business'      => 'mdi:briefcase',
        'education'     => 'mdi:school',
        'health'        => 'mdi:heart-pulse',
        'law'           => 'mdi:gavel',
        'finance'       => 'mdi:currency-eur',
        'communication' => 'mdi:chat',
        'science'       => 'mdi:flask',
        'engineering'   => 'mdi:cog',
        'art'           => 'mdi:palette',
        'music'         => 'mdi:music',
        'food'          => 'mdi:food-apple',
        'transport'     => 'mdi:car',
        'energy'        => 'mdi:lightning-bolt',
        'water'         => 'mdi:water',
        'agriculture'   => 'mdi:sprout',
        'construction'  => 'mdi:hammer-wrench',
        'chemistry'     => 'mdi:beaker',
        'math'          => 'mdi:calculator',
        'geography'     => 'mdi:earth',
        'history'       => 'mdi:clock-outline',
        'psychology'    => 'mdi:head-cog',
        'sociology'     => 'mdi:account-group',
        'economy'       => 'mdi:chart-line',
        'marketing'     => 'mdi:bullhorn',
        'programming'   => 'mdi:code-braces',
        'network'       => 'mdi:lan',
        'cloud'         => 'mdi:cloud',
        'ai'            => 'mdi:robot',
        'statistics'    => 'mdi:chart-bar',
        'management'    => 'mdi:clipboard-check',
        'quality'       => 'mdi:check-decagram',
        'innovation'    => 'mdi:lightbulb-on',
        'sustainability'=> 'mdi:recycle',
        'rights'        => 'mdi:scale-balance',
        'safety'        => 'mdi:hard-hat',
        'warning'       => 'mdi:alert',
        'info'          => 'mdi:information',
        'process'       => 'mdi:cogs',
        'analysis'      => 'mdi:magnify',
        'plan'          => 'mdi:map',
        'team'          => 'mdi:account-multiple',
        'goal'          => 'mdi:target',
        'idea'          => 'mdi:lightbulb',
        'document'      => 'mdi:file-document',
        'settings'      => 'mdi:tune',
        'list'          => 'mdi:format-list-bulleted',
    ];

    /**
     * Busca un icono por keyword y devuelve el SVG inline
     * @param string $keyword Palabra clave de búsqueda (en inglés)
     * @param int $size Tamaño del icono en px
     * @param string $color Color hex del icono
     * @return string SVG inline o cadena vacía si falla
     */
    public static function getIcon(string $keyword, int $size = 40, string $color = '#1a4a6e'): string
    {
        $cacheKey = $keyword . '_' . $size . '_' . $color;
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        $svg = '';
        
        // 1. Intentar búsqueda en Iconify API
        $iconName = self::searchIcon($keyword);
        
        // 2. Si no hay resultado, buscar en fallbacks
        if (!$iconName) {
            $iconName = self::getFallbackIcon($keyword);
        }
        
        // 3. Descargar el SVG
        if ($iconName) {
            $svg = self::downloadSvg($iconName, $size, $color);
        }
        
        self::$cache[$cacheKey] = $svg;
        return $svg;
    }

    /**
     * Busca un icono en la API de Iconify
     */
    private static function searchIcon(string $query): ?string
    {
        $url = self::$searchUrl . '?' . http_build_query([
            'query' => $query,
            'limit' => 10,
            'prefixes' => implode(',', self::$preferredPrefixes)
        ]);

        $response = @file_get_contents($url, false, stream_context_create([
            'http' => ['timeout' => 5, 'ignore_errors' => true]
        ]));

        if (!$response) return null;

        $data = json_decode($response, true);
        if (empty($data['icons'])) return null;

        // Devolver el primer icono
        return $data['icons'][0] ?? null;
    }

    /**
     * Descarga un SVG de Iconify
     * Formato URL: https://api.iconify.design/{prefix}/{name}.svg
     */
    private static function downloadSvg(string $iconName, int $size, string $color): string
    {
        // iconName format: "prefix:name" o "prefix-name"
        $parts = preg_split('/[:\-]/', $iconName, 2);
        if (count($parts) < 2) return '';

        $prefix = $parts[0];
        $name = $parts[1];

        $url = self::$svgUrl . '/' . urlencode($prefix) . '/' . urlencode($name) . '.svg?' 
            . http_build_query([
                'height' => $size,
                'color' => $color
            ]);

        $svg = @file_get_contents($url, false, stream_context_create([
            'http' => ['timeout' => 5, 'ignore_errors' => true]
        ]));

        if (!$svg || strpos($svg, '<svg') === false) return '';

        return $svg;
    }

    /**
     * Busca un icono en el mapa de fallbacks por keyword
     */
    private static function getFallbackIcon(string $keyword): string
    {
        $keyword = strtolower(trim($keyword));
        
        // Búsqueda exacta
        if (isset(self::$fallbackIcons[$keyword])) {
            return self::$fallbackIcons[$keyword];
        }
        
        // Búsqueda parcial
        foreach (self::$fallbackIcons as $key => $icon) {
            if (strpos($keyword, $key) !== false || strpos($key, $keyword) !== false) {
                return $icon;
            }
        }
        
        return self::$fallbackIcons['default'];
    }

    /**
     * Descarga múltiples iconos en paralelo (para optimizar)
     * @param array $keywords Array de keywords
     * @param int $size Tamaño
     * @param string $color Color
     * @return array Mapa keyword => SVG
     */
    public static function getIcons(array $keywords, int $size = 40, string $color = '#1a4a6e'): array
    {
        $results = [];
        foreach ($keywords as $kw) {
            $results[$kw] = self::getIcon($kw, $size, $color);
        }
        return $results;
    }

    /**
     * Genera un SVG placeholder si todo falla
     */
    public static function placeholder(int $size = 40, string $color = '#1a4a6e'): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size 
            . '" viewBox="0 0 24 24" fill="none" stroke="' . $color 
            . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
            . '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/>'
            . '<line x1="12" y1="8" x2="12.01" y2="8"/></svg>';
    }
}
