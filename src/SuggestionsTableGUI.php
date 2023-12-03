<?php namespace SRAG\ILIAS\Plugins\LearningObjectiveSuggestionsUI;

use ilExcel;
use ilObjOrgUnit;
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
	protected \ilCtrl $ctrl;
	protected LearningObjectiveCourse $course;
	protected array $filter = array();
	protected LearningObjectiveQuery $learning_objective_query;
	protected \ilTree $tree;
	protected \ilLearningObjectiveSuggestionsUIPlugin $pl;


    /**
     * @param                         $a_parent_obj
     * @param LearningObjectiveCourse $course
     * @throws \ilCtrlException
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
		$this->setExportFormats([ self::EXPORT_EXCEL ]);
	}
	public function getSelectableColumns(): array
    {
		return array(
			'login' => array( 'txt' => 'login', 'default' => true ),
			'firstname' => array( 'txt' => 'firstname', 'default' => true ),
			'lastname' => array( 'txt' => 'lastname', 'default' => true ),
			'email' => array( 'txt' => 'email', 'default' => false ),
			'suggestions' => array( 'txt' => 'suggestions', 'default' => true ),
			'notification_sent_at' => array( 'txt' => 'notified_on', 'default' => true ),
			'is_cron_active' => array( 'txt' => 'cron', 'default' => true )
		);
	}
	public function parseData(): void
    {
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
	public function initFilter(): void
    {
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
	protected function getOrgUnits(): array
    {
		return array( '' => '' ) + $this->getOrgUnitsRecursive(ilObjOrgUnit::getRootOrgRefId(), 0);
	}
	private function getOrgUnitsRecursive(int $ref_id, int $level): array
    {
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
	protected function getGroups(): array
    {
		$groups = array( '' => '' );
		$parent_node = $this->tree->getNodeData($this->course->getRefId());
		$nodes = $this->tree->getSubTree($parent_node, true, ['grp']);
		foreach ($nodes as $node) {
			if ($node['deleted']) {
				continue;
			}
			$groups[$node['obj_id']] = $node['title'];
		}

		return $groups;
	}
	protected function addFilterItemWithValue($item): void
    {
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
	protected function addColumns(): void
    {
		foreach ($this->getSelectableColumns() as $column => $data) {
			if ($this->isColumnSelected($column)) {
				$sort = ($column == 'suggestions') ? '' : $column;
				$this->addColumn($this->pl->txt($data['txt']), $sort);
			}
		}
		if(!$this->export_mode) {
			$this->addColumn($this->pl->txt('actions'));
		}
	}
	protected function fillRow(array $a_set): void
    {
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

		switch ($a_set['is_cron_active']) {
			case 1:
				$list->addItem($this->pl->txt('deactivate_cron'), '', $this->ctrl->getLinkTarget($this->parent_obj, \alouiCourseGUI::CMD_DEACTIVATE_CRON));
				break;
			default:
				$list->addItem($this->pl->txt('activate_cron'), '', $this->ctrl->getLinkTarget($this->parent_obj, \alouiCourseGUI::CMD_ACTIVATE_CRON));
				break;
		}

		$this->ctrl->clearParameters($this->parent_obj);
		$list->setListTitle($this->pl->txt('actions'));
		$this->tpl->setCurrentBlock('td');
		$this->tpl->setVariable('VALUE', $list->getHTML());
		$this->tpl->parseCurrentBlock();
	}
	protected function fillRowExcel(ilExcel $a_excel, int &$a_row, array $a_set): void
    {
		$col = 0;
		foreach (array_keys($this->getSelectableColumns()) as $column) {

			if (!$this->isColumnSelected($column)) {
				continue;
			}

			$a_excel->setCell($a_row, $col, $this->getFormatedValueExcel($column, $a_set));
			$col = $col + 1;
		}
	}
	protected function getFormattedValue($column, $a_set): string {
		global $DIC;

		$value = $a_set[$column];
		switch ($column) {
			case 'suggestions':
				$objectives = array_map(function ($objective) {
					return $objective->getTitle();
				}, $this->getSuggestedLearningObjectives($a_set['user_id']));

				return implode('<br>', $objectives);
			case 'notification_sent_at':
				return ($value) ? date('d.m.Y, H:i:s', strtotime($value)) : '&nbsp;';
			case 'is_cron_active':
				$factory = $DIC->ui()->factory();
				if ($value == 1) {
					return $renderer = $DIC->ui()->renderer()->render($factory->image()->standard($this->pl->getImagePath("on.svg"), ''));
				}

				return $renderer = $DIC->ui()->renderer()->render($factory->image()->standard($this->pl->getImagePath("off.svg"), ''));
			default:
				return ($value) ? $value : '&nbsp;';
		}
	}
	protected function getFormatedValueExcel($column, $a_set): string {
		$value = $a_set[$column];
		switch ($column) {
			case 'suggestions':
				$objectives = array_map(function ($objective) {
					return $objective->getTitle();
				}, $this->getSuggestedLearningObjectives($a_set['user_id']));
				$lfcr = chr(10);
				return implode($lfcr, $objectives);
			case 'notification_sent_at':
				return ($value) ? date('d.m.Y, H:i:s', strtotime($value)) : ' ';
			default:
				return ($value) ? $value : ' ';
		}
	}
	protected function getSuggestedLearningObjectives(int $user_id): array
    {
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