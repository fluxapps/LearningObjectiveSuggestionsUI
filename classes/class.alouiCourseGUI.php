<?php

use SRAG\ILIAS\Plugins\LearningObjectiveSuggestions\Config\CourseConfigProvider;
use SRAG\ILIAS\Plugins\LearningObjectiveSuggestions\LearningObjective\LearningObjectiveCourse;
use SRAG\ILIAS\Plugins\LearningObjectiveSuggestions\LearningObjective\LearningObjectiveQuery;
use SRAG\ILIAS\Plugins\LearningObjectiveSuggestions\Log\Log;
use SRAG\ILIAS\Plugins\LearningObjectiveSuggestions\Log\ModificationLog;
use SRAG\ILIAS\Plugins\LearningObjectiveSuggestions\Notification\InternalMail;
use SRAG\ILIAS\Plugins\LearningObjectiveSuggestions\Notification\Notification;
use SRAG\ILIAS\Plugins\LearningObjectiveSuggestions\Notification\Sender;
use SRAG\ILIAS\Plugins\LearningObjectiveSuggestions\Notification\TwigParser;
use SRAG\ILIAS\Plugins\LearningObjectiveSuggestions\Suggestion\LearningObjectiveSuggestionModification;
use SRAG\ILIAS\Plugins\LearningObjectiveSuggestions\User\User;
use SRAG\ILIAS\Plugins\LearningObjectiveSuggestionsUI\SuggestionsFormGUI;
use SRAG\ILIAS\Plugins\LearningObjectiveSuggestionsUI\SuggestionsSendFormGUI;
use SRAG\ILIAS\Plugins\LearningObjectiveSuggestionsUI\SuggestionsTableGUI;

require_once('./Modules/Course/classes/class.ilObjCourse.php');
require_once('./Services/AccessControl/classes/class.ilObjRole.php');

/**
 * Class alouiCourseGUI
 * @author Stefan Wanzenried <sw@studer-raimann.ch>
 *
 * @ilCtrl_IsCalledBy alouiCourseGUI : ilUIPluginRouterGUI
 */
class alouiCourseGUI {

	/**
	 * @var ilCtrl
	 */
	protected $ctrl;

	/**
	 * @var ilLanguage
	 */
	protected $lng;

	/**
	 * @var ilTemplate
	 */
	protected $tpl;

	/**
	 * @var ilObjCourse
	 */
	protected $course;

	/**
	 * @var ilTabsGUI
	 */
	protected $tabs;

	/**
	 * @var ilAccessHandler
	 */
	protected $access;

	public function __construct() {
		global $ilCtrl, $lng, $tpl, $ilTabs, $ilAccess;
		$this->ctrl = $ilCtrl;
		$this->lng = $lng;
		$this->tpl = $tpl;
		$this->tabs = $ilTabs;
		$this->access = $ilAccess;
		$this->tpl->getStandardTemplate();
	}

	public function executeCommand() {
		if (!$this->checkAccess()) {
			ilUtil::sendFailure($this->lng->txt('permission_denied'), true);
			$this->ctrl->redirectByClass('ilpersonaldesktopgui');
		}
		$this->course = new ilObjCourse((int)$_GET['ref_id']);
		$this->initCourseHeader();
		$cmd = $this->ctrl->getCmd('index');
		$this->ctrl->saveParameter($this, 'ref_id');
		$this->$cmd();
		$this->tpl->show();
	}

	protected function index() {
		$table = new SuggestionsTableGUI($this, new LearningObjectiveCourse($this->course));
		$table->parseData();
		$this->tpl->setContent($table->getHTML());
	}

	protected function applyFilter() {
		$table = new SuggestionsTableGUI($this, new LearningObjectiveCourse($this->course));
		$table->writeFilterToSession();
		$table->resetOffset();
		$this->index();
	}

	protected function resetFilter() {
		$table = new SuggestionsTableGUI($this, new LearningObjectiveCourse($this->course));
		$table->resetOffset();
		$table->resetFilter();
		$this->index();
	}

	protected function editSendNotification() {
		$this->ctrl->saveParameter($this, 'user_id');
		$user = new User(new ilObjUser((int)$_GET['user_id']));
		$form = new SuggestionsSendFormGUI(new LearningObjectiveCourse($this->course), $user, new TwigParser());
		$form->setFormAction($this->ctrl->getFormAction($this));
		$this->tpl->setContent($form->getHTML());
		if ($this->getNotification($user->getId())) {
			ilUtil::sendInfo("Die Lernziel Empfehlungen wurden bereits an diesen Benutzer versendet.");
		}
	}

