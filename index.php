<?php

define('BASE_DIR', dirname(__FILE__));
define('BASE_URL', str_replace($_SERVER['DOCUMENT_ROOT'], '', BASE_DIR));

require 'app/vendor/autoload.php';

$app = new \Ldg\App();
$app->run();