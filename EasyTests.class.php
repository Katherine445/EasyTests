<?php

require_once dirname(__FILE__) . '/includes/urandom.php';

class EasyTestsPage extends SpecialPage
{
    const DEFAULT_OK_PERCENT = 80;

    static $modes = array(
        'show' => 1,
        'check' => 1,
        'print' => 1,
        'review' => 1,
        'qr' => 1,
        'getticket' => 1,
    );

    static $questionInfoCache = array();

    static $is_adm = NULL;

    /**
     * Methods used in hooks outside Special:EasyTests
     */

    /**
     * Constructor
     */
    function __construct()
    {
        global $IP, $wgScriptPath, $wgUser, $wgParser, $wgEmergencyContact;
        parent::__construct('EasyTests');
    }

    /**
     * Display parse log and quiz actions for parsed quiz article
     *
     */
    static function quizArticleInfo($test_title)
    {
        global $wgOut, $wgScriptPath;
        $wgOut->addExtensionStyle("$wgScriptPath/extensions/" . basename(dirname(__FILE__)) . "/css/easytest-page.css");
        /* Load the test without questions */
        $quiz = self::loadTest(array('name' => $test_title), NULL, true);
        if (!$quiz)
            return;
        $s = Title::newFromText('Special:EasyTests');
        $actions = array(
            'try' => $s->getFullUrl(array('id' => $quiz['test_id']) + (array())),
            'print' => $s->getFullUrl(array('id' => $quiz['test_id'], 'mode' => 'print')),
        );
        $wgOut->addHTML(wfMsg('easytests-actions', $quiz['test_name'], $actions['try'], $actions['print']));
        /* Display log */
        $log = $quiz['test_log'];
        if ($log) {
            $html = '';
            $a = self::xelement('a', array(
                'href' => 'javascript:void(0)',
                'onclick' => "document.getElementById('easytests-parselog').style.display='';document.getElementById('easytests-show-parselog').style.display='none'",
            ), wfMsg('easytests-show-parselog'));
            $html .= self::xelement('p', array('id' => 'easytests-show-parselog'), $a);
            $log = explode("\n", $log);
            foreach ($log as &$s) {
                if (preg_match('/^\s*\[([^\]]*)\]\s*/s', $s, $m)) {
                    $s = substr($s, strlen($m[0]));
                    if (mb_strlen($s) > 120)
                        $s = mb_substr($s, 0, 117) . '...';
                    $s = str_repeat(' ', 5 - strlen($m[1])) . self::xelement('span', array('class' => 'easytests-log-' . strtolower($m[1])), '[' . $m[1] . ']') . ' ' . $s;
                }
            }
            $log = self::xelement('pre', NULL, implode("\n", $log));
            $a = self::xelement('a', array(
                'href' => 'javascript:void(0)',
                'onclick' => "document.getElementById('easytests-parselog').style.display='none';document.getElementById('easytests-show-parselog').style.display=''",
            ), wfMsg('easytests-hide-parselog'));
            $log = self::xelement('p', array('id' => 'easytests-hide-parselog'), $a) . $log;
            $html .= self::xelement('div', array('id' => 'easytests-parselog', 'style' => 'display: none'), $log);
            $wgOut->addHTML($html);
        }
    }

    /**
     * Load a test from database. Optionally shuffle/limit questions and answers,
     * compute variant ID (sequence hash) and scores.
     * $cond = array('id' => int $testId)
     * or $cond = array('name' => string $testName)
     * or $cond = array('name' => Title $testTitle)
     */
    static function loadTest($cond, $variant = NULL, $without_questions = false)
    {
        global $wgOut;
        $dbr = wfGetDB(DB_SLAVE);

        if (!empty($cond['id']))
            $where = array('test_id' => $cond['id']);
        elseif (!empty($cond['name'])) {
            if ($cond['name'] instanceof Title)
                $cond['name'] = $cond['name']->getText();
            else
                $cond['name'] = str_replace('_', ' ', $cond['name']);
            $where = array('test_page_title' => $cond['name']);
        }
        $result = $dbr->select('et_test', '*', $where, __METHOD__);
        $test = (array)$dbr->fetchObject($result);
        $dbr->freeResult($result);

        if (!$test)
            return NULL;

        $id = $test['test_id'];

        // decode entities inside test_name as it is used inside HTML <title>
        $test['test_name'] = html_entity_decode($test['test_name']);

        // default OK%
        if (!isset($test['ok_percent']) || $test['ok_percent'] <= 0)
            $test['ok_percent'] = self::DEFAULT_OK_PERCENT;

        // do not load questions if $without_questions == true
        if ($without_questions)
            return $test;

        if ($variant) {
            $variant = @unserialize($variant);
            if (!is_array($variant))
                $variant = NULL;
            else {
                $qhashes = array();
                foreach ($variant as $question)
                    $qhashes[] = $question[0];
            }
        }

        $fields = 'et_question.*, IFNULL(COUNT(cs_correct),0) tries, IFNULL(SUM(cs_correct),0) correct_tries';
        $tables = array('et_question', 'et_choice_stats', 'et_question_test');
        $where = array();
        $options = array('GROUP BY' => 'qn_hash', 'ORDER BY' => 'qt_num');
        $joins = array(
            'et_choice_stats' => array('LEFT JOIN', array('cs_question_hash=qn_hash')),
            'et_question_test' => array('INNER JOIN', array('qt_question_hash=qn_hash', 'qt_test_id' => $id)),
        );

        if ($variant) {
            /* Select questions with known hashes for loading a specific variant.
               This is needed because quiz set of questions can change over time,
               but we want to always display the known variant. */
            $where['qn_hash'] = $qhashes;
            $joins['et_question_test'][0] = 'LEFT JOIN';
        }

        /* Read questions: */
        $result = $dbr->select($tables, $fields, $where, __METHOD__, $options, $joins);
        if ($dbr->numRows($result) <= 0)
            return NULL;

        $rows = array();
        while ($question = $dbr->fetchObject($result)) {
            $question = (array)$question;
            if (!$question['correct_tries'])
                $question['correct_tries'] = 0;
            if (!$question['tries'])
                $question['tries'] = 0;

            if (!$variant && $test['test_autofilter_min_tries'] > 0 &&
                $question['tries'] >= $test['test_autofilter_min_tries'] &&
                $question['correct_tries'] / $question['tries'] >= $test['test_autofilter_success_percent'] / 100.0
            ) {
                /* Statistics tells us this question is too simple, skip it */
                wfDebug(__CLASS__ . ': Skipping ' . $question['qn_hash'] . ', because correct percent = ' . $question['correct_tries'] . '/' . $question['tries'] . ' >= ' . $test['test_autofilter_success_percent'] . "%\n");
                continue;
            }
            $question['choices'] = array();
            $question['correct_count'] = 0;
            $rows[$question['qn_hash']] = $question;
        }

        /* Optionally shuffle and limit questions */
        if (!$variant && ($test['test_shuffle_questions'] || $test['test_limit_questions'])) {
            $new = $rows;
            if ($test['test_shuffle_questions'])
                shuffle($new);
            if ($test['test_limit_questions'])
                array_splice($new, $test['test_limit_questions']);
            $rows = array();
            foreach ($new as $question)
                $rows[$question['qn_hash']] = $question;
        } elseif ($variant) {
            $new = array();
            foreach ($variant as $question) {
                if ($rows[$question[0]]) {
                    $rows[$question[0]]['ch_order'] = $question[1];
                    $new[$question[0]] = &$rows[$question[0]];
                }
            }
            $rows = $new;
        }

        /* Read choices: */
        if ($rows) {
            $result = $dbr->select(
                'et_choice', '*', array('ch_question_hash' => array_keys($rows)),
                __METHOD__, array('ORDER BY' => 'ch_question_hash, ch_num')
            );
            $question = NULL;
            while ($choice = $dbr->fetchObject($result)) {
                $choice = (array)$choice;
                if (!$question) {
                    $question = &$rows[$choice['ch_question_hash']];
                } elseif ($question['qn_hash'] != $choice['ch_question_hash']) {
                    if (!self::finalizeQuestionRow($question, $variant && true, $test['test_shuffle_choices'])) {
                        unset($rows[$question['qn_hash']]);
                    }
                    $question = &$rows[$choice['ch_question_hash']];
                }
                $question['choiceByNum'][$choice['ch_num']] = $choice;
                $question['choices'][] = &$question['choiceByNum'][$choice['ch_num']];
                if ($choice['ch_correct']) {
                    $question['correct_count']++;
                    $question['correct_choices'][] = &$question['choiceByNum'][$choice['ch_num']];
                }
            }
            if (!self::finalizeQuestionRow($question, $variant && true, $test['test_shuffle_choices']))
                unset($rows[$question['qn_hash']]);
            unset($question);
            $dbr->freeResult($result);
        }

        /* Finally, build question array for the test */
        $test['questions'] = array();
        foreach ($rows as $question) {
            $test['questionByHash'][$question['qn_hash']] = $question;
            $test['questions'][] = &$test['questionByHash'][$question['qn_hash']];
        }

        // a variant ID is computed using hashes of selected questions and sequences of their answers
        $variant = array();
        foreach ($test['questions'] as $question) {
            $v = array($question['qn_hash']);
            foreach ($question['choices'] as $c)
                $v[1][] = $c['ch_num'];
            $variant[] = $v;
        }
        $test['variant_hash'] = serialize($variant);
        $test['variant_hash_crc32'] = sprintf("%u", crc32($test['variant_hash']));
        $test['variant_hash_md5'] = md5($test['variant_hash']);

        $test['random_correct'] = 0;
        $test['max_score'] = 0;
        foreach ($test['questions'] as $question) {
            // correct answers count for random selection
            $test['random_correct'] += $question['correct_count'] / count($question['choices']);
            // maximum total score
            $test['max_score'] += $question['score_correct'];
        }

        return $test;
    }

