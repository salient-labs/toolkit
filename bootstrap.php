<?php

if (\Composer\InstalledVersions::isInstalled("adodb/adodb-php"))
{
    require \Composer\InstalledVersions::getInstallPath("adodb/adodb-php") . "/adodb-exceptions.inc.php";
}
