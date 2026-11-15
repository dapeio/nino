<?php return array (
  'title' => 'Events',
  'model' =>
  array (
    'title' =>
    array (
      'type' => 'string',
      'locale' => true,
      'required' => true,
      'maxlength' => 200,
    ),
    'start' =>
    array (
      'type' => 'datetime',
      'required' => true,
    ),
    'end' =>
    array (
      'type' => 'datetime',
    ),
    'place' =>
    array (
      'type' => 'string',
      'locale' => true,
      'maxlength' => 200,
    ),
    'text' =>
    array (
      'type' => 'string',
      'locale' => true,
      'html' => true,
      'blocks' => true,
      'inputsize' => 6,
    ),
    'link' =>
    array (
      'type' => 'string',
      'maxlength' => 500,
    ),
    'group' =>
    array (
      'type' => 'string',
      'maxlength' => 60,
    ),
  ),
  '*' =>
  array (
    '*' =>
    array (
    ),
  ),
  'de_DE' =>
  array (
  ),
);