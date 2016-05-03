<?php

/**
 * Copyright (c) 2016 Robert van den Baar
 *
 * This program is free software; you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation; either version 3 of the License, or (at your option)
 * any later version.  See COPYING for more details.
*/

$requestFile = urldecode(str_replace('/detail', '', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)));
$requestFile = str_replace(APP_ROOT, '', $requestFile);

$sourceImageFilePath = IMAGE_BASE_DIR . $requestFile;

checkPath($sourceImageFilePath);

$relative = str_replace(IMAGE_BASE_DIR, '', $sourceImageFilePath);
$fileDetail = CACHE_DIR_DETAIL . $relative;

updateDetailImage($sourceImageFilePath);

header('Content-Type: image/jpeg');

readfile($fileDetail);

exit;

?>