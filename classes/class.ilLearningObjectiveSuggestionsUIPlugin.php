<?php
include_once('./Services/UIComponent/classes/class.ilUserInterfaceHookPlugin.php');
require_once(dirname(__DIR__) . '/vendor/autoload.php');
require_once('./Customizing/global/plugins/Services/Cron/CronHook/LearningObjectiveSuggestions/vendor/autoload.php');

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

	/**
	 * @return string
	 */
	public function getPluginName() {
		return 'LearningObjectiveSuggestionsUI';
	}
}