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

/* overwrite the session variable for full-size */
if (isset($_GET['full-size']))
{
	if ($_GET['full-size'] == 'true')
	{
		$_SESSION['full-size'] = true;
	}
	else
	{
		$_SESSION['full-size'] = false;
	}
}

exit;

?>