    /**
     * Question must have at least 1 correct choice
     * @param $question
     * @param $var
     * @param int $shuffle
     * @return bool
     */
    static function finalizeQuestionRow(&$question, $var, $shuffle = 0)
    {
        $hash = $question['qn_hash'];
        $hard_question = ($question['qn_type'] == 'order' or $question['qn_type'] == 'parallel') ? true : false;

        if (!$var && !count($question['choices'])) {
            /* No choices defined for this question, skip it */
            wfDebug(__CLASS__ . ": Skipping $hash, no choices!\n");
        } elseif (!$var && $question['correct_count'] <= 0) {
            /* No correct choices defined for this question, skip it */
            wfDebug(__CLASS__ . ": Skipping $hash, no correct choices!\n");
        } else {
            if (isset($question['ch_order'])) {
                /* Reorder choices according to saved variant */
                $nc = array();
                foreach ($question['ch_order'] as $num) {
                    $nc[] = &$question['choiceByNum'][$num];
                }
                $question['choices'] = $nc;
                unset($question['ch_order']);
            } elseif ($shuffle or $hard_question) {
                /* Or else optionally shuffle choices */
                shuffle($question['choices']);
            }
            //TODO: refactor score calculating
            /* Calculate scores */
            if ($question['correct_count']) {
                // add 1/n for correct answers
                $question['score_correct'] = 1/$question['correct_count'];
//                $question['score_correct'] = 1;
                // subtract 1/(m-n) for incorrect answers, so universal mean would be 0
                $question['score_incorrect'] = $question['correct_count'] < count($question['choices']) ? -$question['score_correct'] / (count($question['choices']) - $question['correct_count']) : 0;
            }
            foreach ($question['choices'] as $i => &$c) {
                $c['index'] = $i + 1;
            }
            return true;
        }
        return false;
    }

    /**
     * Identical to Xml::element, but does no htmlspecialchars() on $contents
     */
    static function xelement($element, $attribs = null, $contents = '', $allowShortTag = true)
    {
        if (is_null($contents))
            return Xml::openElement($element, $attribs);
        elseif ($contents == '')
            return Xml::element($element, $attribs, $contents, $allowShortTag);
        return Xml::openElement($element, $attribs) . $contents . Xml::closeElement($element);
    }

    /**
     * Display quiz question statistics near editsection link
     */
    static function quizQuestionInfo($title, $section, &$result)
    {
        $k = $title->getPrefixedDBkey();
        /* Load questions taken from this article into cache, if not yet */
        if (!isset(self::$questionInfoCache[$k])) {
            $dbr = wfGetDB(DB_SLAVE);
            $r = $dbr->select(
                array('et_question', 'et_choice_stats'),
                '*, COUNT(cs_correct) complete_count, SUM(cs_correct) correct_count',
                array(
                    'qn_anchor IS NOT NULL', 'qn_hash=cs_question_hash',
                    'qn_anchor LIKE ' . $dbr->addQuotes("$k|%"),
                    // Old questions which are really not present in article
                    // text may reside in the DB, filter them out:
                    'EXISTS (SELECT * FROM et_question_test WHERE qt_question_hash=qn_hash)',
                ),
                __FUNCTION__,
                array('GROUP BY' => 'qn_hash')
            );
            foreach ($r as $obj) {
                preg_match('/\\|(\d+)$/', $obj->qn_anchor, $m);
                self::$questionInfoCache[$k][$m[1]] = $obj;
            }
            if (empty(self::$questionInfoCache[$k]))
                self::$questionInfoCache[$k] = NULL;
        }
        preg_match('/\d+/', $section, $m);
        $sectnum = $m[0];
        /* Append colored statistic hint to editsection span */
        if (self::$questionInfoCache[$k] && !empty(self::$questionInfoCache[$k][$sectnum])) {
            $obj = self::$questionInfoCache[$k][$sectnum];
            $result .= self::questionStatsHtml($obj->correct_count, $obj->complete_count);
        }
    }

    /**
     * Get HTML for one question statistics message
     */
    static function questionStatsHtml($correct, $complete)
    {
        global $egEasyTestsEasyQuestionCompl, $egEasyTestsHardQuestionCompl;
        $style = '';
        if ($complete) {
            $percent = intval(100 * $correct / $complete);
            $stat = wfMsg('easytests-complete-stats', $correct,
                $complete, $percent);
            if ($complete > 4) {
                if ($percent >= $egEasyTestsEasyQuestionCompl)
                    $style = ' style="color: white; background: #080;"';
                elseif ($percent <= $egEasyTestsHardQuestionCompl)
                    $style = ' style="color: white; background: #a00;"';
            }
            if ($style)
                $stat = '&nbsp;' . $stat . '&nbsp;';
        } else
            $stat = wfMsg('easytests-no-complete-stats');
        $stat = '<span class="editsection"' . $style . '>' . $stat . '</span>';
        return $stat;
    }

    /** SPECIAL PAGE ENTRY POINT */
    function execute($par = null)
    {
        global $wgOut, $wgRequest, $wgTitle, $wgLang, $wgServer, $wgScriptPath, $wgUser;
        $args = $_GET + $_POST;
        $wgOut->addExtensionStyle("$wgScriptPath/extensions/" . basename(dirname(__FILE__)) . "/css/easytest.css");

        $mode = isset($args['mode']) ? $args['mode'] : '';

        // Do not create Title from name because it will lead to permission errors
        // for unauthorized users in case of IntraACL Quiz: namespace protection
        $id = false;
        if ($par)
            $id = array('name' => $par);
        elseif (isset($args['id']))
            $id = array('id' => $args['id']);
        elseif (isset($args['id_test']))
            $id = array('name' => $args['id_test']); // backward compatibility

        $is_adm = self::isAdminForTest(NULL);
        $default_mode = false;
        if (!isset(self::$modes[$mode])) {
            $default_mode = true;
            $mode = $is_adm && !$id ? 'review' : 'show';
        }

        if ($mode == 'check') {
            /* Check mode requires loading of a specific variant, so don't load random one */
            $this->checkTest($args);
            return;
        } elseif ($mode == 'qr') {
            $this->qrCode($args);
            return;
        } elseif ($mode == 'review') {
            /* Review mode is available to test administrators and users who have access to source */
            if (!isset($args['quiz_name']))
                $args['quiz_name'] = '';
            if (self::isAdminForTest($args['quiz_name']))
                $this->review($args);
            else {
                $wgOut->setRobotPolicy('noindex,nofollow');
                $wgOut->setArticleRelated(false);
                $wgOut->enableClientCache(false);
                $wgOut->addWikiMsg(isset($args['quiz_name']) ? 'easytests-review-denied-quiz' : 'easytests-review-denied-all');
                $wgOut->addHTML($this->getSelectTestForReviewForm($args));
                $wgOut->setPageTitle(wfMsg('easytests-review-denied-title'));
            }
            return;
        }

        /* Allow viewing ticket variant with specified key for print mode */
        $variant = $answers = $ticket = NULL;
        if (($mode == 'print' || $mode == 'show') &&
            !empty($args['ticket_id']) && !empty($args['ticket_key']) &&
            ($ticket = self::loadTicket($args['ticket_id'], $args['ticket_key']))
        ) {
            $id = array('id' => $ticket['tk_test_id']);
            $variant = $ticket['tk_variant'];
            $answers = self::loadAnswers($ticket['tk_id']);
            if ($mode == 'show' && $ticket['tk_end_time']) {
                $this->checkTest($args);
                return;
            }
        }

        /* Raise error when no test is specified for mode=print or mode=show */
        if (!$id) {
            $wgOut->setRobotPolicy('noindex,nofollow');
            $wgOut->setArticleRelated(false);
            $wgOut->enableClientCache(false);
            if ($default_mode) {
                $wgOut->addWikiMsg('easytests-review-option');
                $wgOut->addHTML($this->getSelectTestForReviewForm($args));
            } else
                $wgOut->addWikiMsg('easytests-no-test-id-text');
            $wgOut->setPageTitle(wfMsg('easytests-no-test-id-title'));
            return;
        }

        /* Load random or specific test variant */
        $test = self::loadTest($id, $variant);
        if (!$test && !$ticket &&
            !self::isAdminForTest($test['test_id']) /*&& !$wgUser->isAllowed('secretquiz')*/
        ) {
            $wgOut->showErrorPage('easytests-test-not-found-title', 'easytests-test-not-found-text');
            return;
        }

        if ($mode == 'print')
            $this->printTest($test, $args, $answers);
        elseif ($mode == 'getticket')
            self::showTicket($test);
        else
            self::showTest($test, $ticket, $args);
    }

