# config-repository-php
Repository of configuration properties for PHP>=5.4


## usage

```php
require_once 'config-repository.php';

$conf = new ConfigRepository();
// set/get
$conf->set('email', 'info@example.com');
var_export($conf->get('contact'));// 'info@example.com'
// get deep item
$conf->set('db', [
    'host' => '127.0.0.1',
    'username' => 'admin',
    'password' => 'qwerty12345',
]);
var_export($conf->get('db.username'));// 'admin'
// default value
var_export($conf->get('db.charset'));// NULL
var_export($conf->get('db.charset', 'UTF-8'));// 'UTF-8'
// remove
$conf->remove('db.password');
var_export($conf->get('db.password'));// NULL
// set deep item
$conf->set('db.options.attr.'.PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
var_export($conf->get('db.options.attr', []));// array(3 => 2)
// lazy set
$conf->lazySet('some.heavy.setting', function($filename) {
    echo "loading from [$filename]...";
    return [['name' => 'ABC']];
}, ['heavy.dat']);
var_export($conf->get('some.heavy.setting'));// loading from heavy.dat...array(array('name' => 'ABC'))
var_export($conf->get('some.heavy.setting'));// array(array('name' => 'ABC'))
// lazy default value
$func = function() use ($conf) {
    echo 'load default.';
    return $conf->get('defaults.timeout', 200);
};
var_export($conf->get('db.options.attr.'.PDO::ATTR_TIMEOUT, $func));// load default. 200
```