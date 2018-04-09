<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * @author Katherine Fomenko
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 */
$messages = array();

$messages['en'] = array(
    'easytests' => 'EasyTests Mediawiki',
    'easytests-actions' => '<p><a href="$2">Try the quiz "$1"</a> &nbsp; | &nbsp; <a href="$3">Printable version</a></p>',
    'easytests-show-parselog' => '[+] Show quiz parse log',
    'easytests-hide-parselog' => '[-] Hide quiz parse log',
    'easytests-complete-stats' => 'Correct answers: $1/$2 ($3 %)',
    'easytests-no-complete-stats' => '(no statistics)',

    'group-secretquiz' => 'Secret quiz access group',
    'group-secretquiz-member' => 'has access to secret quizzes',

    /* Errors */
    'easytests-no-test-id-title' => 'Quiz ID is undefined!',
    'easytests-no-test-id-text' => 'You opened a link without valid quiz ID to run.',
    'easytests-test-not-found-title' => 'Quiz not found',
    'easytests-test-not-found-text' => 'Quiz with this ID is not found in database!',
    'easytests-check-no-ticket-title' => 'Incorrect check link',
    'easytests-check-no-ticket-text' => 'You want to check the test, but no correct ticket ID is present in the request.<br />Try <a href="$2">the quiz «$1»</a> again.',
    'easytests-review-denied-title' => 'Review access denied',
    'easytests-review-option' =>
        'You opened a link without valid quiz ID to run.

Maybe you\'ve meant to open quiz result review form?

If so, enter the ID (article title) of a test \'\'\'to source of which you do have read\'\'\' into the field below and click "Select results".',
    'easytests-review-denied-all' =>
        'Reviewing completion attempts for \'\'\'all\'\'\' quizzes is available only for easytests administrators.

However, you can review completion attempts for quizzes to source of which you do have access.

To do so, enter the ID (article title) of such a test into the field below and click "Select results".',
    'easytests-review-denied-quiz' =>
        'Reviewing completion attempts for \'\'\'this\'\'\' quiz is not available for only.

Try entering the ID (article title) of quiz to source of which you do have access.

Then click "Select results" again.',

    'easytests-pagetitle' => '$1 — questions',
    'easytests-print-pagetitle' => '$1 — printable version',
    'easytests-check-pagetitle' => '$1 — results',
    'easytests-review-pagetitle' => 'MediaWiki Quizzer — review test results',

    'easytests-question' => 'Question $1',
    'easytests-freetext' => 'Enter your answer:',
    'easytests-counter-format' => '%%H%%:%%M%%:%%S%% elapsed.',
    'easytests-refresh-to-retry' => 'This form is already sent. To retry the quiz you must reload the page. Reload the page?',
    'easytests-prompt' => 'Your name',
    'easytests-prompt-needed' => 'mandatory field',
    'easytests-empty' => 'Please fill in all mandatory fields!',
    'easytests-submit' => 'Submit answers',
    'easytests-question-sheet' => 'Question List',
    'easytests-test-sheet' => 'Questionnaire',
    'easytests-user-answers' => 'User Answers',
    'easytests-is-correct' => 'Correct',
    'easytests-is-incorrect' => 'Incorrect',
    'easytests-answer-sheet' => 'Control Sheet',
    'easytests-table-number' => '№',
    'easytests-table-answer' => 'Answer',
    'easytests-table-stats' => 'Statistics',
    'easytests-table-label' => 'Label',
    'easytests-table-remark' => 'Remarks',
    'easytests-right-answer' => 'Correct answer',
    'easytests-your-answer' => 'Selected answer',
    'easytests-variant-already-seen' => '<b style="color: red">You have already tried this variant.</b>',
    'easytests-try-another' => '<a href="$1">Try another one.</a>',
    'easytests-ticket-details' => '<p>User: $1. Time: $2 — $3 ($4).</p>',
    'easytests-ticket-reviewed' => '<b>This result is already reviewed by administrator.</b>',
    'easytests-results' => 'Results',
    'easytests-variant-msg' => '<p>Variant $1.</p>',
    'easytests-right-answers' => 'Correct answers',
    'easytests-score-long' => 'Score',
    'easytests-random-correct' => '<i>Note that the average correct answers with randoms selection ≈ <b>$1</b></i>',
    'easytests-test-average' => 'All users average correct answers to this test ≈ <b>$1 %</b>',
    'easytests-try-quiz' => 'Try <a href="$2">the quiz «$1»</a>!',
    'easytests-try' => 'try',
    'easytests-congratulations' => 'You passed the quiz! Insert the following HTML code into your blog or homepage:',
    'easytests-explanation' => 'Explanation',
    'easytests-anonymous' => 'Anonymous',
    'easytests-select-tickets' => 'Select results',
    'easytests-ticket-count' => 'Found $1, showing $3 from $2.',
    'easytests-no-tickets' => 'No tickets found.',
    'easytests-pages' => 'Pages: ',

    /* Names of various fields */
    'easytests-ticket-id' => 'Ticket ID',
    'easytests-quiz' => 'Quiz',
    'easytests-quiz-title' => 'Quiz title',
    'easytests-variant' => 'Variant',
    'easytests-who' => 'Display name',
    'easytests-user' => 'User',
    'easytests-start' => 'Start time',
    'easytests-end' => 'End time',
    'easytests-duration' => 'Duration',
    'easytests-ip' => 'IP address',
    'easytests-perpage' => 'Count on one page',
    'easytests-show-details' => 'show user details',
    'easytests-score' => 'Score',
    'easytests-correct' => 'Correct',

    /* Regular expressions used to parse various quiz field names */
    'easytests-parse-test_name' => 'Name|Title',
    'easytests-parse-test_intro' => 'Intro|Short[\s_]*Desc(?:ription)?',
    'easytests-parse-test_mode' => 'Mode',
    'easytests-parse-test_shuffle_questions' => 'Shuffle[\s_]*questions',
    'easytests-parse-test_shuffle_choices' => 'Shuffle[\s_]*answers|Shuffle[\s_]*choices',
    'easytests-parse-test_limit_questions' => 'Limit[\s_]*questions|Questions?[\s_]*limit',
    'easytests-parse-test_ok_percent' => 'OK\s*%|Pass[\s_]*percent|OK[\s_]*percent|Completion\s*percent',
    'easytests-parse-test_autofilter_min_tries' => '(?:too[\s_]*simple|autofilter)[\s_]*min[\s_]*tries',
    'easytests-parse-test_autofilter_success_percent' => '(?:too[\s_]*simple|autofilter)[\s_]*(?:ok|success)[\s_]*percent',
    'easytests-parse-test_user_details' => 'Ask[\s_]*user',
    'easytests-parse-test_secret' => 'Is[\s_]*secret|Secret',


    /* Regular expressions used to parse questions etc */
    'easytests-parse-question' => 'Question[:\s]*',
    'easytests-parse-choice' => '(?:Choice|Answer)(?!s)',
    'easytests-parse-choices' => 'Choices|Answers',
    'easytests-parse-correct' => '(?:Correct|Right)\s*(?:Choice|Answer)(?!s)[:\s]*',
    'easytests-parse-corrects' => '(?:Correct|Right)\s*(?:Choices|Answers)',
    'easytests-parse-label' => 'Label',
    'easytests-parse-explanation' => 'Explanation',
    'easytests-parse-comments' => 'Comments?',
    'easytests-parse-true' => 'Yes|True|1',
);

