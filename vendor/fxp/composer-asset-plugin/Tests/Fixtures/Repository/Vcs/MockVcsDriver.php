<?php

/*
 * This file is part of the Fxp Composer Asset Plugin package.
 *
 * (c) François Pluchino <francois.pluchino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fxp\Composer\AssetPlugin\Tests\Fixtures\Repository\Vcs;

use Composer\Config;
use Composer\IO\IOInterface;
use Composer\Repository\Vcs\VcsDriverInterface;

/**
 * Mock vcs driver.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 */
class MockVcsDriver implements VcsDriverInterface
{
    /**
     * @var bool
     */
    public static $supported = true;

    /**
     * @var mixed
     */
    public $contents;

    /**
     * {@inheritdoc}
     */
    public function initialize()
    {
        // no action
    }

    /**
     * {@inheritdoc}
     */
    public function getComposerInformation($identifier)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getRootIdentifier()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getBranches()
    {
        return array();
    }

    /**
     * {@inheritdoc}
     */
    public function getTags()
    {
        return array();
    }

    /**
     * {@inheritdoc}
     */
    public function getDist($identifier)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getSource($identifier)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getUrl()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function hasComposerFile($identifier)
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function cleanup()
    {
        // no action
    }

    /**
     * {@inheritdoc}
     */
    public static function supports(IOInterface $io, Config $config, $url, $deep = false)
    {
        return static::$supported;
    }

    /**
     * {@inheritdoc}
     */
    public function getFileContent($file, $identifier)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getChangeDate($identifier)
    {
        return new \DateTime();
    }

    /**
     * @return mixed
     */
    protected function getContents()
    {
        return $this->contents;
    }
}
