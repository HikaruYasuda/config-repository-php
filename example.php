<?php
require_once 'config-repository.php';

echo '<pre>';
$conf = new ConfigRepository();
// set/get
$conf->set('email', 'info@example.com');
var_export($conf->get('email'));// 'info@example.com'
echo PHP_EOL;
// get deep item
$conf->set('db', [
    'host' => '127.0.0.1',
    'username' => 'admin',
    'password' => 'qwerty12345',
]);
var_export($conf->get('db.username'));// 'admin'
echo PHP_EOL;
// default value
var_export($conf->get('db.charset'));// NULL
echo PHP_EOL;
var_export($conf->get('db.charset', 'UTF-8'));// 'UTF-8'
echo PHP_EOL;
// remove
$conf->remove('db.password');
var_export($conf->get('db.password'));// NULL
echo PHP_EOL;
// set deep item
$conf->set('db.options.attr.'.PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
var_export($conf->get('db.options.attr', []));// array(3 => 2,)
echo PHP_EOL;
// lazy set
$conf->lazySet('some.heavy.setting', function($filename) {
    echo "loading from [$filename]...";
    return [['name' => 'ABC']];
}, ['heavy.dat']);
var_export($conf->get('some.heavy.setting'));// loading from [heavy.dat]...array(0 => array('name' => 'ABC',),)
echo PHP_EOL;
var_export($conf->get('some.heavy.setting'));// array(0 => array('name' => 'ABC',),)
echo PHP_EOL;
// lazy default value
$func = function() use ($conf) {
    echo 'load default.';
    return $conf->get('defaults.timeout', 200);
};
var_export($conf->get('db.options.attr.'.PDO::ATTR_TIMEOUT, $func));// load default.200
echo PHP_EOL;
echo '</pre>';