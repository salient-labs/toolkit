<?php declare(strict_types=1);

if (\Composer\InstalledVersions::isInstalled('adodb/adodb-php')) {
    require \Composer\InstalledVersions::getInstallPath('adodb/adodb-php') . '/adodb-exceptions.inc.php';
}
