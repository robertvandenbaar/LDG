<?php

/**
 * This speaks for itself
 * NOTE: do NOT add a trailing slash
 */
define('IMAGE_BASE_DIR', '/var/www/testfoto');

/**
 * Dimensions of the thumbnails
 */
define('THUMBNAIL_WIDTH', 120);
define('THUMBNAIL_HEIGHT', 78);

/**
 * Dimensions of the mid-sized images
 */
define('DETAIL_WIDTH', 800);
define('DETAIL_HEIGHT', 600);

/*
 * Quality of the mid-sized and the thumbnail images
 */
define('RESIZE_IMAGE_QUALITY', 72);

/**
 * Whether to start browsing in full-size at a new session
 */
define('FULL_SIZE_BY_DEFAULT', false);

?>
