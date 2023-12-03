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
	protected static ?ilLearningObjectiveSuggestionsUIPlugin $instance = null;
	public static function getInstance(): ilLearningObjectiveSuggestionsUIPlugin
    {
        if (!isset(self::$instance)) {
            global $DIC;
            /** @var $component_factory ilComponentFactory */
            $component_factory = $DIC['component.factory'];
            /** @var $plugin ilLearningObjectiveSuggestionsUIPlugin */
            $plugin  = $component_factory->getPlugin(ilLearningObjectiveSuggestionsUIPlugin::PLUGIN_ID);
            self::$instance = $plugin;
        }

		return self::$instance;
	}
	protected ilPluginAdmin $ilPluginAdmin;

    public function __construct(
        ilDBInterface $db,
        ilComponentRepositoryWrite $component_repository,
        string $id
    ) {
        global $DIC;
        parent::__construct($db, $component_repository, $id);
		$this->ilPluginAdmin = $DIC["ilPluginAdmin"];
	}
	protected function init(): void
    {
		parent::init();
        if(file_exists( __DIR__ . "/../../../../Cron/CronHook/LearningObjectiveSuggestions/vendor/autoload.php")) {
            require_once __DIR__ . "/../../../../Cron/CronHook/LearningObjectiveSuggestions/vendor/autoload.php";
        }
        if(file_exists(__DIR__ . "/../../../../EventHandling/EventHook/UserDefaults/vendor/autoload.php")) {
            require_once __DIR__ . "/../../../../EventHandling/EventHook/UserDefaults/vendor/autoload.php";

        }
        if(file_exists( __DIR__ . "/../../../../UIComponent/UserInterfaceHook/ParticipationCertificate/vendor/autoload.php")) {
            require_once __DIR__ . "/../../../../UIComponent/UserInterfaceHook/ParticipationCertificate/vendor/autoload.php";
        }
	}
	protected function beforeActivation(): bool
    {
		return $this->beforeUpdate();
	}
	protected function beforeUpdate(): bool
    {
		if (!is_file(__DIR__ . "/../../../../Cron/CronHook/LearningObjectiveSuggestions/classes/class.ilLearningObjectiveSuggestionsPlugin.php")) {
			// Note: if we throw an ilPluginException the message of the exception is not displayed --> it's useless
            global $DIC;
            $tpl = $DIC["tpl"];
            $tpl->setOnScreenMessage('failure',"Plugin LearningObjectiveSuggestions must be installed", true);
			return false;
		}
		return true;
	}
	public function getPluginName(): string
    {
		return self::PLUGIN_NAME;
	}
	protected function beforeUninstall(): bool
    {
		return true;
	}

    protected function afterUninstall(): void
    {
        //nothing to do
    }

    public function getImagePath(string $imageName): string {
        return $this->getDirectory()."/templates/images/".$imageName;
    }
}