    /**
     * Check if the user is an administrator for the test $name
     */
    static function isAdminForTest($name)
    {
        if (self::$is_adm === NULL)
            self::$is_adm = EasyTests::isTestAdmin();
        if (self::$is_adm)
            return true;
        if ($name || !is_object($name) && strlen($name)) {
            if (is_object($name))
                $title = $name;
            else
                $title = Title::newFromText($name, NS_EATEST);
            if ($title && $title->exists() && $title->userCan('read'))
                return true;
        }
        return false;
    }

    /** Check mode: check selected choices if not already checked,
     * display results and completion certificate */
    function checkTest($args)
    {
        global $wgOut, $wgTitle, $wgUser;

        $ticket = self::loadTicket($args['ticket_id'], $args['ticket_key']);
        if (!$ticket) {
            if ($args['id']) {
                $test = self::loadTest(array('id' => $args['id']));
                $name = $test['test_name'];
                $href = $wgTitle->getFullUrl(array('id' => $test['test_id']));
            }
            $wgOut->showErrorPage('easytests-check-no-ticket-title', 'easytests-check-no-ticket-text', array($name, $href));
            return;
        }

        $test = self::loadTest(array('id' => $ticket['tk_test_id']), $ticket['tk_variant']);
        $testresult = self::checkOrLoadResult($ticket, $test, $args);
        if (!$testresult) {
            // checkOrLoadResult had shown the detail form - user must fill in additional fields
            return;
        }

        $html = '';
        if ($testresult['seen']) {
            $html .= wfMsg('easytests-variant-already-seen') . ' ';
        }
        $href = $wgTitle->getFullUrl(array('id' => $test['test_id']));
        if (self::isAdminForTest($test['test_id'])) {
            $html .= wfMsg('easytests-try-another', $href);
        }

        if ($test['test_intro']) {
            $html .= self::xelement('div', array('class' => 'easytests-intro easytests-intro-finish'), $test['test_intro']);
        }

        $f = self::formatTicket($ticket);
        $html .= wfMsg('easytests-ticket-details',
            $f['name'], $f['start'], $f['end'], $f['duration']
        );

        $is_adm = self::isAdminForTest($test['test_id']);
        if ($is_adm) {
            if ($ticket['tk_reviewed']) {
                $html .= '<p>' . wfMsg('easytests-ticket-reviewed') . '</p>';
            } else {
                wfGetDB(DB_MASTER)->update(
                    'et_ticket', array('tk_reviewed' => 1),
                    array('tk_id' => $ticket['tk_id']), __METHOD__
                );
            }
        }

        $detail = $ticket['tk_details'] ? json_decode($ticket['tk_details'], true) : array();
        if ($detail) {
            $html .= '<ul>';
            foreach ($detail as $k => $v) {
                $html .= '<li>' . htmlspecialchars($k) . ': ' . htmlspecialchars($v) . '</li>';
            }
            $html .= '</ul>';
        }

        if ($is_adm) {
            // Average result for admins
            $html .= self::xelement('p', NULL, wfMsg('easytests-test-average', self::getAverage($test)));
        }

        // Variant number
        $html .= wfMsg('easytests-variant-msg', $test['variant_hash_crc32']);

        // Result
        $html .= $this->getResultHtml($ticket, $test, $testresult);

        if ($testresult['passed'] && ($ticket['tk_displayname'] || $ticket['tk_user_id'])) {
            $html .= Xml::element('hr', array('style' => 'clear: both'));
        }

        /* Display answers also for links from result review table (showtut=1)
           to users who are admins or have read access to quiz source */
        if ($test['test_mode'] == 'TUTOR' || !empty($args['showtut']) && $is_adm) {
            $html .= $this->getTutorHtml($ticket, $test, $testresult, $is_adm);
        }

        $wgOut->setPageTitle(wfMsg('easytests-check-pagetitle', $test['test_name']));
        $wgOut->addHTML($html);
    }

    /** Get ticket from the database */
    static function loadTicket($id, $key)
    {
        $dbr = wfGetDB(DB_SLAVE);
        $result = $dbr->select('et_ticket', '*', array(
            'tk_id' => $id,
            'tk_key' => $key,
        ), __FUNCTION__);
        $ticket = (array)$dbr->fetchObject($result);
        $dbr->freeResult($result);
        return $ticket;
    }

    /** Either check an unchecked ticket, or load results from the database
     * if the ticket is already checked */
    static function checkOrLoadResult(&$ticket, $test, $args)
    {
        global $wgUser;
        $testresult = array(
            'correct_count' => 0,
            'score' => 0,
        );

        $updated = false;
        if ($ticket['tk_end_time']) {
            /* Ticket already checked, load answers from database */
            $testresult['answers'] = self::loadAnswers($ticket['tk_id']);
            $testresult['details'] = $ticket['tk_details'] ? json_decode($ticket['tk_details'], true) : array();
            $testresult['seen'] = true;
        } else {
            /* Else check POSTed answers */
            $empty = trim(@$_REQUEST['prompt']) === '';
            $formdef = self::formDef($test);
            $values = array();
            if ($formdef) {
                // Check for empty form fields
                foreach ($formdef as $i => $field) {
                    if (!isset($field['type'])) {
                        $field['type'] = false;
                    }
                }
                if ($empty) {
                    // Ask user to fill fields if some of them are empty
                    self::showTest($test, $ticket, $args, true);
                    return false;
                }
            }
            $testresult['details'] = $values;
            $testresult['answers'] = self::checkAnswers($test, $ticket);
            $testresult['seen'] = false;
            /* Need to send mail and update ticket in the DB */
            $updated = true;
        }

        /* Calculate scores */
        self::calculateScores($testresult, $test);

        if ($updated) {
            /* Update ticket */
            $userid = $wgUser->getId();
            global $wgRequest;
            if (!$userid)
                $userid = NULL;
            $update = array(
                'tk_end_time' => wfTimestampNow(),
                'tk_displayname' => $args['prompt'],
                'tk_user_id' => $userid,
                'tk_user_text' => $wgUser->getName(),
                'tk_user_ip' => $wgRequest->getIP(),
                /* Testing result to be shown in the table.
                   Nothing relies on these values. */
                'tk_score' => $testresult['score'],
                'tk_score_percent' => $testresult['score_percent'],
                'tk_correct' => $testresult['correct_count'],
                'tk_correct_percent' => $testresult['correct_percent'],
                'tk_pass' => $testresult['passed'] ? 1 : 0,
                'tk_details' => $testresult['details'] ? json_encode($values, JSON_UNESCAPED_UNICODE) : '',
            );
            $ticket = array_merge($ticket, $update);
            $dbw = wfGetDB(DB_MASTER);
            $dbw->update('et_ticket', $update, array('tk_id' => $ticket['tk_id']), __METHOD__);
            /* Send mail with test results to administrator(s) */
            self::sendMail($ticket, $test, $testresult);
        }

        return $testresult;
    }

    /**
     * ***********
     * CHECK MODE
     * ***********
     * Load saved answer numbers from database
     */
    static function loadAnswers($ticket_id)
    {
        $answers = array();
        $dbr = wfGetDB(DB_SLAVE);
        $result = $dbr->select('et_choice_stats', '*', array(
            'cs_ticket' => $ticket_id,
        ), __FUNCTION__);
        while ($row = $dbr->fetchObject($result)) {
            $answers[$row['cs_question_hash']] = (array)$row;
        }
        $dbr->freeResult($result);
        return $answers;
    }

