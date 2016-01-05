<?php
require '../config-repository.php';

class ConfigRepositoryTest extends PHPUnit_Framework_TestCase
{
    /** @var ConfigRepository */
    public $conf;

    public function setUp()
    {
        parent::setUp();
        $this->conf = new ConfigRepository();
    }

    public function testInit()
    {
        $conf = new ConfigRepository();
        $this->assertEmpty($conf->asArray());

        $conf = new ConfigRepository([
            'db' => [
                'host' => '127.0.0.1',
                'username' => 'admin',
                'password' => 'qwerty12345',
            ],
        ], [
            ConfigRepository::OPT_DEFAULT_VALUE => '__default_value__',
            ConfigRepository::OPT_PATH_SEPARATOR => '\\',
        ]);
        $this->assertEquals('admin', $conf->asArray()['db']['username']);
        $this->assertEquals('__default_value__', $conf->get('nothing'));
        $this->assertEquals('__custom_value__', $conf->get('nothing', '__custom_value__'));
        $this->assertEquals('admin', $conf->get('db\\username'));
        $conf->set('db\\charset', 'UTF-8');
        $conf->set('db.charset', 'EUC-JP');
        $this->assertEquals('UTF-8', $conf->asArray()['db']['charset']);
        $this->assertEquals('EUC-JP', $conf->asArray()['db.charset']);
    }

    public function testSet()
    {
        $this->conf->set('email', 'info@example.com');

        $this->assertArrayHasKey('email', $this->conf->asArray());

        $this->conf->set('null', null);
        $this->assertArrayHasKey('null', $this->conf->asArray());
        $this->assertNull($this->conf->asArray()['null']);

        $this->conf->set('db', [
            'host' => '127.0.0.1',
            'username' => 'admin',
            'password' => 'qwerty12345',
        ]);
        $this->assertArrayHasKey('db', $this->conf->asArray());
        $this->assertEquals('admin', $this->conf->asArray()['db']['username']);

        $e = null;
        try {
            $this->conf->set(null, 'test');
        } catch (Exception $e) {}
        $this->assertTrue($e instanceof InvalidArgumentException);

        $this->conf->set('db.options.tables.book', [
            'columns' => [
                'id' => 'INT(9) UNSIGNED NOT NULL PRIMARY KEY',
                'title' => 'VARCHAR(50) NOT NULL',
                'author' => 'VARCHAR(50)',
            ],
        ]);
        $this->assertEquals('VARCHAR(50)', $this->conf->asArray()['db']['options']['tables']['book']['columns']['author']);
        $this->assertEquals('admin', $this->conf->asArray()['db']['username']);
    }

    public function testGet()
    {
        $this->conf->set('db.options.tables.book', [
            'columns' => [
                'id' => 'INT(9) UNSIGNED NOT NULL PRIMARY KEY',
                'title' => 'VARCHAR(50) NOT NULL',
                'author' => 'VARCHAR(50)',
            ],
        ]);
        $this->assertArrayHasKey('book', $this->conf->get('db.options.tables'));
        $this->assertEquals('VARCHAR(50)', $this->conf->get('db.options.tables.book.columns.author'));
        $this->assertNull($this->conf->get('db.options.tables.author'));
        $this->assertNull($this->conf->get('db.options.tables.author', ConfigRepository::_UNSPECIFIED));
        $this->assertEquals('__default__', $this->conf->get('db.options.tables.author', '__default__'));
        $this->assertEquals('__custom__', $this->conf->get('db.options.tables.author', function($a, $b, $c) {
            return "$a$b$c";
        }, ['__', 'custom', '__']));

        $this->conf->lazySet('db.options.tables.author', function() {
            return [
                'columns' => [
                    'id' => 'INT(9) UNSIGNED NOT NULL PRIMARY KEY',
                    'name' => "VARCHAR(50) NOT NULL",
                ],
            ];
        });
        $this->assertInternalType('array', $this->conf->get('db.options.tables.author'));
        $this->assertEquals('INT(9) UNSIGNED NOT NULL PRIMARY KEY', $this->conf->get('db.options.tables.author.columns.id'));
    }

    public function testLazySet()
    {
        $this->conf->lazySet('db.options.tables.author', function($len) {
            return [
                'columns' => [
                    'id' => 'INT(9) UNSIGNED NOT NULL PRIMARY KEY',
                    'name' => "VARCHAR($len) NOT NULL",
                ],
            ];
        }, [50]);

        $this->assertInstanceOf(ConfigRepository::LAZY_VAL_CLASS, $this->conf->asArray()['db']['options']['tables']['author']);
        $this->assertEquals('VARCHAR(50) NOT NULL', $this->conf->get('db.options.tables.author.columns.name'));
        $this->assertEquals('VARCHAR(50) NOT NULL', $this->conf->asArray()['db']['options']['tables']['author']['columns']['name']);

        $this->conf->lazySet('db.options.tables.book', function() {
            return [
                'columns' => [
                    'id' => 'INT(9) UNSIGNED NOT NULL PRIMARY KEY',
                    'title' => 'VARCHAR(50) NOT NULL',
                    'author' => 'VARCHAR(50)',
                ],
            ];
        }, null, true);
        $this->assertInstanceOf(ConfigRepository::LAZY_VAL_CLASS, $this->conf->asArray()['db']['options']['tables']['book']);
        $this->assertEquals('VARCHAR(50)', $this->conf->get('db.options.tables.book.columns.author'));
        $this->assertInstanceOf(ConfigRepository::LAZY_VAL_CLASS, $this->conf->asArray()['db']['options']['tables']['book']);
    }
}