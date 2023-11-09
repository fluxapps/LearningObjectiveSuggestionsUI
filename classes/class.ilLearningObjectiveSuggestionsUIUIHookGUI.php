<?php

require_once __DIR__ . "/../vendor/autoload.php";

use SRAG\ILIAS\Plugins\LearningObjectiveSuggestions\Config\ConfigProvider;

/**
 * Class ilLearningObjectiveSuggestionsUIUIHookGUI
 *
 * @author Stefan Wanzenried <sw@studer-raimann.ch>
 */
class ilLearningObjectiveSuggestionsUIUIHookGUI extends ilUIHookPluginGUI {

	const TAB_SUGGESTIONS = "suggestions";
	protected ilPluginAdmin $ilPluginAdmin;
	protected ilCtrl|ilCtrlInterface $ctrl;
	protected ilTemplate $tpl;
	protected ilAccessHandler $access;
	protected ilLearningObjectiveSuggestionsUIPlugin $pl;

	public function __construct() {
		global $DIC;
		$this->ilPluginAdmin = $DIC["ilPluginAdmin"];
		$this->ctrl = $DIC->ctrl();
		$this->access = $DIC->access();
		$this->pl = ilLearningObjectiveSuggestionsUIPlugin::getInstance();
	}
	function modifyGUI($a_comp, $a_part, $a_par = array()): void
    {
		/*if (!$this->ilPluginAdmin->isActive('Services', 'Cron', 'crnhk', ilLearningObjectiveSuggestionsPlugin::PLUGIN_NAME)) {
			return;
		}*/
		if ($a_part == 'tabs' && $this->displayTab() && $this->checkAccess()) {
			$this->addTab($a_par['tabs']);
		}
	}
	protected function addTab(ilTabsGUI $tabs): void
    {
		global $DIC;
		$tpl = $DIC->ui()->mainTemplate();
		$this->ctrl->setParameterByClass(alouiCourseGUI::class, 'ref_id', (int)$_GET['ref_id']);
		$href = $this->ctrl->getLinkTargetByClass(array( ilUIPluginRouterGUI::class, alouiCourseGUI::class ));
		$tabs->addTab(self::TAB_SUGGESTIONS, $this->pl->txt("suggestions"), $href);
		$this->ctrl->clearParametersByClass(alouiCourseGUI::class);
		// Hack to NOT make the tab active -.-
		$tpl->addOnLoadCode("
			var activeTabs = $('#ilTab li.active'); 
			if (activeTabs.length > 1) { 
			    activeTabs.each(function(i) { 
			        if (i > 0) $(this).removeClass('active');
			    }); 
			}");
	}
	/**
	 * Check if the current course is configured by the plugin
	 */
	protected function displayTab(): bool
    {
		$config = new ConfigProvider();

		return in_array((int)$_GET['ref_id'], $config->getCourseRefIds());
	}
	protected function checkAccess(): bool
    {
		return $this->access->checkAccess('write', '', (int)$_GET['ref_id']);
	}
}