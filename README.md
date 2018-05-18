# CUSPUWikiTests
Quiz addon for CUSPU MediaWiki
## Instalation
* clone or download repo to `extensions` folder;
* make some settings in your `LocalSettings.php` file:
    * add following lines:
        `$egMWQuizzerAdmins = array('WikiSysop');
require_once('extensions/EasyTests/EasyTests.php');`
    * setup EasyTests namespace, adding following line: 
        `EasyTests::setupNamespace(<FreeNamespaceNumber>);`
* run `php maintenance/update.php`
##Usage