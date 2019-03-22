<?php

    namespace Bolt;

    use Composer\Config;
    use Composer\DependencyResolver\Operation\InstallOperation;
    use Composer\DependencyResolver\Operation\UpdateOperation;
    use Composer\Installer\InstallationManager;
    use Composer\Installer\PackageEvent;
    use Composer\Package\PackageInterface;
    use Composer\Package\RootPackageInterface;
    use Composer\Util\Filesystem;
    use Composer\Util\ProcessExecutor;

    /**
     * Class ComposerAssetInstaller
     *
     * @package Bolt
     */
    class ComposerAssetInstaller {
        /**
         * @type array
         */
        private static $defaultPathConfig = [
            'base-path'      => '{{COMPASS_PATH_PROJECT}}',
            'js-path'        => '{{COMPASS_PATH_JS}}',
            'css-path'       => '{{COMPASS_PATH_CSS}}',
            'sass-path'      => '{{COMPASS_PATH_SASS}}',
            'fonts-path'     => '{{COMPASS_PATH_FONTS}}',
            'image-path'     => '{{COMPASS_PATH_IMAGE}}',
            'lib-path'       => 'lib/{{LIBRARY_NAME}}',
            'js-lib-path'    => '{{JS_PATH}}/{{LIB_PATH}}',
            'css-lib-path'   => '{{CSS_PATH}}/{{LIB_PATH}}',
            'sass-lib-path'  => '{{SASS_PATH}}/{{LIB_PATH}}',
            'fonts-lib-path' => '{{FONTS_PATH}}/{{LIB_PATH}}',
            'image-lib-path' => '{{IMAGE_PATH}}/{{LIB_PATH}}',
        ];

        /**
         * @type array
         */
        private static $placeholderMap = [
            "{{COMPASS_PATH_JS}}"      => [ 'compassProperty' => 'javascripts_path' ],
            "{{COMPASS_PATH_CSS}}"     => [ 'compassProperty' => 'css_path' ],
            "{{COMPASS_PATH_SASS}}"    => [ 'compassProperty' => 'sass_path' ],
            "{{COMPASS_PATH_FONTS}}"   => [ 'compassProperty' => 'fonts_path' ],
            "{{COMPASS_PATH_IMAGE}}"   => [ 'compassProperty' => 'images_path' ],
            "{{COMPASS_PATH_PROJECT}}" => [ 'compassProperty' => 'project_path' ],
            "{{BASE_PATH}}"            => [ 'path' => 'base-path' ],
            "{{JS_PATH}}"              => [ 'path' => 'js-path' ],
            "{{CSS_PATH}}"             => [ 'path' => 'css-path' ],
            "{{SASS_PATH}}"            => [ 'path' => 'sass-path' ],
            "{{FONTS_PATH}}"           => [ 'path' => 'fonts-path' ],
            "{{IMAGE_PATH}}"           => [ 'path' => 'image-path' ],
            "{{LIB_PATH}}"             => [ 'path' => 'lib-path' ],
            "{{JS_LIB_PATH}}"          => [ 'path' => 'js-lib-path' ],
            "{{CSS_LIB_PATH}}"         => [ 'path' => 'css-lib-path' ],
            "{{SASS_LIB_PATH}}"        => [ 'path' => 'sass-lib-path' ],
            "{{FONTS_LIB_PATH}}"       => [ 'path' => 'fonts-lib-path' ],
            "{{IMAGE_LIB_PATH}}"       => [ 'path' => 'image-lib-path' ],
        ];

        /**
         * @param PackageEvent $event
         */
        public static function postPackageUpdate( PackageEvent $event ) {
            /** @type UpdateOperation $operation */
            $operation = $event->getOperation();
            /** @var PackageInterface $package */
            $package = $operation->getTargetPackage();

            self::installAssets( $event, $package );
        }

        /**
         * @param PackageEvent $event
         */
        public static function postPackageInstall( PackageEvent $event ) {
            /** @type InstallOperation $operation */
            $operation = $event->getOperation();
            /** @var PackageInterface $package */
            $package = $operation->getPackage();

            self::installAssets( $event, $package );
        }

        /**
         * @param PackageEvent     $event
         * @param PackageInterface $package
         */
        private static function installAssets( PackageEvent $event, PackageInterface $package ) {
            $composer = $event->getComposer();
            /** @type RootPackageInterface $rootPackage */
            $rootPackage = $composer->getPackage();
            $composerExtra = $rootPackage->getExtra();
            $pathConfig = self::$defaultPathConfig;

            if( !isset( $composerExtra[ 'asset-installer' ], $composerExtra[ 'asset-installer' ][ 'assets' ] ) ) {
                return;
            }
            $assetConfig = $composerExtra[ 'asset-installer' ][ 'assets' ];
            if( isset( $composerExtra[ 'asset-installer' ][ 'path-config' ] ) ) {
                $pathConfig = array_merge( $pathConfig, $composerExtra[ 'asset-installer' ][ 'path-config' ] );
            }
            /** @type InstallationManager $installationManager */
            $installationManager = $composer->getInstallationManager();

            $packageName = $package->getPrettyName();

            if( isset( $assetConfig[ $packageName ] ) ) {
                self::copyAssets(
                    $packageName,
                    $pathConfig,
                    $assetConfig[ $packageName ],
                    $installationManager->getInstallPath( $package )
                );
            }
        }

        /**
         * @param $packageName
         * @param $pathConfig
         * @param $assetConfig
         * @param $packageInstallPath
         *
         * @throws \Exception
         */
        private static function copyAssets( $packageName, $pathConfig, $assetConfig, $packageInstallPath ) {
            $processExecutor = new ProcessExecutor();
            $filesystemUtil = new Filesystem( $processExecutor );

            $resolvedPlaceholders = [];
            $resolvedPlaceholders[ '{{LIBRARY_NAME}}' ] = basename( $packageName );
            foreach( $assetConfig as $sourcePath => $targetPath ) {
                $sourcePath = $packageInstallPath . '/' . trim( $sourcePath, '/' );
                $targetPath = trim( $targetPath, '/' );
                $installPath = self::resolvePath(
                    $targetPath,
                    $pathConfig,
                    $processExecutor,
                    $resolvedPlaceholders
                );
                self::cleanupAndCopy( $sourcePath, $installPath, $filesystemUtil, $processExecutor );
            }
        }

        /**
         * @param $targetPath
         * @param $pathConfig
         * @param $processExecutor
         * @param $resolvedPlaceholders
         *
         * @return string
         */
        private static function resolvePath( $targetPath, $pathConfig, $processExecutor, &$resolvedPlaceholders ) {
            if( isset( $pathConfig[ $targetPath ] ) ) {
                $targetPath = $pathConfig[ $targetPath ];
            }
            $matches = [];
            preg_match_all( '/{{[^}]+}}/', $targetPath, $matches, PREG_PATTERN_ORDER );
            foreach( $matches[ 0 ] as $match ) {
                if( isset( $resolvedPlaceholders[ $match ] ) ) {
                    continue;
                }
                if( isset( self::$placeholderMap[ $match ] ) ) {
                    $placeholder = self::$placeholderMap[ $match ];
                    switch( key( $placeholder ) ) {
                        case 'compassProperty':
                            $replacement = self::getCompassProperty( $processExecutor, reset( $placeholder ) );
                            break;
                        case 'path':
                            $replacement = self::resolvePath(
                                reset( $placeholder ),
                                $pathConfig,
                                $processExecutor,
                                $resolvedPlaceholders
                            );
                            break;
                        default:
                            $replacement = '';
                    }
                    $resolvedPlaceholders[ $match ] = $replacement;
                }
            }

            foreach( $resolvedPlaceholders as $placeholder => $replacement ) {
                $targetPath = str_replace( $placeholder, $replacement, $targetPath );
            }

            return $targetPath;
        }

        /**
         * @param string          $sourcePath
         * @param Filesystem      $fileSystemUtil
         * @param string          $installPath
         * @param ProcessExecutor $processExecutor
         */
        private static function cleanupAndCopy(
            $sourcePath,
            $installPath,
            Filesystem $fileSystemUtil,
            ProcessExecutor $processExecutor
        ) {
            $cleanupPath = $installPath;
            $fileWildcard = '/*';
            if( is_file( $sourcePath ) ) {
                $fileWildcard = '';
                $cleanupPath .= basename( $sourcePath );
            }
            $fileSystemUtil->remove( $cleanupPath );
            $fileSystemUtil->ensureDirectoryExists( $installPath );
            if( defined( 'PHP_WINDOWS_VERSION_BUILD' ) ) {
                $sourcePath = str_replace( '/', '\\', $sourcePath );
                $installPath = str_replace( '/', '\\', $installPath );
                $processExecutor->execute(
                    "xcopy \"{$sourcePath}\" \"{$installPath}\" /E /I /Q /Y"
                );
            } else {
                $processExecutor->execute(
                    "cp -r {$sourcePath}{$fileWildcard} {$installPath}"
                );
            }
        }

        /**
         * @param ProcessExecutor $processExecutor
         * @param                 $property
         *
         * @return string
         */
        private static function getCompassProperty( ProcessExecutor $processExecutor, $property ) {
            $output = '';
            $processExecutor->execute( "compass config -p {$property}", $output );

            return trim( $output );
        }
    }