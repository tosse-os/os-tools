<?php

return [

  'dimensions' => [
    'content' => [
      'label' => 'Content & Relevanz',
      'description' => 'Inhaltliche Qualität und Keyword-Relevanz.'
    ],
    'technical' => [
      'label' => 'Technische Optimierung',
      'description' => 'Strukturierte Daten und technische SEO-Faktoren.'
    ],
    'trust' => [
      'label' => 'Vertrauen & Konsistenz',
      'description' => 'Konsistenz von Unternehmensdaten.'
    ],
  ],

  'title' => [
    'dimension' => 'content',
    'label' => 'Title Tag',
    'description' => 'Der Title-Tag ist einer der wichtigsten Ranking-Faktoren.',
    'how_scoring_works' => [
      'keyword_present' => 'Hauptkeyword im Title',
      'city_present' => 'Stadt im Title',
      'length_optimal' => 'Optimale Länge (50–60 Zeichen)',
    ],
    'how_to_fix' => 'Optimiere den Title mit Hauptkeyword + Stadt und halte ihn zwischen 50–60 Zeichen.',
  ],

  'h1' => [
    'dimension' => 'content',
    'label' => 'H1 Überschrift',
    'description' => 'Die H1 signalisiert das Hauptthema der Seite.',
    'how_scoring_works' => [
      'exists' => 'H1 vorhanden',
      'keyword_present' => 'Keyword in H1',
      'city_present' => 'Stadt in H1',
    ],
    'how_to_fix' => 'Erstelle eine H1 mit Hauptkeyword + Stadt.',
  ],

  'content' => [
    'dimension' => 'content',
    'label' => 'Seiteninhalt',
    'description' => 'Bewertung des Textumfangs und Keyword-Relevanz.',
    'how_scoring_works' => [
      'sufficient_length' => 'Ausreichende Textlänge',
      'keyword_density' => 'Keyword sinnvoll integriert',
    ],
    'how_to_fix' => 'Erweitere den Content und integriere das Keyword sinnvoll.',
  ],

  'schema' => [
    'dimension' => 'technical',
    'label' => 'LocalBusiness Schema',
    'description' => 'Strukturierte Daten helfen Google dein Unternehmen korrekt zu verstehen.',
    'how_scoring_works' => [
      'exists' => 'Schema vorhanden',
      'phone_present' => 'Telefonnummer enthalten',
      'address_present' => 'Adresse enthalten',
    ],
    'how_to_fix' => 'Implementiere ein vollständiges JSON-LD LocalBusiness Schema.',
  ],

  'nap' => [
    'dimension' => 'trust',
    'label' => 'NAP Konsistenz',
    'description' => 'Name, Adresse und Telefonnummer müssen konsistent sein.',
    'how_scoring_works' => [
      'name_present' => 'Name vorhanden',
      'address_present' => 'Adresse vorhanden',
      'phone_present' => 'Telefonnummer vorhanden',
    ],
    'how_to_fix' => 'Stelle sicher, dass Name, Adresse und Telefonnummer vollständig und korrekt sind.',
  ],

];
