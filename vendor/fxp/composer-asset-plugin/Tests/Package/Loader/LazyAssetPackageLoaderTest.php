<?php

/*
 * This file is part of the Fxp Composer Asset Plugin package.
 *
 * (c) François Pluchino <francois.pluchino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fxp\Composer\AssetPlugin\Tests\Package\Loader;

use Composer\Downloader\TransportException;
use Composer\Package\CompletePackageInterface;
use Composer\Package\Loader\LoaderInterface;
use Composer\Repository\Vcs\VcsDriverInterface;
use Fxp\Composer\AssetPlugin\Converter\PackageConverterInterface;
use Fxp\Composer\AssetPlugin\Converter\VersionConverterInterface;
use Fxp\Composer\AssetPlugin\Package\LazyPackageInterface;
use Fxp\Composer\AssetPlugin\Package\Loader\LazyAssetPackageLoader;
use Fxp\Composer\AssetPlugin\Repository\AssetRepositoryManager;
use Fxp\Composer\AssetPlugin\Tests\Fixtures\IO\MockIO;
use Fxp\Composer\AssetPlugin\Type\AssetTypeInterface;

/**
 * Tests of lazy asset package loader.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 *
 * @internal
 */
final class LazyAssetPackageLoaderTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var LazyAssetPackageLoader
     */
    protected $lazyLoader;

    /**
     * @var LazyPackageInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $lazyPackage;

    /**
     * @var AssetTypeInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $assetType;

    /**
     * @var LoaderInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $loader;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|VcsDriverInterface
     */
    protected $driver;

    /**
     * @var MockIO
     */
    protected $io;

    /**
     * @var AssetRepositoryManager|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $assetRepositoryManager;

    protected function setUp()
    {
        $this->lazyPackage = $this->getMockBuilder(LazyPackageInterface::class)->getMock();
        $this->assetType = $this->getMockBuilder(AssetTypeInterface::class)->getMock();
        $this->loader = $this->getMockBuilder(LoaderInterface::class)->getMock();
        $this->driver = $this->getMockBuilder(VcsDriverInterface::class)->getMock();
        $this->assetRepositoryManager = $this->getMockBuilder(AssetRepositoryManager::class)
            ->disableOriginalConstructor()->getMock();

        $this->assetRepositoryManager->expects(static::any())
            ->method('solveResolutions')
            ->willReturnCallback(function ($value) {
                return $value;
            })
        ;

        $this->lazyPackage
            ->expects(static::any())
            ->method('getName')
            ->willReturn('PACKAGE_NAME')
        ;
        $this->lazyPackage
            ->expects(static::any())
            ->method('getUniqueName')
            ->willReturn('PACKAGE_NAME-1.0.0.0')
        ;
        $this->lazyPackage
            ->expects(static::any())
            ->method('getPrettyVersion')
            ->willReturn('1.0')
        ;
        $this->lazyPackage
            ->expects(static::any())
            ->method('getVersion')
            ->willReturn('1.0.0.0')
        ;

        $versionConverter = $this->getMockBuilder(VersionConverterInterface::class)->getMock();
        $versionConverter->expects(static::any())
            ->method('convertVersion')
            ->willReturn('VERSION_CONVERTED')
        ;
        $versionConverter->expects(static::any())
            ->method('convertRange')
            ->willReturnCallback(function ($value) {
                return $value;
            })
        ;
        $packageConverter = $this->getMockBuilder(PackageConverterInterface::class)->getMock();
        /** @var LazyPackageInterface $lasyPackage */
        $lasyPackage = $this->lazyPackage;
        $packageConverter->expects(static::any())
            ->method('convert')
            ->willReturnCallback(function ($value) use ($lasyPackage) {
                $value['version'] = $lasyPackage->getPrettyVersion();
                $value['version_normalized'] = $lasyPackage->getVersion();

                return $value;
            })
        ;
        $this->assetType->expects(static::any())
            ->method('getComposerVendorName')
            ->willReturn('ASSET')
        ;
        $this->assetType->expects(static::any())
            ->method('getComposerType')
            ->willReturn('ASSET_TYPE')
        ;
        $this->assetType->expects(static::any())
            ->method('getFilename')
            ->willReturn('ASSET.json')
        ;
        $this->assetType->expects(static::any())
            ->method('getVersionConverter')
            ->willReturn($versionConverter)
        ;
        $this->assetType->expects(static::any())
            ->method('getPackageConverter')
            ->willReturn($packageConverter)
        ;

        $this->driver
            ->expects(static::any())
            ->method('getDist')
            ->willReturnCallback(function ($value) {
                return array(
                    'type' => 'vcs',
                    'url' => 'http://foobar.tld/dist/'.$value,
                );
            })
        ;
        $this->driver
            ->expects(static::any())
            ->method('getSource')
            ->willReturnCallback(function ($value) {
                return array(
                    'type' => 'vcs',
                    'url' => 'http://foobar.tld/source/'.$value,
                );
            })
        ;
    }

    protected function tearDown()
    {
        $this->lazyPackage = null;
        $this->assetType = null;
        $this->loader = null;
        $this->driver = null;
        $this->io = null;
        $this->assetRepositoryManager = null;
        $this->lazyLoader = null;
    }

    /**
     * @expectedException \Fxp\Composer\AssetPlugin\Exception\InvalidArgumentException
     * @expectedExceptionMessage The "assetType" property must be defined
     */
    public function testMissingAssetType()
    {
        $loader = $this->createLazyLoader('TYPE');
        $loader->load($this->lazyPackage);
    }

    /**
     * @expectedException \Fxp\Composer\AssetPlugin\Exception\InvalidArgumentException
     * @expectedExceptionMessage The "loader" property must be defined
     */
    public function testMissingLoader()
    {
        /** @var AssetTypeInterface $assetType */
        $assetType = $this->assetType;
        $loader = $this->createLazyLoader('TYPE');
        $loader->setAssetType($assetType);
        $loader->load($this->lazyPackage);
    }

    /**
     * @expectedException \Fxp\Composer\AssetPlugin\Exception\InvalidArgumentException
     * @expectedExceptionMessage The "driver" property must be defined
     */
    public function testMissingDriver()
    {
        /** @var AssetTypeInterface $assetType */
        $assetType = $this->assetType;
        /** @var LoaderInterface $cLoader */
        $cLoader = $this->loader;
        /** @var LazyPackageInterface $lazyPackage */
        $lazyPackage = $this->lazyPackage;
        $loader = $this->createLazyLoader('TYPE');
        $loader->setAssetType($assetType);
        $loader->setLoader($cLoader);
        $loader->load($lazyPackage);
    }

    /**
     * @expectedException \Fxp\Composer\AssetPlugin\Exception\InvalidArgumentException
     * @expectedExceptionMessage The "io" property must be defined
     */
    public function testMissingIo()
    {
        /** @var AssetTypeInterface $assetType */
        $assetType = $this->assetType;
        /** @var LoaderInterface $cLoader */
        $cLoader = $this->loader;
        /** @var VcsDriverInterface $driver */
        $driver = $this->driver;
        $loader = $this->createLazyLoader('TYPE');
        $loader->setAssetType($assetType);
        $loader->setLoader($cLoader);
        $loader->setDriver($driver);
        $loader->load($this->lazyPackage);
    }

    public function getConfigIo()
    {
        return array(
            array(false),
            array(true),
        );
    }

    /**
     * @param $verbose
     *
     * @dataProvider getConfigIo
     */
    public function testWithoutJsonFile($verbose)
    {
        /** @var \PHPUnit_Framework_MockObject_MockObject $driver */
        $driver = $this->driver;
        $driver
            ->expects(static::any())
            ->method('getComposerInformation')
            ->willReturn(false)
        ;

        /** @var \PHPUnit_Framework_MockObject_MockObject $loader */
        $loader = $this->loader;
        $loader
            ->expects(static::any())
            ->method('load')
            ->willReturn(false)
        ;

        $this->lazyLoader = $this->createLazyLoaderConfigured('TYPE', $verbose);
        $package = $this->lazyLoader->load($this->lazyPackage);

        static::assertFalse($package);

        $filename = $this->assetType->getFilename();
        $validOutput = array('');

        if ($verbose) {
            $validOutput = array(
                'Reading '.$filename.' of <info>'.$this->lazyPackage->getName().'</info> (<comment>'.$this->lazyPackage->getPrettyVersion().'</comment>)',
                'Importing empty TYPE '.$this->lazyPackage->getPrettyVersion().' ('.$this->lazyPackage->getVersion().')',
                '',
            );
        }
        static::assertSame($validOutput, $this->io->getTraces());

        $packageCache = $this->lazyLoader->load($this->lazyPackage);
        static::assertFalse($packageCache);
        static::assertSame($validOutput, $this->io->getTraces());
    }

    /**
     * @param $verbose
     *
     * @dataProvider getConfigIo
     */
    public function testWithJsonFile($verbose)
    {
        $arrayPackage = array(
            'name' => 'PACKAGE_NAME',
            'version' => '1.0',
        );

        $realPackage = $this->getMockBuilder(CompletePackageInterface::class)->getMock();
        $realPackage
            ->expects(static::any())
            ->method('getName')
            ->willReturn('PACKAGE_NAME')
        ;
        $realPackage
            ->expects(static::any())
            ->method('getUniqueName')
            ->willReturn('PACKAGE_NAME-1.0.0.0')
        ;
        $realPackage
            ->expects(static::any())
            ->method('getPrettyVersion')
            ->willReturn('1.0')
        ;
        $realPackage
            ->expects(static::any())
            ->method('getVersion')
            ->willReturn('1.0.0.0')
        ;

        /** @var \PHPUnit_Framework_MockObject_MockObject $driver */
        $driver = $this->driver;
        $driver
            ->expects(static::any())
            ->method('getComposerInformation')
            ->willReturn($arrayPackage)
        ;

        /** @var \PHPUnit_Framework_MockObject_MockObject $loader */
        $loader = $this->loader;
        $loader
            ->expects(static::any())
            ->method('load')
            ->willReturn($realPackage)
        ;

        $this->lazyLoader = $this->createLazyLoaderConfigured('TYPE', $verbose);
        $package = $this->lazyLoader->load($this->lazyPackage);

        $filename = $this->assetType->getFilename();
        $validOutput = array('');

        if ($verbose) {
            $validOutput = array(
                'Reading '.$filename.' of <info>'.$this->lazyPackage->getName().'</info> (<comment>'.$this->lazyPackage->getPrettyVersion().'</comment>)',
                'Importing TYPE'.' '.$this->lazyPackage->getPrettyVersion().' ('.$this->lazyPackage->getVersion().')',
                '',
            );
        }

        static::assertInstanceOf('Composer\Package\CompletePackageInterface', $package);
        static::assertSame($validOutput, $this->io->getTraces());

        $packageCache = $this->lazyLoader->load($this->lazyPackage);
        static::assertInstanceOf('Composer\Package\CompletePackageInterface', $packageCache);
        static::assertSame($package, $packageCache);
        static::assertSame($validOutput, $this->io->getTraces());
    }

    public function getConfigIoForException()
    {
        return array(
            array('tag', false, 'Exception', '<warning>Skipped tag 1.0, MESSAGE</warning>'),
            array('tag', true, 'Exception', '<warning>Skipped tag 1.0, MESSAGE</warning>'),
            array('branch', false, 'Exception', '<error>Skipped branch 1.0, MESSAGE</error>'),
            array('branch', true, 'Exception', '<error>Skipped branch 1.0, MESSAGE</error>'),
            array('tag', false, TransportException::class, '<warning>Skipped tag 1.0, no ASSET.json file was found</warning>'),
            array('tag', true, TransportException::class, '<warning>Skipped tag 1.0, no ASSET.json file was found</warning>'),
            array('branch', false, TransportException::class, '<error>Skipped branch 1.0, no ASSET.json file was found</error>'),
            array('branch', true, TransportException::class, '<error>Skipped branch 1.0, no ASSET.json file was found</error>'),
        );
    }

    /**
     * @param string $type
     * @param bool   $verbose
     * @param string $exceptionClass
     * @param string $validTrace
     *
     * @dataProvider getConfigIoForException
     */
    public function testTagWithTransportException($type, $verbose, $exceptionClass, $validTrace)
    {
        /** @var \PHPUnit_Framework_MockObject_MockObject $loader */
        $loader = $this->loader;
        $loader
            ->expects(static::any())
            ->method('load')
            ->will(static::throwException(new $exceptionClass('MESSAGE')))
        ;

        $this->lazyLoader = $this->createLazyLoaderConfigured($type, $verbose);
        $package = $this->lazyLoader->load($this->lazyPackage);

        static::assertFalse($package);

        $filename = $this->assetType->getFilename();
        $validOutput = array('');

        if ($verbose) {
            $validOutput = array(
                'Reading '.$filename.' of <info>'.$this->lazyPackage->getName().'</info> (<comment>'.$this->lazyPackage->getPrettyVersion().'</comment>)',
                'Importing empty '.$type.' '.$this->lazyPackage->getPrettyVersion().' ('.$this->lazyPackage->getVersion().')',
                $validTrace,
                '',
            );
        }
        static::assertSame($validOutput, $this->io->getTraces());

        $packageCache = $this->lazyLoader->load($this->lazyPackage);
        static::assertFalse($packageCache);
        static::assertSame($validOutput, $this->io->getTraces());
    }

    /**
     * Creates the lazy asset package loader with full configuration.
     *
     * @param string $type
     * @param bool   $verbose
     *
     * @return LazyAssetPackageLoader
     */
    protected function createLazyLoaderConfigured($type, $verbose = false)
    {
        $this->io = new MockIO($verbose);

        $cLoader = $this->loader;
        $loader = $this->createLazyLoader($type);
        $loader->setAssetType($this->assetType);
        $loader->setLoader($cLoader);
        $loader->setDriver($this->driver);
        $loader->setIO($this->io);
        $loader->setAssetRepositoryManager($this->assetRepositoryManager);

        return $loader;
    }

    /**
     * Creates the lazy asset package loader.
     *
     * @param string $type
     *
     * @return LazyAssetPackageLoader
     */
    protected function createLazyLoader($type)
    {
        $data = array(
            'foo' => 'bar',
            'bar' => 'foo',
        );

        return new LazyAssetPackageLoader($type, 'IDENTIFIER', $data);
    }
}
