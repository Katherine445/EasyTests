CREATE TABLE /*$wgDBprefix*/et_test (
  -- test ID,
  test_id                         INT UNSIGNED           NOT NULL AUTO_INCREMENT,
  -- test page title
  test_page_title                 VARCHAR(255) BINARY    NOT NULL,
  -- test title
  test_name                       VARCHAR(255)           NOT NULL DEFAULT '',
  -- brief description of test
  test_intro                      LONGBLOB,
  -- TEST or TUTOR
  test_mode                       ENUM ('TEST', 'TUTOR') NOT NULL DEFAULT 'TEST',
  -- randomize choice positions
  test_shuffle_choices            TINYINT(1)             NOT NULL DEFAULT '0',
  -- randomize question positions
  test_shuffle_questions          TINYINT(1)             NOT NULL DEFAULT '0',
  -- select only X random questions from test
  test_limit_questions            TINYINT(4)             NOT NULL DEFAULT '0',
  -- percent of correct answers to pass
  test_ok_percent                 TINYINT(3)             NOT NULL DEFAULT '80',
  -- user details
  test_user_details               BLOB                   NOT NULL,
  -- each variant includes questions shown less than X times ("too new to filter")...
  test_autofilter_min_tries       SMALLINT               NOT NULL,
  -- ...and questions with correct answer percent greater than Y ("too simple")
  -- but if qt_autofilter_min_tries <= 0 then autofilter is disabled
  test_autofilter_success_percent TINYINT                NOT NULL,
  -- is the quiz secret for non-admins, i.e. accessible only by pre-generated URLs with tokens
  -- test_secret tinyint(1) not null default 0,
  -- quiz article parse log
  test_log                        LONGBLOB               NOT NULL,
  PRIMARY KEY (test_id),
  UNIQUE KEY (test_page_title)
) /*$wgDBTableOptions*/;

CREATE TABLE /*$wgDBprefix*/et_question (
  -- question ID (md5 hash of question text)
  qn_hash        BINARY(32)     NOT NULL,
  -- question type (simple, order or parallel)
  qn_type        ENUM ('simple', 'order', 'parallel', 'free-text') NOT NULL DEFAULT 'simple',
  -- question text
  qn_text        BLOB           NOT NULL,
  -- correct answer explanation text
  qn_explanation BLOB                    DEFAULT NULL,
  -- arbitrary label to classify questions
  qn_label       VARBINARY(255)          DEFAULT NULL,
  -- HTML anchor of question section inside article
  qn_anchor      VARBINARY(255) NOT NULL DEFAULT '',
  -- extracted HTML code with edit question section link
  qn_editsection BLOB                    DEFAULT NULL,
  PRIMARY KEY (qn_hash)
) /*$wgDBTableOptions*/;

CREATE TABLE /*$wgDBprefix*/et_question_test (
  -- test ID
  qt_test_id       INT UNSIGNED NOT NULL,
  -- question hash
  qt_question_hash BINARY(32)   NOT NULL,
  -- question index number inside the test
  qt_num           INT UNSIGNED NOT NULL,
  PRIMARY KEY (qt_test_id, qt_num)
) /*$wgDBTableOptions*/;

CREATE TABLE /*$wgDBprefix*/et_choice (
  -- question hash
  ch_question_hash BINARY(32)          NOT NULL,
  -- choice index number inside the question
  ch_num           INT UNSIGNED        NOT NULL,
  -- choice text
  ch_text          BLOB                NOT NULL,
  -- choice order for order question
  ch_order_index   INT UNSIGNED        NOT NULL DEFAULT 0,
  -- choice parallel
  ch_parallel      BLOB                NULL,
  -- is this choice correct?
  ch_correct       TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (ch_question_hash, ch_num)
) /*$wgDBTableOptions*/;

