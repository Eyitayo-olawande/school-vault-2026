<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * @package : Ramom school management system
 * @version : 7.0
 * @developed by : RamomCoder
 * @support : ramomcoder@yahoo.com
 * @author url : http://codecanyon.net/user/RamomCoder
 * @filename : Onlineexam.php
 * @copyright : Reserved RamomCoder Team
 */

class Onlineexam extends Admin_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('onlineexam_model');
        $this->load->model('email_model');
        $this->load->model('sms_model');
        $this->load->model('subject_model');
        $this->data['headerelements'] = array(
            'css' => array(
                'vendor/summernote/summernote.css',
                'vendor/bootstrap-timepicker/css/bootstrap-timepicker.css',
            ),
            'js' => array(
                'vendor/summernote/summernote.js',
                'vendor/bootstrap-timepicker/bootstrap-timepicker.js',
                'js/online-exam.js',
            ),
        );
        if (!moduleIsEnabled('online_exam')) {
            access_denied();
        }
    }

    /* online exam controller */
    public function index()
    {
        // check access permission
        if (!get_permission('online_exam', 'is_view')) {
            access_denied();
        }
        $this->data['branch_id'] = $this->application_model->get_branch_id();
        $this->data['examList'] = $this->onlineexam_model->examList();
        $this->data['title'] = translate('online_exam');
        $this->data['sub_page'] = 'onlineexam/index';
        $this->data['main_menu'] = 'onlineexam';
        $this->load->view('layout/index', $this->data);
    }

    /* online exam table list controller */
    public function getExamListDT()
    {
        if ($_POST) {
            $postData = $this->input->post();
            $currencySymbol = $this->data['global_config']['currency_symbol'];
            echo $this->onlineexam_model->examListDT($postData, $currencySymbol);
        }
    }

    /* online exam edit controller */
    public function edit($id = '')
    {
        // check access permission
        if (!get_permission('online_exam', 'is_edit')) {
            access_denied();
        }
        if ($_POST) {
            $this->exam_validation();
            if ($this->form_validation->run() == true) {
                $post = $this->input->post();
                $branchID = $this->application_model->get_branch_id();
                $this->onlineexam_model->saveExam($post, $branchID);
                set_alert('success', translate('information_has_been_saved_successfully'));
                $url = base_url('onlineexam');
                $array = array('status' => 'success', 'url' => $url);
            } else {
                $error = $this->form_validation->error_array();
                $array = array('status' => 'fail', 'error' => $error);
            }
            echo json_encode($array);
            exit();
        }
        $this->data['onlineexam'] = $this->app_lib->getTable('online_exam', array('t.id' => $id), true);
        $this->data['title'] = translate('online_exam');
        $this->data['sub_page'] = 'onlineexam/edit';
        $this->data['main_menu'] = 'onlineexam';
        $this->load->view('layout/index', $this->data);
    }

    protected function exam_validation()
    {
        if (is_superadmin_loggedin()) {
            $this->form_validation->set_rules('branch_id', translate('branch'), 'required');
        }

        $this->form_validation->set_rules('title', translate('title'), 'trim|required');
        $this->form_validation->set_rules('class_id', translate('class'), 'trim|required');
        $this->form_validation->set_rules('section[]', translate('section'), 'trim|required');
        $this->form_validation->set_rules('subject[]', translate('subject'), 'trim|required');
        $this->form_validation->set_rules('start_date', translate('start_date'), 'trim|required');
        $this->form_validation->set_rules('end_date', translate('end_date'), 'trim|required');
        $this->form_validation->set_rules('start_time', translate('start_time'), 'trim|required');
        $this->form_validation->set_rules('end_time', translate('end_time'), 'trim|required');
        $this->form_validation->set_rules('duration', translate('duration'), 'trim|required|callback_validate_duration');
        $this->form_validation->set_rules('participation_limit', translate('limits_of_participation'), 'trim|required|numeric');
        $this->form_validation->set_rules('mark_type', translate('mark_type'), 'trim|required');
        $this->form_validation->set_rules('passing_mark', translate('passing_mark'), 'trim|required|numeric');
        $this->form_validation->set_rules('instruction', translate('instruction'), 'trim|required');
        $this->form_validation->set_rules('question_type', translate('question_type'), 'trim|required');
        $this->form_validation->set_rules('publish_result', translate('result_publish'), 'trim|required');
        $this->form_validation->set_rules('exam_type', translate('exam_type'), 'trim|required');
        $examType = $this->input->post('exam_type');
        if (!empty($examType) && $examType == 1) {
            $this->form_validation->set_rules('exam_fee', translate('exam_fee'), 'trim|required|numeric');
        }
    }

    public function validate_duration($value)
    {
        if (!empty($value)) {
            if ($value != "0:00") {
                if (!preg_match('/^(?(?=\d{2})(?:2[0-3]|[01][0-9])|[0-9]):[0-5][0-9]$/', $value)) {
                    $this->form_validation->set_message('validate_duration', 'The %s field must be H:mm');
                    return false;
                }
            } else {
                $this->form_validation->set_message('validate_duration', 'The %s field can not be 0:00.');
                return false;
            }
            return true;
        }
        return true;
    }

    /* online exam save in DB controller */
    public function exam_save()
    {
        if ($_POST) {
            $this->exam_validation();
            if ($this->form_validation->run() == true) {
                $post = $this->input->post();
                $branchID = $this->application_model->get_branch_id();
                //online exam save in DB
                $this->onlineexam_model->saveExam($post, $branchID);
                
                set_alert('success', translate('information_has_been_saved_successfully'));
                $array = array('status' => 'success');
            } else {
                $error = $this->form_validation->error_array();
                $array = array('status' => 'fail', 'error' => $error);
            }
            echo json_encode($array);
        }
    }

    /* online exam delete in DB controller */
    public function delete($id = '')
    {
        if (get_permission('online_exam', 'is_delete')) {
            if (!is_superadmin_loggedin()) {
                $this->db->where('branch_id', get_loggedin_branch_id());
            }
            $this->db->where('id', $id);
            $this->db->delete('online_exam');
            $done = $this->db->affected_rows();
            if ($done == true) {
                $this->db->where('onlineexam_id', $id);
                $this->db->delete('questions_manage');

                $this->db->where('online_exam_id', $id);
                $this->db->delete('online_exam_submitted');

                $this->db->where('online_exam_id', $id);
                $this->db->delete('online_exam_attempts');

                $this->db->where('online_exam_id', $id);
                $this->db->delete('online_exam_answer');

                $this->db->where('exam_id', $id);
                $this->db->delete('online_exam_payment');
            }
        }
    }

    public function question_list($id='')
    {
        if (!get_permission('online_exam', 'is_view')) {
            access_denied();
        }
        $exam = $this->onlineexam_model->getExamDetails($id, false);
        if (empty($exam)) {
            access_denied();
        }
        $this->data['exam'] = $exam;
        $this->data['title'] = translate('view') . " " . translate('question');
        $this->data['sub_page'] = 'onlineexam/question_list';
        $this->data['main_menu'] = 'onlineexam';
        $this->load->view('layout/index', $this->data);
    }

    public function exam_students($id = '')
    {
        if (!get_permission('online_exam', 'is_edit')) {
            access_denied();
        }
        $exam = $this->onlineexam_model->getExamDetails($id, false);
        if (empty($exam)) {
            access_denied();
        }
        $this->data['exam']     = $exam;
        $this->data['students'] = $this->onlineexam_model->getExamStudents($id);
        $this->data['title']    = 'Exam Students';
        $this->data['sub_page'] = 'onlineexam/exam_students';
        $this->data['main_menu'] = 'onlineexam';
        $this->load->view('layout/index', $this->data);
    }

    public function restore_student()
    {
        if (!get_permission('online_exam', 'is_edit')) {
            ajax_access_denied();
        }
        if ($_POST) {
            $studentID   = (int)$this->input->post('student_id');
            $examID      = (int)$this->input->post('exam_id');
            $extraMinutes = (int)$this->input->post('extra_minutes');
            if ($studentID && $examID) {
                $this->onlineexam_model->restoreStudentExam($studentID, $examID, $extraMinutes);
                echo json_encode(['status' => 'success', 'message' => 'Student restored successfully.']);
            } else {
                echo json_encode(['status' => 'fail', 'message' => 'Invalid data.']);
            }
        }
    }

    public function remove_question($id='')
    {
        if (get_permission('online_exam', 'is_edit')) {
            $this->db->select('questions_manage.id');
            $this->db->from('questions_manage');
            $this->db->join('online_exam', 'online_exam.id = questions_manage.onlineexam_id', 'inner');
            $this->db->where('questions_manage.id', $id);
            $this->db->where('online_exam.session_id', get_session_id());
            if (!is_superadmin_loggedin()) {
                $this->db->where('online_exam.branch_id', get_loggedin_branch_id());
            }
            $row = $this->db->get();
            if ($row->num_rows() > 0) {
                $this->db->where('id', $id);
                $this->db->delete('questions_manage');
            }
        }
    }

    /* Online exam question controller */
    public function question()
    {
        if (!get_permission('question_bank', 'is_view')) {
            access_denied();
        }
        $this->data['branch_id'] = $this->application_model->get_branch_id();
        $this->data['title'] = translate('question');
        $this->data['sub_page'] = 'onlineexam/question';
        $this->data['main_menu'] = 'onlineexam';
        $this->load->view('layout/index', $this->data);
    }

    public function getQuestionListDT()
    {
        if ($_POST) {
            $postData = $this->input->post();
            echo $this->onlineexam_model->questionListDT($postData);
        }
    }

    public function question_add()
    {
        if (!get_permission('question_bank', 'is_add')) {
            access_denied();
        }
        $this->data['branch_id'] = $this->application_model->get_branch_id();
        $this->data['title'] = translate('question');
        $this->data['sub_page'] = 'onlineexam/question_add';
        $this->data['main_menu'] = 'onlineexam';
        $this->load->view('layout/index', $this->data);
    }

    public function question_edit($id = '')
    {
        if (!get_permission('question_bank', 'is_edit')) {
            access_denied();
        }
        $this->data['branch_id'] = $this->application_model->get_branch_id();
        $this->data['questions'] = $this->app_lib->getTable('questions', array('t.id' => $id), true);
        $this->data['title'] = translate('question_edit');
        $this->data['sub_page'] = 'onlineexam/question_edit';
        $this->data['main_menu'] = 'onlineexam';
        $this->load->view('layout/index', $this->data);
    }

    public function question_edit_save($id = '')
    {
        if (!get_permission('question_bank', 'is_edit')) {
            ajax_access_denied();
        }
        if ($_POST) {
            $this->question_validation();
            if ($this->form_validation->run() == true) {
                $this->onlineexam_model->saveQuestions();
                set_alert('success', translate('information_has_been_saved_successfully'));
                $url = base_url('onlineexam/question');
                $array = array('status' => 'success', 'url' => $url);
            } else {
                $error = $this->form_validation->error_array();
                $array = array('status' => 'fail', 'error' => $error);
            }
            echo json_encode($array);
            exit();
        }
    }

    protected function question_validation()
    {
        $questionType = $this->input->post('question_type');
        $this->form_validation->set_rules('question_level', translate('question_level'), 'trim|required');
        $this->form_validation->set_rules('group_id', translate('question') . " " . translate('group'), 'trim|required');
        $this->form_validation->set_rules('mark', translate('mark'), 'trim|required|numeric');
        $this->form_validation->set_rules('question', translate('question'), 'trim|required');
        if ($questionType == 1) {
            $this->form_validation->set_rules('option1', translate('option') . " " . 1, 'trim|required');
            $this->form_validation->set_rules('option2', translate('option') . " " . 2, 'trim|required');
            $this->form_validation->set_rules('answer', translate('answer'), 'trim|required');
        }
        if ($questionType == 2) {
            $this->form_validation->set_rules('option1', translate('option') . " " . 1, 'trim|required');
            $this->form_validation->set_rules('option2', translate('option') . " " . 2, 'trim|required');
            $this->form_validation->set_rules('option3', translate('option') . " " . 3, 'trim|required');
            $this->form_validation->set_rules('option4', translate('option') . " " . 4, 'trim|required');
            $this->form_validation->set_rules('answer[]', translate('answer'), 'trim|required');
        }
        if ($questionType == 3 || $questionType == 4) {
            $this->form_validation->set_rules('answer', translate('answer'), 'trim|required');
        }
    }

    public function question_save()
    {
        if (!get_permission('question_bank', 'is_add')) {
            ajax_access_denied();
        }
        $this->question_validation();
        if ($this->form_validation->run() == true) {
            $this->onlineexam_model->saveQuestions();
            $message = translate('information_has_been_saved_successfully');
            $array = array('status' => 'success', 'message' => $message);
        } else {
            $error = $this->form_validation->error_array();
            $array = array('status' => 'fail', 'error' => $error);
        }
        echo json_encode($array);
    }

    public function getQuestion()
    {
        $id = $this->input->post('id');
        $this->data['questions'] = $this->onlineexam_model->get('questions', array('id' => $id), true);
        $this->load->view('onlineexam/question_view', $this->data);
    }

    public function question_delete($id = '')
    {
        if (get_permission('question_bank', 'is_delete')) {
            if (!is_superadmin_loggedin()) {
                $this->db->where('branch_id', get_loggedin_branch_id());
            }
            $this->db->where('id', $id);
            $this->db->delete('questions');
            if ($this->db->affected_rows() > 0) {
                $this->db->where('question_id', $id)->delete('questions_manage');
            }
        }
    }

    public function manage_question($examid = '')
    {
        if (!get_permission('add_questions', 'is_add')) {
            access_denied();
        }
        $this->data['questionType'] = $this->input->post('question_type');
        $this->data['questionLevel'] = $this->input->post('question_level');
        $this->data['classID'] = $this->input->post('class_id');
        $this->data['sectionID'] = $this->input->post('section_id');
        $this->data['subjectID'] = $this->input->post('subject_id');
        $exam = $this->onlineexam_model->get('online_exam', array('id' => $examid), true);
        $this->data['exam'] = $exam;
        $this->data['title'] = translate('manage') . " " . translate('question');
        $this->data['sub_page'] = 'onlineexam/manage_question';
        $this->data['main_menu'] = 'onlineexam';
        $this->load->view('layout/index', $this->data);
    }

    public function getQuestionDT()
    {
        if ($_POST) {
            $postData = $this->input->post();
            echo $this->onlineexam_model->questionList($postData);
        }
    }

    public function randomAssignQuestions()
    {
        if (!get_permission('add_questions', 'is_add')) {
            ajax_access_denied();
        }
        if (!$_POST) { echo json_encode(['status' => 'fail']); return; }

        $examID   = (int)$this->input->post('exam_id');
        $branchID = (int)$this->input->post('branch_id');
        $counts   = [
            1 => max(0, (int)$this->input->post('easy_count')),
            2 => max(0, (int)$this->input->post('medium_count')),
            3 => max(0, (int)$this->input->post('hard_count')),
        ];
        $filters = [
            'group_id'   => $this->input->post('questionGroup'),
            'class_id'   => $this->input->post('classID'),
            'section_id' => $this->input->post('sectionID'),
            'subject_id' => $this->input->post('subjectID'),
            'term'       => $this->input->post('term'),
            'ca_type'    => $this->input->post('ca_type'),
        ];

        // IDs already assigned to this exam — skip them
        $assignedRows = $this->db->select('question_id')->where('onlineexam_id', $examID)
                                 ->get('questions_manage')->result_array();
        $assignedIDs  = array_column($assignedRows, 'question_id');

        $insertData    = [];
        $totalAssigned = 0;

        foreach ($counts as $level => $count) {
            if ($count <= 0) continue;

            $this->db->select('questions.id, questions.mark')
                     ->from('questions')
                     ->where('questions.branch_id', $branchID)
                     ->where('questions.level', $level);

            foreach (['group_id', 'class_id', 'section_id', 'subject_id'] as $f) {
                if (!empty($filters[$f])) {
                    $this->db->where("questions.$f", $filters[$f]);
                }
            }
            if (!empty($filters['term']))    { $this->db->where('questions.term',    $filters['term']); }
            if (!empty($filters['ca_type'])) { $this->db->where('questions.ca_type', $filters['ca_type']); }

            $rows = $this->db->get('questions')->result_array();
            // Remove already-assigned questions
            $available = [];
            foreach ($rows as $r) {
                if (!in_array($r['id'], $assignedIDs)) {
                    $available[] = $r;
                }
            }
            if (empty($available)) continue;

            if ($count >= count($available)) {
                $sample = $available;
            } else {
                $keys   = (array)array_rand($available, $count);
                $sample = [];
                foreach ($keys as $k) { $sample[] = $available[$k]; }
            }

            foreach ($sample as $q) {
                $insertData[] = [
                    'question_id'   => $q['id'],
                    'onlineexam_id' => $examID,
                    'marks'         => ($q['mark'] > 0 ? $q['mark'] : 1),
                    'neg_marks'     => 0,
                ];
                $assignedIDs[] = $q['id']; // prevent cross-level duplicates
            }
            $totalAssigned += count($sample);
        }

        if (!empty($insertData)) {
            $this->db->insert_batch('questions_manage', $insertData);
        }

        echo json_encode([
            'status'   => 'success',
            'assigned' => $totalAssigned,
            'message'  => $totalAssigned . ' question(s) randomly assigned.',
        ]);
    }

    public function question_assign()
    {
        if (!get_permission('add_questions', 'is_add')) {
            ajax_access_denied();
        }
        if ($_POST) {
            $inputQuestions = $this->input->post('question');
            $examID = $this->input->post('exam_id');
            $negMark = $this->db->select('neg_mark')->where('id', $examID)->get('online_exam')->row()->neg_mark;
            foreach ($inputQuestions as $key => $value) {
                $this->form_validation->set_rules("question[$key][marks]", translate('marks'), 'trim|required|numeric');
                if ($negMark == 1) {
                    $this->form_validation->set_rules("question[$key][negative_marks]", translate('negative_marks'), 'trim|required|numeric');
                }
            }
            if ($this->form_validation->run() == true) {
                $questionsID = array();
                $cb_questionsID = array();
                $insertData = array();
                foreach ($inputQuestions as $key => $value) {
                    $questionsID[] = $value['id'];
                    if (isset($value['cb_id'])) {
                        $questionID = $value['cb_id'];
                        $cb_questionsID[] = $questionID;

                        $this->db->where(['question_id' => $questionID, 'onlineexam_id' => $examID]);
                        $query = $this->db->get('questions_manage');
                        $result = $query->num_rows();
                        if ($result > 0) {
                            $updateData = array(
                                'marks' => $value['marks'],
                                'neg_marks' => (empty($value['negative_marks']) ? 0 : $value['negative_marks']),
                            );
                            $this->db->where('id', $query->row()->id);
                            $this->db->update('questions_manage', $updateData);
                        } else {
                            $insertData[] = array(
                                'question_id' => $questionID,
                                'onlineexam_id' => $examID,
                                'marks' => $value['marks'],
                                'neg_marks' => (empty($value['negative_marks']) ? 0 : $value['negative_marks']),
                            );
                        }
                    }
                }
                if (!empty($insertData)) {
                    $this->db->insert_batch('questions_manage', $insertData);
                }
                $result = array_diff($questionsID, $cb_questionsID);
                if (!empty($result)) {
                    $this->db->where('onlineexam_id', $examID);
                    $this->db->where_in('question_id', $result);
                    $this->db->delete('questions_manage');
                }
                $array = array('status' => 'success', 'message' => translate('information_has_been_saved_successfully'));
            } else {
                $error = $this->form_validation->error_array();
                $array = array('status' => 'fail', 'error' => $error);
            }
            echo json_encode($array);
        }
    }

    // add new question group
    public function question_group()
    {
        if (!get_permission('question_group', 'is_view')) {
            access_denied();
        }
        if (isset($_POST['group'])) {
            if (!get_permission('question_group', 'is_add')) {
                access_denied();
            }
            if (is_superadmin_loggedin()) {
                $this->form_validation->set_rules('branch_id', translate('branch'), 'required');
            }
            $this->form_validation->set_rules('group_name', translate('group') . " " . translate('name'), 'trim|required|callback_unique_group');
            if ($this->form_validation->run() !== false) {
                $arrayData = array(
                    'name' => $this->input->post('group_name'),
                    'branch_id' => $this->application_model->get_branch_id(),
                );
                $this->db->insert('question_group', $arrayData);
                set_alert('success', translate('information_has_been_saved_successfully'));
                redirect(base_url('onlineexam/question_group'));
            }
        }
        $this->data['title'] = translate('question') . " " . translate('group');
        $this->data['sub_page'] = 'onlineexam/question_group';
        $this->data['main_menu'] = 'onlineexam';
        $this->load->view('layout/index', $this->data);
    }

    // update existing question group
    public function group_edit()
    {
        if (!get_permission('question_group', 'is_edit')) {
            ajax_access_denied();
        }
        if (is_superadmin_loggedin()) {
            $this->form_validation->set_rules('branch_id', translate('branch'), 'required');
        }
        $this->form_validation->set_rules('group_name', translate('group') . " " . translate('name'), 'trim|required|callback_unique_group');
        if ($this->form_validation->run() !== false) {
            $category_id = $this->input->post('group_id');
            $arrayData = array(
                'name' => $this->input->post('group_name'),
                'branch_id' => $this->application_model->get_branch_id(),
            );
            $this->db->where('id', $category_id);
            $this->db->update('question_group', $arrayData);
            set_alert('success', translate('information_has_been_updated_successfully'));
            $array = array('status' => 'success');
        } else {
            $error = $this->form_validation->error_array();
            $array = array('status' => 'fail', 'error' => $error);
        }
        echo json_encode($array);
    }

    // delete question group from database
    public function group_delete($id)
    {
        if (get_permission('question_group', 'is_delete')) {
            if (!is_superadmin_loggedin()) {
                $this->db->where('branch_id', get_loggedin_branch_id());
            }
            $this->db->where('id', $id);
            $this->db->delete('question_group');
            if ($this->db->affected_rows() > 0) {
                // Null out group_id on questions that referenced this group
                $this->db->where('group_id', $id)->update('questions', ['group_id' => null]);
            }
        }
    }

    // question group details send by ajax
    public function groupDetails()
    {
        if (get_permission('question_group', 'is_edit')) {
            $id = $this->input->post('id');
            $this->db->where('id', $id);
            if (!is_superadmin_loggedin()) {
                $this->db->where('branch_id', get_loggedin_branch_id());
            }
            $query = $this->db->get('question_group');
            $result = $query->row_array();
            echo json_encode($result);
        }
    }

    /* validate here, if the check unique group name */
    public function unique_group($name)
    {
        $branchID = $this->application_model->get_branch_id();
        $group_id = $this->input->post('group_id');
        if (!empty($group_id)) {
            $this->db->where_not_in('id', $group_id);
        }
        $this->db->where(array('name' => $name, 'branch_id' => $branchID));
        $uniform_row = $this->db->get('question_group')->num_rows();
        if ($uniform_row == 0) {
            return true;
        } else {
            $this->form_validation->set_message("unique_group", translate('already_taken'));
            return false;
        }
    }

    public function exam_status()
    {
        $id = $this->input->post('id');
        $status = $this->input->post('status');
        if ($status == 'true') {
            $arrayData['publish_status'] = 1;
        } else {
            $arrayData['publish_status'] = 0;
        }
        if (!is_superadmin_loggedin()) {
            $this->db->where('branch_id', get_loggedin_branch_id());
        }
        $this->db->where('id', $id);
        $this->db->update('online_exam', $arrayData);

        if ($status == 'true') {
            $onlineExam = $this->db->where('id', $id)->get('online_exam')->row();
            $percent = $onlineExam->mark_type == 1 ? "%" : "";
            $exam_fee = $onlineExam->exam_type == 1 ? $onlineExam->fee : "Free";
            $sectionArr = json_decode($onlineExam->section_id, true);
            //send online exam sms/email notification
            foreach ($sectionArr as $key => $value) {
                $stuList = $this->application_model->getStudentListByClassSection($onlineExam->class_id, $value, $onlineExam->branch_id);
                foreach ($stuList as $row) {
                    $row['exam_title'] = $onlineExam->title;
                    $row['start_time'] = _d($onlineExam->exam_start) . " - " . date("h:i A", strtotime($onlineExam->exam_start));
                    $row['end_time'] = _d($onlineExam->exam_end) . " - " . date("h:i A", strtotime($onlineExam->exam_end));
                    $row['time_duration'] = $onlineExam->duration;
                    $row['attempt'] = $onlineExam->limits_participation;
                    $row['passing_mark'] = $onlineExam->passing_mark . $percent;
                    $row['exam_fee'] = $exam_fee;
                    // send email/sms
                    $this->sms_model->sendOnlineExam($row);
                    $this->email_model->onlineExamPublish($row);
                }
            }
        }
        $return = array('msg' => translate('information_has_been_updated_successfully'), 'status' => true);
        echo json_encode($return);
    }

    public function make_result_publish($id = '')
    {
        if (!is_superadmin_loggedin()) {
            $this->db->where('branch_id', get_loggedin_branch_id());
        }
        $this->db->where('id', $id);
        $this->db->update('online_exam', ['publish_result' => 1]);

        $exam = $this->db->select('notify_parents')->where('id', $id)->get('online_exam')->row_array();
        if (!empty($exam['notify_parents'])) {
            $this->_notifyExamParents((int)$id);
        }
    }

    private function _notifyExamParents($examID)
    {
        $exam = $this->db->where('id', $examID)->get('online_exam')->row_array();
        if (empty($exam)) return;

        $smsApi = $this->application_model->smsServiceProvider($exam['branch_id']);
        if (empty($smsApi) || $smsApi === 'disabled') return;

        $subjectIDs  = json_decode($exam['subject_id'], true) ?: [];
        $subjectName = '';
        if (!empty($subjectIDs)) {
            $s = $this->db->select('name')->where('id', (int)$subjectIDs[0])->get('subject')->row_array();
            $subjectName = $s['name'] ?? '';
        }

        $sess = $this->db->select('session')->where('id', $exam['session_id'])->get('sessions')->row_array();
        $sessionLabel = $sess['session'] ?? '';

        $submitted = $this->db->select('online_exam_submitted.student_id, student.parent_id, student.first_name, student.last_name')
            ->from('online_exam_submitted')
            ->join('student', 'student.id = online_exam_submitted.student_id', 'left')
            ->where('online_exam_submitted.online_exam_id', $examID)
            ->get()->result_array();

        if (empty($submitted)) return;

        $logRows = [];
        foreach ($submitted as $row) {
            if (empty($row['parent_id'])) continue;
            $parent = $this->db->select('mobileno')->where('id', $row['parent_id'])->get('parent')->row_array();
            $mobile = trim($parent['mobileno'] ?? '');
            if (empty($mobile)) continue;

            $studentName = trim($row['first_name'] . ' ' . $row['last_name']);
            $res         = $this->onlineexam_model->examResult($examID, $row['student_id']);
            $obtained    = (float)$res['total_obtain_marks'] - (float)$res['total_neg_marks'];
            $score       = ($res['total_marks'] > 0)
                           ? number_format(($obtained / $res['total_marks']) * 100, 1)
                           : '0.0';

            $message = "{$studentName} scored {$score}% in {$subjectName} ({$sessionLabel}). Log in to view full result.";
            $this->sms_model->_send($smsApi, $mobile, $message);

            $logRows[] = [
                'exam_id'       => $examID,
                'student_id'    => $row['student_id'],
                'parent_mobile' => $mobile,
                'message'       => $message,
                'sent_at'       => date('Y-m-d H:i:s'),
            ];
        }

        if (!empty($logRows)) {
            $this->db->insert_batch('online_exam_notify_log', $logRows);
        }
    }

    // get subject list based on class
    public function getByClass()
    {
        $html = '';
        $classID = $this->input->post('classID');
        if (!empty($classID)) {
            $query = $this->onlineexam_model->getSubjectByClass($classID);
            if ($query->num_rows() > 0) {
                $subjects = $query->result_array();
                foreach ($subjects as $row) {
                    $html .= '<option value="' . $row['subject_id'] . '">' . $row['subjectname'] . '</option>';
                }
            } else {
                $html .= '<option value="">' . translate('no_information_available') . '</option>';
            }
        } else {
            $html .= '<option value="">' . translate('select') . '</option>';
        }
        echo $html;
    }

    public function getExamByClass()
    {
        $html = '';
        $classID = $this->input->post('class_id');
        if (!empty($classID)) {
            $this->db->where('class_id', $classID);
            $this->db->where('session_id', get_session_id());
            if (!is_superadmin_loggedin()) {
                $this->db->where('branch_id', get_loggedin_branch_id());
            }
            if (!is_superadmin_loggedin() && !is_admin_loggedin()) {
                $this->db->where('created_by', get_loggedin_user_id());
            }
            $this->db->where('publish_status', 1);
            $this->db->where('publish_result', 1);
            $query = $this->db->get('online_exam');
            if ($query->num_rows() > 0) {
                $subjects = $query->result();
                $html .= '<option value="">' . translate('select') . '</option>';
                foreach ($subjects as $row) {
                    $html .= '<option value="' . $row->id . '">' . $row->title . '</option>';
                }
            } else {
                $html .= '<option value="">' . translate('no_information_available') . '</option>';
            }
        } else {
            $html .= '<option value="">' . translate('select') . '</option>';
        }
        echo $html;
    }

    public function result()
    {
        // check access permission
        if (!get_permission('exam_result', 'is_view')) {
            access_denied();
        }

        $branchID = $this->application_model->get_branch_id();
        if (isset($_POST['search'])) {
            $classID = $this->input->post('class_id');
            $examID = $this->input->post('exam_id');
            $exam = $this->onlineexam_model->getExamDetails($examID);
            $this->data['exam'] = $exam;
            $position_order = 0;
            if ($exam->position_generated == 1) {
                $position_order = 1;
            }
            $this->data['result'] = $this->onlineexam_model->examReport($examID, $classID, $branchID, $position_order);
        }
        $this->data['branch_id'] = $branchID;
        $this->data['title'] = translate('online_exam') . " " . translate('result');
        $this->data['main_menu'] = 'onlineexam';
        $this->data['sub_page'] = 'onlineexam/result';
        $this->load->view('layout/index', $this->data);
    }

    public function position_generate()
    {
        // check access permission
        if (!get_permission('position_generate', 'is_view')) {
            access_denied();
        }

        $branchID = $this->application_model->get_branch_id();
        if (isset($_POST['search'])) {
            $classID = $this->input->post('class_id');
            $examID = $this->input->post('exam_id');
            $this->data['exam'] = $this->onlineexam_model->getExamDetails($examID);
            $this->data['result'] = $this->onlineexam_model->examReport($examID, $classID, $branchID);
        }
        $this->data['branch_id'] = $branchID;
        $this->data['title'] = translate('position') . " " . translate('generate');
        $this->data['main_menu'] = 'onlineexam';
        $this->data['sub_page'] = 'onlineexam/position_generate';
        $this->load->view('layout/index', $this->data);
    }

    public function save_position()
    {
        if ($_POST) {
            if (!get_permission('position_generate', 'is_add')) {
                ajax_access_denied();
            }
            $remark = $this->input->post('remark');
            foreach ($remark as $key => $value) {
                $this->form_validation->set_rules('remark[' . $key . '][position]', translate('position'), 'trim|numeric|required');
            }
            if ($this->form_validation->run() == true) {
                $examID = $this->input->post('exam_id');
                $examRow = $this->db->select('position_generated')->where('id', $examID)->get('online_exam')->row();
                if (!empty($examRow) && $examRow->position_generated == 1) {
                    echo json_encode(['status' => 'fail', 'error' => ['position' => 'Position has already been generated for this exam and cannot be overwritten.']]);
                    return;
                }
                foreach ($remark as $key => $value) {
                    $array = array(); 
                    if (!empty($value['position'])) {
                        $array['position'] = $value['position'];
                    }
                    if (!empty($value['remark'])) {
                        $array['remark'] = $value['remark'];
                    } else {
                        $array['remark'] = NULL;
                    }
                    if (!empty($array) && !empty($value['student_id'])) {
                        $this->db->where('online_exam_id', $examID);
                        $this->db->where('student_id', $value['student_id']);
                        $this->db->update('online_exam_submitted', $array);
                    }
                }

                $this->db->where('id', $examID);
                $this->db->update('online_exam', ['position_generated' => 1]);
                $message = translate('information_has_been_saved_successfully');
                $array = array('status' => 'success', 'message' => $message);
            } else {
                $error = $this->form_validation->error_array();
                $array = array('status' => 'fail', 'error' => $error);
            }
            echo json_encode($array);
        }
    }

    public function getStudent_result()
    {
        if (get_permission('exam_result', 'is_view')) {
            if ($_POST) {
                $examID = $this->input->post('examID');
                $studentID = $this->input->post('studentID');
                $exam = $this->onlineexam_model->getExamDetails($examID);
                $data['exam'] = $exam;
                $data['studentID'] = $studentID;
                echo $this->load->view('onlineexam/student_result', $data, true);
            }
        }
    }

    /* sample csv downloader */
    public function csv_Sampledownloader()
    {
        $this->load->helper('download');
        $data = file_get_contents('uploads/import_question_sample.csv');
        force_download("import_question_sample.csv", $data);
    }

    /* csv file to import question page */
    public function question_import()
    {
        // check access permission
        if (!get_permission('question_bank', 'is_add')) {
            access_denied();
        }
        $branchID = $this->application_model->get_branch_id();
        $this->data['title'] = translate('question') . " " . translate('import');
        $this->data['branch_id'] = $branchID;
        $this->data['sub_page'] = 'onlineexam/question_import';
        $this->data['main_menu'] = 'onlineexam';
        $this->data['headerelements'] = array(
            'css' => array(
                'vendor/dropify/css/dropify.min.css',
            ),
            'js' => array(
                'vendor/dropify/js/dropify.min.js',
            ),
        );
        $this->load->view('layout/index', $this->data);
    }

    /* csv file to import question stored in the database here */
    public function questionCsvImport()
    {
        if ($_POST) {
            if (!get_permission('question_bank', 'is_add')) {
                ajax_access_denied();
            }
            $branchID = $this->application_model->get_branch_id();
            // form validation rules
            if (is_superadmin_loggedin() == true) {
                $this->form_validation->set_rules('branch_id', 'Branch', 'trim|required');
            }
            $this->form_validation->set_rules('class_id', 'Class', 'trim|required');
            $this->form_validation->set_rules('section_id', 'Section', 'trim|required');
            $this->form_validation->set_rules('subject_id', 'Subject', 'trim|required');
            $this->form_validation->set_rules('userfile', 'CSV File', 'callback_csvfileHandleUpload[userfile]');

            if (isset($_FILES["userfile"]) && empty($_FILES['userfile']['name'])) {
                $this->form_validation->set_rules('userfile', 'CSV File', 'required');
            }
            if ($this->form_validation->run() == true) {
                $classID = $this->input->post('class_id');
                $sectionID = $this->input->post('section_id');
                $subjectID = $this->input->post('subject_id');
                $questionsExam = array();
                if (isset($_FILES["userfile"]) && !empty($_FILES['userfile']['name']) && $_FILES["userfile"]["size"] > 0) {
                    $fileName = $_FILES["userfile"]["tmp_name"];
                    $file = fopen($fileName, "r");
                    $num = true;
                    $count = 0;
                    while (($column = fgetcsv($file, 10000, ",")) !== false) {
                        if ($num) {
                            $num = false;
                            continue;
                        }
                        if (!empty($column['0']) && !empty($column['1']) && !empty($column['2']) && !empty($column['3']) && !empty($column['4'])) {
                            $count++;
                            $questionLevel = trim($column['2']);
                            $answer = trim($column['9']);

                            if ($questionLevel == 'easy')
                                $questionLevel = 1;
                            if ($questionLevel == 'medium')
                                $questionLevel = 2;
                            if ($questionLevel == 'hard')
                                $questionLevel = 3;

                            $questionType = trim($column['0']);
                            if ($questionType == 'single_choice')
                                $questionType = 1;
                            if ($questionType == 'multi_choice')
                                $questionType = 2;
                            if ($questionType == 'true_false') {
                                $questionType = 3;
                                if (strtolower($answer) === 'true') {
                                    $answer = 1;
                                } else {
                                    $answer = 2;
                                }
                            }
                            if ($questionType == 'descriptive')
                                $questionType = 4;

                            $answer = str_replace("option_", "", $answer);

                            $csvTerm = isset($column['10']) ? strtoupper(trim($column['10'])) : '';
                            $validTerms = ['1ST', '2ND', '3RD'];
                            $csvTerm = in_array($csvTerm, $validTerms) ? $csvTerm : null;

                            $csvCaType = isset($column['11']) ? strtoupper(trim($column['11'])) : '';
                            $validCaTypes = ['CA1', 'CA2', 'EXAM', 'GENERAL'];
                            $csvCaType = in_array($csvCaType, $validCaTypes) ? $csvCaType : 'GENERAL';

                            $questionsExam[] = array(
                                'class_id' => $classID,
                                'section_id' => $sectionID,
                                'subject_id' => $subjectID,
                                'branch_id' => $branchID,
                                'type' => $questionType,
                                'level' => $questionLevel,
                                'group_id' => trim($column['1']),
                                'question' => trim($column['3']),
                                'mark' => trim($column['4']),
                                'opt_1' => trim($column['5']),
                                'opt_2' => trim($column['6']),
                                'opt_3' => trim($column['7']),
                                'opt_4' => trim($column['8']),
                                'answer' => $answer,
                                'term' => $csvTerm,
                                'ca_type' => $csvCaType,
                            );
                        }
                    }
                    if (!empty($questionsExam)) {
                        $this->db->insert_batch('questions', $questionsExam);
                    }
                    if ($count == 0) {
                        $url = base_url('onlineexam/question_import');
                        set_alert('error', "No questions found.");
                    } else {
                        $url = base_url('onlineexam/question');
                        set_alert('success', $count . ' Questions added successfully');
                    }
                } else {
                    $url = base_url('onlineexam/question_import');
                    set_alert('error', 'Question import failed.');
                }
                $array = array('status' => 'success', 'url' => $url);
            } else {
                $error = $this->form_validation->error_array();
                $array = array('status' => 'fail', 'error' => $error);
            }
            echo json_encode($array);
        }
    }

    public function csvfileHandleUpload($str, $fields)
    {
        $allowedExts = array_map('trim', array_map('strtolower', explode(',', 'csv')));
        if (isset($_FILES["$fields"]) && !empty($_FILES["$fields"]['name'])) {
            $file_size = $_FILES["$fields"]["size"];
            $file_name = $_FILES["$fields"]["name"];
            $extension = pathinfo($file_name, PATHINFO_EXTENSION);
            if ($files = filesize($_FILES["$fields"]['tmp_name'])) {
                if (!in_array(strtolower($extension), $allowedExts)) {
                    $this->form_validation->set_message('fileHandleUpload', translate('this_file_type_is_not_allowed'));
                    return false;
                }
            } else {
                $this->form_validation->set_message('fileHandleUpload', translate('error_reading_the_file'));
                return false;
            }
            return true;
        }
    }

    public function questionDocxImport()
    {
        if (!$_POST) return;
        if (!get_permission('question_bank', 'is_add')) { ajax_access_denied(); }

        $branchID  = $this->application_model->get_branch_id();
        $classID   = (int)$this->input->post('class_id');
        $sectionID = (int)$this->input->post('section_id');
        $subjectID = (int)$this->input->post('subject_id');

        if (!$classID || !$sectionID || !$subjectID) {
            echo json_encode(['status' => 'fail', 'error' => ['docxfile' => 'Class, Section and Subject are required.']]);
            return;
        }

        if (empty($_FILES['docxfile']['name']) || $_FILES['docxfile']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['status' => 'fail', 'error' => ['docxfile' => 'Please upload a valid .docx file.']]);
            return;
        }

        $ext = strtolower(pathinfo($_FILES['docxfile']['name'], PATHINFO_EXTENSION));
        if ($ext !== 'docx') {
            echo json_encode(['status' => 'fail', 'error' => ['docxfile' => 'Only .docx files are accepted.']]);
            return;
        }

        $zip = new ZipArchive();
        if ($zip->open($_FILES['docxfile']['tmp_name']) !== true) {
            echo json_encode(['status' => 'fail', 'error' => ['docxfile' => 'Could not open file. Make sure it is a valid .docx file.']]);
            return;
        }
        $xmlContent = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xmlContent === false) {
            echo json_encode(['status' => 'fail', 'error' => ['docxfile' => 'Could not read document content.']]);
            return;
        }

        $paragraphs = $this->_parseDocxParagraphs($xmlContent);

        // Split into question blocks by '---'
        $blocks = [];
        $current = [];
        foreach ($paragraphs as $line) {
            $trimmed = trim($line);
            if ($trimmed === '---' || $trimmed === "\xe2\x80\x94" /* em-dash */ || $trimmed === '') {
                if (!empty($current)) { $blocks[] = $current; $current = []; }
            } else {
                $current[] = $trimmed;
            }
        }
        if (!empty($current)) $blocks[] = $current;

        if (empty($blocks)) {
            echo json_encode(['status' => 'fail', 'error' => ['docxfile' => 'No questions found. Check the document format.']]);
            return;
        }

        $optMap    = ['A' => 1, 'B' => 2, 'C' => 3, 'D' => 4];
        $optMapStr = ['A' => '1', 'B' => '2', 'C' => '3', 'D' => '4'];
        $typeMap   = [
            'single_choice' => 1, 'singlechoice' => 1,
            'multi_choice'  => 2, 'multichoice'  => 2,
            'true_false'    => 3, 'truefalse'    => 3, 'tf' => 3,
            'descriptive'   => 4,
        ];
        $levelMap  = ['easy' => 1, 'medium' => 2, 'hard' => 3];
        $validTerms  = ['1ST', '2ND', '3RD'];
        $validCaTypes = ['CA1', 'CA2', 'EXAM', 'GENERAL'];

        $insertData = [];
        $errors     = [];

        foreach ($blocks as $idx => $block) {
            $qNum = $idx + 1;
            $f    = [];
            foreach ($block as $line) {
                if (preg_match('/^(TYPE|LEVEL|MARKS|TERM|CA_TYPE|GROUP|Q|A|B|C|D|ANS)\s*:\s*(.+)$/i', $line, $m)) {
                    $f[strtoupper(trim($m[1]))] = trim($m[2]);
                }
            }

            if (empty($f['TYPE'])) { $errors[] = "Q{$qNum}: missing TYPE"; continue; }
            if (empty($f['Q']))    { $errors[] = "Q{$qNum}: missing Q";    continue; }
            if (empty($f['ANS']))  { $errors[] = "Q{$qNum}: missing ANS";  continue; }

            $typeKeyFull = str_replace([' ', '-'], ['', ''], strtolower($f['TYPE']));
            if (!isset($typeMap[$typeKeyFull])) { $errors[] = "Q{$qNum}: unknown TYPE '{$f['TYPE']}'"; continue; }
            $type = $typeMap[$typeKeyFull];

            $level = $levelMap[strtolower(trim($f['LEVEL'] ?? 'easy'))] ?? 1;

            $ansRaw = strtoupper(trim($f['ANS']));
            $answer = '';
            if ($type === 1) {
                if (!isset($optMap[$ansRaw])) { $errors[] = "Q{$qNum}: ANS must be A/B/C/D"; continue; }
                $answer = $optMap[$ansRaw];
            } elseif ($type === 2) {
                $letters = array_map('trim', explode(',', $ansRaw));
                $nums    = [];
                $valid   = true;
                foreach ($letters as $l) {
                    if (!isset($optMapStr[$l])) { $errors[] = "Q{$qNum}: invalid ANS letter '$l'"; $valid = false; break; }
                    $nums[] = $optMapStr[$l];
                }
                if (!$valid) continue;
                $answer = json_encode($nums);
            } elseif ($type === 3) {
                if ($ansRaw === 'TRUE')       $answer = 1;
                elseif ($ansRaw === 'FALSE')  $answer = 2;
                else { $errors[] = "Q{$qNum}: ANS must be TRUE or FALSE"; continue; }
            } elseif ($type === 4) {
                $answer = $f['ANS'];
            }

            $term   = null;
            if (!empty($f['TERM']) && in_array(strtoupper(trim($f['TERM'])), $validTerms)) {
                $term = strtoupper(trim($f['TERM']));
            }
            $caType = 'GENERAL';
            if (!empty($f['CA_TYPE']) && in_array(strtoupper(trim($f['CA_TYPE'])), $validCaTypes)) {
                $caType = strtoupper(trim($f['CA_TYPE']));
            }

            $insertData[] = [
                'class_id'   => $classID,
                'section_id' => $sectionID,
                'subject_id' => $subjectID,
                'branch_id'  => $branchID,
                'type'       => $type,
                'level'      => $level,
                'group_id'   => !empty($f['GROUP']) ? (int)$f['GROUP'] : 0,
                'question'   => $f['Q'],
                'mark'       => !empty($f['MARKS']) ? (float)$f['MARKS'] : 1,
                'opt_1'      => $f['A'] ?? '',
                'opt_2'      => $f['B'] ?? '',
                'opt_3'      => $f['C'] ?? '',
                'opt_4'      => $f['D'] ?? '',
                'answer'     => $answer,
                'term'       => $term,
                'ca_type'    => $caType,
            ];
        }

        $imported = count($insertData);
        if (!empty($insertData)) {
            $this->db->insert_batch('questions', $insertData);
        }

        if ($imported === 0) {
            $msg = 'No questions imported.' . (!empty($errors) ? ' ' . implode('; ', $errors) : '');
            echo json_encode(['status' => 'fail', 'error' => ['docxfile' => $msg]]);
            return;
        }

        $msg = $imported . ' question(s) imported from DOCX.';
        if (!empty($errors)) $msg .= ' Skipped ' . count($errors) . ': ' . implode('; ', $errors);
        set_alert('success', $msg);
        echo json_encode(['status' => 'success', 'url' => base_url('onlineexam/question')]);
    }

    public function liveMonitor($examID = '')
    {
        if (!get_permission('online_exam', 'is_view')) { access_denied(); }
        $exam = $this->onlineexam_model->getExamDetails((int)$examID, false);
        if (empty($exam)) { access_denied(); }
        $this->data['exam']      = $exam;
        $this->data['examID']    = (int)$examID;
        $this->data['title']     = 'Live Exam Monitor';
        $this->data['sub_page']  = 'onlineexam/live_monitor';
        $this->data['main_menu'] = 'onlineexam';
        $this->load->view('layout/index', $this->data);
    }

    public function liveMonitorData($examID = '')
    {
        if (!get_permission('online_exam', 'is_view')) { echo json_encode([]); return; }
        $examID = (int)$examID;
        $exam = $this->db->where('id', $examID)->get('online_exam')->row_array();
        if (empty($exam)) { echo json_encode([]); return; }

        // Total questions in this exam
        $totalQ = $this->db->where('onlineexam_id', $examID)->count_all_results('questions_manage');

        // Students who have been enrolled / started (have answers or submitted)
        $submitted = $this->db->select('student_id, created_at')->where('online_exam_id', $examID)->get('online_exam_submitted')->result_array();
        $submittedMap = [];
        foreach ($submitted as $s) { $submittedMap[$s['student_id']] = $s['created_at']; }

        // Answer counts per student
        $answers = $this->db->select('student_id, COUNT(*) as cnt')
            ->where('online_exam_id', $examID)
            ->where('answer !=', '')
            ->group_by('student_id')
            ->get('online_exam_answer')->result_array();
        $answerMap = [];
        foreach ($answers as $a) { $answerMap[$a['student_id']] = (int)$a['cnt']; }

        // All relevant student IDs
        $studentIDs = array_unique(array_merge(array_keys($submittedMap), array_keys($answerMap)));
        if (empty($studentIDs)) { echo json_encode(['total_questions' => $totalQ, 'exam_end' => $exam['exam_end'], 'students' => []]); return; }

        $students = $this->db->select('id, first_name, last_name')->where_in('id', $studentIDs)->get('student')->result_array();

        // Calculate exam end time from start + duration (HH:MM:SS)
        $examEnd = $exam['exam_end'];

        $rows = [];
        foreach ($students as $st) {
            $sid = $st['id'];
            $rows[] = [
                'student_id'      => $sid,
                'name'            => trim($st['first_name'] . ' ' . $st['last_name']),
                'answered'        => $answerMap[$sid] ?? 0,
                'total_questions' => $totalQ,
                'submitted'       => isset($submittedMap[$sid]),
                'submitted_at'    => $submittedMap[$sid] ?? null,
            ];
        }
        // Sort: active first, then submitted
        usort($rows, function($a, $b) { return $a['submitted'] <=> $b['submitted']; });

        echo json_encode(['total_questions' => $totalQ, 'exam_end' => $examEnd, 'students' => $rows]);
    }

    public function saveQuestionLog()
    {
        if (!$_POST) return;
        $examID    = (int)$this->input->post('exam_id');
        $timeJson  = $this->input->post('q_time_spent');
        $changesJson = $this->input->post('q_ans_changes');
        if (!$examID) return;

        $studentID = get_loggedin_user_id();
        $timeSpent  = json_decode($timeJson,  true) ?: [];
        $ansChanges = json_decode($changesJson, true) ?: [];

        $allQids = array_unique(array_merge(array_keys($timeSpent), array_keys($ansChanges)));
        if (empty($allQids)) return;

        foreach ($allQids as $qid) {
            $qid = (int)$qid;
            if (!$qid) continue;
            $existing = $this->db->where(['exam_id' => $examID, 'student_id' => $studentID, 'question_id' => $qid])->get('online_exam_question_log')->row_array();
            if ($existing) {
                $this->db->where(['exam_id' => $examID, 'student_id' => $studentID, 'question_id' => $qid])
                         ->update('online_exam_question_log', [
                             'time_spent'     => ($existing['time_spent'] + (int)($timeSpent[$qid] ?? 0)),
                             'answer_changes' => ($existing['answer_changes'] + (int)($ansChanges[$qid] ?? 0)),
                         ]);
            } else {
                $this->db->insert('online_exam_question_log', [
                    'exam_id'        => $examID,
                    'student_id'     => $studentID,
                    'question_id'    => $qid,
                    'time_spent'     => (int)($timeSpent[$qid] ?? 0),
                    'answer_changes' => (int)($ansChanges[$qid] ?? 0),
                ]);
            }
        }
    }

    private function _parseDocxParagraphs($xmlContent)
    {
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        if (!$dom->loadXML($xmlContent)) { return []; }
        $ns = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
        $paragraphs = [];
        foreach ($dom->getElementsByTagNameNS($ns, 'p') as $para) {
            $text = '';
            foreach ($para->getElementsByTagNameNS($ns, 't') as $t) {
                $text .= $t->nodeValue;
            }
            $paragraphs[] = $text;
        }
        return $paragraphs;
    }
}
