<?php
/**
 * Procesador de IA usando Claude API
 * Genera contenido educativo inteligente
 */

namespace ScormConverter;

class AIProcessor
{
    private string $apiKey;
    private string $model;
    private int $maxTokens;
    private string $apiUrl = 'https://api.anthropic.com/v1/messages';
    
    public function __construct()
    {
        $this->apiKey = CLAUDE_API_KEY;
        $this->model = CLAUDE_MODEL;
        $this->maxTokens = CLAUDE_MAX_TOKENS;
        
        if (empty($this->apiKey) || $this->apiKey === 'tu-api-key-aqui') {
            throw new \Exception("API Key de Claude no configurada. Edita config.php");
        }
    }
    
    /**
     * Analiza el contenido del documento y extrae estructura
     */
    public function analyzeDocument(string $content): array
    {
        // Limitar contenido para no exceder tokens
        $content = $this->truncateContent($content, 15000);
        
        $prompt = str_replace('{content}', $content, PROMPT_ANALYZE);
        
        $response = $this->callAPI($prompt);
        
        // Parsear respuesta JSON
        $data = $this->parseJsonResponse($response);
        
        if (!isset($data['modulo']) || !isset($data['unidades'])) {
            throw new \Exception("La IA no pudo analizar correctamente el documento");
        }
        
        return $data;
    }
    
    /**
     * Genera preguntas de autoevaluación para una unidad
     */
    public function generateQuestions(string $unitTitle, string $unitContent, array $concepts): array
    {
        $conceptsText = '';
        foreach ($concepts as $c) {
            $conceptsText .= "- {$c['termino']}: {$c['definicion']}\n";
        }
        
        $prompt = PROMPT_QUESTIONS;
        $prompt = str_replace('{unit_title}', $unitTitle, $prompt);
        $prompt = str_replace('{unit_content}', $this->truncateContent($unitContent, 5000), $prompt);
        $prompt = str_replace('{concepts}', $conceptsText, $prompt);
        
        $response = $this->callAPI($prompt);
        $data = $this->parseJsonResponse($response);
        
        return $data['preguntas'] ?? [];
    }
    
    /**
     * Genera flashcards adicionales basados en el contenido
     */
    public function generateFlashcards(string $content, int $count = 6): array
    {
        $prompt = <<<PROMPT
Analiza el siguiente contenido educativo y genera exactamente {$count} flashcards (tarjetas de estudio) con los conceptos más importantes.

CONTENIDO:
{$this->truncateContent($content, 6000)}

Responde ÚNICAMENTE con un JSON válido:
{
    "flashcards": [
        {"termino": "término clave", "definicion": "definición clara y concisa (máximo 150 caracteres)"}
    ]
}

REGLAS:
- Los términos deben ser conceptos clave del contenido
- Las definiciones deben ser claras, concisas y educativas
- No repetir conceptos
- Incluir terminología técnica relevante
PROMPT;

        $response = $this->callAPI($prompt);
        $data = $this->parseJsonResponse($response);
        
        return $data['flashcards'] ?? [];
    }
    
    /**
     * Enriquece secciones clasificando cada bloque por tipo de componente visual
     * Fase 2: la IA decide qué componente usar para cada fragmento de contenido
     */
    public function enrichSections(string $unitTitle, array $secciones, string $unitContent): array
    {
        $sectionTitles = implode(', ', array_map(fn($s) => $s['titulo'], $secciones));
        
        $prompt = PROMPT_ENRICH_SECTIONS;
        $prompt = str_replace('{unit_title}', $unitTitle, $prompt);
        $prompt = str_replace('{section_titles}', $sectionTitles, $prompt);
        $prompt = str_replace('{unit_content}', $this->truncateContent($unitContent, 10000), $prompt);
        
        $response = $this->callAPI($prompt);
        $data = $this->parseJsonResponse($response);
        
        return $data;
    }

    /**
     * Resume y estructura el contenido de una sección
     */
    public function summarizeContent(string $content, string $context = ''): string
    {
        $prompt = <<<PROMPT
Resume el siguiente contenido educativo de forma clara y estructurada para un curso online.

CONTEXTO: {$context}

CONTENIDO:
{$this->truncateContent($content, 4000)}

Genera un resumen educativo de 3-5 párrafos que:
- Sea claro y fácil de entender
- Mantenga los conceptos técnicos importantes
- Esté estructurado de forma lógica
- Use un tono profesional pero accesible

Responde solo con el texto del resumen, sin formato JSON.
PROMPT;

        return $this->callAPI($prompt);
    }
    
    /**
     * Llama a la API de Claude
     */
    private function callAPI(string $prompt): string
    {
        $data = [
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ]
        ];
        
        $ch = curl_init($this->apiUrl);
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01'
            ],
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_TIMEOUT => 120,
            CURLOPT_SSL_VERIFYPEER => true
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            logError("Error de cURL: {$error}");
            throw new \Exception("Error de conexión con la API: {$error}");
        }
        
        if ($httpCode !== 200) {
            $errorData = json_decode($response, true);
            $errorMsg = $errorData['error']['message'] ?? "Error HTTP {$httpCode}";
            logError("Error de API: {$errorMsg}", ['response' => $response]);
            throw new \Exception("Error de API: {$errorMsg}");
        }
        
        $responseData = json_decode($response, true);
        
        if (!isset($responseData['content'][0]['text'])) {
            throw new \Exception("Respuesta de API inválida");
        }
        
        return $responseData['content'][0]['text'];
    }
    
    /**
     * Parsea una respuesta JSON de la IA
     */
    private function parseJsonResponse(string $response): array
    {
        // Limpiar posibles caracteres extra
        $response = trim($response);
        
        // Intentar extraer JSON si está envuelto en markdown
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $response, $matches)) {
            $response = $matches[1];
        }
        
        // Intentar extraer JSON si hay texto antes o después
        if (preg_match('/\{[\s\S]*\}/', $response, $matches)) {
            $response = $matches[0];
        }
        
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            logError("Error parseando JSON de IA", [
                'error' => json_last_error_msg(),
                'response' => substr($response, 0, 500)
            ]);
            throw new \Exception("Error procesando respuesta de IA: " . json_last_error_msg());
        }
        
        return $data;
    }
    
    /**
     * Trunca el contenido para no exceder límites de tokens
     */
    private function truncateContent(string $content, int $maxChars): string
    {
        if (strlen($content) <= $maxChars) {
            return $content;
        }
        
        // Truncar de forma inteligente (en límite de palabra)
        $truncated = substr($content, 0, $maxChars);
        $lastSpace = strrpos($truncated, ' ');
        
        if ($lastSpace !== false) {
            $truncated = substr($truncated, 0, $lastSpace);
        }
        
        return $truncated . "\n\n[Contenido truncado por límite de procesamiento...]";
    }
    
    /**
     * Verifica si la API está disponible
     */
    public function testConnection(): bool
    {
        try {
            $response = $this->callAPI("Responde únicamente con la palabra: OK");
            return strpos(strtoupper($response), 'OK') !== false;
        } catch (\Exception $e) {
            return false;
        }
    }
}
