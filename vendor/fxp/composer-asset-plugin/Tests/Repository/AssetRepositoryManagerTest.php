<?php

/*
 * This file is part of the Fxp Composer Asset Plugin package.
 *
 * (c) François Pluchino <francois.pluchino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fxp\Composer\AssetPlugin\Tests\Repository;

use Composer\DependencyResolver\Pool;
use Composer\IO\IOInterface;
use Composer\Repository\RepositoryInterface;
use Composer\Repository\RepositoryManager;
use Fxp\Composer\AssetPlugin\Config\Config;
use Fxp\Composer\AssetPlugin\Repository\AssetRepositoryManager;
use Fxp\Composer\AssetPlugin\Repository\ResolutionManager;
use Fxp\Composer\AssetPlugin\Repository\VcsPackageFilter;

/**
 * Tests of Asset Repository Manager.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 *
 * @internal
 */
final class AssetRepositoryManagerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|RepositoryManager
     */
    protected $rm;

    /**
     * @var IOInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $io;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|VcsPackageFilter
     */
    protected $filter;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject|ResolutionManager
     */
    protected $resolutionManager;

    /**
     * @var AssetRepositoryManager
     */
    protected $assetRepositoryManager;

    protected function setUp()
    {
        $this->io = $this->getMockBuilder(IOInterface::class)->getMock();
        $this->rm = $this->getMockBuilder(RepositoryManager::class)->disableOriginalConstructor()->getMock();
        $this->config = new Config(array());
        $this->filter = $this->getMockBuilder(VcsPackageFilter::class)->disableOriginalConstructor()->getMock();

        $this->resolutionManager = $this->getMockBuilder(ResolutionManager::class)->getMock();
        $this->assetRepositoryManager = new AssetRepositoryManager($this->io, $this->rm, $this->config, $this->filter);
    }

    public function getDataForSolveResolutions()
    {
        return array(
            array(true),
            array(false),
        );
    }

    /**
     * @dataProvider getDataForSolveResolutions
     *
     * @param bool $withResolutionManager
     */
    public function testSolveResolutions($withResolutionManager)
    {
        $expected = array(
            'name' => 'foo/bar',
        );

        if ($withResolutionManager) {
            $this->assetRepositoryManager->setResolutionManager($this->resolutionManager);
            $this->resolutionManager->expects(static::once())
                ->method('solveResolutions')
                ->with($expected)
                ->willReturn($expected)
            ;
        } else {
            $this->resolutionManager->expects(static::never())
                ->method('solveResolutions')
            ;
        }

        $data = $this->assetRepositoryManager->solveResolutions($expected);

        static::assertSame($expected, $data);
    }

    public function testAddRepositoryInPool()
    {
        $repos = array(
            array(
                'name' => 'foo/bar',
                'type' => 'asset-vcs',
                'url' => 'https://github.com/helloguest/helloguest-ui-app.git',
            ),
        );

        $repoConfigExpected = array_merge($repos[0], array(
            'asset-repository-manager' => $this->assetRepositoryManager,
            'vcs-package-filter' => $this->filter,
        ));

        $repo = $this->getMockBuilder(RepositoryInterface::class)->getMock();

        $this->rm->expects(static::once())
            ->method('createRepository')
            ->with('asset-vcs', $repoConfigExpected)
            ->willReturn($repo)
        ;

        $this->assetRepositoryManager->addRepositories($repos);

        /** @var \PHPUnit_Framework_MockObject_MockObject|Pool $pool */
        $pool = $this->getMockBuilder(Pool::class)->disableOriginalConstructor()->getMock();
        $pool->expects(static::once())
            ->method('addRepository')
            ->with($repo)
        ;

        $this->assetRepositoryManager->setPool($pool);
    }

    public function testGetConfig()
    {
        static::assertSame($this->config, $this->assetRepositoryManager->getConfig());
    }
}
