# CSPUWikiTests
Quiz addon for CSPUMediaWiki
## Instalation
- clone or download repo to `extensions` folder;
- put some settings in your `LocalSettings.php` file:
    - add following lines:
        `$egMWQuizzerAdmins = array('WikiSysop');
require_once('extensions/EasyTests/EasyTests.php');`

    - setup EasyTests namespace, adding following line: 
        `EasyTests::setupNamespace(<FreeNamespaceNumber>);`
