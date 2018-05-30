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
    "journal.plugins" => "",
    "journal.theme" => "desert",
    "journal.title" => "test",
    "journal.path" => "test",
    "theme" => "desert"
);
$opt['path'] = realpath($opt['path']);
$opt['journal.plugins'] = explode(",", $opt['journal.plugins']);

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
        if (!is_array($plugins)) {
            echo "No Plugins to enable";
            return;
        }
        foreach ($plugins as $pluginAndCategory) {
            echo "Enable Plugin: $pluginAndCategory ...";
            list($category, $pluginName) = explode("/", $pluginAndCategory);
            $plugin = PluginRegistry::loadPlugin($category, $pluginName);

            if (!is_a($plugin, "Plugin")) {
                echo "nope, because it's a " . get_class($plugin);
                continue;
            }

            if ($plugin->isSitePlugin()) {
                echo " (sidewide) ";
                $plugin->updateSetting(0, 'enabled', true);
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


    function clearTemplateCache() {
        $templateMgr =& TemplateManager::getManager();
        $templateMgr->clearTemplateCache();
    }

}

try {
  $tool = new ojs_config_tool();
  $journalId = $tool->createJournal($opt["journal.title"], $opt["journal.path"]);
  $tool->enablePlugins($journalId, $opt["journal.plugins"]);
  $tool->setTheme($opt["theme"]);
  $tool->setJournalTheme($journalId, $opt["journal.theme"]);
  $tool->clearTemplateCache();
} catch (Exception $e) {
  error($e->getMessage());
}

?>
