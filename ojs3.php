<?php

/**
 * Maintainer: Deutsches Archäologisches Institut (dev@dainst.de)
 * 
 * Copyright: Deutsches Archäologisches Institut (DAI)
 * Licensed under GNU GPL v3. For full terms see LICENSE file.
 * 
 * Attributions:
 * Code is based on previous works of John Willinsky, Simon Fraser University for Public Knowledge Project (PKP) and Phillipp Franck for DAI
 * 
 * Description:
 * Small command line tool to setup OJS with a new journal and by default the administrator with all default roles.
 * 
 * Usage:
 * php ojs3config.php
 *
 * Parameters:
 * -- path=<path to OJS3 installton. defaults to  /var/www/html>
 * -- journal.path=<new journal path>
 * -- journal.title=<new journal title>
 * -- journal.plugins=<list of activate plugins> form:
 *  generic/dfm,pubIds/urnDNB (comma-separated list of plugins paths including plugin category
 *
 */


register_shutdown_function(function()  {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_CORE_WARNING, E_COMPILE_ERROR, E_COMPILE_WARNING))) {
        echo "Error: {$error['message']} in line {$error['line']} of {$error['file']}\n";
    }
    echo "\n";
});


$opt = getopt(
    "",
    array(
        "path::",
        "journal.plugins::",
        "journal.title::",
        "journal.path::",
        "journal.theme::",
        "theme::"
    )
) + array(
    "path" => '/var/www/html',
    "journal.plugins" => "",
    "journal.title" => "test",
    "journal.path" => "test",
    "journal.theme" => "default",
    "theme" => "default"
);
$opt['path'] = realpath($opt['path']);
$opt['journal.plugins'] = explode(",", $opt['journal.plugins']);

if (!file_exists(realpath($opt['path'] . '/tools/bootstrap.inc.php'))) {
    die("No OJS3 installation at '{$opt['path']}' found. Aborted.'\n");
}
require(realpath($opt['path'] . '/tools/bootstrap.inc.php'));
import('classes.journal.Journal');

function error($msg) {
  fwrite(STDERR, "$msg\n");
  exit(1);
}

register_shutdown_function(function()  {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_CORE_WARNING, E_COMPILE_ERROR, E_COMPILE_WARNING))) {
        error("Error: {$error['message']} in line {$error['line']} of {$error['file']}\n");
    }
});


class omp_config_tool extends CommandLineTool {

    //
    // createJournal
    //
    // $title = title of the journal to be created
    // $path = path of the new journal
    function createJournal($title, $path) {
        $path = $path or $this->options['path'];
        
        $journalDao = DAORegistry::getDAO('JournalDAO');
        $journal = $journalDao->newDataObject();
        $journalPath = $path;
        $journal->setPath($journalPath);
        $journal->setEnabled(1);
        $locale = 'de_DE';
        $journal->setPrimaryLocale ($locale);
        $journalId = $journalDao->insertObject($journal);
        $journalDao->resequence();

        // load the default user groups and stage assignments.
        AppLocale::requireComponents(LOCALE_COMPONENT_APP_DEFAULT, LOCALE_COMPONENT_PKP_DEFAULT);
        $userGroupDao = DAORegistry::getDAO('UserGroupDAO');
        $userGroupDao->installSettings($journalId, 'registry/userGroups.xml');
        
        // Make the file directories for the journal
        import('lib.pkp.classes.file.FileManager');
        $fileManager = new FileManager();
        $fileManager->mkdir(Config::getVar('files', 'files_dir') . '/journals/' . $journalId);
        $fileManager->mkdir(Config::getVar('files', 'files_dir'). '/journals/' . $journalId . '/articles');
        $fileManager->mkdir(Config::getVar('files', 'files_dir'). '/journals/' . $journalId . '/issues');
        $fileManager->mkdir(Config::getVar('files', 'public_files_dir') . '/journals/' . $journalId);

        // Install default journal settings
        $journalSettingsDao = DAORegistry::getDAO('JournalSettingsDAO');
        $names = $title;
        AppLocale::requireComponents(LOCALE_COMPONENT_APP_DEFAULT, LOCALE_COMPONENT_APP_COMMON);
        $this->installSettings($journalId,'registry/journalSettings.xml');

        $journalSettingsDao->updateSetting($journalId, 'name', array($locale => $title), 'string', true);
        $journalSettingsDao->updateSetting($journalId, 'primary_locale', $locale, 'string', false);
        $journalSettingsDao->updateSetting($journalId, 'supportedFormLocales', array($locale, 'en_US'), 'object', false);
        $journalSettingsDao->updateSetting($journalId, 'supportedLocales', array($locale, 'en_US'), 'object', false);
        $journalSettingsDao->updateSetting($journalId, 'supportedSubmissionLocales', array($locale, 'en_US'), 'object', false);

        // Create a default "Articles" section
        $sectionDao = DAORegistry::getDAO('SectionDAO');
        $section = new Section();
        $section->setJournalId($journal->getId());
        $section->setTitle(__('section.default.title'), $journal->getPrimaryLocale());
        $section->setAbbrev(__('section.default.abbrev'), $journal->getPrimaryLocale());
        $section->setMetaIndexed(true);
        $section->setMetaReviewed(true);
        $section->setPolicy(__('section.default.policy'), $journal->getPrimaryLocale());
        $section->setEditorRestricted(false);
        $section->setHideTitle(false);
        $sectionDao->insertObject($section);

        $journal->updateSetting('name', $title, 'string', true);

		// Install default menus
		$navigationMenuDao = DAORegistry::getDAO('NavigationMenuDAO');
        $navigationMenuDao->installSettings($journalId, 'registry/navigationMenus.xml');

        PluginRegistry::loadAllPlugins();
        AppLocale::requireComponents(LOCALE_COMPONENT_APP_DEFAULT, LOCALE_COMPONENT_PKP_DEFAULT);
        
        HookRegistry::call('JournalSiteSettingsForm::execute', array($this, $journal, $section, true));

        echo "..done\n";
        return $journalId;
    }

