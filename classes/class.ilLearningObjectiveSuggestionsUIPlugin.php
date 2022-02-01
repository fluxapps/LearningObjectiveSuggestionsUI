<?php

require_once __DIR__ . "/../vendor/autoload.php";

/**
 * Class ilLearningObjectiveSuggestionsUIPlugin
 *
 * @author Stefan Wanzenried <sw@studer-raimann.ch>
 */
class ilLearningObjectiveSuggestionsUIPlugin extends ilUserInterfaceHookPlugin {

	const PLUGIN_ID = "dhbwautoloui";
	const PLUGIN_NAME = "LearningObjectiveSuggestionsUI";
	/**
	 * @var ilLearningObjectiveSuggestionsUIPlugin
	 */
	protected static $instance;


	/**
	 * @return ilLearningObjectiveSuggestionsUIPlugin
	 */
	public static function getInstance() {
		if (self::$instance === NULL) {
			self::$instance = new self();
		}

		return self::$instance;
	}


	/**
	 * @var ilPluginAdmin
	 */
	protected $ilPluginAdmin;


	/**
	 *
	 */
	public function __construct() {
		parent::__construct();

		global $DIC;

		$this->ilPluginAdmin = $DIC["ilPluginAdmin"];
	}


	/**
	 *
	 */
	protected function init() {
		parent::init();
		require_once __DIR__ . "/../../../../Cron/CronHook/LearningObjectiveSuggestions/vendor/autoload.php";
		require_once __DIR__ . "/../../../../EventHandling/EventHook/UserDefaults/vendor/autoload.php";
		require_once __DIR__ . "/../../../../UIComponent/UserInterfaceHook/ParticipationCertificate/vendor/autoload.php";
	}


	/**
	 * @return bool
	 */
	protected function beforeActivation() {
		return $this->beforeUpdate();
	}


	/**
	 * @return bool
	 */
	protected function beforeUpdate() {
		if (!is_file(__DIR__ . "/../../../../Cron/CronHook/LearningObjectiveSuggestions/classes/class.ilLearningObjectiveSuggestionsPlugin.php")) {
			// Note: if we throw an ilPluginException the message of the exception is not displayed --> it's useless
			ilUtil::sendFailure("Plugin LearningObjectiveSuggestions must be installed", true);

			return false;
		}

		/*require_once __DIR__ . "/../../../../Cron/CronHook/LearningObjectiveSuggestions/vendor/autoload.php";
		if (!$this->ilPluginAdmin->isActive('Services', 'Cron', 'crnhk', ilLearningObjectiveSuggestionsPlugin::PLUGIN_NAME)) {
			ilUtil::sendFailure("Plugin LearningObjectiveSuggestions must be active", true);

			return false;
		}*/

		return true;
	}


	/**
	 * @return string
	 */
	public function getPluginName() {
		return self::PLUGIN_NAME;
	}


	/**
	 * @return bool
	 */
	protected function beforeUninstall() {
		return true;
	}
}
