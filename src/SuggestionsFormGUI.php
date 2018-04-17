<?php namespace SRAG\ILIAS\Plugins\LearningObjectiveSuggestionsUI;

use SRAG\ILIAS\Plugins\LearningObjectiveSuggestions\LearningObjective\LearningObjectiveCourse;
use SRAG\ILIAS\Plugins\LearningObjectiveSuggestions\LearningObjective\LearningObjectiveQuery;
use SRAG\ILIAS\Plugins\LearningObjectiveSuggestions\Score\LearningObjectiveScore;
use SRAG\ILIAS\Plugins\LearningObjectiveSuggestions\Suggestion\LearningObjectiveSuggestion;
use SRAG\ILIAS\Plugins\LearningObjectiveSuggestions\User\User;

/**
 * Class LearningObjectiveSuggestionsFormGUI
 *
 * @author Stefan Wanzenried <sw@studer-raimann.ch>
 */
class SuggestionsFormGUI extends \ilPropertyFormGUI {

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
	 * @var \ilLearningObjectiveSuggestionsUIPlugin
	 */
	protected $pl;


	/**
	 * @param LearningObjectiveCourse $course
	 * @param User                    $user
	 * @param LearningObjectiveQuery  $learning_objective_query
	 */
	public function __construct(LearningObjectiveCourse $course, User $user, LearningObjectiveQuery $learning_objective_query) {
		parent::__construct();
		$this->course = $course;
		$this->user = $user;
		$this->pl = \ilLearningObjectiveSuggestionsUIPlugin::getInstance();
		$this->learning_objective_query = $learning_objective_query;
		$this->init();
	}


	protected function init() {
		$this->setTitle($this->pl->txt("edit_suggestions_for") . ' ' . $this->user->getFirstname() . ' ' . $this->user->getLastname());
		$item = new ilAsmSelectInputGUI($this->pl->txt("suggested"), 'suggestions');
		$item->setInfo($this->pl->txt("suggested_info"));
		$item->setRequired(true);
		$scores = $this->getScores();
		$options = array();
		foreach ($scores as $score) {
			$objective = $this->learning_objective_query->getByObjectiveId($score->getObjectiveId());
			$options[$score->getObjectiveId()] = $objective->getTitle() . ' [' . $this->pl->txt("score") . '=' . $score->getScore() . ']';
		}
		$item->setOptions($options);
		$selected = array_map(function ($suggestion) {
			/** @var $suggestion LearningObjectiveSuggestion */
			return $suggestion->getObjectiveId();
		}, $this->getSuggestions());
		$item->setValue($selected);
		$this->addItem($item);

		$this->addCommandButton(\alouiCourseGUI::CMD_SAVE_SUGGESTIONS, $this->pl->txt("save"));
		$this->addCommandButton(\alouiCourseGUI::CMD_CANCEL, $this->pl->txt("cancel"));
	}


	/**
	 * @return LearningObjectiveSuggestion[]
	 */
	protected function getSuggestions() {
		return LearningObjectiveSuggestion::where(array(
			'user_id' => $this->user->getId(),
			'course_obj_id' => $this->course->getId(),
		))->orderBy('sort')->get();
	}


	/**
	 * @return LearningObjectiveScore[]
	 */
	protected function getScores() {
		return LearningObjectiveScore::where(array(
			'user_id' => $this->user->getId(),
			'course_obj_id' => $this->course->getId()
		))->orderBy('score', 'DESC')->get();
	}
}