<?php

return [

  /*
    |--------------------------------------------------------------------------
    | Aktivierte Prüfmodule
    |--------------------------------------------------------------------------
    |
    | Definiere hier, welche Check-Module aktuell aktiv sein sollen.
    | Diese Liste wird z. B. vom Scanner oder der UI ausgewertet.
    |
    */

  'enabled_checks' => [
    'alt',           // ALT-Text-Prüfung
    'status',        // HTTP-Statuscode
    'heading',       // Überschriftenstruktur
    'accessibility', // Barrierefreiheit (axe-core)
    'html',          // HTML-Validierung
    'skiplink',      // Skip-Link-Prüfung
    'taborder',      // Tastatur-Navi-Reihenfolge
  ],

  /*
    |--------------------------------------------------------------------------
    | Abo-Modell-Features
    |--------------------------------------------------------------------------
    |
    | Diese Flags können später dynamisch aus DB oder Lizenzsystem kommen.
    | Für MVP aber hier im Code konfigurierbar.
    |
    */

  'features' => [
    'multi_scan'      => true,
    'pdf_export'      => false,
    'monitoring'      => false,
    'csv_export'      => true,
    'zip_download'    => true,
    'grouping'        => true,
    'filtering'       => true,
    'bfsg_highlight'  => true,
  ],

  /*
    |--------------------------------------------------------------------------
    | Technische Limits
    |--------------------------------------------------------------------------
    |
    | Definiere systemweite Limits je nach Abo-Plan oder Systemleistung.
    |
    */

  'limits' => [
    'max_urls_per_scan'     => 80,
    'max_parallel_scans'    => 3,
    'max_reports_retention' => 30, // in Tagen
  ],

  /*
    |--------------------------------------------------------------------------
    | UI-Optionen / Verhalten
    |--------------------------------------------------------------------------
    |
    | UI-spezifische Konfigs wie Darstellung, Farben, Legenden etc.
    |
    */

  'ui' => [
    'show_legends'        => true,
    'enable_dark_mode'    => false,
    'default_locale'      => 'de',
    'show_debug_info'     => env('APP_DEBUG', false),
  ],
];
