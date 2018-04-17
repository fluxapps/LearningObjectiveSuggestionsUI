<?php namespace SRAG\ILIAS\Plugins\LearningObjectiveSuggestionsUI;

use SRAG\ILIAS\Plugins\LearningObjectiveSuggestions\LearningObjective\LearningObjectiveCourse;
use SRAG\ILIAS\Plugins\LearningObjectiveSuggestions\Notification\Notification;
use SRAG\ILIAS\Plugins\LearningObjectiveSuggestions\Suggestion\LearningObjectiveSuggestion;

/**
 * Class LearningObjectiveSuggestionsQuery
 *
 * @author  Stefan Wanzenried <sw@studer-raimann.ch>
 * @package SRAG\ILIAS\Plugins\LearningObjectiveSuggestionsUI
 */
class SuggestionsQuery {

	/**
	 * @var LearningObjectiveCourse
	 */
	protected $course;
	/**
	 * @var array
	 */
	protected $filters = array();
	/**
	 * @var array
	 */
	protected $limit = array( 0, 10 );
	/**
	 * @var array
	 */
	protected $orderBy = array( 'user_id' => 'ASC' );
	/**
	 * @var \ilDB
	 */
	protected $db;


	/**
	 * @param LearningObjectiveCourse $course
	 */
	public function __construct(LearningObjectiveCourse $course) {
		global $DIC;
		$this->db = $DIC->database();
		$this->course = $course;
	}


	/**
	 * @param array $filters
	 */
	public function setFilters(array $filters) {
		$this->filters = $filters;
	}


	/**
	 * @param string $key
	 * @param mixed  $value
	 *
	 * @return $this
	 */
	public function filter($key, $value) {
		$this->filters[$key] = $value;

		return $this;
	}


	/**
	 * @param int $start
	 * @param int $offset
	 *
	 * @return $this
	 */
	public function limit($start, $offset) {
		$this->limit = array( $start, $offset );

		return $this;
	}


	/**
	 * @param        $field
	 * @param string $direction
	 *
	 * @return $this
	 */
	public function orderBy($field, $direction = 'ASC') {
		$this->orderBy = array( $field => $direction );

		return $this;
	}


	/**
	 * @return array
	 */
	public function getData() {
		$set = $this->db->query($this->getSQL());
		$data = array();
		while ($row = $this->db->fetchAssoc($set)) {
			$data[] = $row;
		}

		return $data;
	}


	/**
	 * @return int
	 */
	public function getCount() {
		$set = $this->db->query($this->getSQL(true));

		return $this->db->numRows($set);
	}


	/**
	 * @param bool $count
	 *
	 * @return string
	 */
	protected function getSQL($count = false) {
		$sql = 'SELECT
				usr_data.usr_id,
				usr_data.usr_id AS user_id,
				usr_data.firstname,
				usr_data.lastname,
				usr_data.login,
				usr_data.email,
				NULL as suggestions,
				' . Notification::TABLE_NAME . '.sent_at AS notification_sent_at
				FROM ' . LearningObjectiveSuggestion::TABLE_NAME . '
				INNER JOIN usr_data ON (usr_data.usr_id = '
			. LearningObjectiveSuggestion::TABLE_NAME . '.user_id)
				LEFT JOIN ' . Notification::TABLE_NAME . ' ON 
					(
					' . Notification::TABLE_NAME . '.course_obj_id = '
			. LearningObjectiveSuggestion::TABLE_NAME . '.course_obj_id 
					AND 
					' . Notification::TABLE_NAME . '.user_id = '
			. LearningObjectiveSuggestion::TABLE_NAME . '.user_id
					)
				WHERE ' . LearningObjectiveSuggestion::TABLE_NAME . '.course_obj_id = '
			. $this->db->quote($this->course->getId(), 'integer');
		$member_ids = $this->course->getMemberIds();
		if (count($member_ids)) {
			$sql .= ' AND ' . LearningObjectiveSuggestion::TABLE_NAME . '.user_id IN ('
				. implode(',', $member_ids) . ') ';
		}
		foreach ($this->filters as $key => $value) {
			switch ($key) {
				case 'email':
				case 'firstname':
				case 'lastname':
				case 'login':
					$sql .= " AND usr_data.{$key} = " . $this->db->quote($value, 'text');
					break;
				case 'notification_sent':
					$sql .= " AND " . Notification::TABLE_NAME
						. ".sent_at IS NOT NULL ";
					break;
				case 'group_id':
					/** @var \ilGroupParticipants $participants */
					$participants = \ilGroupParticipants::_getInstanceByObjId((int)$value);
					$user_ids = $participants->getParticipants();
					$sql .= (count($user_ids)) ? " AND usr_data.usr_id IN (" . implode(',', $user_ids) . ") " : " AND FALSE ";
					break;
				case 'orgu_ref_id':
					$tree = \ilObjOrgUnitTree::_getInstance();
					$user_ids = array_unique($tree->getSuperiors((int)$value) + $tree->getEmployees((int)$value));
					$sql .= (count($user_ids)) ? " AND usr_data.usr_id IN (" . implode(',', $user_ids) . ") " : " AND FALSE ";
					break;
			}
		}
		$sql .= ' GROUP BY user_id, usr_data.firstname, usr_data.lastname, usr_data.login, usr_data.email, notification_sent_at';
		if (!$count) {
			$sql .= ' ORDER BY ';
			foreach ($this->orderBy as $field => $direction) {
				$field = ($field) ? $field : 'user_id';
				$sql .= "{$field} {$direction}";
			}
			list($start, $limit) = $this->limit;
			$sql .= " LIMIT {$start}, {$limit}";
		}

		return $sql;
	}
}