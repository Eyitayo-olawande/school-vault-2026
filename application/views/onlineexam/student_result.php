<?php 
$result = $this->onlineexam_model->examResult($exam->id, $studentID);
$correct_ans = $result['correct_ans'];
$total_question = $result['total_question'];
$total_marks = $result['total_marks'];
$total_obtain_marks = $result['total_obtain_marks'];
$wrong_ans = $result['wrong_ans'];
$total_answered = $result['total_answered'];
$total_neg_marks = ($exam->neg_mark == 0 ? 0 : $result['total_neg_marks']);
?>
<div class="table-responsive">
	<table class="table table-striped table-bordered table-condensed mb-md mt-md">
		<tbody>
			<tr>
				<th><?=translate('student') . " " . translate('name')?></th>
				<td><?php echo $this->db->select('CONCAT_WS(" ",first_name, last_name) as name')->where('id', $studentID)->get('student')->row()->name; ?></td>
				<th><?=translate('exam') . " " . translate('title')?></th>
				<td><?php echo $exam->title ?></td>
			</tr>
			<tr>
				<th><?=translate('class')?></th>
				<td><?php echo $exam->class_name . " (" . $this->onlineexam_model->getSectionDetails($exam->section_id) . ")"; ?></td>
				<th><?=translate('subject')?></th>
				<td><?php echo $this->onlineexam_model->getSubjectDetails($exam->subject_id); ?></td>
			</tr>
			<tr>
				<th><?=translate('start_time')?></th>
				<td><?php echo _d($exam->exam_start) . " <p class='text-muted'>" . date("h:i A", strtotime($exam->exam_start)) . "</p>" ?></td>
				<th><?=translate('end_time')?></th>
				<td><?php echo _d($exam->exam_end) . " <p class='text-muted'>" . date("h:i A", strtotime($exam->exam_end)) . "</p>" ?></td>
			</tr>
			<tr>
				<th><?=translate('mark') . " " . translate('type')?></th>
				<td><?php echo $exam->mark_type == 1 ? translate('percent') : translate('fixed'); ?></span></td>
				<th><?=translate('passing_mark')?></th>
				<td><?php echo $exam->passing_mark . ($exam->mark_type == 1 ? "%" : ""); ?></td>
			</tr>
			<tr>
				<th><?=translate('total') . " " . translate('question')?></th>
				<td><?php echo $total_question ?></td>
				<th><?=translate('total_answered')?></th>
				<td><?php echo $total_answered ?></td>
			</tr>
			<tr>
				<th><?=translate('total') . " " . translate('mark')?></th>
				<td><?php echo $total_marks ?></td>
				<th><?=translate('negative_mark')?></th>
				<td><?php echo $total_neg_marks ?></td>
			</tr>
			<tr>
				<th><?=translate('total_obtain_mark')?></th>
				<td><?php echo ($total_obtain_marks - $total_neg_marks) ?></td>
				<th><?=translate('score')?></th>
				<td><?php echo ($total_marks === 0) ? '0.00' : number_format(((($total_obtain_marks - $total_neg_marks) * 100) / $total_marks), 2, '.', ''); ?> (%)</td>
			</tr>
			<tr>
				<th><?=translate('correct_answer')?></th>
				<td><?php echo $correct_ans ?></td>
				<th><?=translate('incorrect_answer')?></th>
				<td><?php echo $wrong_ans ?></td>
			</tr>
			<tr>
				<th><?=translate('result')?></th>
				<td colspan="3"><?php 
					$status = '';
					if ($exam->mark_type == 1) {
						$obtain = $total_obtain_marks == 0 ? 0 : ((($total_obtain_marks - $total_neg_marks) * 100) / $total_marks);
						if ($obtain >= $exam->passing_mark) {
							echo "<span class='label label-success-custom'>Passed</span>";
						} else {
							echo "<span class='label label-danger-custom'>Failed</span>";
						}
					} else {
						$obtain = ($total_obtain_marks - $total_neg_marks);
						if ($obtain >= $exam->passing_mark) {
							echo "<span class='label label-success-custom'>Passed</span>";
						} else {
							echo "<span class='label label-danger-custom'>Failed</span>";
						}
					}
				 ?></td>
			</tr>
		</tbody>
	</table>
