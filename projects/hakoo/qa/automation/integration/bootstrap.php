<?php
// Test bootstrap: loads the TEST config (docker/web/config.test.php, mounted as
// <CONFIG_FILE>) and a private copy of the users library.
//
// The library cleans JSON files next to itself (wot_boxes.json, ...). Loading a
// copy from a temp folder means the tests clean test files, never src/.

require '/var/www/html/<CONFIG_FILE>';

define('HAKOO_LIB_DIR', sys_get_temp_dir() . '/hakoo-lib');
if (!is_dir(HAKOO_LIB_DIR)) mkdir(HAKOO_LIB_DIR, 0777, true);
copy('/var/www/html/hakoo-session.inc.php', HAKOO_LIB_DIR . '/hakoo-session.inc.php');
copy('/var/www/html/hakoo-users.inc.php', HAKOO_LIB_DIR . '/hakoo-users.inc.php');
require HAKOO_LIB_DIR . '/hakoo-users.inc.php';

require __DIR__ . '/HakooTestData.php';
require __DIR__ . '/HakooTestCase.php';
@mkdir('/qa/test-results', 0777, true);
