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
        $this->enablePlugins();
    }

    function createJournal($title='test', $path='test') {
        $journal = New Journal();
        $journal->setPath('test');
        $journal->setEnabled(true);
        $journal->setPrimaryLocale(AppLocale::getLocale());
        $journalDao = DAORegistry::getDAO('JournalDAO');
        $journalId = $journalDao->insertJournal($journal);
        $journalSettingsDao =& DAORegistry::getDAO('JournalSettingsDAO');
        $journalSettingsDao->updateSetting($journalId, 'title', array(AppLocale::getLocale()=>$title), 'string', true);
    }

    function enablePlugins() {
        foreach (PluginRegistry::getPlugins() as $id => $plugin) {
            echo "$id\n";
            //echo print_r($plugin);
            echo "\nn: " $plugin->getName() ;
            echo "\nd: " $plugin->getDisplayName() ;
            echo "\ne: " . $plugin->getEnabled($journalId);
            /*$this->updateSetting($journalId, 'enabled', true);*/
            echo "\n";
        }
    }
}

$tool = new ojs_config_tool();

?>