</div>

<?php
$qRows2 = $this->getExamResults($exam->id, $studentID);
$qLogs = [];
$logRows = $this->db->where(['exam_id' => $exam->id, 'student_id' => $studentID])->get('online_exam_question_log')->result_array();
foreach ($logRows as $lr) { $qLogs[$lr['question_id']] = $lr; }

$classAnswers = $this->db->select('oa.question_id, q.answer, q.type, oa.answer as sb_ans')
    ->from('online_exam_answer oa')
    ->join('questions q', 'q.id = oa.question_id', 'left')
    ->where('oa.online_exam_id', $exam->id)
    ->get()->result_array();
$classCorrect = []; $classTotal = [];
foreach ($classAnswers as $ca) {
    $qid = $ca['question_id'];
    $classTotal[$qid] = ($classTotal[$qid] ?? 0) + 1;
    $isCorrect = false;
    if ($ca['type'] == 1 || $ca['type'] == 3) {
        $isCorrect = ($ca['sb_ans'] == $ca['answer']);
    } elseif ($ca['type'] == 2) {
        $a = json_decode($ca['answer'], true); $b = json_decode($ca['sb_ans'], true);
        $isCorrect = (is_array($a) && is_array($b) && !array_diff($a,$b) && !array_diff($b,$a));
    } elseif ($ca['type'] == 4) {
        $isCorrect = (strtolower(str_replace(' ','_',$ca['sb_ans'])) == strtolower(str_replace(' ','_',$ca['answer'])));
    }
    if ($isCorrect) $classCorrect[$qid] = ($classCorrect[$qid] ?? 0) + 1;
}
?>
<?php if (!empty($qRows2)): ?>
<div style="padding-top:8px">
    <h5 class="mb-sm" style="border-bottom:1px solid #eee;padding-bottom:6px"><i class="fas fa-chart-bar"></i> Per-Question Analytics</h5>
    <div class="table-responsive">
        <table class="table table-condensed table-bordered" style="font-size:12px">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Question</th>
                    <th>Your Answer</th>
                    <th>Time Spent</th>
                    <th>Answer Changes</th>
                    <th>Class Difficulty</th>
                </tr>
            </thead>
            <tbody>
            <?php $qNum = 0; foreach ($qRows2 as $qr): $qNum++;
                $qid = $qr->question_id;
                $log = $qLogs[$qid] ?? null;
                $secs = $log ? (int)$log['time_spent'] : null;
                $timeStr = ($secs !== null) ? sprintf('%d:%02d', intdiv($secs, 60), $secs % 60) : '&#8212;';
                $changes = $log ? (int)$log['answer_changes'] : '&#8212;';
                $total = $classTotal[$qid] ?? 0;
                $correct = $classCorrect[$qid] ?? 0;
                $diffPct = $total > 0 ? round($correct / $total * 100) : null;
                if ($diffPct === null)      { $diffLabel = '&#8212;'; $diffClass = ''; }
                elseif ($diffPct >= 70)    { $diffLabel = $diffPct.'% correct'; $diffClass = 'label-success'; }
                elseif ($diffPct >= 40)    { $diffLabel = $diffPct.'% correct'; $diffClass = 'label-warning'; }
                else                       { $diffLabel = $diffPct.'% correct'; $diffClass = 'label-danger'; }
            ?>
                <tr>
                    <td><?=$qNum?></td>
                    <td style="max-width:240px;white-space:normal"><?=htmlspecialchars(mb_strimwidth($qr->question, 0, 120, '&#8230;'))?></td>
                    <td><?= !empty($qr->sb_ans) ? htmlspecialchars($qr->sb_ans) : '<span class="text-muted">&#8212;</span>' ?></td>
                    <td><?=$timeStr?></td>
                    <td><?=$changes?></td>
                    <td><?= $diffPct !== null ? "<span class='label {$diffClass}'>{$diffLabel}</span>" : '&#8212;' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="text-muted" style="font-size:11px">Difficulty: % of students in this exam who answered correctly. Time shown as m:ss.</p>
</div>
<?php endif; ?>