	protected function sendNotification() {
		$this->ctrl->saveParameter($this, 'user_id');
		$user = new User(new ilObjUser((int)$_GET['user_id']));
		$course = new LearningObjectiveCourse($this->course);
		$form = new SuggestionsSendFormGUI($course, $user, new TwigParser());
		$form->setFormAction($this->ctrl->getFormAction($this));
		if ($form->checkInput()) {
			$sender = new Sender($course, $user, new Log());
			$sender->subject($form->getInput('subject'))
				->body($form->getInput('body'));
			if ($sender->send()) {
				ilUtil::sendSuccess('Die Lernziel Empfehlungen wurden an den Benutzer und Betreuer verschickt', true);
				$this->ctrl->redirect($this);
			}
			ilUtil::sendFailure('Die Lernziel Empfehlungen konnten nicht verschickt werden');
		}
		$form->setValuesByPost();
		$this->tpl->setContent($form->getHTML());
	}

	protected function saveSuggestions() {
		global $ilUser;
		$this->ctrl->saveParameter($this, 'user_id');
		$user = new User(new ilObjUser((int)$_GET['user_id']));
		$course = new LearningObjectiveCourse($this->course);
		$query = new LearningObjectiveQuery(new CourseConfigProvider($course));
		$form = new SuggestionsFormGUI($course, $user, $query);
		if ($form->checkInput()) {
			$editor = new User($ilUser);
			$modifier = new LearningObjectiveSuggestionModification($course, $user, $editor, new ModificationLog());
			$objectives = array_map(function($objective_id) use ($query) {
				return $query->getByObjectiveId($objective_id);
			}, $form->getInput('suggestions'));
			$modifier->replaceSuggestions($objectives);
			ilUtil::sendSuccess('Die Lernziel Empfehlungen wurden angepasst', true);
			$this->ctrl->redirect($this);
		}
		$form->setValuesByPost();
		$this->tpl->setContent($form->getHTML());
	}

	protected function editSuggestions() {
		$this->ctrl->saveParameter($this, 'user_id');
		$user = new User(new ilObjUser((int)$_GET['user_id']));
		$course = new LearningObjectiveCourse($this->course);
		$query = new LearningObjectiveQuery(new CourseConfigProvider($course));
		$form = new SuggestionsFormGUI($course, $user, $query);
		$form->setFormAction($this->ctrl->getFormAction($this));
		$this->tpl->setContent($form->getHTML());
	}

	protected function cancel() {
		$this->index();
	}

	/**
	 * Fake the course header with title, description, icon etc.
	 */
	protected function initCourseHeader() {
		global $ilLocator;
		$this->tpl->setTitle($this->course->getPresentationTitle());
		$this->tpl->setDescription($this->course->getLongDescription());
		$this->tpl->setTitleIcon(ilObject::_getIcon("", "big", $this->course->getType()), $this->lng->txt("obj_" . $this->course->getType()));
		$this->ctrl->setParameterByClass('ilrepositorygui', 'ref_id', (int)$_GET['ref_id']);
		$this->tabs->setBackTarget('Zurück zum Kurs', $this->ctrl->getLinkTargetByClass(array('ilrepositorygui', 'ilobjcoursegui')));
		include_once './Services/Object/classes/class.ilObjectListGUIFactory.php';
		$lgui = ilObjectListGUIFactory::_getListGUIByType($this->course->getType());
		$lgui->initItem((int)$_GET['ref_id'], $this->course->getId());
		$this->tpl->setAlertProperties($lgui->getAlertProperties());
		$ilLocator->addRepositoryItems();
		$this->tpl->setLocator();
	}

	/**
	 * @param int $user_id
	 * @return ActiveRecord|Notification
	 */
	protected function getNotification($user_id) {
		return Notification::where(array(
			'user_id' => $user_id,
			'course_obj_id' => $this->course->getId()
		))->first();
	}

	/**
	 * @return bool
	 */
	protected function checkAccess() {
		return $this->access->checkAccess('write', '', (int)$_GET['ref_id']);
	}

}