    static function formDef($test)
    {
        $fields = trim($test['test_user_details']);
        $found_name = false;
        $formdef = array();
        if ($fields) {
            if ($fields{0} != '[' ||
                ($formdef = @json_decode($test['test_user_details'], true)) === NULL
            ) {
                $def = array();
                foreach (explode(',', $fields) as $f) {
                    $formdef[] = array('name' => $f, 'type' => 'text', 'mandatory' => true);
                }
            }
        }
        return $formdef;
    }

    /** Display main form for testing */
    static function showTest($test, $ticket, $args, $empty = false)
    {
        global $wgTitle, $wgOut;

        if (!$ticket) {
            $ticket = self::createTicket($test, wfTimestampNow());
        } elseif ($ticket['tk_end_time']) {
            die('BUG: ticket is already answered');
        } elseif (!$ticket['tk_start_time']) {
            global $wgUser, $wgRequest;

            $userid = $wgUser->getId();
            if (!$userid)
                $userid = NULL;
            $update = array(
                'tk_start_time' => wfTimestampNow(),
                'tk_user_id' => $userid,
                'tk_user_text' => $wgUser->getName(),
                'tk_user_ip' => $wgRequest->getIP(),
            );
            $ticket = array_merge($ticket, $update);
            $dbw = wfGetDB(DB_MASTER);
            $dbw->update('et_ticket', $update, array('tk_id' => $ticket['tk_id']), __METHOD__);
        }
        $action = $wgTitle->getFullUrl(array(
            'id' => $test['test_id'],
            'ticket_id' => $ticket['tk_id'],
            'ticket_key' => $ticket['tk_key'],
            'mode' => 'check',
        ));

        $form = '';
        $formdef = self::formDef($test);
        $found_name = false;
        $mandatory = '<span style="color:red" title="' . wfMsg('easytests-prompt-needed') . '">*</span>';
        if ($formdef) {
            foreach ($formdef as $i => $field) {
                if (isset($field['type'])) {
                    if ($field['type'] == 'name') {
                        if ($found_name) {
                            $field['type'] = 'text';
                        }
                        $found_name = true;
                    }
                    if ($field['type'] == 'name' || $field['type'] == 'text') {
                        $nm = $field['type'] == 'name' ? 'prompt' : "detail_$i";
                        $form .= '<tr><th><label for="' . $nm . '">' . trim($field['name']) . ($field['mandatory'] ? $mandatory : '') .
                            ':</label></th><td><input type="text" name="' . $nm . '" id="' . $nm . '" size="40" value="' . htmlspecialchars(@$args[$nm]) . '" /></td></tr>';
                    } elseif ($field['type'] == 'html') {
                        $form .= '<tr><td colspan="2">' . $field['name'] . '</td></tr>';
                    } elseif ($field['type'] == 'checkbox') {
                        $form .= '<tr><td colspan="2"><input type="checkbox" name="detail_' . $i . '" id="detail_' . $i .
                            '" value="' . htmlspecialchars($field['value']) . '"' . (@$args["detail_$i"] ? ' checked="checked"' : '') . ' /> ' .
                            '<label for="detail_' . $i . '">' . ($field['mandatory'] && empty($field['multiple']) ? $mandatory . ' ' : '') .
                            ($field['value'] == '1' ? $field['name'] : $field['value']) .
                            '</label></td></tr>';
                    }
                }
            }
        }
        if (!$found_name) {
            // Prompt user displayname
            $form = '<tr><th>' . wfMsg('easytests-prompt') . $mandatory . ':</th><td>' .
                Xml::input('prompt', 30, @$args['prompt']) . '</td></tr>' .
                $form;
        }
        $form = '<table class="easytests-form">' . $form . '</table>';
        if ($empty) {
            $form = '<p class="error">' . wfMsg('easytests-empty') . '</p>' . $form;
        }
        $form .= self::xelement('p', NULL, Xml::submitButton(wfMsg('easytests-submit')));
        if (empty($args['a'])) {
            $form .= self::getQuestionList($test['questions'], true);
            $form .= Xml::element('hr');
            $form .= Xml::submitButton(wfMsg('easytests-submit'));
        } else {
            // Include hidden answers if the form is already submitted
            $form .= Xml::input('a_values', false, json_encode($args['a'], JSON_UNESCAPED_UNICODE), array('type' => 'hidden'));
        }
        $form .= '<input type="hidden" name="_submitted" id="_submitted" value="" />';
        $form = self::xelement('form', array('action' => $action, 'method' => 'POST', 'onsubmit' => 'this._submitted.value = 1;'), $form);

        $html = self::getToc(count($test['questions']));
        if ($test['test_intro']) {
            $html .= self::xelement('div', array('class' => 'easytests-intro'), $test['test_intro']);
        }
        $html .= wfMsg('easytests-variant-msg', $test['variant_hash_crc32']);
        $html .= Xml::element('hr');
        $html .= self::getCounterJs();
        $html .= $form;

        $wgOut->setPageTitle(wfMsg('easytests-pagetitle', $test['test_name']));
        $wgOut->addHTML($html);
    }

    /** Create a ticket and a secret key for testing, and remember the variant */
    static function createTicket($test, $start)
    {
        global $wgUser;
        $key = unpack('H*', urandom(16));
        $key = $key[1];
        $userid = $wgUser->getId();
        if (!$userid)
            $userid = NULL;
        $dbw = wfGetDB(DB_MASTER);
        global $wgRequest;
        $ticket = array(
            'tk_id' => $dbw->nextSequenceValue('et_ticket_tk_id_seq'),
            'tk_key' => $key,
            'tk_start_time' => $start,
            'tk_end_time' => NULL,
            'tk_displayname' => NULL,
            'tk_user_id' => $userid,
            'tk_user_text' => $wgUser->getName(),
            'tk_user_ip' => $wgRequest->getIP(),
            'tk_test_id' => $test['test_id'],
            'tk_variant' => $test['variant_hash'],
        );
        $dbw->insert('et_ticket', $ticket, __METHOD__);
        $ticket['tk_id'] = $dbw->insertId();
        return $ticket;
    }

    /** Get HTML ordered list with questions, choices,
     * optionally radio-buttons for selecting them when $inputs is TRUE,
     * and optionally edit question section links when $editsection is TRUE. */
    static function getQuestionList($questions, $inputs = false, $editsection = false)
    {
        $html = '';
        foreach ($questions as $key => $question) {
            $html .= Xml::element('hr');
            $html .= self::xelement('a', array('name' => "q$key"), '', false);
            $h = wfMsg('easytests-question', $key + 1);
            if ($editsection)
                $h .= $question['qn_editsection'];
            $html .= self::xelement('h3', NULL, $h);

            $html .= self::getQuestionHtml($question, $key, $inputs);
        }
        return $html;
    }

    /**
     * We will use it also in TUTOR mode to get question with choices.
     */
    static function getQuestionHtml($question, $qn_key, $inputs = false)
    {
        $html = '';
        $html = self::xelement('div', array('class' => 'easytests-question'), $question['qn_text']);
        $choices = '';
        switch ($question['qn_type']) {
            case 'free-text':
                if ($inputs) {
                    $html .= wfMsg('easytests-freetext') . ' ' . self::xelement('input', array('name' => "a[$qn_key]", 'type' => 'text'));
                }
                break;
            case 'order':
                $options = self::buildOptionsArray($question['choices'], $question['qn_type']);
                foreach ($question['choices'] as $i => $choice) {
                    $h = $choice['ch_text'] . '&nbsp;' . self::xelement('select', array(
                            'name' => "a[$qn_key]",
                        ), $options);

                    $choices .= self::xelement('li', array('class' => 'easytests-choice'), $h);
                }
                $html .= self::xelement('ol', array('class' => 'easytests-choices'), $choices);
                break;
            case 'parallel':
                $options = self::buildOptionsArray($question['choices'], $question['qn_type']);
                foreach ($question['choices'] as $i => $choice) {
                    $h = $choice['ch_text'] . '&nbsp;' . self::xelement('select', array(
                            'name' => "a[$qn_key]",
                        ), $options);

                    $choices .= self::xelement('li', array('class' => 'easytests-choice'), $h);
                }
                $html .= self::xelement('ol', array('class' => 'easytests-choices'), $choices);
                break;
            default:
                foreach ($question['choices'] as $i => $choice) {
                    if ($inputs) {
                        /*
                         * If correct choices more than 1, build input with checkboxes
                         */
                        if ($question['correct_count'] > 1) {
                            $h = Xml::element('input', array(
                                    'name' => "a[$qn_key][]",
                                    'type' => 'checkbox',
                                    'value' => $i + 1,
                                )) . '&nbsp;' . $choice['ch_text'];
                        } else {
                            /*
                             * Question hashes and choice numbers are hidden from user.
                             * They are taken from ticket during check.
                             */
                            $h = Xml::element('input', array(
                                    'name' => "a[$qn_key]",
                                    'type' => 'radio',
                                    'value' => $i + 1,
                                )) . '&nbsp;' . $choice['ch_text'];
                        }
                    } else {
                        $h = $choice['ch_text'];
                    }
                    $choices .= self::xelement('li', array('class' => 'easytests-choice'), $h);
                }
                $html .= self::xelement('ol', array('class' => 'easytests-choices'), $choices);
                break;
        }
        return $html;
    }

