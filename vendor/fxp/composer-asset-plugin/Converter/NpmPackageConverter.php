<?php

/*
 * This file is part of the Fxp Composer Asset Plugin package.
 *
 * (c) François Pluchino <francois.pluchino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Fxp\Composer\AssetPlugin\Converter;

/**
 * Converter for NPM package to composer package.
 *
 * @author François Pluchino <francois.pluchino@gmail.com>
 */
class NpmPackageConverter extends AbstractPackageConverter
{
    /**
     * {@inheritdoc}
     */
    protected function getMapKeys()
    {
        $assetType = $this->assetType;

        return array(
            'name' => array('name', function ($value) use ($assetType) {
                return $assetType->formatComposerName(NpmPackageUtil::convertName($value));
            }),
            'type' => array('type', function () use ($assetType) {
                return $assetType->getComposerType();
            }),
            'version' => array('version', function ($value) use ($assetType) {
                return $assetType->getVersionConverter()->convertVersion($value);
            }),
            'version_normalized' => 'version_normalized',
            'description' => 'description',
            'keywords' => 'keywords',
            'homepage' => 'homepage',
            'license' => array('license', function ($value) {
                return NpmPackageUtil::convertLicenses($value);
            }),
            'time' => 'time',
            'author' => array('authors', function ($value) {
                return NpmPackageUtil::convertAuthor($value);
            }),
            'contributors' => array('authors', function ($value, $prevValue) {
                return NpmPackageUtil::convertContributors($value, $prevValue);
            }),
            'bin' => array('bin', function ($value) {
                return (array) $value;
            }),
            'dist' => array('dist', function ($value) {
                return NpmPackageUtil::convertDist($value);
            }),
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function getMapExtras()
    {
        return array(
            'bugs' => 'npm-asset-bugs',
            'files' => 'npm-asset-files',
            'main' => 'npm-asset-main',
            'man' => 'npm-asset-man',
            'directories' => 'npm-asset-directories',
            'repository' => 'npm-asset-repository',
            'scripts' => 'npm-asset-scripts',
            'config' => 'npm-asset-config',
            'bundledDependencies' => 'npm-asset-bundled-dependencies',
            'optionalDependencies' => 'npm-asset-optional-dependencies',
            'engines' => 'npm-asset-engines',
            'engineStrict' => 'npm-asset-engine-strict',
            'os' => 'npm-asset-os',
            'cpu' => 'npm-asset-cpu',
            'preferGlobal' => 'npm-asset-prefer-global',
            'private' => 'npm-asset-private',
            'publishConfig' => 'npm-asset-publish-config',
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function convertDependency($dependency, $version, array &$vcsRepos, array $composer)
    {
        $dependency = NpmPackageUtil::convertName($dependency);

        return parent::convertDependency($dependency, $version, $vcsRepos, $composer);
    }
}