CREATE TABLE /*$wgDBprefix*/et_ticket (
  -- ticket ID
  tk_id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  -- ticket key
  tk_key             CHAR(32)      NOT NULL,
  -- start time
  tk_start_time      BINARY(14)             DEFAULT NULL,
  -- end time
  tk_end_time        BINARY(14)             DEFAULT NULL,
  -- user ID or NULL for anonymous users
  tk_user_id         INT UNSIGNED           DEFAULT NULL,
  -- user display name (printed on the completion certificate)
  tk_displayname     VARCHAR(255)
                     COLLATE utf8_bin       DEFAULT NULL,
  -- user details (JSON hash of arbitrary data)
  tk_details         BLOB                   DEFAULT NULL,
  -- is the ticket reviewed by admins?
  tk_reviewed        TINYINT(1)    NOT NULL DEFAULT 0,
  -- user name
  tk_user_text       VARCHAR(255)
                     COLLATE utf8_bin       DEFAULT NULL,
  -- user IP address
  tk_user_ip         VARBINARY(64) NOT NULL,
  -- test ID
  tk_test_id         INT UNSIGNED  NOT NULL,
  -- variant
  tk_variant         BLOB          NOT NULL,
  -- score
  tk_score           FLOAT                  DEFAULT NULL,
  -- score %
  tk_score_percent   DECIMAL(4, 1)          DEFAULT NULL,
  -- correct answers count
  tk_correct         INT                    DEFAULT NULL,
  -- correct answers %
  tk_correct_percent DECIMAL(4, 1)          DEFAULT NULL,
  -- passed or no?
  tk_pass            TINYINT(1)             DEFAULT NULL,
  PRIMARY KEY (tk_id)
) /*$wgDBTableOptions*/;

CREATE TABLE /*$wgDBprefix*/et_choice_stats (
  -- ticket ID
  cs_ticket        INT UNSIGNED NOT NULL,
  -- question hash
  cs_question_hash BINARY(32)   NOT NULL,
  -- choice index number
  cs_choice_num    INT UNSIGNED NOT NULL,
  -- is this answer correct?
  cs_correct       TINYINT(1)   NOT NULL,
  -- free-text answer
  cs_text          TEXT,
  KEY (cs_question_hash)
) /*$wgDBTableOptions*/;

-- Create foreign keys (InnoDB only)

ALTER TABLE /*$wgDBprefix*/et_question_test
  ADD FOREIGN KEY (qt_test_id) REFERENCES /*$wgDBprefix*/et_test (test_id)
  ON DELETE CASCADE
  ON UPDATE CASCADE;
ALTER TABLE /*$wgDBprefix*/et_question_test
  ADD FOREIGN KEY (qt_question_hash) REFERENCES /*$wgDBprefix*/et_question (qn_hash)
  ON DELETE CASCADE
  ON UPDATE CASCADE;
ALTER TABLE /*$wgDBprefix*/et_choice
  ADD FOREIGN KEY (ch_question_hash) REFERENCES /*$wgDBprefix*/et_question (qn_hash)
  ON DELETE CASCADE
  ON UPDATE CASCADE;
ALTER TABLE /*$wgDBprefix*/et_ticket
  ADD FOREIGN KEY (tk_test_id) REFERENCES /*$wgDBprefix*/et_test (test_id)
  ON UPDATE CASCADE;
ALTER TABLE /*$wgDBprefix*/et_ticket
  ADD FOREIGN KEY (tk_user_id) REFERENCES /*$wgDBprefix*/user (user_id)
  ON DELETE SET NULL
  ON UPDATE CASCADE;
ALTER TABLE /*$wgDBprefix*/et_choice_stats
  ADD FOREIGN KEY (cs_question_hash) REFERENCES /*$wgDBprefix*/et_question (qn_hash)
  ON UPDATE CASCADE;
ALTER TABLE /*$wgDBprefix*/et_choice_stats
  ADD FOREIGN KEY (cs_ticket) REFERENCES /*$wgDBprefix*/et_ticket (tk_id)
  ON UPDATE CASCADE;

