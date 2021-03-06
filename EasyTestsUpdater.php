<?php

/**
 * This class is responsible for parsing quiz articles and writing parsed quizzes into DB.
 * It is done using a recursive DOM parser and DOMParseUtilsUpdated class.
 */
class EasyTestsUpdater
{
    const FIELD_HTML = 0;
    const FIELD_STRING = 1;
    const FIELD_MODE = 2;
    const FIELD_BOOL = 3;
    const FIELD_INT = 4;

    static $test_field_types = array(
        'name' => 1,
        'intro' => 0,
        'mode' => 2,
        'shuffle_questions' => 3,
        'shuffle_choices' => 3,
        'secret' => 3,
        'limit_questions' => 4,
        'ok_percent' => 4,
        'autofilter_min_tries' => 4,
        'autofilter_success_percent' => 4,
        'user_details' => 1,
    );
    static $test_default_values = array(
        'test_name' => '',
        'test_intro' => '',
        'test_mode' => 'TEST',
        'test_shuffle_questions' => 0,
        'test_shuffle_choices' => 0,
        'test_limit_questions' => 0,
        'test_ok_percent' => 80,
        'test_autofilter_min_tries' => 0,
        'test_autofilter_success_percent' => 90,
    );
    static $test_keys;
    static $regexp_test, $regexp_test_nq, $regexp_question, $regexp_true, $regexp_correct, $regexp_form;
    static $qn_keys = array('choice', 'choices', 'correct', 'corrects', 'label', 'explanation', 'comments', 'correct-matches', 'correct-parallels');

    /* Parse wiki-text $text without TOC, heading numbers and EditSection links turned on */
    static $parser = NULL, $parserOptions;

    /**
     * @param $article
     * @param $text
     * @return mixed|null|string|string[]
     */
    static function parse($article, $text)
    {
        global $wgUser;
        /* Compatibility with MagicNumberedHeadings extension */
        if (defined('MAG_NUMBEREDHEADINGS') && ($mag = MagicWord::get(MAG_NUMBEREDHEADINGS)))
            $mag->matchAndRemove($text);
        MagicWord::get('toc')->matchAndRemove($text);
        MagicWord::get('forcetoc')->matchAndRemove($text);
        MagicWord::get('noeditsection')->matchAndRemove($text);
        /* Disable insertion of question statistics into editsection links */
        EasyTests::$disableQuestionInfo = true;
        if (!self::$parser) {
            self::$parser = new Parser;
            self::$parserOptions = new ParserOptions($wgUser);
            self::$parserOptions->setNumberHeadings(false);
            self::$parserOptions->setEditSection(true);
            self::$parserOptions->setIsSectionPreview(true);
        }
        $html = self::$parser->parse("__NOTOC__\n$text", $article->getTitle(), self::$parserOptions)->getText();
        EasyTests::$disableQuestionInfo = false;
        return $html;
    }

    /**
     * Get regular expressions
     */
    static function getRegexps()
    {
        global $egEasyTestsContLang;
        $lang = $egEasyTestsContLang ? $egEasyTestsContLang : true;
        $test_regexp = array();
        $qn_regexp = array();
        self::$test_keys = array_keys(self::$test_field_types);

        foreach (self::$test_keys as $k) {
            $test_regexp[] = '(' . wfMsgReal("easytests-parse-test_$k", NULL, true, $lang, false) . ')';
        }
        foreach (self::$qn_keys as $k) {
            $qn_regexp[] = '(' . wfMsgReal("easytests-parse-$k", NULL, true, $lang, false) . ')';
        }
        $test_regexp_nq = $test_regexp;
        array_unshift($test_regexp, '(' . wfMsgReal('easytests-parse-question', NULL, true, $lang, false) . ')');
        array_unshift($test_regexp, '(' . wfMsgReal('easytests-parse-question-match', NULL, true, $lang, false) . ')');
        array_unshift($test_regexp, '(' . wfMsgReal('easytests-parse-question-parallel', NULL, true, $lang, false) . ')');

        self::$regexp_test = str_replace('/', '\\/', implode('|', $test_regexp));
        self::$regexp_test_nq = '()()' . str_replace('/', '\\/', implode('|', $test_regexp_nq));
        self::$regexp_question = str_replace('/', '\\/', implode('|', $qn_regexp));
        self::$regexp_true = wfMsgReal('easytests-parse-true', NULL, true, $lang, false);
        self::$regexp_correct = wfMsgReal('easytests-parse-correct', NULL, true, $lang, false);
        self::$regexp_correct = wfMsgReal('easytests-parse-correct-matches', NULL, true, $lang, false);
        self::$regexp_correct = wfMsgReal('easytests-parse-correct-parallels', NULL, true, $lang, false);
    }

