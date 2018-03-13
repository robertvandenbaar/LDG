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
* Apache with mod_rewrite or nginx (nginx-light on Debian should suffice)
* Either PHP's GD library but preferably the Imagick extension (and ImageMagick installed)

## Installation
* Copy the contents in your DocumentRoot, this can also be a sub-folder
* Install composer (if you haven't done so already). Get it from [https://getcomposer.org/](https://getcomposer.org/) or use your system's package management
* `cd app`; `composer install`; `cd ..`;
* Edit `settings.json` and correctly set `image_base_dir`
* Set permissions to the 'cache' and 'data' folder so your webserver user can write to it
* Done

## Features

### Image viewing
You can choose to browse the original images or view a re-sized version of the images (default). 
This is convenient when you are browsing on a slow connection. 
You can control the size of these images in settings.json. 
These images are created on the fly or you can use the cron.php to create these (and the thumbnails) all at once.

### Search
You can search for images using the search box on the top right. To use the search feature you need to 
use the cron.php file to generate the index (on a daily basis). You should run this command
as the webserver user, for example:
```bash
sudo -u www-data php cron.php
```