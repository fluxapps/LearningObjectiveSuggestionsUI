<?php namespace SRAG\ILIAS\Plugins\LearningObjectiveSuggestionsUI;

use SRAG\ILIAS\Plugins\LearningObjectiveSuggestions\Config\CourseConfigProvider;
use SRAG\ILIAS\Plugins\LearningObjectiveSuggestions\LearningObjective\LearningObjective;
use SRAG\ILIAS\Plugins\LearningObjectiveSuggestions\LearningObjective\LearningObjectiveCourse;
use SRAG\ILIAS\Plugins\LearningObjectiveSuggestions\LearningObjective\LearningObjectiveQuery;
use SRAG\ILIAS\Plugins\LearningObjectiveSuggestions\Notification\Parser;
use SRAG\ILIAS\Plugins\LearningObjectiveSuggestions\Notification\Placeholders;
use SRAG\ILIAS\Plugins\LearningObjectiveSuggestions\Suggestion\LearningObjectiveSuggestion;
use SRAG\ILIAS\Plugins\LearningObjectiveSuggestions\User\User;

/**
 * Class LearningObjectiveSuggestionsSendFormGUI
 *
 * @author Stefan Wanzenried <sw@studer-raimann.ch>
 */
class SuggestionsSendFormGUI extends \ilPropertyFormGUI {
	protected LearningObjectiveCourse $course;
	protected ?\ilObjUser $user;
	private LearningObjectiveQuery $learning_objective_query;
	protected CourseConfigProvider $config;
	protected Parser $parser;
	protected \ilLearningObjectiveSuggestionsUIPlugin $pl;
	public function __construct(LearningObjectiveCourse $course, ilObjUser $user, Parser $parser) {
		parent::__construct();
		$this->course = $course;
		$this->user = $user;
		$this->config = new CourseConfigProvider($course);
		$this->learning_objective_query = new LearningObjectiveQuery($this->config);
		$this->parser = $parser;
		$this->pl = \ilLearningObjectiveSuggestionsUIPlugin::getInstance();
		$this->init();
	}
	protected function init() {
		$this->setTitle($this->user->getFirstname() . ' ' . $this->user->getLastname() . ' benachrichtigen');

		$objectives = $this->getSuggestedLearningObjectives();
		$item = new \ilNonEditableValueGUI('Empfehlungen', '', true);
		$suggestions = array_map(function ($objective) {
			return $objective->getTitle();
		}, $objectives);
		$item->setValue('<ul><li>' . implode('</li><li>', $suggestions) . '</ul>');
		$this->addItem($item);

		$placeholders = new Placeholders();
		$item = new \ilTextInputGUI($this->pl->txt("subject"), 'subject');
		$item->setRequired(true);
		$subject = $this->parser->parse($this->config->getEmailSubjectTemplate(), $placeholders->getPlaceholders($this->course, $this->user, $objectives));
		$item->setValue($subject);
		$this->addItem($item);

		$item = new \ilTextAreaInputGUI($this->pl->txt("body"), 'body');
		$item->setRequired(true);
		$body = $this->parser->parse($this->config->getEmailBodyTemplate(), $placeholders->getPlaceholders($this->course, $this->user, $objectives));
		$item->setValue($body);
		$item->setRows(10);
		$this->addItem($item);

		$this->addCommandButton(\alouiCourseGUI::CMD_SEND_NOTIFICATION, $this->pl->txt("send"));
		$this->addCommandButton(\alouiCourseGUI::CMD_CANCEL, $this->pl->txt("cancel"));
	}
    /**
     * @return LearningObjective[]
     * @throws \arException
     */
	protected function getSuggestedLearningObjectives(): array
    {
		$suggestions = LearningObjectiveSuggestion::where(array(
			'user_id' => $this->user->getId(),
			'course_obj_id' => $this->course->getId(),
		))->orderBy('sort')->get();
		$objectives = array();
		foreach ($suggestions as $suggestion) {
			$objectives[] = $this->learning_objective_query->getByObjectiveId($suggestion->getObjectiveId());
		}
		return $objectives;
	}
}