    /* Transform quiz field value according to its type */
    /**
     * @param $field
     * @param $value
     * @return int|string
     */
    static function transformFieldValue($field, $value)
    {
        $t = self::$test_field_types[$field];
        if ($t > 0) /* not an HTML code */ {
            $value = trim(strip_tags($value));
            if ($t == 2) /* mode */
                $value = strpos(strtolower($value), 'tutor') !== false ? 'TUTOR' : 'TEST';
            elseif ($t == 3) /* boolean */ {
                $re = str_replace('/', '\\/', self::$regexp_true);
                $value = preg_match("/$re/uis", $value) ? 1 : 0;
            } elseif ($t == 4) /* integer */
                $value = intval($value);
            /* else ($t == 1) // just a string */
        } else
            $value = trim($value);
        return $value;
    }

    /**
     * @param $s
     * @return string
     */
    static function textlog($s)
    {
        if (is_object($s))
            $s = DOMParseUtilsUpdated::saveChildren($s);
        return trim(str_replace("\n", " ", strip_tags($s)));
    }

    /* Check last question for correctness */
    /**
     * @param $questions
     * @param $log
     */
    static function checkLastQuestion(&$questions, &$log)
    {
        $last_question = $questions[count($questions) - 1];
        $incorrect = 0;
        $ok = false;
        if ($last_question['choices']) {
            foreach ($last_question['choices'] as $lchoise) {
                if ($lchoise['ch_correct']) {
                    $incorrect++;
                }
            }
        }
        if (empty($last_question['choices'])) {
            $log .= "[ERROR] No choices defined for question: " . self::textlog($last_question['qn_text']) . "\n";
        } elseif (!$incorrect) {
            $log .= "[ERROR] No correct choices for question: " . self::textlog($last_question['qn_text']) . "\n";
        } else {
            $ok = true;
            if ($incorrect >= count($last_question['choices'])) {
                if ($last_question['qn_type'] != 'simple' and $last_question['qn_type'] != 'free-text') {
                    $log .= "[INFO] Defined \"" . $last_question['qn_type'] . "\" question: " . self::textlog($last_question['qn_text']) . "\n";
                    $last_question['choices'] = self::transformChoices($last_question['choices'], $last_question['qn_type']);
                    $questions[count($questions) - 1] = $last_question;
                } else {
                    $log .= "[INFO] All choices are marked correct, question will be free-text: " . self::textlog($last_question['qn_text']) . "\n";
                    $questions[count($questions) - 1]['qn_type'] = 'free-text';
                }
            }
        }
        if (!$ok) {
            array_pop($questions);
        }
    }

    /* states: */
    const ST_OUTER = 0;     /* Outside everything */
    const ST_QUESTION = 1;  /* Inside question */
    const ST_PARAM_DD = 2;  /* Waiting for <dd> with quiz field value */
    const ST_CHOICE = 3;    /* Inside single choice section */
    const ST_CHOICES = 4;   /* Inside multiple choices section */

