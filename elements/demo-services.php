<?php return array (
  'title' => 'Demo Services',
  'model' =>
  array (
    'title' =>
    array (
      'type' => 'string',
      'locale' => true,
    ),
    'description' =>
    array (
      'type' => 'string',
      'locale' => true,
    ),
    'tasks' =>
    array (
      'type' => 'string',
      'options' =>
      array (
        0 => 'Knowledge',
        1 => 'Motivation',
        2 => 'Workshops',
        3 => 'Fun',
      ),
    ),
    'cat' =>
    array (
      'type' => 'string',
      'options' =>
      array (
        0 => 'standard',
        1 => 'premium',
      ),
    ),
    'price' =>
    array (
      'type' => 'double',
    ),
  ),
  '*' =>
  array (
    '*' =>
    array (
    ),
    'coaching-standard' =>
    array (
      'cat' => 'standard',
      'price' => 89.0,
      'tasks' => 'Motivation',
    ),
    'coaching-premium' =>
    array (
      'cat' => 'standard',
      'price' => 349.0,
      'tasks' => 'Workshops',
    ),
    'consulting-standard' =>
    array (
      'cat' => 'standard',
      'price' => 129.0,
      'tasks' => 'Knowledge',
    ),
    'consulting-premium' =>
    array (
      'cat' => 'premium',
      'price' => 499.0,
      'tasks' => 'Knowledge',
    ),
    'training-standard' =>
    array (
      'cat' => 'standard',
      'price' => 199.0,
      'tasks' => 'Workshops',
    ),
  ),
  'de_DE' =>
  array (
    'coaching-standard' =>
    array (
      'title' => 'Einzelcoaching (60 Min.)',
      'description' => 'Eine persönliche Sitzung zur Klärung Ihrer individuellen Fragen und Anliegen.',
    ),
    'coaching-premium' =>
    array (
      'title' => 'Coaching-Paket (5 Tage)',
      'description' => 'Fünf aufbauende Sitzungen mit E-Mail-Unterstützung für nachhaltige Ergebnisse.',
    ),
    'consulting-standard' =>
    array (
      'title' => 'Beratungstermin (90 Min.)',
      'description' => 'Für die strategische Ausrichtung und Lösungsansätze Ihres aktuellen Projekts.',
    ),
    'consulting-premium' =>
    array (
      'title' => 'Strategie-Workshop (1 Tag)',
      'description' => '1 Tages-Workshop zur Entwicklung einer maßgeschneiderten Unternehmensstrategie.',
    ),
    'training-standard' =>
    array (
      'title' => 'Grundlagentraining (3 Std.)',
      'description' => 'Praktisches Training zu den wichtigsten Methoden und Grundlagen Ihres Bereichs.',
    ),
  ),
  'en_US' =>
  array (
    'coaching-standard' =>
    array (
      'title' => 'One-on-One Coaching (60m)',
      'description' => 'Personal session to address and resolve your individual questions and concerns.',
    ),
    'coaching-premium' =>
    array (
      'title' => 'Coaching Package (5 Sessions)',
      'description' => 'Five structured sessions with email support for sustainable results and growth.',
    ),
    'consulting-standard' =>
    array (
      'title' => 'Consultation Call (90 Minutes)',
      'description' => 'Strategic guidance and solutions tailored to your current project or challenge.',
    ),
    'consulting-premium' =>
    array (
      'title' => 'Strategy Workshop (Full Day)',
      'description' => 'Intensive workshop to develop a customized strategy for your business needs.',
    ),
    'training-standard' =>
    array (
      'title' => 'Foundational Training (3 Hours)',
      'description' => 'Hands-on training covering essential methods and fundamentals in your field.',
    ),
  ),
);
