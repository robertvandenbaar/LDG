<?php

/**
 * Copyright (c) 2016 Robert van den Baar
 *
 * This program is free software; you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation; either version 3 of the License, or (at your option)
 * any later version.  See COPYING for more details.
 */

define('APP_DIR', dirname(__FILE__));
define('CACHE_DIR', APP_DIR . '/cache');
define('CACHE_DIR_THUMBNAIL', CACHE_DIR . '/thumbnail');
define('CACHE_DIR_DETAIL', CACHE_DIR . '/detail');
define('APP_ROOT', str_replace($_SERVER['DOCUMENT_ROOT'], '', APP_DIR));
$GLOBALS['IMAGE_EXTENSIONS'] = array('jpg', 'jpeg', 'png', 'gif');

/* start session */
session_start();

/* check and set full-size setting */
if (!isset($_SESSION['full-size']))
{
	if (defined('FULL_SIZE_BY_DEFAULT') && FULL_SIZE_BY_DEFAULT == true)
	{
		$_SESSION['full-size'] = true;
	}
	else
	{
		$_SESSION['full-size'] = false;
	}

}

?>