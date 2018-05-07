<?php

if (!defined('MEDIAWIKI'))
    die();
$dir = dirname(__FILE__) . '/';

/* DEFAULT SETTINGS GO HERE */

// If set, this value is treated as IntraACL/HaloACL "Test admin" group name
// This must be a complete name, with "Group/" prefix
// See http://wiki.4intra.net/IntraACL for extension details
$egEasyTestsIntraACLAdminGroup = false;

// If set to a list of usernames, users with these names are also treated as test administrators
$egEasyTestsAdmins = array('WikiSysop');

// Percent of correct question completion to consider it "easy" (green hint)
$egEasyTestsEasyQuestionCompl = 80;

// Percent of correct question completion to consider it "hard" (red hint)
$egEasyTestsHardQuestionCompl = 30;

// Content language used to parse tests, in addition to English, which is always used
$egEasyTestsContLang = false;

/* END DEFAULT SETTINGS */

$wgExtensionCredits['specialpage'][] = array(
    'name' => 'EasyTests',
    'author' => 'Katherine Fomenko',
    'version' => '1.0.0',
    'description' => 'Quiz addon for CSPUMediaWiki',
);

$wgExtensionMessagesFiles['EasyTests'] = $dir . '/i18n/EasyTests.i18n.php';
$wgSpecialPages['EasyTests'] = 'EasyTestsPage';
$wgAutoloadClasses += array(
    'EasyTestsPage' => $dir . 'EasyTestsPage.php',
    'EasyTestsUpdater' => $dir . 'EasyTestsUpdater.php',
    'DOMParseUtils' => $dir . '/utilites/DOMParseUtils.php',
);
$wgHooks['LoadExtensionSchemaUpdates'][] = 'EasyTests::LoadExtensionSchemaUpdates';
$wgHooks['ArticleSaveComplete'][] = 'EasyTests::ArticleSaveComplete';
$wgHooks['ArticlePurge'][] = 'EasyTests::ArticlePurge';
$wgHooks['ArticleViewHeader'][] = 'EasyTests::ArticleViewHeader';
$wgHooks['DoEditSectionLink'][] = 'EasyTests::DoEditSectionLink';
$wgExtensionFunctions[] = 'EasyTests::init';

//$wgGroupPermissions['secretquiz']['secretquiz'] = true;

class EasyTests
{
    static $disableQuestionInfo = false;
    static $updated = array();

    // Returns true if current user is a test administrator
    // and has all privileges for test system
    static function isTestAdmin()
    {
        global $wgUser, $egEasyTestsAdmins, $egEasyTestsIntraACLAdminGroup;
        if (!$wgUser->getId())
            return false;
        if (in_array('bureaucrat', $wgUser->getGroups()))
            return true;
        if ($egEasyTestsAdmins && in_array($wgUser->getName(), $egEasyTestsAdmins))
            return true;
        if ($egEasyTestsIntraACLAdminGroup && class_exists('HACLGroup')) {
            $intraacl_group = HACLGroup::newFromName($egEasyTestsIntraACLAdminGroup, false);
            if ($intraacl_group && $intraacl_group->hasUserMember($wgUser, true))
                return true;
        }
        return false;
    }

    // Returns true if Title $t corresponds to an article which defines a quiz
    static function isQuiz(Title $t)
    {
        return $t && $t->getNamespace() == NS_EATEST && strpos($t->getText(), '/') === false;
    }

    // Setup MediaWiki namespace for Quizzer
    static function setupNamespace($index)
    {
        $index = $index & ~1;
        define('NS_EATEST', $index);
        define('NS_EATEST_TALK', $index + 1);
    }

    // Initialize extension
    static function init()
    {
        if (!defined('NS_EATEST'))
            die("Please add the following line:\nEasyTests::setupNamespace(XXX);\nto your LocalSettings.php, where XXX is an available integer index for Quiz namespace");
        global $wgExtraNamespaces, $wgCanonicalNamespaceNames, $wgNamespaceAliases, $wgParser;
        global $wgVersion, $wgHooks;
        $wgExtraNamespaces[NS_EATEST] = $wgCanonicalNamespaceNames[NS_EATEST] = 'ETest';
        $wgExtraNamespaces[NS_EATEST_TALK] = $wgCanonicalNamespaceNames[NS_EATEST_TALK] = 'ETest_talk';
        $wgNamespaceAliases['ETest'] = NS_EATEST;
        $wgNamespaceAliases['ETest_talk'] = NS_EATEST_TALK;
        if ($wgVersion < '1.14')
            $wgHooks['NewRevisionFromEditComplete'][] = 'EasyTests::NewRevisionFromEditComplete';
        else
            $wgHooks['ArticleEditUpdates'][] = 'EasyTests::ArticleEditUpdates';
    }

