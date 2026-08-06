<?php return array (
  '/nino/modules' => 
  array (
    0 => '\\Nino\\Modules\\Assets',
    1 => '\\Nino\\Modules\\Elements',
    2 => '\\Nino\\Modules\\Template',
    3 => '\\Nino\\Modules\\Jstext',
    4 => '\\Nino\\Modules\\Csrf',
    5 => '\\Nino\\Modules\\Images',
    6 => '\\Nino\\Modules\\Form',
    7 => '\\Nino\\Modules\\Navigation',
    8 => '\\Nino\\Modules\\Localepicker',
  ),
  '/nino/admin/backups' => false,
  '/nino/admin/logs' => false,
  '/nino/dir' => '',
  '/nino/error/log' => true,
  '/nino/error/display' => false,
  '/nino/session/force-secure-cookie' => false,
  '/nino/locales/native' => 'en_US',
  '/nino/locales/available' => 
  array (
    0 => 'en_US',
    1 => 'de_DE',
  ),
  '/nino/locales/textfiles' => '/text',
  '/nino/auth/maxtries' => 5,
  '/nino/auth/cooldown' => 3600,
  '/nino/html/assets' => 
  array (
    '/.cache/style.css' => 
    array (
      0 => '/_nino/Nino.css',
      1 => '/assets/style.theme.agency.css',
    ),
    '/.cache/script.js' => 
    array (
      0 => '/_nino/Nino.js',
      1 => '/_nino/Nino.ui.js',
      2 => '/assets/script.js',
    ),
  ),
  '/nino/html/images' => 
  array (
  ),
  '/nino/install/theme' => 'agency',
  '/nino/http/routes' => 
  array (
    'GET://robots.txt' => 
    array (
      'uri' => '/robots.txt',
      'body' => '[template /templates/robots]',
      'header' => 
      array (
        'Content-Type' => 'text/plain; charset=utf-8',
      ),
    ),
    'GET://sitemap.xml' => 
    array (
      'uri' => '/sitemap.xml',
      'body' => '[template /templates/sitemap-xml]',
      'header' => 
      array (
        'Content-Type' => 'application/xml; charset=utf-8',
      ),
    ),
    'GET://llms.txt' => 
    array (
      'uri' => '/llms.txt',
      'body' => '[template /templates/llms-txt]',
      'header' => 
      array (
        'Content-Type' => 'text/plain; charset=utf-8',
      ),
    ),
    'GET://' => 
    array (
      'uri' => '/home',
      'body' => '[template /templates/page-home]',
    ),
    'GET://404' => 
    array (
      'uri' => '/404',
      'body' => '[template /templates/page-404]',
      'statusCode' => 404,
    ),
    'GET://legal' => 
    array (
      'body' => '[template /templates/page-legal.[[/nino/http/response/locale]]]',
      'uri' => '/legal',
    ),
    'GET://contact' => 
    array (
      'uri' => '/contact',
      'body' => '[template /templates/page-contact]',
    ),
  ),
  '/nino/install/webpages' => 
  array (
    0 => 
    array (
      'uri' => '/home',
      'httpUri' => '/',
      'template' => 'page-home',
      'libraryKey' => 'home',
      'nav' => true,
      'statusCode' => 200,
      'body' => '[template /templates/page-home]',
      'text' => 
      array (
        'de_DE' => 
        array (
          'name' => 'Start',
          'title' => 'Willkommen',
          'description' => 'Willkommen auf unserer Website.',
        ),
      ),
    ),
    1 => 
    array (
      'uri' => '/404',
      'httpUri' => '/404',
      'template' => 'page-404',
      'libraryKey' => '404',
      'nav' => false,
      'statusCode' => 404,
      'body' => '[template /templates/page-404]',
      'text' => 
      array (
        'de_DE' => 
        array (
          'name' => 'Seite nicht gefunden',
          'title' => 'Seite nicht gefunden',
          'description' => 'Die angeforderte Seite wurde nicht gefunden.',
        ),
      ),
    ),
    2 => 
    array (
      'uri' => '/legal',
      'httpUri' => '/legal',
      'template' => '',
      'libraryKey' => 'legal',
      'nav' => false,
      'statusCode' => 200,
      'body' => '[template /templates/page-legal.[[/nino/http/response/locale]]]',
      'text' => 
      array (
        'de_DE' => 
        array (
          'name' => 'Impressum',
          'title' => 'Impressum',
          'description' => 'Impressum und rechtliche Hinweise.',
        ),
      ),
    ),
    3 => 
    array (
      'uri' => '/contact',
      'httpUri' => '/contact',
      'template' => 'page-contact',
      'libraryKey' => 'contact',
      'nav' => true,
      'statusCode' => 200,
      'body' => '[template /templates/page-contact]',
      'text' => 
      array (
        'de_DE' => 
        array (
          'name' => 'Kontakt',
          'title' => 'Kontakt',
          'description' => 'Kontaktieren Sie uns.',
        ),
      ),
    ),
  ),
  '/nino/auth/user' => 
  array (
  ),
);
