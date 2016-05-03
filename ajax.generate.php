<?php

/**
 * Copyright (c) 2016 Robert van den Baar
 *
 * This program is free software; you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation; either version 3 of the License, or (at your option)
 * any later version.  See COPYING for more details.
 */

include('config.php');
include('init.php');
include('functions.php');

$requestFile = urldecode($_GET['file']);

$sourceImageFilePath = IMAGE_BASE_DIR . $requestFile;

checkPath($sourceImageFilePath);

$relative = str_replace(IMAGE_BASE_DIR, '', $sourceImageFilePath);
$fileThumbnail = CACHE_DIR_THUMBNAIL . $relative;

updateThumbnailImage($sourceImageFilePath);

if (file_exists($fileThumbnail))
{
    echo 'success';
}
else
{
    echo 'fail';
}

exit;

?>