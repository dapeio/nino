<?php return array (
  'title' => 'Projekte',
  'autoincrement' => 1,
  'model' =>
  array (
    'title' =>
    array (
      'type' => 'string',
      'required' => true,
      'maxlength' => 200,
    ),
    'client' =>
    array (
      'type' => 'string',
      'required' => true,
      'maxlength' => 10,
    ),
    'description' =>
    array (
      'type' => 'string',
      'breaks' => true,
      'inputsize' => 4,
      'maxlength' => 2000,
    ),
    'started' =>
    array (
      'type' => 'date',
    ),
    'done' =>
    array (
      'type' => 'boolean',
    ),
    'folder' =>
    array (
      'type' => 'string',
      'maxlength' => 40,
    ),
  ),
  '*' =>
  array (
    '*' =>
    array (
      'done' => false,
    ),
  ),
);