    /**
     * buildOptionsArray
     *
     * @param $choices
     * @param $question_type
     * @return string
     */
    private static function buildOptionsArray($choices, $question_type)
    {
        $options_arr = '';
        switch($question_type) {
            case 'order' :
                foreach ($choices as $key => $value) {
                    $options_arr .= Xml::element('option', array(
                        'value' => $key,
                    ), $key + 1);
                }
                break;
            case 'parallel' :
                usort($choices, function ($a, $b) {
                    if(intval($a['ch_num']) > intval($b['ch_num'])) {
                        return 1;
                    }elseif (intval($a['ch_num']) < intval($b['ch_num'])) {
                        return -1;
                    }else {
                        return 0;
                    }
                });
                foreach ($choices as $key => $value) {
                    $options_arr .= Xml::element('option', array(
                        'value' => $value['ch_parallel'],
                    ), $value['ch_parallel']);
                }
                break;
        }

        return $options_arr;
    }

    /**
     ************
     * SHOW MODE
     ************
     * Get a table with question numbers linked to the appropriate questions
     */
    static function getToc($n, $trues = false)
    {
        if ($n <= 0)
            return '';
        $s = '';
        for ($k = 0; $k < $n;) {
            $row = '';
            for ($j = 0; $j < 10; $j++, $k++) {
                $args = NULL;
                if ($k >= $n)
                    $text = '';
                elseif ($trues && array_key_exists($k, $trues) && !$trues[$k]) {
                    $text = $k + 1;
                    $args = array('class' => 'easytests-noitem');
                } else
                    $text = self::xelement('a', array('href' => "#q$k"), $k + 1);
                $row .= self::xelement('td', $args, $text);
            }
            $s .= self::xelement('tr', NULL, $row);
        }
        $s = self::xelement('table', array('class' => 'easytests-toc'), $s);
        return $s;
    }

    /** Get javascript code for HH:MM:SS counter */
    static function getCounterJs()
    {
        global $wgScriptPath;
        $format = wfMsg('easytests-counter-format');
        $already = wfMsg('easytests-refresh-to-retry');
        return <<<EOT
<script type="text/javascript">
var BackColor = "white";
var ForeColor = "navy";
var CountActive = true;
var CountStepper = 1;
var LeadingZero = true;
var DisplayFormat = "$format";
var FinishMessage = "";
$(window).unload(function() {
    // Prevent fast unload to bfcache
});
$(window).load(function()
{
    if (document.getElementById('_submitted').value && confirm('$already'))
    {
        window.location.href = window.location.href;
    }
});
</script>
<script type="text/javascript" src="$wgScriptPath/extensions/EasyTests/js/countdown.js"></script>
EOT;
    }

    /** Load answers from POST data, save them into DB and return as the result */
    static function checkAnswers($test, $ticket)
    {
        $answers = array();
        $rows = array();
        if (!empty($_POST['a_values'])) {
            $_POST['a'] = json_decode($_POST['a_values'], true);
        }
        foreach ($test['questions'] as $i => $q) {
            if (!empty($_POST['a'][$i])) {
                $n = $_POST['a'][$i];
                switch ($q['correct_count']) {
                    case ($q['correct_count'] == count($q['choices']) and count($q['choices']) == 1):
                        if ($q['choices'] == 1) {
                            $n = trim($n);
                            $is_correct = false;
                            foreach ($q['choices'] as $ch) {
                                if ($n === $ch['ch_text']) {
                                    $is_correct = true;
                                }
                            }
                            $text = $n;
                            $num = 0;
                        } else {
                            continue;
                        }
                        break;
                    case($q['correct_count'] <= count($q['choices']) and $q['correct_count'] > 1):
                        foreach ($n as $key => $val) {
                            $is_correct = $q['choices'][$val - 1]['ch_correct'] ? 1 : 0;
                            $text = NULL;
                            $num = $q['choices'][$val - 1]['ch_num'];
                        }
                        break;
                    default:
                        $is_correct = $q['choices'][$n - 1]['ch_correct'] ? 1 : 0;
                        $text = NULL;
                        $num = $q['choices'][$n - 1]['ch_num'];
                }

                /* Build rows for saving answers into database */
                $rows[] = $answers[$q['qn_hash']] = array(
                    'cs_ticket' => $ticket['tk_id'],
                    'cs_question_hash' => $q['qn_hash'],
                    'cs_choice_num' => $num,
                    'cs_text' => $text,
                    'cs_correct' => $is_correct,
                );
            }
        }
        $dbw = wfGetDB(DB_MASTER);
        $dbw->insert('et_choice_stats', $rows, __METHOD__);
        return $answers;
    }

    /** Calculate scores based on $testresult['answers'] ($hash => $num) */
    static function calculateScores(&$testresult, &$test)
    {
        foreach ($testresult['answers'] as $hash => $row) {
            $c = $row['cs_correct'] ? 1 : 0;
            $testresult['correct_count'] += $c;
            $testresult['score'] += $test['questionByHash'][$hash][$c ? 'score_correct' : 'score_incorrect'];
        }
        $testresult['correct_percent'] = round($testresult['correct_count'] / count($test['questions']) * 100, 1);
        $testresult['score_percent'] = round($testresult['score'] / $test['max_score'] * 100, 1);
        $testresult['passed'] = $testresult['score_percent'] >= $test['test_ok_percent'];
    }

    /** Send emails with test results to administrators */
    static function sendMail($ticket, $test, $testresult)
    {
        global $egEasyTestsAdmins, $wgEmergencyContact;
        $text = self::buildMailText($ticket, $test, $testresult);
        $sender = new MailAddress($wgEmergencyContact);
        foreach ($egEasyTestsAdmins as $admin) {
            if (($user = User::newFromName($admin)) &&
                ($user = $user->getEmail())
            ) {
                $to = new MailAddress($user);
                $mailResult = UserMailer::send($to, $sender, "[Quiz] «" . $test['test_name'] . "» $ticket[tk_id] => $testresult[score_percent]%", $text);
            }
        }
    }

    /** Build email text */
    static function buildMailText($ticket, $test, $testresult)
    {
        $msg_r = wfMsg('easytests-right-answer');
        $msg_y = wfMsg('easytests-your-answer');
        $text = '';
        foreach ($test['questions'] as $i => $q) {
            $msg_q = wfMsg('easytests-question', $i + 1);
            if (isset($testresult['answers'][$q['qn_hash']]))
                $row = $testresult['answers'][$q['qn_hash']];
            else
                $row = NULL;
            if (!$row || !$row['cs_correct']) {
                $qn_text = trim(strip_tags($q['qn_text']));
                $ch_correct = trim(strip_tags($q['correct_choices'][0]['ch_text']));
                $lab = trim(strip_tags($q['qn_label']));
                if ($lab)
                    $lab .= ' | ';
                if ($row) {
                    $ch_user = !empty($row['cs_choice_num']) ? "[№" . $row['cs_choice_num'] . "] " . trim(strip_tags($q['choiceByNum'][$row['cs_choice_num']]['ch_text'])) : $row['cs_text'];
                    $ch_user = "--------------------------------------------------------------------------------\n$msg_y: $ch_user\n";
                } else
                    $ch_user = '';
                $text .= <<<EOT
================================================================================
$msg_q | $lab$q[correct_tries]/$q[tries]
--------------------------------------------------------------------------------
$qn_text

$msg_r
$ch_correct
${ch_user}≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈≈
EOT;
            }
        }
        $values = array(
            array('quiz', "$test[test_name] /* Id: $test[test_id] */"),
            array('variant', $test['variant_hash_crc32']),
            array('who', $ticket['tk_displayname'] ? $ticket['tk_displayname'] : $ticket['tk_user_text']),
            array('user', $ticket['tk_user_text']),
            array('start', $ticket['tk_start_time']),
            array('end', $ticket['tk_end_time']),
            array('ip', $ticket['tk_user_ip']),
            array('right-answers', "$testresult[correct_count] ≈ $testresult[correct_percent]% (random: $test[random_correct])"),
            array('score', "$testresult[score] ≈ $testresult[score_percent]%"),
        );
        $len = 0;
        foreach ($values as &$v) {
            $v[2] = wfMsg('easytests-' . $v[0]);
            $v[3] = mb_strlen($v[2]);
            if ($v[3] > $len)
                $len = $v[3];
        }
        $header = '';
        foreach ($values as &$v)
            $header .= $v[2] . ': ' . str_repeat(' ', $len - $v[3]) . $v[1] . "\n";
        $text = $header . $text;
        $text = "<pre>\n$text\n</pre>";
        return $text;
    }

