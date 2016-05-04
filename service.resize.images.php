<?php

if (php_sapi_name() != 'cli')
{
	die('This file should be run on the commandline');
}

include('config.php');
include('init.php');
include('functions.php');

echo "\n\nStart\n";
echo "--------------------------\n";

function createCroppedImagesRecursively($baseDir)
{
	foreach (scandir($baseDir) as $file)
	{
		if($file == '.' || $file == '..')
		{
			continue;
		}

		$fullPath = $baseDir . '/' . $file;

		if (in_array(getExtension($fullPath), $GLOBALS['IMAGE_EXTENSIONS']))
		{
			echo "Updating thumbnail and mid-size image for " . $fullPath . "\n";

			/* only attempt to create the detail image if the thumbnail was created succesfully */
			if(updateThumbnailImage($fullPath))
			{
				updateDetailImage($fullPath);
			}
		}
		elseif (is_dir($fullPath))
		{
			createCroppedImagesRecursively($fullPath);
		}
	}
}

createCroppedImagesRecursively(IMAGE_BASE_DIR);

echo "\n\nFinished\n";
echo "--------------------------\n";
echo "Make sure you set the correct permissions on the cache folder (or use sudo -u)\n";
echo "and all its subfolders and files so the user the apache\n";
echo "process runs on is able to write to the folder\n";

?>
