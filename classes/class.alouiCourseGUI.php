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
use SRAG\ILIAS\Plugins\LearningObjectiveSuggestions\Suggestion\LearningObjectiveSuggestions;

require_once __DIR__ . "/../vendor/autoload.php";

/**
 * Class alouiCourseGUI
 *
 * @author            Stefan Wanzenried <sw@studer-raimann.ch>
 *
 * @ilCtrl_IsCalledBy alouiCourseGUI : ilUIPluginRouterGUI
 */
class alouiCourseGUI
{

    const CMD_APPLY_FILTER = 'applyFilter';
    const CMD_CANCEL = "cancel";
    const CMD_EDIT_SEND_NOTIFICATION = "editSendNotification";
    const CMD_EDIT_SUGGESTIONS = "editSuggestions";
    const CMD_INDEX = "index";
    const CMD_RESET_FILTER = 'resetFilter';
    const CMD_SAVE_SUGGESTIONS = "saveSuggestions";
    const CMD_SEND_NOTIFICATION = 'sendNotification';
    const CMD_DEACTIVATE_CRON = "deactivateCron";
    const CMD_ACTIVATE_CRON = "activateCron";
    protected ilCtrl|ilCtrlInterface $ctrl;
    protected ilLanguage $lng;
    protected ilTemplate|ilGlobalTemplateInterface $tpl;
    protected ilObjCourse $course;
    protected ilTabsGUI $tabs;
    protected ilAccessHandler $access;
    protected ilObjUser $usr;
    protected mixed $locator;
    protected ilLearningObjectiveSuggestionsUIPlugin $pl;