    //
    // giveUserRoles
    //
    // $journalId = internal contextId
    // $userId = Id of user to be modified (optional, defaults to Admin)
    function giveUserRoles($journalId, $userId = 1) {
        echo "Give user roles...";
        // Give administrator all default roles
        $roles = array(ROLE_ID_MANAGER, 
                        ROLE_ID_SUB_EDITOR,
                        ROLE_ID_AUTHOR,   
                        ROLE_ID_REVIEWER,
                        ROLE_ID_ASSISTANT, 
                        ROLE_ID_READER, 
                        ROLE_ID_SUBSCRIPTION_MANAGER);
        $userGroupDao = DAORegistry::getDAO('UserGroupDAO');
        foreach ($roles as $roleId) {
            $group = $userGroupDao->getDefaultByRoleId($journalId, $roleId);
            $userGroupDao->assignUserToGroup($userId, $group->getId());
        }
        echo "done\n";
    }

    //
    // ----------------
    // helper functions
    // ----------------
    //
    function installSettings($id, $filename, $paramArray = array()) {
        $xmlParser = new XMLParser();
        $tree = $xmlParser->parse($filename);
    
        if (!$tree) {
            $xmlParser->destroy();
            return false;
        }
    
        foreach ($tree->getChildren() as $setting) {
            $nameNode = $setting->getChildByName('name');
            $valueNode = $setting->getChildByName('value');
    
            if (isset($nameNode) && isset($valueNode)) {
                $type = $setting->getAttribute('type');
                $isLocaleField = $setting->getAttribute('locale');
                $name = $nameNode->getValue();
    
                if ($type == 'object') {
                    $arrayNode = $valueNode->getChildByName('array');
                    $value = $this->_buildObject($arrayNode, $paramArray);
                } else {
                    $value = $this->_performReplacement($valueNode->getValue(), $paramArray);
                }
    
                $journalSettingsDao = DAORegistry::getDAO('JournalSettingsDAO');
                $journalSettingsDao->updateSetting(
                    $id,
                    $name,
                    $isLocaleField?array(AppLocale::getLocale() => $value):$value,
                    $type,
                    $isLocaleField
                );
            }
        }
    
        $xmlParser->destroy();
    }
    
    function _buildObject (&$node, $paramArray = array()) {
        $value = array();
        foreach ($node->getChildren() as $element) {
            $key = $element->getAttribute('key');
            $childArray = $element->getChildByName('array');
            if (isset($childArray)) {
                $content = $this->_buildObject($childArray, $paramArray);
            } else {
                $content = $this->_performReplacement($element->getValue(), $paramArray);
            }
            if (!empty($key)) {
                $key = $this->_performReplacement($key, $paramArray);
                $value[$key] = $content;
            } else $value[] = $content;
        }
        return $value;
    }
    
