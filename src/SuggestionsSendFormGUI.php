<?php namespace SRAG\ILIAS\Plugins\LearningObjectiveSuggestionsUI;

use SRAG\ILIAS\Plugins\LearningObjectiveSuggestions\Config\CourseConfigProvider;
use SRAG\ILIAS\Plugins\LearningObjectiveSuggestions\LearningObjective\LearningObjective;
use SRAG\ILIAS\Plugins\LearningObjectiveSuggestions\LearningObjective\LearningObjectiveCourse;
use SRAG\ILIAS\Plugins\LearningObjectiveSuggestions\LearningObjective\LearningObjectiveQuery;
use SRAG\ILIAS\Plugins\LearningObjectiveSuggestions\Notification\Parser;
use SRAG\ILIAS\Plugins\LearningObjectiveSuggestions\Notification\Placeholders;
use SRAG\ILIAS\Plugins\LearningObjectiveSuggestions\Notification\TwigParser;
use SRAG\ILIAS\Plugins\LearningObjectiveSuggestions\Score\LearningObjectiveScore;
use SRAG\ILIAS\Plugins\LearningObjectiveSuggestions\Suggestion\LearningObjectiveSuggestion;
use SRAG\ILIAS\Plugins\LearningObjectiveSuggestions\User\User;

require_once('./Services/Form/classes/class.ilPropertyFormGUI.php');

/**
 * Class LearningObjectiveSuggestionsSendFormGUI
 * @author Stefan Wanzenried <sw@studer-raimann.ch>
 */
class SuggestionsSendFormGUI extends \ilPropertyFormGUI {

	/**
	 * @var LearningObjectiveCourse
	 */
	protected $course;

	/**
	 * @var User
	 */
	protected $user;
	/**
	 * @var LearningObjectiveQuery
	 */
	private $learning_objective_query;

	/**
	 * @var CourseConfigProvider
	 */
	protected $config;

	/**
	 * @var Parser
	 */
	protected $parser;

	/**
	 * @param LearningObjectiveCourse $course
	 * @param User $user
	 * @param Parser $parser
	 */
	public function __construct(LearningObjectiveCourse $course, User $user, Parser $parser) {
		parent::__construct();
		$this->course = $course;
		$this->user = $user;
		$this->config = new CourseConfigProvider($course);
		$this->learning_objective_query = new LearningObjectiveQuery($this->config);
		$this->parser = $parser;
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
		$item = new \ilTextInputGUI('Betreff', 'subject');
		$item->setRequired(true);
		$subject = $this->parser->parse($this->config->getEmailSubjectTemplate(), $placeholders->getPlaceholders($this->course, $this->user, $objectives));
		$item->setValue($subject);
		$this->addItem($item);

		$item = new \ilTextAreaInputGUI('Inhalt', 'body');
		$item->setRequired(true);
		$body = $this->parser->parse($this->config->getEmailBodyTemplate(), $placeholders->getPlaceholders($this->course, $this->user, $objectives));
		$item->setValue($body);
		$item->setRows(10);
		$this->addItem($item);

		$this->addCommandButton('sendNotification', 'Absenden');
		$this->addCommandButton('cancel', 'Abbrechen');
	}

	/**
	 * @return LearningObjective[]
	 */
	protected function getSuggestedLearningObjectives() {
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