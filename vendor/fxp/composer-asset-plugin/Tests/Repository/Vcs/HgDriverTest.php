<?php

/*
 * This file is part of the Fxp Composer Asset Plugin package.
 *
 * (c) François Pluchino <francois.pluchino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fxp\Composer\AssetPlugin\Tests\Repository\Vcs;

use Composer\Config;
use Composer\IO\IOInterface;
use Composer\Util\Filesystem;
use Composer\Util\ProcessExecutor;
use Fxp\Composer\AssetPlugin\Repository\Vcs\HgDriver;

/**
 * Tests of vcs mercurial repository.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 *
 * @internal
 */
final class HgDriverTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Config
     */
    private $config;

    protected function setUp()
    {
        $this->config = new Config();
        $this->config->merge(array(
            'config' => array(
                'home' => sys_get_temp_dir().'/composer-test',
                'cache-repo-dir' => sys_get_temp_dir().'/composer-test-cache',
            ),
        ));
    }

    protected function tearDown()
    {
        $fs = new Filesystem();
        $fs->removeDirectory(sys_get_temp_dir().'/composer-test');
        $fs->removeDirectory(sys_get_temp_dir().'/composer-test-cache');
    }

    public function getAssetTypes()
    {
        return array(
            array('npm', 'package.json'),
            array('bower', 'bower.json'),
        );
    }

    /**
     * @dataProvider getAssetTypes
     *
     * @param string $type
     * @param string $filename
     */
    public function testPublicRepositoryWithEmptyComposer($type, $filename)
    {
        $repoUrl = 'https://bitbucket.org/composer-test/repo-name';
        $identifier = 'v0.0.0';
        $io = $this->getMockBuilder('Composer\IO\IOInterface')->getMock();

        $repoConfig = array(
            'url' => $repoUrl,
            'asset-type' => $type,
            'filename' => $filename,
        );

        $process = $this->getMockBuilder('Composer\Util\ProcessExecutor')->getMock();
        $process->expects(static::any())
            ->method('splitLines')
            ->willReturn(array())
        ;
        $process->expects(static::any())
            ->method('execute')
            ->willReturnCallback(function () {
                return 0;
            })
        ;

        /** @var IOInterface $io */
        /** @var ProcessExecutor $process */
        $driver = new HgDriver($repoConfig, $io, $this->config, $process, null);
        $driver->initialize();

        $validEmpty = array(
            '_nonexistent_package' => true,
        );

        static::assertSame($validEmpty, $driver->getComposerInformation($identifier));
    }

    /**
     * @dataProvider getAssetTypes
     *
     * @param string $type
     * @param string $filename
     */
    public function testPublicRepositoryWithCodeCache($type, $filename)
    {
        $repoUrl = 'https://bitbucket.org/composer-test/repo-name';
        $identifier = '92bebbfdcde75ef2368317830e54b605bc938123';
        $repoConfig = array(
            'url' => $repoUrl,
            'asset-type' => $type,
            'filename' => $filename,
        );
        $io = $this->getMockBuilder('Composer\IO\IOInterface')->getMock();
        $process = $this->getMockBuilder('Composer\Util\ProcessExecutor')->getMock();
        $process->expects(static::any())
            ->method('splitLines')
            ->willReturn(array())
        ;
        $process->expects(static::any())
            ->method('execute')
            ->willReturnCallback(function ($command, &$output = null) use ($identifier, $repoConfig) {
                if ($command === sprintf('hg cat -r %s %s', ProcessExecutor::escape($identifier), $repoConfig['filename'])) {
                    $output = '{"name": "foo"}';
                } elseif (false !== strpos($command, 'hg log')) {
                    $date = new \DateTime(null, new \DateTimeZone('UTC'));
                    $output = $date->format(\DateTime::RFC3339);
                }

                return 0;
            })
        ;

        /** @var IOInterface $io */
        /** @var ProcessExecutor $process */
        $driver = new HgDriver($repoConfig, $io, $this->config, $process, null);
        $driver->initialize();
        $composer1 = $driver->getComposerInformation($identifier);
        $composer2 = $driver->getComposerInformation($identifier);

        static::assertNotNull($composer1);
        static::assertNotNull($composer2);
        static::assertSame($composer1, $composer2);
    }

    /**
     * @dataProvider getAssetTypes
     *
     * @param string $type
     * @param string $filename
     */
    public function testPublicRepositoryWithFilesystemCache($type, $filename)
    {
        $repoUrl = 'https://bitbucket.org/composer-test/repo-name';
        $identifier = '92bebbfdcde75ef2368317830e54b605bc938123';
        $repoConfig = array(
            'url' => $repoUrl,
            'asset-type' => $type,
            'filename' => $filename,
        );
        $io = $this->getMockBuilder('Composer\IO\IOInterface')->getMock();
        $process = $this->getMockBuilder('Composer\Util\ProcessExecutor')->getMock();
        $process->expects(static::any())
            ->method('splitLines')
            ->willReturn(array())
        ;
        $process->expects(static::any())
            ->method('execute')
            ->willReturnCallback(function ($command, &$output = null) use ($identifier, $repoConfig) {
                if ($command === sprintf('hg cat -r %s %s', ProcessExecutor::escape($identifier), $repoConfig['filename'])) {
                    $output = '{"name": "foo"}';
                } elseif (false !== strpos($command, 'hg log')) {
                    $date = new \DateTime(null, new \DateTimeZone('UTC'));
                    $output = $date->format(\DateTime::RFC3339);
                }

                return 0;
            })
        ;

        /** @var IOInterface $io */
        /** @var ProcessExecutor $process */
        $driver1 = new HgDriver($repoConfig, $io, $this->config, $process, null);
        $driver2 = new HgDriver($repoConfig, $io, $this->config, $process, null);
        $driver1->initialize();
        $driver2->initialize();
        $composer1 = $driver1->getComposerInformation($identifier);
        $composer2 = $driver2->getComposerInformation($identifier);

        static::assertNotNull($composer1);
        static::assertNotNull($composer2);
        static::assertSame($composer1, $composer2);
    }

    /**
     * @param object $object
     * @param string $attribute
     * @param mixed  $value
     */
    protected function setAttribute($object, $attribute, $value)
    {
        $attr = new \ReflectionProperty($object, $attribute);
        $attr->setAccessible(true);
        $attr->setValue($object, $value);
    }
}
