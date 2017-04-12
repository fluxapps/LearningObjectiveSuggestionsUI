<?php namespace SRAG\ILIAS\Plugins\LearningObjectiveSuggestionsUI;

use SRAG\ILIAS\Plugins\LearningObjectiveSuggestions\Config\CourseConfigProvider;
use SRAG\ILIAS\Plugins\LearningObjectiveSuggestions\LearningObjective\LearningObjective;
use SRAG\ILIAS\Plugins\LearningObjectiveSuggestions\LearningObjective\LearningObjectiveCourse;
use SRAG\ILIAS\Plugins\LearningObjectiveSuggestions\LearningObjective\LearningObjectiveQuery;
use SRAG\ILIAS\Plugins\LearningObjectiveSuggestions\Suggestion\LearningObjectiveSuggestion;

require_once('./Services/Table/classes/class.ilTable2GUI.php');
require_once('./Services/UIComponent/AdvancedSelectionList/classes/class.ilAdvancedSelectionListGUI.php');
require_once('./Services/Form/classes/class.ilTextInputGUI.php');
require_once('./Services/Form/classes/class.ilCheckboxInputGUI.php');

/**
 * Class LearningObjectiveSuggestionsTableGUI
 * @author Stefan Wanzenried <sw@studer-raimann.ch>
 * @package SRAG\ILIAS\Plugins\AutoLearningObjectivesUI
 */
class SuggestionsTableGUI extends \ilTable2GUI {

	/**
	 * @var \ilCtrl
	 */
	protected $ctrl;

	/**
	 * @var LearningObjectiveCourse
	 */
	protected $course;

	/**
	 * @var array
	 */
	protected $filter = array();

	/**
	 * @var LearningObjectiveQuery
	 */
	protected $learning_objective_query;

	/**
	 * @param $a_parent_obj
	 * @param LearningObjectiveCourse $course
	 */
	public function __construct($a_parent_obj, LearningObjectiveCourse $course) {
		global $ilCtrl;
		$this->setPrefix('alo');
		$this->setId('learning_obj_suggestions_' . $course->getId());
		parent::__construct($a_parent_obj, '', '');
		$this->ctrl = $ilCtrl;
		$this->course = $course;
		$this->learning_objective_query = new LearningObjectiveQuery(new CourseConfigProvider($course));
		$this->setFormAction($this->ctrl->getFormAction($a_parent_obj));
		$this->setRowTemplate('tpl.row_generic.html', './Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/LearningObjectiveSuggestionsUI');
		$this->setTitle('Lernziel Empfehlungen');
		$this->addColumns();
		$this->initFilter();
	}

	/**
	 * @return array
	 */
	public function getSelectableColumns() {
		return array(
			'login' => array('txt' => 'Login', 'default' => true),
			'firstname' => array('txt' => 'Vorname', 'default' => true),
			'lastname' => array('txt' => 'Nachname', 'default' => true),
			'email' => array('txt' => 'E-Mail', 'default' => false),
			'suggestions' => array('txt' => 'Lernziel Empfehlungen', 'default' => true),
			'notification_sent_at' => array('txt' => 'Benachrichtigt am', 'default' => true),
		);
	}

	/**
	 * Initialize data
	 */
	public function parseData() {
		$this->setExternalSegmentation(true);
		$this->setExternalSorting(true);
		$this->determineOffsetAndOrder();
		$query = new SuggestionsQuery($this->course);
		$query->setFilters($this->filter);
		$query->orderBy($this->getOrderField(), $this->getOrderDirection());
		$query->limit($this->getOffset(), $this->getLimit());
		$this->setData($query->getData());
		$this->setMaxCount($query->getCount());
	}

	public function initFilter() {
		$item = new \ilTextInputGUI('Login', 'login');
		$this->addFilterItemWithValue($item);
		$item = new \ilTextInputGUI('Vorname', 'firstname');
		$this->addFilterItemWithValue($item);
		$item = new \ilTextInputGUI('Nachname', 'lastname');
		$this->addFilterItemWithValue($item);
		$item = new \ilTextInputGUI('E-Mail', 'email');
		$this->addFilterItemWithValue($item);
		$item = new \ilCheckboxInputGUI('Benachrichtigung verschickt', 'notification_sent');
		$this->addFilterItemWithValue($item);
	}

	/**
	 * @param $item
	 */
	protected function addFilterItemWithValue($item) {
		$this->addFilterItem($item);
		$item->readFromSession();
		switch (get_class($item)) {
			case 'ilSelectInputGUI':
				$value = $item->getValue();
				break;
			case 'ilCheckboxInputGUI':
				$value = $item->getChecked();
				break;
			case 'ilDateTimeInputGUI':
				$value = $item->getDate();
				break;
			default:
				$value = $item->getValue();
				break;
		}
		if ($value) {
			$this->filter[$item->getPostVar()] = $value;
		}
	}

	protected function addColumns() {
		foreach ($this->getSelectableColumns() as $column => $data) {
			if ($this->isColumnSelected($column)) {
				$sort = ($column == 'suggestions') ? '' : $column;
				$this->addColumn($data['txt'], $sort);
			}
		}
		$this->addColumn('Aktionen');
	}

	/**
	 * @param array $a_set
	 */
	protected function fillRow($a_set) {
		foreach (array_keys($this->getSelectableColumns()) as $column) {
			if (!$this->isColumnSelected($column)) {
				continue;
			}
			$this->tpl->setCurrentBlock('td');
			$this->tpl->setVariable('VALUE', $this->getFormattedValue($column, $a_set));
			$this->tpl->parseCurrentBlock();
		}
		$list = new \ilAdvancedSelectionListGUI();
		static $id = 0;
		$list->setId(implode('_', array($this->getId(), ++$id)));
		$this->ctrl->setParameter($this->parent_obj, 'user_id', $a_set['user_id']);
		$list->addItem('Empfehlungen bearbeiten', '', $this->ctrl->getLinkTarget($this->parent_obj, 'editSuggestions'));
		$list->addItem('Empfehlungen verschicken', '', $this->ctrl->getLinkTarget($this->parent_obj, 'editSendNotification'));
		$this->ctrl->clearParameters($this->parent_obj);
		$list->setListTitle('Aktionen');
		$this->tpl->setCurrentBlock('td');
		$this->tpl->setVariable('VALUE', $list->getHTML());
		$this->tpl->parseCurrentBlock();
	}

	protected function getFormattedValue($column, $a_set) {
		$value = $a_set[$column];
		switch ($column) {
			case 'suggestions':
				$objectives = array_map(function($objective) {
					return $objective->getTitle();
				}, $this->getSuggestedLearningObjectives($a_set['user_id']));
				return implode('<br>', $objectives);
			case 'notification_sent_at':
				return ($value) ? date('d.m.Y, H:i:s', strtotime($value)) : '&nbsp;';
			default:
				return ($value) ? $value : '&nbsp;';
		}
	}

	/**
	 * @param int $user_id
	 * @return LearningObjective[]
	 */
	protected function getSuggestedLearningObjectives($user_id) {
		$suggestions = LearningObjectiveSuggestion::where(array(
			'user_id' => $user_id,
			'course_obj_id' => $this->course->getId()
		))->orderBy('sort')->get();
		$return = array();
		foreach ($suggestions as $suggestion) {
			/** @var $suggestion LearningObjectiveSuggestion */
			$return[] = $this->learning_objective_query->getByObjectiveId($suggestion->getObjectiveId());
		}
		return $return;
	}

}