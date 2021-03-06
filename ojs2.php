<?php
/**
 * command line tool to give an fresj ojs installtion a absic configaraion
 *
 * usage
 *
 * php ojs2.php
 *
 * parameters:
 *
 * -- path=<path to OJS2 installton. defaults to  /var/www/html/ojs/>
 * -- journal.path=<new journal path>
 * -- journal.path=<new journal title>
 * -- theme=<selected theme for site (name of the plugin folder) !>
 * -- journal.theme=<selected theme for journal (name of the plugin folder) !>
 * -- dfm.theme=<the for the frontammter plugin>
 * -- journal.plugins=<list of activate plugins> form:
 *  generic/dfm,pubIds/urnDNB (comma-separated list of plugins paths including plugin category
 *
 *
 *
 *
 *
 */


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
        "theme::",
        "dfm.theme::"
    )
) + array(
    "path" => '/var/www/html/ojs/',
    "journal.plugins" => "",
    "journal.theme" => "desert",
    "journal.title" => "test",
    "journal.path" => "test",
    "theme" => "desert",
    "dfm.theme" => "dai_tcpdf_theme"
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
        $journal->setPath($path);
        $journal->setEnabled(true);
        $journal->setPrimaryLocale(AppLocale::getLocale());
        $journalDao = DAORegistry::getDAO('JournalDAO');
        $journalId = $journalDao->insertJournal($journal);
        $journalSettingsDao =& DAORegistry::getDAO('JournalSettingsDAO');
        $locale = AppLocale::getLocale();
        $journalSettingsDao->updateSetting($journalId, 'title', array($locale => $title), 'string', true);
        $journalSettingsDao->updateSetting($journalId, 'primary_locale', $locale, 'string', false);
        $journalSettingsDao->updateSetting($journalId, 'supportedFormLocales', array($locale), 'object', false);
        $journalSettingsDao->updateSetting($journalId, 'supportedLocales', array($locale), 'object', false);
        $journalSettingsDao->updateSetting($journalId, 'supportedSubmissionLocales', array($locale), 'object', false);
        $journalSettingsDao->updateSetting($journalId, 'copyrightYearBasis', 'article', 'string', false);
        echo "success\n";
        return $journalId;
    }

    function giveRolesToAdmin($journalId) {
        $roleDao =& DAORegistry::getDAO('RoleDAO');
        $roles = array(ROLE_ID_JOURNAL_MANAGER, ROLE_ID_EDITOR, ROLE_ID_SECTION_EDITOR, ROLE_ID_LAYOUT_EDITOR, ROLE_ID_REVIEWER,
            ROLE_ID_COPYEDITOR, ROLE_ID_PROOFREADER, ROLE_ID_AUTHOR, ROLE_ID_READER, ROLE_ID_SUBSCRIPTION_MANAGER);
        foreach ($roles as $roleId) {
            $role = new Role();
            $role->setRoleId($roleId);
            $role->setUserId(1);
            $role->setJournalId($journalId);
            $roleDao->insertRole($role);
        }

    }


    function enablePlugins($journalId, $plugins = array()) {
        if (!is_array($plugins) or !count($plugins)) {
            echo "No Plugins to enable";
            return;
        }
        foreach ($plugins as $pluginAndCategory) {
            if (!$pluginAndCategory) {continue;}
            echo "Enable Plugin: $pluginAndCategory ...";
            list($category, $pluginName) = explode("/", $pluginAndCategory);
            $plugin = PluginRegistry::loadPlugin($category, $pluginName);

            if (!is_a($plugin, "Plugin")) {
                echo "nope, because it's a " . get_class($plugin);
                continue;
            }

            if ($plugin->isSitePlugin()) {
                echo " (sidewide) ";
                $plugin->updateSetting(CONTEXT_ID_NONE, 'enabled', true);
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

    function setDfmTheme($theme) {
        echo "Set dfm theme $theme...";
        $plugin = PluginRegistry::getPlugin('generic', 'dfm');
        $plugin->updateSetting(CONTEXT_ID_NONE, 'dfm_theme', $theme);
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
    $tool->giveRolesToAdmin($journalId);
    $tool->enablePlugins($journalId, $opt["journal.plugins"]);
    $tool->setTheme($opt["theme"]);
    $tool->setJournalTheme($journalId, $opt["journal.theme"]);
    $tool->setDfmTheme($opt["dfm.theme"]);
    $tool->clearTemplateCache();
} catch (Exception $e) {
    error($e->getMessage());
}

?>
