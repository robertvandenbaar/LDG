<?php

define('BASE_DIR', dirname(__FILE__));
define('BASE_URL', str_replace($_SERVER['DOCUMENT_ROOT'], '', BASE_DIR));
define('BASE_URL_LDG', BASE_URL . '/ldg');

require 'app/vendor/autoload.php';

$app = new \Ldg\App();
$app->run();