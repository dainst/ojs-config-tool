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
        echo "success\n";
        return $journalId;
    }

    function enablePlugins($journalId, $plugins) {
        foreach ($plugins as $pluginAndCategory) {
            echo "Enable Plugin: $pluginAndCategory ...";
            list($category, $pluginName) = split($pluginAndCategory);
            $plugin = PluginRegistry::loadPlugin($category, $pluginName);

            if (is_a("Plugin", $plugin)) {
                echo "nope\n";
                continue;
            }

            if ($plugin->isSitePlugin()) {
                echo " (sidewide) ";
                $plugin->updateSetting(null, 'enabled', true);
            } else {
                $plugin->updateSetting($journalId, 'enabled', true);
            }

            echo "success\n";
        }

    }

    private function _getTheme($theme) {
        $plugin = PluginRegistry::loadPlugin("themes", $theme);
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
        echo "success\n";
    }

    function setTheme($theme) {
        echo "Set theme $theme...";
        $this->_getTheme($theme);
        $siteDao = DAORegistry::getDAO('SiteDAO');
        $site = $siteDao->getSite();
        $site->updateSetting('siteTheme', $theme, 'string', false);
        echo "success\n";
    }

}

try {
  $tool = new ojs_config_tool();
  $journalId = $tool->createJournal($opt["journal.title"], $opt["journal.path"]);
  $tool->enablePlugins($journalId, $opt["journal.plugins"]);
  $tool->setTheme($opt["theme"]);
  $tool->setJournalTheme($journalId, $opt["journal.theme"]);
} catch (Exception $e) {
  error($e->getMessage());
}

?>
