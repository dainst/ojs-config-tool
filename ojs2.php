<?php

/*
error_reporting(E_ALL);
ini_set('display_errors', 'on');
//*/

$opt = getopt("", array("path::", "plugins::"));
$opt['path'] = !isset($opt['path']) ? '/var/www/html/ojs/' : realpath($opt['path']);
$opt['plugins'] = !isset($opt['plugins']) ? array() : explode(",", $opt['plugins']);

if (!file_exists(realpath($opt['path'] . '/tools/bootstrap.inc.php'))) {
    die("No OJS2 installation at '{$opt['path']}' found. Aborted.'\n");
}
require(realpath($opt['path'] . '/tools/bootstrap.inc.php'));
import('classes.journal.Journal');


class ojs_config_tool extends CommandLineTool {

    public $options = array();

    function __construct($opt = array()) {
        parent::__construct();

        $this->options = $opt;
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
        return $journalId;
    }

    function enablePlugins($journalId) {
        foreach (PluginRegistry::getCategories() as $category) {
            $plugins = PluginRegistry::loadCategory($category, false, $journalId);
            echo "\n========== $category ===========\n";
            if (is_array($plugins)) {
                foreach ($plugins as $id => $plugin) {
                    echo "\n[$id]";
                    //echo print_r($plugin);
                    echo "\nn: " . $plugin->getName();
                    echo "\ne: " . $plugin->getSetting($journalId, 'enabled');

                    if (in_array($plugin->getName(), $this->options['plugins'])) {
                        $this->updateContextSpecificSetting(array($journalId), 'enabled', true);
                        echo "\n I ENABLED IT, LOOK:";
                        echo "\ne: " . $plugin->getSetting($journalId, 'enabled');
                    }
                    echo "\n";
                }
            } else {
                echo "none found\n";
            }


        }
    }
}

$tool = new ojs_config_tool($opt);
$journalId = $tool->createJournal();
$tool->enablePlugins($journalId);
?>
