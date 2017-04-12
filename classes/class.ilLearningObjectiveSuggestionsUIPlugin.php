<?php
include_once('./Services/UIComponent/classes/class.ilUserInterfaceHookPlugin.php');
require_once(dirname(__DIR__) . '/vendor/autoload.php');

/**
 * Class ilLearningObjectiveSuggestionsUIPlugin
 * @author Stefan Wanzenried <sw@studer-raimann.ch>
 */
class ilLearningObjectiveSuggestionsUIPlugin extends ilUserInterfaceHookPlugin {


	/**
	 * @var ilLearningObjectiveSuggestionsUIPlugin
	 */
	protected static $instance;

	/**
	 * @return ilLearningObjectiveSuggestionsUIPlugin
	 */
	public static function getInstance() {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	protected function init() {
		parent::init();
		require_once('./Customizing/global/plugins/Services/Cron/CronHook/LearningObjectiveSuggestions/vendor/autoload.php');
	}

	protected function beforeActivation() {
		return $this->beforeUpdate();
	}

	protected function beforeUpdate() {
		if (!is_file('./Customizing/global/plugins/Services/Cron/CronHook/LearningObjectiveSuggestions/classes/class.ilLearningObjectiveSuggestionsPlugin.php')) {
			// Note: if we throw an ilPluginException the message of the exception is not displayed --> it's useless
			ilUtil::sendFailure("Plugin LearningObjectiveSuggestions must be installed", true);
			return false;
		}
		global $ilPluginAdmin;
		/** @var $ilPluginAdmin ilPluginAdmin */
		if (!$ilPluginAdmin->isActive('Services', 'Cron', 'crnhk', 'LearningObjectiveSuggestions')) {
			ilUtil::sendFailure("Plugin LearningObjectiveSuggestions must be active", true);
			return false;
		}
		return true;
	}

	/**
	 * @return string
	 */
	public function getPluginName() {
		return 'LearningObjectiveSuggestionsUI';
	}
}