<?php

/** 
 * Author: Dennis Twardy
 * Copyright: Deutsches Archäologisches Institut 
 * Contact: idai.publications@dainst.de
 * Licensed under GNU GPL v3. For full terms see LICENSE file.
 * 
 * Attributions:
 * Code is based on previous works of John Willinsky, Simon Fraser University for Public Knowledge Project (PKP) and Phillipp Franck for DAI
 * 
 * Description:
 * Small command line tool to setup OJS with a new journal and by default the administrator with all default roles.
 */

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
    "path" => '/var/www/html/ojs3/',
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

        return $journalId;
    }

    //
    // giveUserRoles
    //
    // $journalId = internal contextId
    // $userId = Id of user to be modified (optional, defaults to Admin)
    function giveUserRoles($journalId, $userId = 1) {
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
}

try {
    $testTool = new ojs_config_tool();
    $journalId = $testTool->createJournal($opt["journal.title"], $opt["journal.path"]);
    $testTool->giveUserRoles($journalId);
}
catch (Exception $e) {
    error($e->getMessage());
}
?>