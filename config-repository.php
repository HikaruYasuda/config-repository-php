<?php

/**
 * ConfigRepository
 *
 * <code>
 * $conf = new ConfigRepository();
 * // set/get
 * $conf->set('email', 'info@example.com');
 * var_export($conf->get('contact'));// 'info@example.com'
 * // get deep item
 * $conf->set('db', [
 *   'host' => '127.0.0.1',
 *   'username' => 'admin',
 *   'password' => 'qwerty12345',
 * ]);
 * var_export($conf->get('db.username'));// 'admin'
 * // default value
 * var_export($conf->get('db.charset'));// NULL
 * var_export($conf->get('db.charset', 'UTF-8'));// 'UTF-8'
 * // remove
 * $conf->remove('db.password');
 * var_export($conf->get('db.password'));// NULL
 * // set deep item
 * $conf->set('db.options.attr.'.PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
 * var_export($conf->get('db.options.attr', []));// array(3 => 2)
 * // lazy set
 * $conf->lazySet('some.heavy.setting', function($filename) {
 *   echo "loading from [$filename]...";
 *   return [['name' => 'ABC']];
 * }, ['heavy.dat']);
 * var_export($conf->get('some.heavy.setting'));// loading from heavy.dat...array(array('name' => 'ABC'))
 * var_export($conf->get('some.heavy.setting'));// array(array('name' => 'ABC'))
 * // lazy default value
 * $func = function() use ($conf) {
 *   echo 'load default.';
 *   return $conf->get('defaults.timeout', 200);
 * };
 * var_export($conf->get('db.options.attr.'.PDO::ATTR_TIMEOUT, $func));// load default. 200
 * </code>
 *
 * @since 5.4.0
 */
class ConfigRepository
{
    const _UNSPECIFIED = '9V;2Jf:ja+!ejjKn';
    const LAZY_VAL_CLASS = 'ConfigRepositoryLazyAssignValueWrapper';

    /** option key for the path separator */
    const OPT_PATH_SEPARATOR = 'pathSeparator';
    /** option key for the default value */
    const OPT_DEFAULT_VALUE = 'defaultValue';

    /** @var array Stored items */
    protected $items = [];
    /** @var array Cache of the path string's parse results */
    protected $caches = [];
    /** @var string Path separator string */
    protected $pathSeparator = '.';
    /** @var mixed Default value on fail retrieve */
    protected $defaultValue = null;

    /**
     * Constructor
     *
     * @param array $items
     * @param array $options
     */
    public function __construct(array $items = null, array $options = null)
    {
        if (is_array($items)) {
            $this->items = $items;
        }
        foreach ((array)$options as $key => $val) {
            switch ($key) {
                case self::OPT_PATH_SEPARATOR:
                    $this->pathSeparator = (string)$val;
                    break;
                case self::OPT_DEFAULT_VALUE:
                    $this->defaultValue = (string)$val;
                    break;
            }
        }
    }

    /**
     * Whether a key exists
     *
     * @param string $key A key to check for.
     * Concat by a path separator string(.) and can be specified deep hierarchy elements of array
     * @return boolean true on success or false on not fail.
     */
    public function exists($key)
    {
        if (array_key_exists($key, $this->items)) return true;
        if (array_key_exists($key, $this->caches)) return true;
        return static::isArrayLike($this->resolve($key));
    }

    /**
     * Retrieve by key
     *
     * @param string $key The key to retrieve.
     * Concat by a path separator string(.) and can be specified deep hierarchy elements of array
     * @param mixed|callable $default A default value or a function that returns a default value.
     * @param array $args Variable list of array arguments to run through the function.
     * @return mixed Stored value on success or default parameter on fail.
     */
    public function get($key, $default = self::_UNSPECIFIED, array $args = null)
    {
        if (array_key_exists($key, $this->caches)) return $this->caches[$key];

        $scope = &$this->resolve($key, $path);
        if (static::isArrayLike($scope)) {
            return $this->caches[(string)$key] = $scope[$path];
        }
        if (is_callable($default)) {
            return call_user_func_array($default, (array)$args);
        }
        if ($default === self::_UNSPECIFIED) {
            return $this->defaultValue;
        }
        return $default;
    }

    /**
     * Set a value by key
     *
     * @param string $key The key to assign the value to.
     * Concat by a path separator string(.) and can be specified deep hierarchy elements of array
     * @param mixed $value
     */
    public function set($key, $value)
    {
        $scope = &$this->resolve($key, $path, true);
        if (!static::isArrayLike($scope)) {
            $func = __METHOD__;
            $type = gettype($key);
            throw new InvalidArgumentException("Argument 1 passed to $func must be a string, $type given.");
        }
        $scope[$path] = $value;
    }

    /**
     * Set a lazy evaluation value by key
     *
     * @param string $key The key to reserve the lazy evaluation value.
     * Concat by a path separator string(.) and can be specified deep hierarchy elements of array
     * @param callable $func A function that called when needed and returns a value.
     * @param array $args Variable list of array arguments to run through the function.
     * @param boolean $always
     */
    public function lazySet($key, callable $func, array $args = null, $always = false)
    {
        if ( ! class_exists(self::LAZY_VAL_CLASS)) {
            @eval('class '.self::LAZY_VAL_CLASS.' { var $func, $args, $always; }');
        }
        $scope = &$this->resolve($key, $path, true);
        $class = self::LAZY_VAL_CLASS;
        $obj = new $class;
        $obj->func = $func;
        $obj->args = (array)$args;
        $obj->always = (bool)$always;
        $scope[$path] = $obj;
    }

    /**
     * Remove a value by key
     *
     * @param string $key The key to remove the value.
     * Concat by a path separator string(.) and can be specified deep hierarchy elements of array
     */
    public function remove($key)
    {
        $scope = &$this->resolve($key, $path);
        if (static::isArrayLike($scope)) {
            unset($scope[$path]);
            unset($this->caches[$key]);
        }
    }

    /**
     * Cast to array
     *
     * @return array
     */
    public function asArray()
    {
        return $this->items;
    }

    /**
     * @param string $key
     * @param string $lastPath
     * @param bool $create
     * @return array|bool
     */
    public function &resolve($key, &$lastPath = null, $create = false)
    {
        $false = false;
        $key = (string)$key;
        $paths = ($key === '') ? [] : explode($this->pathSeparator, $key);
        $scope = &$this->items;
        while (count($paths)) {
            $path = array_shift($paths);
            if ($create) {
                if ( ! static::isArrayLike($scope)) $scope = [];
                if ( ! array_key_exists($path, $scope)) $scope[$path] = null;
            } else {
                if ( ! static::isArrayLike($scope)) return $false;
                if ( ! array_key_exists($path, $scope)) return $false;
            }
            if (is_a($scope[$path], self::LAZY_VAL_CLASS)) {
                $obj = $scope[$path];
                $val = call_user_func_array($obj->func, $obj->args);
                if ($obj->always) {
                    unset($scope);
                    $scope = [$path => &$val];
                } else {
                    $scope[$path] = $val;
                }
            }
            if (!count($paths)) {
                $lastPath = $path;
                return $scope;
            }
            $scope = &$scope[$path];
        }
        return $false;
    }

    /**
     * Check a value Whether like array
     *
     * @param mixed $val
     * @return boolean
     */
    final protected static function isArrayLike($val)
    {
        return is_array($val) or is_subclass_of($val, 'ArrayAccess', false);
    }
}
