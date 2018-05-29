<?php

/*
error_reporting(E_ALL);
ini_set('display_errors', 'on');
//*/

$argv = isset($argv) ? $argv : array();
$argv['path'] = !isset($argv['path']) ? '/var/www/html/ojs/' : realpath($argv['path']);

if (!file_exists(realpath($argv['path'] . '/tools/bootstrap.inc.php'))) {
    die("No OJS2 installation at '{$argv['path']}' found. Aborted.'\n");
}

require(realpath($argv['path'] . '/tools/bootstrap.inc.php'));
import('classes.journal.Journal');

class ojs_config_tool extends CommandLineTool {


    function __construct($argv = array()) {
        parent::CommandLineTool($argv);
        $this->createJournal();
    }

    function createJournal($name='test', $path='test') {
        $journal = New Journal();
        $journal->setPath('test');
        $journal->setEnabled(true);
        $journal->setData('title', $name);
        $journalDao = DAORegistry::getDAO('JournalDAO');
        return $journalDao->insertJournal($journal);
    }
}

$tool = new ojs_config_tool($argv);

?>
