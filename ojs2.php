<?php

/*
error_reporting(E_ALL);
ini_set('display_errors', 'on');
//*/

$opt = getopt("", array("path::"));
$opt['path'] = !isset($opt['path']) ? '/var/www/html/ojs/' : realpath($opt['path']);


if (!file_exists(realpath($opt['path'] . '/tools/bootstrap.inc.php'))) {
    die("No OJS2 installation at '{$opt['path']}' found. Aborted.'\n");
}

require(realpath($opt['path'] . '/tools/bootstrap.inc.php'));
import('classes.journal.Journal');

class ojs_config_tool extends CommandLineTool {


    function __construct($argv = array()) {
        parent::CommandLineTool($argv);
        $this->createJournal();
    }

    function createJournal($title='test', $path='test') {
        $journal = New Journal();
        $journal->setPath('test');
        $journal->setEnabled(true);
        $journal->updateSetting(array(AppLocale::getLocale()=>'title'), $title, 'string', true);
        $journal->setPrimaryLocale(AppLocale::getLocale());
        $journalDao = DAORegistry::getDAO('JournalDAO');
        return $journalDao->insertJournal($journal);
    }
}

$tool = new ojs_config_tool();

?>
