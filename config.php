<?php return array (
  '/nino/modules' =>
  array (
    0 => '\\Nino\\Modules\\Assets',
    1 => '\\Nino\\Modules\\Elements',
    2 => '\\Nino\\Modules\\Jstext',
    3 => '\\Nino\\Modules\\Localepicker',
    4 => '\\Nino\\Modules\\Navigation',
    5 => '\\Nino\\Modules\\Template',
    6 => '\\Nino\\Modules\\Csrf',
    7 => '\\Nino\\Modules\\Images',
    8 => '\\Nino\\Modules\\Form',
    9 => '\\Nino\\Modules\\Newsletter',
  ),
  '/nino/admin/backups' => false,
  '/nino/admin/logs' => false,
  '/nino/newsletter/confirm-template' => '/templates/mail-newsletter-confirm',
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
      1 => '/assets/style.theme01.css',
    ),
    '/.cache/script.js' =>
    array (
      0 => '/_nino/Nino.js',
      1 => '/_nino/Nino.ui.js',
      3 => '/assets/script.js',
    ),
  ),
  '/nino/html/images' =>
  array (
  ),
  '/nino/http/routes' =>
  array (
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
    'GET://about' =>
    array (
      'uri' => '/about',
      'body' => '[template /templates/page-about]',
    ),
    'GET://services' =>
    array (
      'uri' => '/services',
      'body' => '[template /templates/page-services]',
    ),
    'GET://work' =>
    array (
      'uri' => '/work',
      'body' => '[template /templates/page-work]',
    ),
    'GET://faq' =>
    array (
      'uri' => '/faq',
      'body' => '[template /templates/page-faq]',
    ),
    'GET://contact' =>
    array (
      'uri' => '/contact',
      'body' => '[template /templates/page-contact]',
    ),
    'GET://.demo-elements' =>
    array (
      'uri' => '/.demo-elements',
      'body' => '[template /templates/.demo-elements]',
    ),
    'GET://.demo-sections' =>
    array (
      'uri' => '/.demo-sections',
      'body' => '[template /templates/.demo-sections]',
    ),
    'GET://.demo-vpa' =>
    array (
      'uri' => '/.demo-vpa',
      'body' => '[template /templates/.demo-vpa]',
    ),
    'GET://rechtliches' =>
    array (
      'uri' => '/legal',
      'body' => '[template /templates/page-legal.de_DE]',
      'locale' => 'de_DE',
    ),
    'GET://legal' =>
    array (
      'uri' => '/legal',
      'body' => '[template /templates/page-legal.en_US]',
      'locale' => 'en_US',
    ),
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
  ),
  '/nino/auth/user' =>
  array (
    'changeme@domain.com' =>
    array (
      'pw' => '$2y$10$bdAzpYYC2Yyn3wyr.kcIf.gtjBwDKm1yNNX6oTpAoak15QHnCS2gm',
      'status' => 0,
      'sessions' =>
      array (
      ),
      'perms' =>
      array (
        0 => '/*',
      ),
      'tries' => 0,
      'mail' => 'changeme@domain.com',
    ),
  ),
);