    function _performReplacement($rawInput, $paramArray = array()) {
        $value = preg_replace_callback('{{translate key="([^"]+)"}}', array($this, '_installer_regexp_callback'), $rawInput);
        foreach ($paramArray as $pKey => $pValue) {
            $value = str_replace('{$' . $pKey . '}', $pValue, $value);
        }
        return $value;
    }

    function _installer_regexp_callback($matches) {
		return __($matches[1]);
	}

    function enablePlugins($pressId, $plugins = array()) {
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
                $plugin->updateSetting(CONTEXT_ID_NONE, 'enabled', true, 'bool');
            } else {
                $plugin->updateSetting($pressId, 'enabled', true, 'bool');
            }

            echo "success\n";
        }

    }

    private function _getTheme($theme) {
        $plugin = PluginRegistry::loadPlugin("themes", $theme);
        if (!method_exists($plugin, "setEnabled")) {
            error("$theme does not exist!");
        }

        return $plugin;
    }

    function setJournalTheme($pressId, $theme) {
        echo "Set theme '$theme' for press...";
        $plugin = $this->_getTheme($theme);
        $plugin->setEnabled(true);
        $plugin->updateSetting($pressId, 'enabled', true, 'bool');
        $pressSettingsDao =& DAORegistry::getDAO('PressSettingsDAO');
        $pressSettingsDao->updateSetting($pressId, 'themePluginPath', $theme, 'string', false);
        echo "success\n";
    }

    function setTheme($theme) {
        echo "Set theme '$theme' for site...";
        $plugin = $this->_getTheme($theme);
        $plugin->setEnabled(true);
        $plugin->updateSetting(0, 'enabled', true, 'bool');
        $siteDao = DAORegistry::getDAO('SiteDAO');
        $site = $siteDao->getSite();
        $site->updateSetting('themePluginPath', $theme, 'string', false);
        echo "success\n";
    }

    function registerPluginVersions() {
        echo "Register Plugin versions...";
        $plugins = PluginRegistry::loadAllPlugins(false);

        $versionDao = DAORegistry::getDAO('VersionDAO');
        import('lib.pkp.classes.site.VersionCheck');
        $fileManager = new FileManager();

        $notHiddenPlugins = array();
        foreach ((array) $plugins as $plugin) {
            if (!$plugin->getHideManagement()) {
                $notHiddenPlugins[$plugin->getName()] = $plugin;
            }
            $version = $plugin->getCurrentVersion();
            if ($version == null) { // this plugin is on the file system, but not installed.
                $versionFile = $plugin->getPluginPath() . '/version.xml';
                if ($fileManager->fileExists($versionFile)) {
                    $versionInfo = VersionCheck::parseVersionXML($versionFile);
                    $pluginVersion = $versionInfo['version'];
                } else {
                    $pluginVersion = new Version(
                        1, 0, 0, 0, // Major, minor, revision, build
                        Core::getCurrentDate(), // Date installed
                        1,	// Current
                        'plugins.'.$plugin->getCategory(), // Type
                        basename($plugin->getPluginPath()), // Product
                        '',	// Class name
                        0,	// Lazy load
                        $plugin->isSitePlugin()	// Site wide
                    );
                }
                $versionDao->insertVersion($pluginVersion, true);
            }
        }

        echo "done\n";
        return $notHiddenPlugins;
    }

    function clearCache() {
        // Clear the template cache so that new settings can take effect
        $templateMgr = TemplateManager::getManager(Application::getRequest());
        $templateMgr->clearTemplateCache();
        $templateMgr->clearCssCache();
    }

}

set_time_limit(0);

try {
    $tool = new ojs_config_tool();
    $journalId = $tool->createJournal($opt["journal.title"], $opt["journal.path"]);
    $tool->enablePlugins($pressId, $opt["press.plugins"]);
    $tool->setTheme($opt["theme"]);
    $tool->setJournalTheme($pressId, $opt["journal.theme"]);
    $tool->registerPluginVersions();
    $tool->giveUserRoles($journalId);
    $tool->clearCache();

} catch (Exception $e) {
    error($e->getMessage());
}
?>