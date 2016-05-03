LDG (Lightweight Directory Gallery)
===================

## What does this do?
It generates a list of folders and images (and other files) as they exist on the filesystem.
When browsing the images they will be displayed in a light-box and can be browsed using buttons, swipe actions on
smart-phones and with the keys of your keyboard. 

## Requirements
* PHP 5.3
* Apache with mod_rewrite
* Either PHP's GD library but preferably the Imagick extension (and ImageMagick installed)

## Installation
* Copy the contents in your DocumentRoot, this can also be a sub-folder
* In config.php, set IMAGE_BASE_DIR
* Set permissions to the 'cache' folder so Apache can write to it
* Done

## Options
You can choose to browse the default (large) images or view a re-sized version of the images. You can control the size
of these images in config.php. This is convenient when you are browsing on a slow connection. These images are created
on the fly or you can use the service.resize.images.php to create these (and the thumbnails) all at once (maybe adding 
this script in a cron-tab so you don't have to wait for them to be generated (first time)).