    // Hook for maintenance/update.php
    static function LoadExtensionSchemaUpdates($updater)
    {
        global $wgDBtype;
        $dir = dirname(__FILE__);
        if ($updater) {
            $updater->addExtensionUpdate(array('addTable', 'et_test', $dir . '/easytests-tables.' . $wgDBtype . '.sql', true));
        } else {
            global $wgExtNewTables, $wgExtNewFields;
            $wgExtNewTables[] = array('et_test', $dir . '/easytests-tables.' . $wgDBtype . '.sql');
        }
        return true;
    }

    // Quiz update hook, updates the quiz on every save, even when no new revision was created
    static function ArticleSaveComplete($article, $user, $text, $summary, $minoredit)
    {
        global $wgVersion;
        if (isset(self::$updated[$article->getId()]))
            return true;
        if ($article->getTitle()->getNamespace() == NS_EATEST) {
            if (self::isQuiz($article->getTitle())) {
                // Reload new revision id
                $article = new Article($article->getTitle());
                EasyTestsUpdater::updateQuiz($article, $text);
            }
            // Update quizzes which include updated article
            foreach (self::getQuizLinksTo($article->getTitle()) as $template) {
                $article = new Article($template);
                EasyTestsUpdater::updateQuiz($article, $wgVersion < '1.18' ? $article->getContent() : $article->getRawText());
            }
            self::$updated[$article->getId()] = true;
        }
        return true;
    }

    // Another quiz update hook, for action=purge
    static function ArticlePurge($article)
    {
        global $wgVersion;
        self::ArticleSaveComplete($article, NULL, $wgVersion < '1.18' ? $article->getContent() : $article->getRawText(), NULL, NULL);
        return true;
    }

    // Another quiz update hook, for MW < 1.14, called when a new revision is created
    static function NewRevisionFromEditComplete($article, $rev, $baseID, $user)
    {
        self::ArticlePurge($article);
        return true;
    }

    // Another quiz update hook, for MW >= 1.14, called when a new revision is created
    static function ArticleEditUpdates($article, $editInfo, $changed)
    {
        self::ArticleSaveComplete($article, NULL, $editInfo->newText, NULL, NULL);
        return true;
    }

    // Quiz display hook
    static function ArticleViewHeader(&$article, &$outputDone, &$pcache)
    {
        global $wgOut;
        if (self::isQuiz($t = $article->getTitle()))
            EasyTestsPage::quizArticleInfo($t);
        return true;
    }

    // Hook for displaying statistics near question titles
    static function DoEditSectionLink($skin, $nt, $section, $tooltip, &$result)
    {
        if (!self::$disableQuestionInfo && $nt->getNamespace() == NS_EATEST)
            EasyTestsPage::quizQuestionInfo($nt, $section, $result);
        return true;
    }

    // Get quizzes which include given title
    // Now does not return quizzes which include given title through
    // an article which is not inside Quiz namespace for performance reasons
    static function getQuizLinksTo($title)
    {
        $id_seen = array();
        $quiz_links = array();
        $dbr = wfGetDB(DB_SLAVE);

        $where = array(
            'tl_namespace' => $title->getNamespace(),
            'tl_title' => $title->getDBkey(),
        );

        do {
            $res = $dbr->select(
                array('page', 'templatelinks'),
                array('page_namespace', 'page_title', 'page_id', 'page_len', 'page_is_redirect'),
                $where + array(
                    'page_namespace' => NS_EATEST,
                    'page_is_redirect' => 0,
                    'tl_from=page_id',
                ),
                __METHOD__
            );

            $where['tl_namespace'] = NS_EATEST;
            $where['tl_title'] = array();

            if (!$dbr->numRows($res))
                break;

            foreach ($res as $row) {
                if ($titleObj = Title::makeTitle($row->page_namespace, $row->page_title)) {
                    if ($titleObj->getNamespace() == NS_EATEST && !$id_seen[$row->page_id]) {
                        // Make closure only inside NS_EATEST
                        $where['tl_title'][] = $titleObj->getDBkey();
                        $id_seen[$row->page_id] = 1;
                    }
                    $quiz_links[] = $titleObj;
                }
            }

            $dbr->freeResult($res);
        } while (count($where['tl_title']));

        return $quiz_links;
    }
}