$messages['uk'] = array(
    'easytests' => 'CSPUWikiTests',
    'easytests-actions' => '<p><a href="$2">Пройти тест «$1»</a> &nbsp; | &nbsp; <a href="$3">Версія для друку</a></p>',
    'easytests-actions-secret' => '<p><a href="$2">Отримати одноразове посилання на тестування</a> &nbsp; | &nbsp; <a href="$3">Версія для друку</a></p>',
    'easytests-show-parselog' => '[+] Показати лог розбору сторінки тесту',
    'easytests-hide-parselog' => '[-] Сховати лог розбору сторінки тесту',
    'easytests-complete-stats' => 'Правильних відповідей: $1/$2 ($3 %)',
    'easytests-no-complete-stats' => '(немає статистики)',

    'group-secretquiz' => 'Доступ до секретних тестів',
    'group-secretquiz-member' => 'має доступ до секретних тестів',

    /* Errors */
    'easytests-no-test-id-title' => 'Не заданий ідентификатор тесту!',
    'easytests-no-test-id-text' => 'Ви перейшли за посиланням, яке не має ідентифікатор тесту для запуску.',
    'easytests-test-not-found-title' => 'Тест не знайдено',
    'easytests-test-not-found-text' => 'Тест з таким номером не визначено!',
    'easytests-check-no-ticket-title' => 'неправильне посилання',
    'easytests-check-no-ticket-text' => 'Запитаний режим перевірки, але ідентифікатор вашої спроби проходження тесту не заданий або невірний. <br /> Спробуйте <a href="$2"> пройти тест «$1» </a> заново.',
    'easytests-review-denied-title' => 'Перегляд результатів заборонено',
    'easytests-review-option' =>
        'Ви перейшли за посиланням, яке не має ідентифікатор тесту для запуску.

Можливо, ви хотіли перейти до форми перегляду результатів (тільки для тесту, до якого маєте доступ)?

Якщо це дійсно так, введіть ID (назва вікі-статті) тесту, до якого маєте доступ, в поле нижче і натисніть «Вибрати результати». ',
    'easytests-review-denied-all' =>
        'Перегляд результатів по \'\'\' всім \'\'\'тестів доступний тільки адміністраторам системи тестування.

Тим не менш, ви можете ознайомитись з результатами по тим тестам, до вихідного коду (вікі-статті) яких маєте доступ.

Для цього введіть ID (назва вікі-статті) тесту в поле нижче і натисніть «Вибрати результати».',
    'easytests-review-denied-quiz' =>
        'Вам заборонений перегляд результатів по \'\'\' заданого \'\'\'тесту.

Введіть ID (назва вікі-статті) тесту, до вихідного коду (вікі-статті) якого маєте доступ.

Далі знову натисніть «Вибрати результати». ',

    'easytests-pagetitle' => '$1 — питання',
    'easytests-print-pagetitle' => '$1 — версія для друку',
    'easytests-check-pagetitle' => '$1 — результати',
    'easytests-review-pagetitle' => 'Опитування MediaWiki - перегляд результатів',

    'easytests-ticket-pagetitle' => '$1 — одноразове посилання',
    'easytests-ticket-link' => 'Одноразове посилання на тестування',

    'easytests-question' => 'Питання $1',
    'easytests-freetext' => 'Відповідь:',
    'easytests-counter-format' => 'Пройшло %%H%%:%%M%%:%%S%%.',
    'easytests-refresh-to-retry' => 'Опитування MediaWiki - переглядання результатів',
    'easytests-prompt' => 'Ваше ім’я',
    'easytests-prompt-needed' => 'обов’язкове поле',
    'easytests-empty' => 'Заповніть всі обов’язкові поля!',
    'easytests-submit' => 'Надіслати відповіді',
    'easytests-question-sheet' => 'Лист питань',
    'easytests-test-sheet' => 'Форма для тестування',
    'easytests-user-answers' => 'Відповіді користувача',
    'easytests-is-correct' => 'Правильний',
    'easytests-is-incorrect' => 'Неправильний',
    'easytests-answer-sheet' => 'Перевірочний лист',
    'easytests-table-number' => '№',
    'easytests-table-answer' => 'Відповідь',
    'easytests-table-stats' => 'Статистика',
    'easytests-table-label' => 'Мітка',
    'easytests-table-remark' => 'Примітка',
    'easytests-right-answer' => 'Правильна відповідь',
    'easytests-your-answer' => 'Вибрана відповідь',
    'easytests-variant-already-seen' => '<b style="color: red">На цей варіант ви вже відповідали.</b>',
    'easytests-try-another' => '<a href="$1">Спробуйте інший вариант.</a>',
    'easytests-ticket-details' => '<p>Ім’я: $1. Час тесту: $2 — $3 ($4).</p>',
    'easytests-ticket-reviewed' => '<b>Вже перевірено адміністратором.</b>',
    'easytests-results' => 'Підсумок',
    'easytests-variant-msg' => '<p>Варіант $1.</p>',
    'easytests-right-answers' => 'Кількість правильних відповідей',
    'easytests-score-long' => 'Набрано балів',
    'easytests-random-correct' => '<i>До речі, матсподівання числа правильних відповідей при випадковому виборі ≈ <b>$1</b></i>',
    'easytests-test-average' => 'Загальний средній бал по даному тесту ≈ <b>$1 %</b> правильних відповідей.',
    'easytests-try-quiz' => 'Спробуй <a href="$2">пройти тест «$1»</a>!',
    'easytests-try' => 'пройти',
    'easytests-congratulations' => 'Ви успішно пройшли тест! Можете вставити наступний HTML-код в ваш блог або сайт: ',
    'easytests-explanation' => 'Пояснення',
    'easytests-anonymous' => 'Анонімний',
    'easytests-select-tickets' => 'Вибрати результати',
    'easytests-ticket-count' => 'Знайдено $1, показано $3, починаючи з №$2.',
    'easytests-no-tickets' => 'Не знайдено жодної спроби проходження.',
    'easytests-pages' => 'Сторінки: ',

    /* Field names */
    'easytests-ticket-id' => 'ID спроби',
    'easytests-quiz' => 'Тест',
    'easytests-quiz-title' => 'Заголовок',
    'easytests-variant' => 'Варіант',
    'easytests-who' => 'Ім’я',
    'easytests-user' => 'Користувач',
    'easytests-start' => 'Час початку',
    'easytests-end' => 'Час кінця',
    'easytests-to' => ' до',
    'easytests-duration' => 'Тривалість',
    'easytests-ip' => 'IP-адреса',
    'easytests-perpage' => 'На страниці',
    'easytests-show-details' => 'показати анкети',
    'easytests-score' => 'Бали',
    'easytests-correct' => 'Відповіді',

    /* Regular expressions for test */
    'easytests-parse-test_name' => 'Назва|Name|Title',
    'easytests-parse-test_intro' => 'Вступ|Опис|Intro|Short[\s_]*Desc(?:ription)?',
    'easytests-parse-test_mode' => 'Режим|Mode',
    'easytests-parse-test_shuffle_questions' => 'Переставляти\s*питання|Перемішати\s*питання|Перемішувати\s*питання|Shuffle[\s_]*questions',
    'easytests-parse-test_shuffle_choices' => 'Переставляти\s*відповіді|Перемішати\s*відповіді|Перемішувати\s*відповіді|Shuffle[\s_]*answers|Shuffle[\s_]*choices',
    'easytests-parse-test_limit_questions' => 'Кількість\s*питань|Число\s*питань|Обмежити\s*кількість\s*питань|Limit[\s_]*questions|Questions?[\s_]*limit',
    'easytests-parse-test_ok_percent' => 'Відсоток\s*завершення|%\s*завершення|ОК\s*%|OK\s*%|Pass[\s_]*percent|OK[\s_]*percent|Completion\s*percent',
    'easytests-parse-test_autofilter_min_tries' => 'Мін[\s\.]*спроб\s*дуже\s*простих\s*питань|(?:too[\s_]*simple|autofilter)[\s_]*min[\s_]*tries',
    'easytests-parse-test_autofilter_success_percent' => '%\s*успіхів\s*дуже\s*простих\s*питань|(?:too[\s_]*simple|autofilter)[\s_]*(?:ok|success)[\s_]*percent',
    'easytests-parse-test_user_details' => 'Запитати\s*користувача|Ask[\s_]*user',
    'easytests-parse-test_secret' => 'Секретний|Is[\s_]*secret|Secret',

    /* Regular expressions for questions, answers etc. */
    'easytests-parse-question' => '(?:Питання|Question)[:\s]*',
    'easytests-parse-question-match' => '(?:Питання\s*порядок|Order\s*question)[:\s]*',
    'easytests-parse-question-parallel' => '(?:Питання\s*відповідність|Parallel\s*question)[:\s]*',
    'easytests-parse-choice' => 'Відповідь|(?:Choice|Answer)(?!s)',
    'easytests-parse-choices' => 'Відповіді|Варіанти\s*відповідей|Choices|Answers',
    'easytests-parse-correct-matches' => '(?:Правильний\s*порядок)[:\s]*',
    'easytests-parse-correct' => '(?:Правильна\s*відповідь|(?:Correct|Right)\s*(?:Choice|Answer)(?!s))[:\s]*',
    'easytests-parse-corrects' => '(?:(?:Правильні\s*відповіді|Правильні\s*варіанти\s*відповідей)|(?:Correct|Right)\s*(?:Choices|Answers))[:\s]*',
    'easytests-parse-label' => 'Мітка|Label',
    'easytests-parse-explanation' => 'Пояснення|Explanation',
    'easytests-parse-comments' => 'Примітк[аи]|Коментар(?!і)|Comments?',
    'easytests-parse-true' => 'Так|Yes|True|1',

);


