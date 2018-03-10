LDG (Lightweight Directory Gallery)
===================

## What does this do?
It generates a list of folders and images (and other files) as they exist on the filesystem.
When browsing the images they will be displayed in a light-box and can be browsed using buttons, 
swipe actions on smart-phones and with the keys of your keyboard. The original files will only be
read, no write actions are performed on the original files. For all images a smaller version of the 
image will be generated (on the fly or using cron.php).

## Requirements
* PHP 5.4 and up
* Apache with mod_rewrite
* Either PHP's GD library but preferably the Imagick extension (and ImageMagick installed)

## Installation
* Copy the contents in your DocumentRoot, this can also be a sub-folder
* Install composer (if you haven't done so already) https://getcomposer.org/
* cd app; composer install;
* In settings.json, set image_base_dir
* Set permissions to the 'cache' and 'data' folder so Apache can write to it
* Done

## Options
You can choose to browse the default (large) images or view a re-sized version of the images. You can 
control the size of these images in settings.json. This is convenient when you are browsing on a 
slow connection. 
These images are created
on the fly or you can use the cron.php to create these (and the thumbnails) all at once (maybe adding 
this script in a cron-tab so you don't have to wait for them to be generated (first time)).
You can search for images using the search box on the top right. The search index is updated as images are being viewed but the preferred method is to use the cron.php
