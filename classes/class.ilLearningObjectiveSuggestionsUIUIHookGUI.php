<?php

use SRAG\ILIAS\Plugins\LearningObjectiveSuggestions\Config\ConfigProvider;

require_once('./Services/UIComponent/classes/class.ilUIHookPluginGUI.php');

/**
 * Class ilLearningObjectiveSuggestionsUIUIHookGUI
 * @author Stefan Wanzenried <sw@studer-raimann.ch>
 */
class ilLearningObjectiveSuggestionsUIUIHookGUI extends ilUIHookPluginGUI {

	function modifyGUI($a_comp, $a_part, $a_par = array()) {
		global $ilPluginAdmin;
		/** @var $ilPluginAdmin ilPluginAdmin */
		if (!$ilPluginAdmin->isActive('Services', 'Cron', 'crnhk', 'LearningObjectiveSuggestions')) {
			return;
		}
		if ($a_part == 'tabs' && $this->displayTab() && $this->checkAccess()) {
			$this->addTab($a_par['tabs']);
		}
	}

	/**
	 * @param ilTabsGUI $tabs
	 */
	protected function addTab(ilTabsGUI $tabs) {
		global $ilCtrl, $tpl;
		$ilCtrl->setParameterByClass('alouiCourseGUI', 'ref_id', (int)$_GET['ref_id']);
		$href = $ilCtrl->getLinkTargetByClass(array('ilUIPluginRouterGUI', 'alouiCourseGUI'));
		$tabs->addTab('aloui', 'Lernziel Empfehlungen', $href);
		$ilCtrl->clearParametersByClass('alouiCourseGUI');
		// Hack to NOT make the tab active -.-
		$tpl->addOnLoadCode("
			var activeTabs = $('#ilTab li.active'); 
			if (activeTabs.length > 1) { 
			    activeTabs.each(function(i) { 
			        if (i > 0) $(this).removeClass('active');
			    }); 
			}"
		);
	}

	/**
	 * Check if the current course is configured by the plugin
	 *
	 * @return bool
	 */
	protected function displayTab() {
		$config = new ConfigProvider();
		return in_array((int)$_GET['ref_id'], $config->getCourseRefIds());
	}

	/**
	 * @return bool
	 */
	protected function checkAccess() {
		global $ilAccess;
		/** @var $ilAccess ilAccessHandler */
		return $ilAccess->checkAccess('write', '', (int)$_GET['ref_id']);
	}

}