    /** Format some ticket properties for display */
    static function formatTicket($t)
    {
        global $wgUser;
        $r = array();
        if ($t['tk_user_id'])
            $r['name'] = $wgUser->getSkin()->link(Title::newFromText('User:' . $t['tk_user_text']), $t['tk_displayname'] ? $t['tk_displayname'] : $t['tk_user_text']);
        elseif ($t['tk_displayname'])
            $r['name'] = $t['tk_displayname'];
        else
            $r['name'] = wfMsg('easytests-anonymous');
        $r['duration'] = wfTimestamp(TS_UNIX, $t['tk_end_time']) - wfTimestamp(TS_UNIX, $t['tk_start_time']);
        $d = $r['duration'] > 86400 ? intval($r['duration'] / 86400) . 'd ' : '';
        $r['duration'] = $d . gmdate('H:i:s', $r['duration'] % 86400);
        $r['start'] = wfTimestamp(TS_DB, $t['tk_start_time']);
        $r['end'] = wfTimestamp(TS_DB, $t['tk_end_time']);
        return $r;
    }

    /**
     * Get average correct count for the test
     */
    function getAverage($test)
    {
        $dbr = wfGetDB(DB_SLAVE);
        $res = $dbr->query('SELECT AVG(a) a FROM (' . $dbr->selectSQLtext(
                array('et_ticket', 'et_choice_stats'),
                'SUM(cs_correct)/COUNT(cs_correct)*100 a',
                array('tk_id=cs_ticket', 'tk_test_id' => $test['test_id']),
                __METHOD__,
                array('GROUP BY' => 'cs_ticket')
            ) . ') t', __METHOD__);
        $row = $res->fetchObject();
        return round($row->a);
    }

    /** Get HTML code for result table (answers/score count/percent) */
    function getResultHtml($ticket, $test, $testresult)
    {
        global $wgTitle;
        $row = self::xelement('th', NULL, wfMsg('easytests-right-answers'))
            . self::xelement('th', NULL, wfMsg('easytests-score-long'));
        $html = self::xelement('tr', NULL, $row);
        $row = self::wrapResult($testresult['correct_count'], $testresult['correct_percent'])
            . self::wrapResult($testresult['score'], $testresult['score_percent']);
        $html .= self::xelement('tr', NULL, $row);
        $html = self::xelement('table', array('class' => 'easytests-result'), $html);
        // QR code
        $html = '<img style="float: left; margin: 0 8px 8px 0" src="' . $wgTitle->getLocalUrl(array(
                'ticket_id' => $ticket['tk_id'],
                'ticket_key' => $ticket['tk_key'],
                'mode' => 'qr',
            )) . '" />' . $html;
        $html = self::xelement('h2', NULL, wfMsg('easytests-results')) . $html;
        $html .= self::xelement('p', array('class' => 'easytests-rand'), wfMsg('easytests-random-correct', round($test['random_correct'], 1)));
        return $html;
    }

    /** A cell with <span>$n</span> ≈ $p% */
    static function wrapResult($n, $p, $e = 'td')
    {
        $cell = self::xelement('span', array('class' => 'easytests-count'), $n);
        $cell .= ' ≈ ' . $p . '%';
        return $e ? self::xelement($e, NULL, $cell) : $cell;
    }

    /** TUTOR mode tests display all incorrect answered questions with
     * correct answers and explanations after testing. */
    function getTutorHtml($ticket, $test, $testresult, $is_adm = false)
    {
        $items = array();
        $html = '';
        foreach ($test['questions'] as $k => $q) {
            $row = @$testresult['answers'][$q['qn_hash']];
            if ($row && $row['cs_correct'])
                continue;
            $items[$k] = true;
            $correct = $q['correct_choices'][0];
            $html .= Xml::element('hr');
            $html .= self::xelement('a', array('name' => "q$k"), '', false);
            if ($is_adm)
                $stats = self::questionStatsHtml($q['correct_tries'], $q['tries']);
            else
                $stats = '';
            $html .= self::xelement('h3', NULL, wfMsg('easytests-question', $k + 1) . $stats);
            //$html .= self::xelement('div', array('class' => 'easytests-question'), $q['qn_text']);
            $html .= self::getQuestionHtml($q, $k);
            if ($row) {
                $html .= self::xelement('h4', NULL, wfMsg('easytests-your-answer'));
                $html .= self::xelement('div', array('class' => 'easytests-your-answer'), !empty($row['cs_choice_num']) ? $q['choiceByNum'][$row['cs_choice_num']]['ch_text'] : $row['cs_text']);
            }
            $html .= self::xelement('h4', NULL, wfMsg('easytests-right-answer'));
            $html .= self::xelement('div', array('class' => 'easytests-right-answer'), $correct['ch_text']);
            if ($q['qn_explanation']) {
                $html .= self::xelement('h4', NULL, wfMsg('easytests-explanation'));
                $html .= self::xelement('div', array('class' => 'easytests-explanation'), $q['qn_explanation']);
            }
        }
        if ($items) {
            $html = self::getToc(count($test['questions']), $items) .
                Xml::element('div', array('style' => 'clear: both'), false) .
                $html;
        }
        return $html;
    }

    /** Draws a QR code with ticket check link */
    function qrCode($args)
    {
        global $wgTitle, $IP, $wgOut;
        $ticket = self::loadTicket($args['ticket_id'], $args['ticket_key']);
        if (!$ticket) {
            $wgOut->showErrorPage('easytests-check-no-ticket-title', 'easytests-check-no-ticket-text', array($name, $href));
            return;
        }
        require_once(dirname(__FILE__) . '/includes/phpqrcode.php');
        if (is_writable("$IP/images")) {
            global $QR_CACHE_DIR, $QR_CACHEABLE;
            $dir = "$IP/images/generated/qrcode/";
            if (!file_exists($dir)) {
                mkdir($dir, 0777, true);
            }
            $QR_CACHEABLE = true;
            $QR_CACHE_DIR = $dir;
        }
        QRcode::png($wgTitle->getFullUrl(array(
            'ticket_id' => $args['ticket_id'],
            'ticket_key' => $args['ticket_key'],
            'mode' => 'check',
        )));
        exit;
    }

    /** Review closed tickets (completed attempts) */
    function review($args)
    {
        global $wgOut;
        $html = '';
        $result = self::selectTickets($args);
        $result['info']['show_details'] = !empty($args['show_details']);
        $html .= self::selectTicketForm($result['info']);
        if ($result['total'])
            $html .= self::xelement('p', NULL, wfMsg('easytests-ticket-count', $result['total'], 1 + $result['page'] * $result['perpage'], count($result['tickets'])));
        else
            $html .= self::xelement('p', NULL, wfMsg('easytests-no-tickets'));
        $html .= self::getTicketTable($result['tickets'], !empty($args['show_details']));
        $html .= self::getPages($result['info'], ceil($result['total'] / $result['perpage']), $result['page']);
        $wgOut->setPageTitle(wfMsg('easytests-review-pagetitle'));
        $wgOut->addHTML($html);
    }

