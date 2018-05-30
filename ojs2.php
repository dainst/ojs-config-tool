<?php

/*
error_reporting(E_ALL);
ini_set('display_errors', 'on');
//*/

$opt = getopt(
    "",
    array(
        "path::",
        "journal.plugins::",
        "journal.theme::",
        "journal.title::",
        "journal.path::",
        "theme::"
    )
) + array(
    "path" => '/var/www/html/ojs/',
    "journal.plugins" => "ClassicRedThemePlugin",
    "journal.theme" => "ClassicRedThemePlugin",
    "journal.title" => "test",
    "journal.path" => "test",
    "theme" => "ClassicRedThemePlugin"
);
$opt['path'] = realpath($opt['path']);
$opt['plugins'] = explode(",", $opt['plugins']);

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

    function createJournal($title, $path) {
        echo "Creating Journal with title '$title' and path '$path'...";
        $path = $path or $this->options['path'];
        $journal = New Journal();
        $journal->setPath('test');
        $journal->setEnabled(true);
        $journal->setPrimaryLocale(AppLocale::getLocale());
        $journalDao = DAORegistry::getDAO('JournalDAO');
        $journalId = $journalDao->insertJournal($journal);
        $journalSettingsDao =& DAORegistry::getDAO('JournalSettingsDAO');
        $journalSettingsDao->updateSetting($journalId, 'title', array(AppLocale::getLocale()=>$title), 'string', true);
        echo "seccess";
        return $journalId;
    }

    function enablePlugins($journalId, $plugins) {
        echo "Enable Plugins: " . print_r($plugins, 1) . '...';
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
            echo "seccess";

        }
    }

    private function _getTheme($theme) {
        $plugin = PluginRegistry::getPlugin("themes", $theme);
        if (!method_exists($plugin, "activate")) {
              error("$theme does not exist!");
        }
        return $plugin;
    }

    function setJournalTheme($journalId, $theme) {
        echo "Set theme $theme for Journal...";
        $this->_getTheme($theme);
        $journalSettingsDao =& DAORegistry::getDAO('JournalSettingsDAO');
        $journalSettingsDao->updateSetting($journalId, 'journalTheme', $theme, 'string', false);
        echo "seccess";
    }

    function setTheme($theme) {
        echo "Set theme $theme...";
        $this->_getTheme($theme);
        $siteDao = DAORegistry::getDAO('SiteDAO');
        $site = $siteDao->getSite();
        $site->updateSetting('siteTheme', $theme, 'string', false);
        echo "seccess";
    }

}

try {
  $tool = new ojs_config_tool($opt);
  $journalId = $tool->createJournal($opt["title"], $opt["path"]);
  $tool->enablePlugins($journalId, $opt["journal.plugins"]);
  $tool->setTheme($opt["theme"]);
} catch (Exception $e) {
  error($e->getMessage());
}

?>
