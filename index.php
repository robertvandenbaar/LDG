<?php

/**
 * Copyright (c) 2016 Robert van den Baar
 *
 * This program is free software; you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation; either version 3 of the License, or (at your option)
 * any later version.  See COPYING for more details.
 */

/* CONFIG FILE, edit this file to set the image base dir and other settings */
include('config.php');

include('init.php');
include('functions.php');

$actions = array();
$actions[] = 'list';
$actions[] = 'detail';
$actions[] = 'original';

$uri = $_SERVER['REQUEST_URI'];
$uri = str_replace(APP_ROOT, '', $uri);
$parts = explode('/', $uri);

if (isset($parts[1]) && in_array($parts[1], $actions))
{
	$action = $parts[1];
}
else
{
	$action = 'list';
}

$html = '';

if ($action == 'list')
{
	$html = include('action.list.php');
}
elseif($action == 'detail')
{
	$html = include('action.detail.php');
}
elseif($action == 'original')
{
	$html = include('action.original.php');
}

$html = str_replace('<!-- CONTENT -->', $html, file_get_contents(APP_DIR . '/assets/html/template.html'));
$html = str_replace('/assets/', APP_ROOT . '/assets/', $html);

if ($_SESSION['full-size'] == true)
{
	$html = str_replace('<div id="image-nav-size"', '<div id="image-nav-size" class="active"', $html);
}

echo $html;

?>