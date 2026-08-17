<?php return array (
  '/nino/modules' => 
  array (
    0 => '\\Nino\\Modules\\Assets',
    1 => '\\Nino\\Modules\\Elements',
    2 => '\\Nino\\Modules\\Template',
    3 => '\\Nino\\Modules\\Jstext',
    4 => '\\Nino\\Modules\\Csrf',
    5 => '\\Nino\\Modules\\Images',
    6 => '\\Nino\\Modules\\Cache',
    7 => '\\Nino\\Modules\\Form',
    8 => '\\Nino\\Modules\\Navigation',
    9 => '\\Nino\\Modules\\Localepicker',
  ),
  '/nino/cache/status' => false,
  '/nino/cache/ttl' => 3600,
  '/nino/cache/blacklist' => 
  array (
  ),
  '/nino/editor/backups' => true,
  '/nino/editor/logs' => true,
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
  '/nino/html/navs' => 
  array (
    0 => 'main',
    1 => 'footer',
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
      'navs' => 
      array (
        'main' => 1,
      ),
    ),
    'GET://contact' =>
    array (
      'uri' => '/contact',
      'body' => '[template /templates/page-contact]',
      'navs' =>
      array (
        'main' => 2,
        'footer' => 1,
      ),
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
  ),
  '/nino/auth/user' => 
  array (
  ),
);