    /** Select tickets from database */
    static function selectTickets($args)
    {
        $dbr = wfGetDB(DB_SLAVE);
        $info = array(
            'mode' => 'review',
            'quiz_name' => '',
            'variant_hash_crc32' => '',
            'user_text' => '',
            'start_time_min' => '',
            'start_time_max' => '',
            'end_time_min' => '',
            'end_time_max' => '',
        );
        $where = array('tk_end_time IS NOT NULL', 'tk_test_id=test_id');
        $args = $args + array(
                'quiz_name' => '',
                'variant_hash_crc32' => '',
                'user_text' => '',
                'start_time_min' => '',
                'start_time_max' => '',
                'end_time_min' => '',
                'end_time_max' => '',
                'perpage' => '',
                'page' => '',
            );
        if (isset($args['quiz_name']) && ($t = Title::newFromText('Quiz:' . $args['quiz_name']))) {
            $where['test_page_title'] = $t->getText();
            $info['quiz_name'] = $t->getText();
        }
        $crc = $args['variant_hash_crc32'];
        if (preg_match('/^\d+$/s', $crc)) {
            $where[] = "crc32(tk_variant)=$crc";
            $info['variant_hash_crc32'] = $crc;
        }
        if ($u = $args['user_text']) {
            $info['user_text'] = $u;
            $u = $dbr->addQuotes($u);
            $where[] = "(INSTR(tk_user_text,$u)>0 OR INSTR(tk_displayname,$u)>0)";
        }
        if (($d = $args['start_time_min']) && ($ts = wfTimestamp(TS_MW, $d))) {
            $info['start_time_min'] = $d;
            $where[] = "tk_start_time>=$ts";
        }
        if (($d = $args['start_time_max']) && ($ts = wfTimestamp(TS_MW, $d))) {
            $info['start_time_max'] = $d;
            $where[] = "tk_start_time<=$ts";
        }
        if (($d = $args['end_time_min']) && ($ts = wfTimestamp(TS_MW, $d))) {
            $info['end_time_min'] = $d;
            $where[] = "tk_end_time>=$ts";
        }
        if (($d = $args['end_time_max']) && ($ts = wfTimestamp(TS_MW, $d))) {
            $info['end_time_max'] = $d;
            $where[] = "tk_end_time<=$ts";
        }
        $tickets = array();
        if (!($perpage = $args['perpage']))
            $perpage = 20;
        $info['perpage'] = $perpage;
        $page = $args['page'];
        if ($page <= 0)
            $page = 0;
        $result = $dbr->select(array('et_ticket', 'et_test'), '*', $where, __FUNCTION__, array(
            'ORDER BY' => 'tk_start_time DESC',
            'LIMIT' => $perpage,
            'OFFSET' => $perpage * $page,
            'SQL_CALC_FOUND_ROWS',
        ));
        while ($row = $dbr->fetchObject($result)) {
            $row = (array) $row;
            /* Recalculate scores */
            if ($row['tk_end_time'] !== NULL && $row['tk_score'] === NULL)
                self::recalcTicket($row);
            $tickets[] = $row;
        }
        $dbr->freeResult($result);
        $total = $dbr->query('SELECT FOUND_ROWS()');
        $total = $dbr->fetchRow($total);
        $total = $total[0];
        if ($page * $perpage >= $total)
            $page = intval($total / $perpage);
        return array(
            'info' => $info,
            'page' => $page,
            'perpage' => $perpage,
            'total' => $total,
            'tickets' => $tickets,
        );
    }

    /** Recalculate scores for a completed ticket */
    static function recalcTicket(&$ticket)
    {
        if ($ticket['tk_end_time'] === NULL)
            return;
        $test = self::loadTest(array('id' => $ticket['tk_test_id']), $ticket['tk_variant']);
        $testresult = self::checkOrLoadResult($ticket, $test, array());
        $update = array(
            /* Testing result to be shown in the table.
               Nothing relies on these values. */
            'tk_score' => $testresult['score'],
            'tk_score_percent' => $testresult['score_percent'],
            'tk_correct' => $testresult['correct_count'],
            'tk_correct_percent' => $testresult['correct_percent'],
            'tk_pass' => $testresult['passed'] ? 1 : 0,
        );
        $ticket = array_merge($ticket, $update);
        $dbw = wfGetDB(DB_MASTER);
        $dbw->update('et_ticket', $update, array('tk_id' => $ticket['tk_id']), __METHOD__);
    }

    /** Get HTML form for selecting tickets */
    static function selectTicketForm($info)
    {
        global $wgTitle;
        $html = '';
        $html .= Html::hidden('mode', 'review');
        $html .= Xml::inputLabel(wfMsg('easytests-quiz') . ': ', 'quiz_name', 'quiz_name', 30, $info['quiz_name']) . '<br />';
        $html .= Xml::inputLabel(wfMsg('easytests-variant') . ': ', 'variant_hash_crc32', 'variant_hash_crc32', 10, $info['variant_hash_crc32']) . '<br />';
        $html .= Xml::inputLabel(wfMsg('easytests-user') . ': ', 'user_text', 'user_text', 30, $info['user_text']) . '<br />';
        $html .= Xml::inputLabel(wfMsg('easytests-start') . ': ', 'start_time_min', 'start_time_min', 19, $info['start_time_min']);
        $html .= Xml::inputLabel(wfMsg('easytests-to'), 'start_time_max', 'start_time_max', 19, $info['start_time_max']) . '<br />';
        $html .= Xml::inputLabel(wfMsg('easytests-end') . ': ', 'end_time_min', 'end_time_min', 19, $info['end_time_min']);
        $html .= Xml::inputLabel(wfMsg('easytests-to'), 'end_time_max', 'end_time_max', 19, $info['end_time_max']) . '<br />';
        $html .= Xml::inputLabel(wfMsg('easytests-perpage') . ': ', 'perpage', 'perpage', 5, $info['perpage']) . ' &nbsp; ';
        $html .= Xml::checkLabel(wfMsg('easytests-show-details'), 'show_details', 'show_details', $info['show_details']) . '<br />';
        $html .= Xml::submitButton(wfMsg('easytests-select-tickets'));
        $html = self::xelement('form', array('method' => 'GET', 'action' => $wgTitle->getFullUrl(), 'class' => 'easytests-select-tickets'), $html);
        return $html;
    }

    /** Get HTML table with tickets */
    static function getTicketTable($tickets, $showDetails = false)
    {
        global $wgTitle, $wgUser;
        $skin = $wgUser->getSkin();
        $tr = array();
        foreach (explode(' ', 'ticket-id quiz quiz-title variant user start end duration ip score correct') as $i) {
            $tr[] = self::xelement('th', NULL, wfMsg("easytests-$i"));
        }
        $html = array($tr);
        $detailKeys = array();
        // ID[LINK] TEST_ID TEST[LINK] VARIANT_CRC32 USER START END DURATION IP
        foreach ($tickets as &$t) {
            $f = self::formatTicket($t);
            if ($showDetails) {
                $t['tk_details'] = $t['tk_details'] ? json_decode($t['tk_details'], true) : array();
                $detailKeys += $t['tk_details'];
            }
            $tr = array();
            /* 1. Ticket ID + link to standard results page */
            $tr[] = self::xelement('a', array('href' => $wgTitle->getFullUrl(array(
                'mode' => 'check',
                'showtut' => 1,
                'ticket_id' => $t['tk_id'],
                'ticket_key' => $t['tk_key'],
            ))), $t['tk_id']);
            /* 2. Quiz name + link to its page + link to quiz form */
            if ($t['test_id']) {
                $name = $t['test_page_title'];
                $testtry = $wgTitle->getFullUrl(array('id' => $t['test_id']));
                $testhref = Title::newFromText('Quiz:' . $t['test_page_title'])->getFullUrl();
                $tr[] = self::xelement('a', array('href' => $testhref), $name) . ' (' .
                    self::xelement('a', array('href' => $testtry), wfMsg('easytests-try')) . ')';
                $tr[] = $t['test_name'] ?: $name;
            } /* Or 2. Quiz ID in the case when it is not found */
            else {
                $tr[] = self::xelement('s', array('class' => 'easytests-dead'), $t['tk_test_page_title']);
                $tr[] = '';
            }
            /* 3. Variant CRC32 + link to printable version of this variant */
            $a = sprintf("%u", crc32($t['tk_variant']));
            $href = $wgTitle->getFullUrl(array(
                'mode' => 'print',
                'id' => $t['tk_test_id'],
                'edit' => 1,
                'ticket_id' => $t['tk_id'],
                'ticket_key' => $t['tk_key'],
            ));
            $a = self::xelement('a', array('href' => $href), $a);
            $tr[] = $a;
            /* 4. User link and/or name/displayname */
            $tr[] = $f['name'];
            /* 5. Start time in YYYY-MM-DD HH:MM:SS format */
            $tr[] = $f['start'];
            /* 6. End time in YYYY-MM-DD HH:MM:SS format */
            $tr[] = $f['end'];
            /* 7. Test duration in Xd HH:MM:SS format */
            $tr[] = $f['duration'];
            /* 8. User IP */
            $tr[] = $t['tk_user_ip'];
            /* 9. Score and % */
            $tr[] = self::wrapResult($t['tk_score'], $t['tk_score_percent'], '');
            /* 10. Correct answers count and % */
            $tr[] = self::wrapResult($t['tk_correct'], $t['tk_correct_percent'], '');
            /* Format HTML row */
            $row = array();
            foreach ($tr as $i => &$td) {
                $attr = array();
                if ($i == 0 || $i == 1) {
                    $attr['style'] = 'text-align: center';
                    if ($i == 0 && !$t['tk_reviewed']) {
                        // Mark non-reviewed rows
                        $attr['class'] = 'easytests-incoming';
                    }
                } elseif ($i == 8 || $i == 9) {
                    $attr['class'] = $t['tk_pass'] ? 'easytests-pass' : 'easytests-fail';
                }
                $row[] = self::xelement('td', $attr, $td);
            }
            $html[] = $row;
        }
        if ($showDetails) {
            $detailKeys = array_keys($detailKeys);
            foreach ($detailKeys as $k) {
                $html[0][] = self::xelement('th', NULL, htmlspecialchars($k));
            }
            foreach ($html as $i => &$row) {
                if ($i > 0) {
                    foreach ($detailKeys as $k) {
                        $row[] = self::xelement('td', NULL, @$tickets[$i - 1]['tk_details'][$k]);
                    }
                }
            }
        }
        foreach ($html as $i => &$row) {
            $row = self::xelement('tr', NULL, implode('', $row));
        }
        $html = self::xelement('table', array('class' => 'easytests-review'), implode('', $html));
        return $html;
    }

