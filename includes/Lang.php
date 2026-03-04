<?php
/**
 * Lang — Sistema de traducciones para labels del template SCORM/PDF
 * Soporta es (español) y en (inglés). Fácil de ampliar.
 */
namespace ScormConverter;

class Lang
{
    private static string $lang = 'es';

    private static array $translations = [
        'es' => [
            // Navegación / UI
            'home'                    => 'Inicio',
            'check'                   => 'Comprobar',
            'start'                   => 'Comenzar',
            'view_content'            => 'Ver contenido',
            'click_to_see'            => 'Clic para ver',
            'click_cards_instruction' => 'Haz clic en cada tarjeta para ver la definición:',
            'drag_instruction'        => 'Arrastra cada definición junto al concepto correcto:',
            'drop_here'               => 'Suelta aquí',
            'show_next'               => 'Mostrar siguiente',
            'completed_list'          => 'Completado',
            'digital_classroom'       => 'Aula digital de formación',

            // Secciones
            'table_of_contents'       => 'Índice de contenidos',
            'objectives'              => 'Objetivos',
            'key_concepts'            => 'Conceptos clave',
            'self_assessment'         => 'Autoevaluación',
            'conclusions'             => 'Conclusiones',
            'match_concepts'          => 'Relaciona conceptos',

            // Feedback
            'correct'                 => 'Correcto.',
            'incorrect'               => 'Incorrecto.',
            'review_content'          => 'Revisa el contenido.',
            'choose_option'           => 'Elige una opción.',
            'perfect'                 => '¡Perfecto!',
            'review_retry'            => 'Revisa e intenta de nuevo.',
            'correct_answers'         => 'correctas',

            // Finalización
            'module_completed'        => 'Módulo completado',
            'congratulations'         => '¡Enhorabuena!',
            'what_you_learned'        => 'Lo que has aprendido:',

            // Bloques
            'important'               => 'Importante',
            'did_you_know'            => 'Sabías que',
            'example'                 => 'Ejemplo',
            'step'                    => 'Paso',
            'practice'                => 'Práctica',

            // Bloom
            'bloom_remember'          => 'Recordar',
            'bloom_understand'        => 'Comprender',
            'bloom_apply'             => 'Aplicar',
            'bloom_analyze'           => 'Analizar',
            'bloom_evaluate'          => 'Evaluar',
            'bloom_create'            => 'Crear',

            // PDF
            'index'                   => 'Índice',
            'duration'                => 'Duración',
            'hours'                   => 'horas',
            'unit'                    => 'unidad',
            'units'                   => 'unidades',
            'unit_label'              => 'Unidad',
            'section'                 => 'Sección',
            'learning_objectives'     => 'Objetivos de aprendizaje',
            'definition'              => 'Definición',
            'auto_generated'          => 'Documento generado automáticamente',
            'all_rights'              => 'Todos los derechos reservados',
        ],

        'en' => [
            // Navegación / UI
            'home'                    => 'Home',
            'check'                   => 'Check',
            'start'                   => 'Start',
            'view_content'            => 'View content',
            'click_to_see'            => 'Click to reveal',
            'click_cards_instruction' => 'Click each card to see the definition:',
            'drag_instruction'        => 'Drag each definition next to the correct concept:',
            'drop_here'               => 'Drop here',
            'show_next'               => 'Show next',
            'completed_list'          => 'Completed',
            'digital_classroom'       => 'Digital learning classroom',

            // Secciones
            'table_of_contents'       => 'Table of Contents',
            'objectives'              => 'Objectives',
            'key_concepts'            => 'Key Concepts',
            'self_assessment'         => 'Self-Assessment',
            'conclusions'             => 'Conclusions',
            'match_concepts'          => 'Match Concepts',

            // Feedback
            'correct'                 => 'Correct.',
            'incorrect'               => 'Incorrect.',
            'review_content'          => 'Review the content.',
            'choose_option'           => 'Choose an option.',
            'perfect'                 => 'Perfect!',
            'review_retry'            => 'Review and try again.',
            'correct_answers'         => 'correct',

            // Finalización
            'module_completed'        => 'Module completed',
            'congratulations'         => 'Congratulations!',
            'what_you_learned'        => 'What you have learned:',

            // Bloques
            'important'               => 'Important',
            'did_you_know'            => 'Did you know',
            'example'                 => 'Example',
            'step'                    => 'Step',
            'practice'                => 'Practice',

            // Bloom
            'bloom_remember'          => 'Remember',
            'bloom_understand'        => 'Understand',
            'bloom_apply'             => 'Apply',
            'bloom_analyze'           => 'Analyze',
            'bloom_evaluate'          => 'Evaluate',
            'bloom_create'            => 'Create',

            // PDF
            'index'                   => 'Index',
            'duration'                => 'Duration',
            'hours'                   => 'hours',
            'unit'                    => 'unit',
            'units'                   => 'units',
            'unit_label'              => 'Unit',
            'section'                 => 'Section',
            'learning_objectives'     => 'Learning Objectives',
            'definition'              => 'Definition',
            'auto_generated'          => 'Automatically generated document',
            'all_rights'              => 'All rights reserved',
        ],
    ];

    /**
     * Establece el idioma activo
     */
    public static function set(string $lang): void
    {
        $lang = strtolower(substr(trim($lang), 0, 2));
        self::$lang = isset(self::$translations[$lang]) ? $lang : 'es';
    }

    /**
     * Obtiene una traducción por clave
     */
    public static function get(string $key): string
    {
        return self::$translations[self::$lang][$key]
            ?? self::$translations['es'][$key]
            ?? $key;
    }

    /**
     * Devuelve el idioma activo
     */
    public static function current(): string
    {
        return self::$lang;
    }
}
