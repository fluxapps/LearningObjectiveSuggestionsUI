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
	protected LearningObjectiveCourse $course;
	protected array $filters = array();
	protected array $limit = array( 0, 10 );
	protected array $orderBy = array( 'user_id' => 'ASC' );
	protected \ilDBInterface $db;
	public function __construct(LearningObjectiveCourse $course) {
		global $DIC;
		$this->db = $DIC->database();
		$this->course = $course;
	}
	public function setFilters(array $filters): void
    {
		$this->filters = $filters;
	}
	public function filter(string $key, mixed $value): static
    {
		$this->filters[$key] = $value;

		return $this;
	}
	public function limit(int $start, int $offset): static
    {
		$this->limit = array( $start, $offset );

		return $this;
	}
	public function orderBy($field, string $direction = 'ASC'): static
    {
		$this->orderBy = array( $field => $direction );

		return $this;
	}
	public function getData(): array
    {
		$set = $this->db->query($this->getSQL());
		$data = array();
		while ($row = $this->db->fetchAssoc($set)) {
			$data[] = $row;
		}

		return $data;
	}
	public function getCount(): int
    {
		$set = $this->db->query($this->getSQL(true));

		return $this->db->numRows($set);
	}
	protected function getSQL(bool $count = false): string
    {
		$sql = 'SELECT
				usr_data.usr_id,
				usr_data.usr_id AS user_id,
				usr_data.firstname,
				usr_data.lastname,
				usr_data.login,
				usr_data.email,
				NULL as suggestions,
				' . Notification::TABLE_NAME . '.sent_at AS notification_sent_at,
				is_cron_active
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