    /**
     * ***********
     * REVIEW MODE
     * ***********
     *
     * Get HTML page list
     */
    static function getPages($args, $npages, $curpage)
    {
        global $wgTitle;
        if ($npages <= 1)
            return '';
        $pages = array();
        if ($curpage > 0)
            $pages[] = self::xelement('a', array('href' => $wgTitle->getFullUrl(array('page' => $curpage - 1) + $args)), '‹');
        for ($i = 0; $i < $npages; $i++) {
            if ($i != $curpage)
                $pages[] = self::xelement('a', array('href' => $wgTitle->getFullUrl(array('page' => $i) + $args)), $i + 1);
            else
                $pages[] = self::xelement('b', array('class' => 'easytests-curpage'), $i + 1);
        }
        if ($curpage < $npages - 1)
            $pages[] = self::xelement('a', array('href' => $wgTitle->getFullUrl(array('page' => $curpage + 1) + $args)), '›');
        $html = wfMsg('easytests-pages');
        $html .= implode(' ', $pages);
        $html = self::xelement('p', array('class' => 'easytests-pages'), $html);
        return $html;
    }

    /** Return HTML content for "Please select test to review results" form */
    static function getSelectTestForReviewForm($args)
    {
        global $wgTitle;
        $form = '';
        $form .= wfMsg('easytests-quiz') . ': ';
        $name = isset($args['quiz_name']) ? $args['quiz_name'] : '';
        $form .= self::xelement('input', array('type' => 'text', 'name' => 'quiz_name', 'value' => $name)) . ' ';
        $form .= Xml::submitButton(wfMsg('easytests-select-tickets'));
        $form = self::xelement('form', array('action' => $wgTitle->getLocalUrl(array('mode' => 'review')), 'method' => 'POST'), $form);
        return $form;
    }

    /**
     * ************
     * PRINT MODE
     * ************
     *
     * Display a "dump" for the test:
     * - all questions without information about correct answers
     * - a printable empty table for filling it with answer numbers
     * - a table similar to the previous, but filled with correct answer numbers and question labels ("check-list")
     *   (question label is intended to briefly describe question subject)
     * Check list is shown only to test administrators and users who can read the quiz source article.
     * Note that read access to articles included into the quiz are not checked.
     * CSS page-break styles are specified so you can print this page.
     */
    static function printTest($test, $args, $answers = NULL)
    {
        global $wgOut;
        $html = '';

        $is_adm = self::isAdminForTest($test['test_id']);

        /* TestInfo */
        $ti = wfMsg('easytests-variant-msg', $test['variant_hash_crc32']);
        if ($test['test_intro']) {
            $ti = self::xelement('div', array('class' => 'easytests-intro'), $test['test_intro']) . $ti;
        }

        /* Display question list (with editsection links for admins) */
        $html .= self::xelement('h2', NULL, wfMsg('easytests-question-sheet'));
        $html .= $ti;
        $html .= self::getQuestionList($test['questions'], false, !empty($args['edit']) && $is_adm);

        /* Display questionnaire */
        $html .= Xml::element('hr', array('style' => 'page-break-after: always'), '');
        $html .= self::xelement('h2', NULL, wfMsg('easytests-test-sheet'));
        $html .= $ti;
        $html .= self::getCheckList($test, $args, false);

        /* Display questionnaire filled with user's answers */
        if ($answers !== NULL) {
            $html .= Xml::element('hr', array('style' => 'page-break-after: always'), '');
            $html .= self::xelement('h2', NULL, wfMsg('easytests-user-answers'));
            $html .= wfMsg('easytests-variant-msg', $test['variant_hash_crc32']);
            $html .= self::getCheckList($test, $args, false, $answers);
        }

        if ($is_adm) {
            /* Display check-list to users who can read source article */
            $html .= self::xelement('h2', array('style' => 'page-break-before: always'), wfMsg('easytests-answer-sheet'));
            $html .= $ti;
            $html .= self::getCheckList($test, $args, true);
        }

        $wgOut->setPageTitle(wfMsg('easytests-print-pagetitle', $test['test_name']));
        $wgOut->addHTML($html);
    }

    /**
     * Display a table with question numbers, correct answers, statistics and labels when $checklist is TRUE
     * Display a table with question numbers and two blank columns - "answer" and "remark" when $checklist is FALSE
     * Display a table with question numbers and user answers when $answers is specified */
    static function getCheckList($test, $args, $checklist = false, $answers = NULL)
    {
        $table = '';
        $table .= self::xelement('th', array('class' => 'easytests-tn'), wfMsg('easytests-table-number'));
        $table .= self::xelement('th', NULL, wfMsg('easytests-table-answer'));
        if ($checklist) {
            $table .= self::xelement('th', NULL, wfMsg('easytests-table-stats'));
            $table .= self::xelement('th', NULL, wfMsg('easytests-table-label'));
        } else
            $table .= self::xelement('th', NULL, wfMsg('easytests-table-remark'));
        foreach ($test['questions'] as $k => $q) {
            $row = '<td>' . ($k + 1) . '</td>';
            if ($checklist) {
                /* build a list of correct choice indexes in the shuffled array (or texts for free-text questions) */
                $correct_indexes = array();
                foreach ($q['correct_choices'] as $c) {
                    $correct_indexes[] = $q['correct_count'] < count($q['choices']) ? $c['index'] : $c['ch_text'];
                }
                $row .= '<td>' . htmlspecialchars(implode(', ', $correct_indexes)) . '</td>';
                if ($q['tries']) {
                    $row .= '<td>' . $q['correct_tries'] . '/' . $q['tries'] .
                        ' ≈ ' . round($q['correct_tries'] * 100.0 / $q['tries']) . '%</td>';
                } else
                    $row .= '<td></td>';
                $row .= '<td>' . $q['qn_label'] . '</td>';
            } elseif ($answers && !empty($answers[$q['qn_hash']])) {
                $ans = $answers[$q['qn_hash']];
                $ch = !empty($ans['cs_choice_num']) ? $q['choiceByNum'][$ans['cs_choice_num']] : NULL;
                $row .= '<td>' . ($ch ? $ch['index'] : $ans['cs_text']) . '</td><td' . ($ans['cs_correct'] ? '' : ' class="easytests-fail-bd"') . '>' .
                    wfMsg('easytests-is-' . ($ans['cs_correct'] ? 'correct' : 'incorrect')) . '</td>';
            } else
                $row .= '<td></td><td></td>';
            $table .= '<tr>' . $row . '</tr>';
        }
        $table = self::xelement('table', array('class' => $checklist ? 'easytests-checklist' : 'easytests-questionnaire'), $table);
        return $table;
    }

    static function showTicket($test)
    {
        global $wgOut, $wgTitle;
        $ticket = self::createTicket($test, NULL);
        $link = $wgTitle->getFullUrl(array(
            'id' => $test['test_id'],
            'ticket_id' => $ticket['tk_id'],
            'ticket_key' => $ticket['tk_key'],
        ));
        $wgOut->setPageTitle(wfMsg('easytests-pagetitle', $test['test_name']));
        $wgOut->addHTML(
            wfMsg('easytests-ticket-link') . ': <a href="' . $link . '">' . htmlspecialchars($link) . '</a>'
        );
    }
}