    /**
     * parse quiz using a state machine
     *
     * @param $html
     * @return array
     */
    static function parseQuiz($html)
    {
        self::getRegexps();
        $log = '';
        $document = DOMParseUtilsUpdated::loadDOM($html);

        /* Stack: [ [ Element, ChildIndex, AlreadyProcessed, AppendStrlen ] , [ ... ] ] */
        $stack = array(array($document->documentElement, 0, false, 0));

        $st = self::ST_OUTER;   /* State index */
        $append = NULL;         /* Array(&$str) or NULL. When array(&$str), content is appended to $str. */

        /* Variables: */
        $form = array();        /* Form definition */
        $q = array();           /* Questions */
        $quiz = self::$test_default_values; /* Quiz field => value */
        $field = '';            /* Current parsed field */
        $correct = 0; /* Is current choice(s) section for correct choices */
        $end = true;

        /* Loop through all elements: */
        while ($stack) {
            list($nodeDOMElement, $i, $h, $l) = $stack[count($stack) - 1];
            if ($i >= $nodeDOMElement->childNodes->length) {
                array_pop($stack);
                if ($append && !$h) {
                    /* Remove children from the end of $append[0]
                       and append element itself */
                    $append[0] = substr($append[0], 0, $l) . $document->saveXML($nodeDOMElement);
                } elseif ($h && $stack) {
                    /* Propagate "Already processed" value */
                    $stack[count($stack) - 1][2] = true;
                }
                continue;
            }
            //increment ChildIndex
            $stack[count($stack) - 1][1]++;

            //get first child element
            $element = $nodeDOMElement->childNodes->item($i);

            if ($element->nodeType == XML_ELEMENT_NODE) {
                // we don't need to parse <body> element
                if ($element->nodeName !== 'body') {
                    $end = false;

                    // find section definition
                    if (preg_match('/^h(\d)$/is', $element->nodeName, $m)) {
                        $level = $m[1];
                        $log_el = str_repeat('=', $level);
                        /* Remove editsection links */
                        $editsection = NULL;
                        if ($element->childNodes->length) {
                            foreach ($element->childNodes as $span) {
                                if ($span->nodeName == 'span' && ($span->getAttribute('class') == 'editsection' ||
                                        $span->getAttribute('class') == 'mw-editsection')) {
                                    $element->removeChild($span);
                                    $editsection = $document->saveXML($span);
                                }
                            }
                        }

                        $log_el = $log_el . self::textlog($element) . $log_el;

                        /* Match question/parameter section title */
                        $chk = DOMParseUtilsUpdated::checkNode($element, self::$regexp_test, true);
                        if ($chk) {
                            if ($chk[1][1][0]) {
                                if ($q) {
                                    self::checkLastQuestion($q, $log);
                                }
                                if (isset($chk[1][1]['type'])) {
                                    $question_type = $chk[1][1]['type'];
                                } else {
                                    $question_type = '';
                                }
                                /* Question section - found */
                                $log .= "[INFO] Begin question section: $log_el\n";
                                $st = self::ST_QUESTION;
                                if (preg_match('/\?([^"\'\s]*)/s', $editsection, $m)) {

                                    /* Extract page title and section number from editsection link */
                                    $es = array();
                                    parse_str(htmlspecialchars_decode($m[1]), $es);
                                    preg_match('/\d+/', $es['section'], $m);
                                    $anch = $es['title'] . '|' . $m[0];
                                } else {
                                    $anch = NULL;
                                }
                                // define question default array
                                $q[] = array(
                                    'qn_label' => DOMParseUtilsUpdated::saveChildren($chk[0], true),
                                    'qn_anchor' => $anch,
                                    'qn_editsection' => $editsection,
                                    'qn_type' => $question_type ? $question_type : 'simple'
                                );
                                $append = array(&$q[count($q) - 1]['qn_text']);

                            } else {
                                /* INFO: Parameter section inside question section / choice section */
                                $log .= "[WARN] Field section must come before questions: $log_el\n";
                            }
                        } elseif ($st == self::ST_QUESTION || $st == self::ST_CHOICE || $st == self::ST_CHOICES) {
                            $chk = DOMParseUtilsUpdated::checkNode($element, self::$regexp_question, true);
                            if ($chk) {
                                /* Question sub-section */
                                $sid = '';
                                foreach ($chk[1] as $i => $c) {
                                    if ($c[0]) {
                                        $sid = self::$qn_keys[$i];
                                        break;
                                    }
                                }
                                if (!$sid) {
                                    /* This should never happen ! */
                                    $line = __FILE__ . ':' . __LINE__;
                                    $log .= "[ERROR] MYSTICAL BUG: Unknown question field at $line in: $log_el\n";
                                } elseif ($sid == 'comments') {
                                    /* Question comments */
                                    $log .= "[INFO] Begin question comments: $log_el\n";
                                    $st = self::ST_QUESTION;
                                    $append = NULL;
                                } elseif ($sid == 'explanation' || $sid == 'label') {
                                    /* Question field */
                                    $log .= "[INFO] Begin question $sid: $log_el\n";
                                    $st = self::ST_QUESTION;
                                    $append = array(&$q[count($q) - 1]["qn_$sid"]);
                                } else {
                                    /* Some kind of choice(s) section */
                                    $correct = ($sid == 'correct' || $sid == 'corrects' || $sid == 'correct-matches' || $sid == 'correct-parallels') ? 1 : 0;
                                    $lc = $correct ? 'correct choice' : 'choice';
                                    if ($sid == 'correct' || $sid == 'choice') {
                                        $log .= "[INFO] Begin single $lc section: $log_el\n";
                                        $q[count($q) - 1]['choices'][] = array('ch_correct' => $correct);
                                        $st = self::ST_CHOICE;
                                        $append = array(&$q[count($q) - 1]['choices'][count($q[count($q) - 1]['choices']) - 1]['ch_text']);
                                    } else {
                                        $log .= "[INFO] Begin multiple $lc section: $log_el\n";
                                        $st = self::ST_CHOICES;
                                        $append = NULL;
                                    }
                                }
                            } else {
                                /* INFO: unknown heading inside question */
                                $log .= "[WARN] Unparsed heading inside question: $log_el\n";
                                $end = true;
                            }
                        } else {
                            /* INFO: unknown heading */
                            $log .= "[WARN] Unparsed heading outside question: $log_el\n";
                            $end = true;
                        }
                    } /* <dt> for a parameter */
                    elseif (($st == self::ST_OUTER || $st == self::ST_PARAM_DD) && $element->nodeName == 'dt') {
                        $chk = DOMParseUtilsUpdated::checkNode($element, self::$regexp_test_nq, true);
                        $log_el = '; ' . trim(strip_tags(DOMParseUtilsUpdated::saveChildren($element))) . ':';
                        if ($chk) {
                            $field = '';
                            //remove empty elements from array
                            $fields_array = self::trimEmptyElements($chk[1]);
                            foreach ($fields_array as $key => $val) {
                                $field = self::$test_keys[$val['current_index'] - 2]; /* -2 because there are two extra (question) and (form) keys in the beginning */
                                break;
                            }
                            if ($field) {
                                /* Parameter - found */
                                $log .= "[INFO] Begin definition list item for quiz field \"$field\": $log_el\n";
                                $st = self::ST_PARAM_DD;
                            } else {
                                /* This should never happen ! */
                                $line = __FILE__ . ':' . __LINE__;
                                $log .= "[ERROR] MYSTICAL BUG: Unknown quiz field at $line in: $log_el\n";
                            }
                        } else {
                            /* INFO: unknown <dt> key */
                            $log .= "[WARN] Unparsed definition list item: $log_el\n";
                            $end = true;
                        }
                    } /* Value for quiz parameter $field */
                    elseif ($st == self::ST_PARAM_DD && $element->nodeName == 'dd') {
                        $value = self::transformFieldValue($field, DOMParseUtilsUpdated::saveChildren($element));
                        $log .= "[INFO] Quiz $field = " . self::textlog($value) . "\n";
                        $quiz["test_$field"] = $value;
                        $st = self::ST_OUTER;
                        $field = '';
                    } elseif ($st == self::ST_CHOICE && ($element->nodeName == 'ul' || $element->nodeName == 'ol') &&
                        $element->childNodes->length == 1 && !$append[0]
                    ) {
                        /* <ul>/<ol> with single <li> inside choice */
                        $log .= "[INFO] Stripping single-item list from single-choice section";
                        $element = $element->childNodes->item(0);
                        $chk = DOMParseUtilsUpdated::checkNode($element, self::$regexp_correct, true);
                        if ($chk) {
                            $element = $chk[0];
                            $n = count($q[count($q) - 1]['choices']);
                            $q[count($q) - 1]['choices'][$n - 1]['ch_correct'] = 1;
                            $log .= "[INFO] Correct choice marker is present in single-item list";
                        }
                        $append[0] .= trim(DOMParseUtilsUpdated::saveChildren($element));
                    } elseif ($st == self::ST_CHOICE && $element->nodeName == 'p') {
                        if ($append[0])
                            $append[0] .= '<br />';
                        $append[0] .= trim(DOMParseUtilsUpdated::saveChildren($element));
                    } elseif ($st == self::ST_CHOICES && $element->nodeName == 'li') {
                        $chk = DOMParseUtilsUpdated::checkNode($element, wfMsgNoTrans('easytests-parse-correct'), true);
                        $c = $correct;
                        if ($chk) {
                            $element = $chk[0];
                            $c = 1;
                        }
                        $children = DOMParseUtilsUpdated::saveChildren($element);
                        $log .= "[INFO] Parsed " . ($c ? "correct " : "") . "choice: " . trim(strip_tags($children)) . "\n";
                        $q[count($q) - 1]['choices'][] = array(
                            'ch_correct' => $c,
                            'ch_text' => trim($children),
                        );
                    } else {
                        $end = true;
                    }
                }
                if ($end) {
                    /* Save position inside append-string to remove
                       children before appending the element itself */
                    $stack[] = array($element, 0, false, $append ? strlen($append[0]) : 0);
                } else
                    $stack[count($stack) - 1][2] = true;
            } elseif ($append && $element->nodeType == XML_TEXT_NODE && trim($element->nodeValue)) {
                $append[0] .= $element->nodeValue;
            }
            // end while
        }
        if ($q) {
            self::checkLastQuestion($q, $log);
        }
        $quiz['questions'] = $q;
        if (!empty($quiz['test_user_details'])) {
            /* Compatibility with older "Ask User:" */
            foreach (explode(',', $quiz['test_user_details']) as $f) {
                $f = trim($f);
                if ($f) {
                    $form[] = array($f, 'text', true);
                }
            }
        }
        $quiz['test_user_details'] = json_encode($form, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $quiz['test_log'] = $log;
        return $quiz;
    }

    /* Parse $text and update data of the quiz linked to article title */
    /**
     * @param $article
     * @param $text
     * @throws DBUnexpectedError
     */
    static function updateQuiz($article, $text)
    {
        /*
         * defining variables
         */
        $t2q = array();
        $question_keys = array();
        $choices_keys = array();
        $questions = array();
        $choices = array();
        $hashes = array();

        $dbw = wfGetDB(DB_MASTER);
        $html = self::parse($article, $text);
        $quiz = self::parseQuiz($html);
        $quiz['test_page_title'] = $article->getTitle()->getText();
        $quiz['test_id'] = $dbw->selectField('et_test', 'test_id', array('test_page_title' => $quiz['test_page_title']), __METHOD__);

        if (!$quiz['test_id']) {
            $quiz['test_id'] = $article->getId();
        }
        if (!$quiz['questions']) {
            // No questions found.
            // Append error to the top of quiz test_log and return.
            $res = $dbw->select('et_test', '*', array('test_id' => $quiz['test_id']), __METHOD__);
            $row = $dbw->fetchRow($res);
            if (!$row) {
                unset($quiz['questions']);
                $quiz['test_log'] = "[ERROR] Article revision: " . $article->getLatest() . "\n" .
                    "[ERROR] No questions found in this revision, test not parsed!" . "\n" .
                    $quiz['test_log'];
                try {
                    $dbw->insert('et_test', $quiz, __METHOD__);
                } catch (DBQueryError $e) {
                    $quiz['test_log'] = "[CRITICAL ERROR] Can't save test to database!;\n";
                }
            } else {
                $row['test_log'] = preg_replace('/^.*?No questions found in this revision[^\n]*\n/so', '', $row['test_log']);
                $row['test_log'] = "[ERROR] Article revision: " . $article->getLatest() . "\n" .
                    "[ERROR] No questions found in this revision, test not updated!" . "\n" .
                    $row['test_log'];
                $dbw->update(
                    'et_test', array('test_log' => $row['test_log']),
                    array('test_id' => $row['test_id']), __METHOD__
                );
            }
            return;
        }

        foreach ($quiz['questions'] as $i => $q) {
            $hash = $q['qn_text'];
            foreach ($q['choices'] as $c) {
                $hash .= $c['ch_text'];
            }
            $hash = mb_strtolower(preg_replace('/\s+/s', '', $hash));
            $hash = md5($hash);
            foreach ($q['choices'] as $j => $c) {
                $c['ch_question_hash'] = $hash;
                $c['ch_num'] = $j + 1;
                $choices_keys += $c;
                $choices[] = $c;
            }
            $q['qn_hash'] = $hash;
            $hashes[] = $hash;
            unset($q['choices']);
            $question_keys += $q;
            $questions[] = $q;
            $t2q[] = array(
                'qt_test_id' => $quiz['test_id'],
                'qt_question_hash' => $hash,
                'qt_num' => $i + 1,
            );
        }
        foreach ($question_keys as $k => $v) {
            if (!array_key_exists($k, $questions[0])) {
                $questions[0][$k] = '';
            }
        }
        foreach ($choices_keys as $k => $v) {
            if (!array_key_exists($k, $choices[0])) {
                $choices[0][$k] = '';
            }
        }
        unset($quiz['questions']);
        try {
            $dbw->delete('et_question_test', array('qt_test_id' => $quiz['test_id']), __METHOD__);
        } catch (DBUnexpectedError $e) {
            $quiz['test_log'] = "[DATABASE ERROR] Something went wrong \n";
        }

        try {
            $dbw->delete('et_choice', array('ch_question_hash' => $hashes), __METHOD__);
        } catch (DBUnexpectedError $e) {
            $quiz['test_log'] = "[DATABASE ERROR] Something went wrong \n";
        }

        self::insertOrUpdate($dbw, 'et_test', array($quiz), __METHOD__);
        self::insertOrUpdate($dbw, 'et_question', $questions, __METHOD__);
        self::insertOrUpdate($dbw, 'et_question_test', $t2q, __METHOD__);
        self::insertOrUpdate($dbw, 'et_choice', $choices, __METHOD__);
    }

    /* A helper for updating many rows at once (MySQL-specific) */
    /**
     * @param $dbw
     * @param $table
     * @param $rows
     * @param $fname
     * @return mixed
     */
    static function insertOrUpdate($dbw, $table, $rows, $fname)
    {
        global $wgDBtype;
        if ($wgDBtype != 'mysql') {
            die('EasyTests uses MySQL-specific INSERT INTO ... ON DUPLICATE KEY UPDATE by now. Fix it if you want.');
        }
        $keys = array_keys($rows[0]);
        $sql = 'INSERT INTO ' . $dbw->tableName($table) . ' (' . implode(',', $keys) . ') VALUES ';
        foreach ($rows as &$row) {
            $r = array();
            foreach ($keys as $k)
                if (array_key_exists($k, $row))
                    $r[] = $row[$k];
                else
                    $r[] = '';
            $row = '(' . $dbw->makeList($r) . ')';
        }
        $sql .= implode(',', $rows);
        foreach ($keys as &$key)
            $key = "`$key`=VALUES(`$key`)";
        $sql .= ' ON DUPLICATE KEY UPDATE ' . implode(',', $keys);
        return $dbw->query($sql, $fname);
    }

    /**
     * Remove empty elements from array
     *
     * @param $arr
     * @return array
     */
    static function trimEmptyElements($arr)
    {
        $new_arr = array();
        foreach ($arr as $key => $val) {
            if ($val[0] !== '') {
                $val['current_index'] = $key;
                array_push($new_arr, $val);
            }
        }
        return $new_arr;
    }

    /**
     * Define correct choices order
     *
     * @param array $choices
     * @param string $question_type
     * @return array
     */
    private static function transformChoices($choices = [], $question_type = '')
    {
        if (!empty($choices)) {
            switch ($question_type) {
                case 'parallel':
                    $choices = self::parseParallelChoices($choices);
                    break;
                case 'order':
                    for ($i = 0; $i < count($choices); $i++) {
                        $choices[$i]['ch_order_index'] = $i;
                    }
                    break;
            }
        }
        return $choices;
    }

    /**
     * @param array $choices
     * @return array
     */
    private static function parseParallelChoices($choices = array())
    {
        $new_choices = array();
        foreach ($choices as $key => $choice) {
            $ch = $choice;
            $exploded_str = explode(':=', $choice['ch_text']);

            $ch['ch_text'] = $exploded_str[0];
            $ch['ch_parallel'] = $exploded_str[1];

            array_push($new_choices, $ch);
        }
        return $new_choices;
    }
}
