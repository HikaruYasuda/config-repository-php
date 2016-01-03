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

    public function testSetter()
    {
        $items = new  ReflectionProperty(ConfigRepository::class, 'items');
        $items->setAccessible(true);

        $this->assertEmpty($items->getValue($this->conf));

        $this->conf->set('email', 'info@example.com');

        $this->assertArrayHasKey('email', $items->getValue($this->conf));

        $this->assertTrue($this->conf->exists('email'));

        $this->assertEquals('info@example.com', $this->conf->get('email'));

        $this->conf->set('db', [
            'host' => '127.0.0.1',
            'username' => 'admin',
            'password' => 'qwerty12345',
        ]);
        $this->assertEquals('admin', $this->conf->get('db.username'));

        $this->assertArrayHasKey('db', $items->getValue($this->conf));

        $this->assertArrayHasKey('username', $items->getValue($this->conf)['db']);

        $this->assertTrue($this->conf->exists('db.username'));

        $this->assertEquals('admin', $this->conf->get('db.username'));
    }
}