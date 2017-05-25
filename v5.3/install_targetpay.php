<?php
require_once 'top.inc.php';

$paths = [
    'classes/XLite/Module/TargetPay/Payment/install.yaml'
];

foreach ($paths as $path) {
    \XLite\Core\Database::getInstance()->loadFixturesFromYaml($path);
}
echo "Done!";
