<?php namespace SRAG\ILIAS\Plugins\LearningObjectiveSuggestionsUI;

use SRAG\ILIAS\Plugins\LearningObjectiveSuggestions\Config\CourseConfigProvider;
use SRAG\ILIAS\Plugins\LearningObjectiveSuggestions\LearningObjective\LearningObjective;
use SRAG\ILIAS\Plugins\LearningObjectiveSuggestions\LearningObjective\LearningObjectiveCourse;
use SRAG\ILIAS\Plugins\LearningObjectiveSuggestions\LearningObjective\LearningObjectiveQuery;
use SRAG\ILIAS\Plugins\LearningObjectiveSuggestions\Suggestion\LearningObjectiveSuggestion;

/**
 * Class LearningObjectiveSuggestionsTableGUI
 *
 * @author  Stefan Wanzenried <sw@studer-raimann.ch>
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
	 * @var \ilTree
	 */
	protected $tree;
	/**
	 * @var \ilLearningObjectiveSuggestionsUIPlugin
	 */
	protected $pl;


	/**
	 * @param                         $a_parent_obj
	 * @param LearningObjectiveCourse $course
	 */
	public function __construct($a_parent_obj, LearningObjectiveCourse $course) {
		global $DIC;
		$this->setPrefix('alo');
		$this->setId('learning_obj_suggestions_' . $course->getId());
		parent::__construct($a_parent_obj, '', '');
		$this->ctrl = $DIC->ctrl();
		$this->tree = $DIC->repositoryTree();
		$this->course = $course;
		$this->pl = \ilLearningObjectiveSuggestionsUIPlugin::getInstance();
		$this->learning_objective_query = new LearningObjectiveQuery(new CourseConfigProvider($course));
		$this->setFormAction($this->ctrl->getFormAction($a_parent_obj));
		$this->setRowTemplate('tpl.row_generic.html', $this->pl->getDirectory());
		$this->setTitle($this->pl->txt("suggestions"));
		$this->addColumns();
		$this->initFilter();
	}


	/**
	 * @return array
	 */
	public function getSelectableColumns() {
		return array(
			'login' => array( 'txt' => 'login', 'default' => true ),
			'firstname' => array( 'txt' => 'firstname', 'default' => true ),
			'lastname' => array( 'txt' => 'lastname', 'default' => true ),
			'email' => array( 'txt' => 'email', 'default' => false ),
			'suggestions' => array( 'txt' => 'suggestions', 'default' => true ),
			'notification_sent_at' => array( 'txt' => 'notified_on', 'default' => true ),
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
		$item = new \ilTextInputGUI($this->pl->txt('login'), 'login');
		$this->addFilterItemWithValue($item);
		$item = new \ilTextInputGUI($this->pl->txt('firstname'), 'firstname');
		$this->addFilterItemWithValue($item);
		$item = new \ilTextInputGUI($this->pl->txt('lastname'), 'lastname');
		$this->addFilterItemWithValue($item);
		$item = new \ilTextInputGUI($this->pl->txt('email'), 'email');
		$this->addFilterItemWithValue($item);
		$item = new \ilCheckboxInputGUI($this->pl->txt('notification_sent'), 'notification_sent');
		$this->addFilterItemWithValue($item);
		$item = new \ilSelectInputGUI($this->pl->txt('member_in_group'), 'group_id');
		$item->setOptions($this->getGroups());
		$this->addFilterItemWithValue($item);
		$item = new \ilSelectInputGUI($this->pl->txt('in'), 'orgu_ref_id');
		$item->setOptions($this->getOrgUnits());
		$this->addFilterItemWithValue($item);
	}


	/**
	 * @return array
	 */
	protected function getOrgUnits() {
		return array( '' => '' ) + $this->getOrgUnitsRecursive(\ilObjOrgUnit::getRootOrgRefId(), 0);
	}


	/**
	 * @param array $ref_id
	 * @param int   $level
	 *
	 * @return array
	 */
	private function getOrgUnitsRecursive($ref_id, $level) {
		$orgus = array();
		$nodes = $this->tree->getChildsByType($ref_id, 'orgu');
		foreach ($nodes as $node) {
			$orgus[$node['ref_id']] = str_repeat("&nbsp;", $level * 3) . $node['title'];
			$children = $this->getOrgUnitsRecursive($node['ref_id'], $level + 1);
			if (count($children)) {
				$orgus += $children;
			}
		}

		return $orgus;
	}


	/**
	 * @return array
	 */
	protected function getGroups() {
		$groups = array( '' => '' );
		$parent_node = $this->tree->getNodeData($this->course->getRefId());
		$nodes = $this->tree->getSubTree($parent_node, true, 'grp');
		foreach ($nodes as $node) {
			if ($node['deleted']) {
				continue;
			}
			$groups[$node['obj_id']] = $node['title'];
		}

		return $groups;
	}


	/**
	 * @param $item
	 */
	protected function addFilterItemWithValue($item) {
		$this->addFilterItem($item);
		$item->readFromSession();
		switch (get_class($item)) {
			case \ilSelectInputGUI::class:
				$value = $item->getValue();
				break;
			case \ilCheckboxInputGUI::class:
				$value = $item->getChecked();
				break;
			case \ilDateTimeInputGUI::class:
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
				$this->addColumn($this->pl->txt($data['txt']), $sort);
			}
		}
		$this->addColumn($this->pl->txt('actions'));
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
		$list->setId(implode('_', array( $this->getId(), ++ $id )));
		$this->ctrl->setParameter($this->parent_obj, 'user_id', $a_set['user_id']);
		$list->addItem($this->pl->txt('edit_suggestions'), '', $this->ctrl->getLinkTarget($this->parent_obj, \alouiCourseGUI::CMD_EDIT_SUGGESTIONS));
		$list->addItem($this->pl->txt('send_suggestions'), '', $this->ctrl->getLinkTarget($this->parent_obj, \alouiCourseGUI::CMD_EDIT_SEND_NOTIFICATION));
		$this->ctrl->clearParameters($this->parent_obj);
		$list->setListTitle($this->pl->txt('actions'));
		$this->tpl->setCurrentBlock('td');
		$this->tpl->setVariable('VALUE', $list->getHTML());
		$this->tpl->parseCurrentBlock();
	}


	protected function getFormattedValue($column, $a_set) {
		$value = $a_set[$column];
		switch ($column) {
			case 'suggestions':
				$objectives = array_map(function ($objective) {
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
	 *
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