    public function __construct()
    {
        global $DIC;
        $this->ctrl = $DIC->ctrl();
        $this->lng = $DIC->language();
        $this->tpl = $DIC->ui()->mainTemplate();
        $this->tabs = $DIC->tabs();
        $this->access = $DIC->access();
        $this->usr = $DIC->user();
        $this->locator = $DIC["ilLocator"];
        $this->pl = ilLearningObjectiveSuggestionsUIPlugin::getInstance();
        if (method_exists($this->tpl, 'loadStandardTemplate')) {
            $this->tpl->loadStandardTemplate();
        } else {
            $this->tpl->getStandardTemplate();
        }
    }
    public function executeCommand(): void
    {
        if (!$this->checkAccess()) {
            ilUtil::sendFailure($this->lng->txt('permission_denied'), true);
            $this->ctrl->redirectByClass(ilDashboardGUI::class);
        }
        $this->course = new ilObjCourse((int)$_GET['ref_id']);
        $this->initCourseHeader();
        $cmd = $this->ctrl->getCmd(self::CMD_INDEX);
        $this->ctrl->saveParameter($this, 'ref_id');
        $this->$cmd();
        if (method_exists($this->tpl, 'printToStdout')) {
            $this->tpl->printToStdout();
        } else {
            $this->tpl->show();
        }
    }
    protected function index(): void
    {
        $table = new SuggestionsTableGUI($this, new LearningObjectiveCourse($this->course));
        $table->parseData();
        $this->tpl->setContent($table->getHTML());
    }
    protected function applyFilter(): void
    {
        $table = new SuggestionsTableGUI($this, new LearningObjectiveCourse($this->course));
        $table->writeFilterToSession();
        $table->resetOffset();
        $this->index();
    }
    protected function resetFilter(): void
    {
        $table = new SuggestionsTableGUI($this, new LearningObjectiveCourse($this->course));
        $table->resetOffset();
        $table->resetFilter();
        $this->index();
    }
    protected function editSendNotification(): void
    {
        $this->ctrl->saveParameter($this, 'user_id');
        $user = new User(new ilObjUser((int)$_GET['user_id']));
        $form = new SuggestionsSendFormGUI(new LearningObjectiveCourse($this->course), $user, new TwigParser());
        $form->setFormAction($this->ctrl->getFormAction($this));
        $this->tpl->setContent($form->getHTML());
        if ($this->getNotification($user->getId())) {
            ilUtil::sendInfo($this->pl->txt("suggestions_already_sent"));
        }
    }
    protected function sendNotification(): void
    {
        $this->ctrl->saveParameter($this, 'user_id');
        $user = new User(new ilObjUser((int)$_GET['user_id']));
        $course = new LearningObjectiveCourse($this->course);
        $form = new SuggestionsSendFormGUI($course, $user, new TwigParser());
        $form->setFormAction($this->ctrl->getFormAction($this));
        if ($form->checkInput()) {
            $sender = new Sender($course, $user, new Log());
            $sender->subject($form->getInput('subject'))->body($form->getInput('body'));
            if ($sender->send()) {
                ilUtil::sendSuccess($this->pl->txt("suggestions_sent"), true);
                $this->ctrl->redirect($this);
            }
            ilUtil::sendFailure($this->pl->txt('send_suggestions_failed'));
        }
        $form->setValuesByPost();
        $this->tpl->setContent($form->getHTML());
    }
    protected function saveSuggestions(): void
    {
        $this->ctrl->saveParameter($this, 'user_id');
        $user = new User(new ilObjUser((int)$_GET['user_id']));
        $course = new LearningObjectiveCourse($this->course);
        $query = new LearningObjectiveQuery(new CourseConfigProvider($course));
        $form = new SuggestionsFormGUI($course, $user, $query);
        if ($form->checkInput()) {
            $editor = new User($this->usr);
            $modifier = new LearningObjectiveSuggestionModification($course, $user, $editor, new ModificationLog());
            $objectives = array_map(function ($objective_id) use ($query) {
                return $query->getByObjectiveId($objective_id);
            }, $form->getInput('suggestions'));
            $modifier->replaceSuggestions($objectives);
            ilUtil::sendSuccess($this->pl->txt('saved_suggestions'), true);
            $this->ctrl->redirect($this);
        }
        $form->setValuesByPost();
        $this->tpl->setContent($form->getHTML());
    }
    protected function editSuggestions(): void
    {
        $this->ctrl->saveParameter($this, 'user_id');
        $user = new User(new ilObjUser((int)$_GET['user_id']));
        $course = new LearningObjectiveCourse($this->course);
        $query = new LearningObjectiveQuery(new CourseConfigProvider($course));
        $form = new SuggestionsFormGUI($course, $user, $query);
        $form->setFormAction($this->ctrl->getFormAction($this));
        $this->tpl->setContent($form->getHTML());
    }
    protected function activateCron(): void
    {
        $this->ctrl->saveParameter($this, 'user_id');
        $user = new User(new ilObjUser((int)$_GET['user_id']));
        $course = new LearningObjectiveCourse($this->course);

        $learning_objective_suggestions = new LearningObjectiveSuggestions($course, $user);
        $learning_objective_suggestions->setCronActive();

        ilUtil::sendSuccess($this->pl->txt('saved_suggestions'), true);
        $this->ctrl->redirect($this);

    }
    protected function deactivateCron(): void
    {
        $this->ctrl->saveParameter($this, 'user_id');
        $user = new User(new ilObjUser((int)$_GET['user_id']));
        $course = new LearningObjectiveCourse($this->course);

        $learning_objective_suggestions = new LearningObjectiveSuggestions($course, $user);
        $learning_objective_suggestions->setCronInactive();

        ilUtil::sendSuccess($this->pl->txt('saved_suggestions'), true);
        $this->ctrl->redirect($this);
    }
    protected function cancel(): void
    {
        $this->index();
    }
    /**
     * Fake the course header with title, description, icon etc.
     */
    protected function initCourseHeader(): void
    {
        $this->tpl->setTitle($this->course->getPresentationTitle());
        $this->tpl->setDescription($this->course->getLongDescription());
        $this->tpl->setTitleIcon(ilObject::_getIcon("", "big", $this->course->getType()), $this->lng->txt("obj_" . $this->course->getType()));
        $this->ctrl->setParameterByClass(ilRepositoryGUI::class, 'ref_id', (int)$_GET['ref_id']);
        $this->tabs->setBackTarget($this->pl->txt("back_to_course"), $this->ctrl->getLinkTargetByClass(array(
            ilRepositoryGUI::class,
            ilObjCourseGUI::class
        )));
        $lgui = ilObjectListGUIFactory::_getListGUIByType($this->course->getType());
        $lgui->initItem((int)$_GET['ref_id'], $this->course->getId(), false);
        $this->tpl->setAlertProperties($lgui->getAlertProperties());
        $this->locator->addRepositoryItems();
        $this->tpl->setLocator();
    }
    protected function getNotification(int $user_id): Notification|ActiveRecord
    {
        return Notification::where(array(
            'user_id' => $user_id,
            'course_obj_id' => $this->course->getId()
        ))->first();
    }
    protected function checkAccess(): bool
    {
        return $this->access->checkAccess('write', '', (int)$_GET['ref_id']);
    }
}