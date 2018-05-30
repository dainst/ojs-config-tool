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

function error($msg) {
  fwrite(STDERR, "$msg\n");
  exit(1);
}

class ojs_config_tool extends CommandLineTool {

    public $options = array();

    function __construct($opt = array()) {
        parent::__construct();

        $this->options = $opt;
    }

    function createJournal($title = false, $path = false) {
        $title = $title or $this->options['title'];
        $path = $path or $this->options['path'];
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

    function enablePlugins($journalId, $plugins = false) {
        $plugins = $plugins or $this->options['plugins'] or array();
        echo "enable pugins: " . print_r($plugins, 1);
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
                        $plugin->updateSetting($journalId, 'enabled', true);
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

    private function _getTheme($theme) {
      $plugin = PluginRegistry::getPlugin("themes", $theme);
      if (!method_exists($plugin, activate)) {
          error("")
      }
      return $plugin;
    }

    function setJournalTheme($journalId, $theme = false) {
        $theme = $theme or "ClassicRedThemePlugin";
        $journalSettingsDao =& DAORegistry::getDAO('JournalSettingsDAO');
        $journalSettingsDao->updateSetting($journalId, 'journalTheme', $theme, 'string', false);
    }

    function setTheme($theme = false) {
        $theme = $theme or "ClassicRedThemePlugin";
        $this->_getTheme($theme);
        $siteDao = DAORegistry::getDAO('SiteDAO');
        $site = $siteDao->getSite();
        $site->updateSetting('siteTheme', $theme, 'string', false);
    }

}

try {
  $tool = new ojs_config_tool($opt);
  $journalId = $tool->createJournal();
  $tool->enablePlugins($journalId);
  $tool->setTheme();
} catch (Exception $e) {
  error($e->getMessage());
}

?>
