<?php

/**
 * Copyright (c) 2016 Robert van den Baar
 *
 * This program is free software; you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation; either version 3 of the License, or (at your option)
 * any later version.  See COPYING for more details.
 */

$requestDir = urldecode(str_replace('/list', '', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)));
$requestDir = str_replace(APP_ROOT, '', $requestDir);

$listBaseDir = IMAGE_BASE_DIR . $requestDir;

if (!is_dir($listBaseDir))
{
	header("HTTP/1.0 404 Not Found");

	return '<p>File not found, please return to the <a href="' . APP_ROOT . '/">homepage</a></p>';
}

checkPath($listBaseDir);

$files = scandir($listBaseDir);

$folders = $images = $otherFiles = array();

foreach ($files as $file)
{
	if ($file == '..' || $file == '.')
	{
		continue;
	}

	if (is_dir($listBaseDir . '/' . $file))
	{
		$folders[] = $file;
	}
	else
	{
		$extension = getExtension($file);

		if (in_array($extension, $GLOBALS['IMAGE_EXTENSIONS']))
		{
			$images[] = $file;
		}
		else
		{
			$otherFiles[] = $file;
		}
	}
}

if($requestDir == '/')
{
	$requestDir = '';
}

$html .= '<div class="breadcrumb">';
$html .= '<a class="back-to-folder" href="' . APP_ROOT . '/">Home</a>';
$parts = explode('/', trim($requestDir, '/'));

$buildPart = '';

$i = 0;

if (count($parts) > 0 && $parts[0] != '')
{
	foreach($parts as $part)
	{
		$buildPart .= '/' . $part;

		/* last item will be a span */
		if (++$i == count($parts))
		{
			$html .= htmlTagContent('span', $part);
		}
		else
		{
			$html .= htmlTagContent('a', $part, array(
			   'class' => 'back-to-folder',
			   'href' => APP_ROOT . $buildPart
			));
		}
	}
}

$html .= '<em id="information"></em>';
$html .= '</div>';

$html .= '<div class="navigation">';

$html .= '<div class="folders">';
if (!empty($requestDir))
{
	$parts = explode('/', trim($requestDir, '/'));
	array_pop($parts);

	$html .= htmlTagContent('a', '^', array(
		'title' => 'up one directory',
		'class' => 'folder',
		'href'  => APP_ROOT . '/list/' . implode('/', $parts)
	));
}

foreach($folders as $folder)
{
	$html .= htmlTagContent('a', $folder, array(
		'title' => $folder,
		'class' => 'folder',
		'href'  => APP_ROOT . '/list' . $requestDir . '/' . $folder
	));
}
$html .= '</div>';

if (count($otherFiles) > 0)
{
	$html .= '<div class="other-files">';
	$html .= '<p class="other-file-header">Files in this folder</p>';
	foreach ($otherFiles as $otherFile)
	{
		$html .= htmlTagContent('a', $otherFile, array(
			'title' => $otherFile,
			'class' => 'other-file',
			'href'  => APP_ROOT . '/original' . $requestDir . '/' . $otherFile
		));
	}
	$html .= '</div>';
}

$html .= '</div>';

$html .= '<div class="images">';

foreach($images as $image)
{
	$imageFilePath = IMAGE_BASE_DIR . $requestDir . '/' . $image;

	$relative = str_replace(IMAGE_BASE_DIR, '', $imageFilePath);
	$fileDetail = CACHE_DIR_DETAIL . $relative;
	$fileThumbnail = CACHE_DIR_THUMBNAIL . $relative;

	$thumbnailCurrent = file_exists($fileThumbnail) && filectime($fileThumbnail) >= filectime($imageFilePath);

	if ($thumbnailCurrent)
	{
		$thumbnailSrc = APP_ROOT . '/cache/thumbnail' . $requestDir . '/' . $image;
	}
	else
	{
		$thumbnailSrc = '/assets/images/blank.gif';
	}

	$detailLink = APP_ROOT;

	if (isset($_SESSION['full-size']) && $_SESSION['full-size'] === true)
	{
		$detailLink .= '/original';
	}
	else
	{
		/* show the detail file using cached version (so it doesn't have to go though PHP) */
		if (file_exists($fileDetail))
		{
			$detailLink .= '/cache/detail';

		}
		/* if not, call the detail view so the image is generated on the fly when it is opened */
		else
		{
			$detailLink .= '/detail';
		}
	}

	$detailLink .= $requestDir . '/' . $image;

	$html .= htmlTagOpen('a', array('class' => 'image', 'href'  =>  $detailLink));

	$imageAttributes = array('src' => $thumbnailSrc, 'alt' => basename($imageFilePath), 'title' => basename($imageFilePath));
	if (!$thumbnailCurrent)
	{
		$imageAttributes['class'] = 'loading';
		$imageAttributes['data-src'] = $relative;
		$imageAttributes['id'] = uniqid();
	}

	$html .= htmlTagSingle('img', $imageAttributes);
	$html .= htmlTagClose('a');
}
$html .= '</div>';

$html .= '<script type="text/javascript">';
$html .= 'window.appRoot = "' . APP_ROOT . '";';
$html .= '</script>';

return $html;

?>
