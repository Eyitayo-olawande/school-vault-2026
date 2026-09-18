<?php if (!empty($questions)) {
	$totalQuestions = count($questions);
?>
<style>
.cbt-wrapper{display:flex;flex-direction:column;height:calc(100vh - 120px);min-height:420px}
.cbt-topbar{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;padding:8px 16px;background:#f7f7f7;border-bottom:1px solid #ddd;flex-shrink:0}
.cbt-timer{font-size:17px;font-weight:700;letter-spacing:.5px;color:#333}
.cbt-timer-danger .remain_duration{color:#d9534f!important}
.cbt-body{display:flex;flex:1;min-height:0;overflow:hidden}
.cbt-sidebar{width:200px;flex-shrink:0;overflow-y:auto;border-right:1px solid #ddd;padding:10px 8px}
.cbt-grid{display:flex;flex-wrap:wrap;gap:4px;margin-bottom:12px}
.cbt-chip{width:34px;height:34px;line-height:34px;text-align:center;border-radius:4px;cursor:pointer;font-size:12px;font-weight:600;background:#e0e0e0;color:#555;display:inline-block;transition:background .12s}
.cbt-chip.answered{background:#5cb85c;color:#fff}
.cbt-chip.flagged{background:#f0ad4e;color:#fff}
.cbt-chip.current{outline:2px solid #337ab7;outline-offset:1px}
.cbt-legend{font-size:11px;color:#777}
.cbt-legend-row{display:flex;align-items:center;gap:6px;margin-bottom:3px}
.cbt-dot{width:12px;height:12px;border-radius:2px;flex-shrink:0}
.cbt-main{flex:1;overflow-y:auto;padding:16px 20px;display:flex;flex-direction:column}
.cbt-qpane{display:none}
.cbt-qpane.active{display:block}
.cbt-qheader{font-size:12px;color:#888;margin-bottom:6px}
.cbt-qtext{font-size:15px;margin-bottom:14px;line-height:1.5}
.cbt-nav{display:flex;align-items:center;gap:8px;padding-top:14px;border-top:1px solid #eee;margin-top:16px;flex-wrap:wrap}
.cbt-nav-right{margin-left:auto;display:flex;gap:8px}
.cbt-confirm{display:none;position:absolute;inset:0;background:rgba(0,0,0,.6);z-index:500;align-items:center;justify-content:center}
.cbt-confirm.show{display:flex}
.cbt-confirm-box{background:#fff;border-radius:6px;padding:28px 30px;max-width:380px;width:90%;text-align:center;box-shadow:0 8px 32px rgba(0,0,0,.3)}
.cbt-confirm-box h4{margin-top:0;font-size:18px}
.cbt-stat{display:inline-block;margin:4px 10px;font-size:26px;font-weight:700;line-height:1.1}
.cbt-stat small{display:block;font-size:11px;font-weight:400;color:#888;text-transform:uppercase;letter-spacing:.5px}
.cbt-drawer-btn{display:none}
@media(max-width:767px){
  .cbt-wrapper{height:calc(100vh - 100px)}
  .cbt-body{flex-direction:column}
  .cbt-sidebar{width:100%;flex-shrink:0;max-height:0;overflow:hidden;transition:max-height .3s;border-right:none;border-bottom:1px solid #ddd;padding:0 8px}
  .cbt-sidebar.open{max-height:200px;padding:10px 8px;overflow-y:auto}
  .cbt-drawer-btn{display:block;width:100%;padding:6px 12px;background:#f7f7f7;border:none;border-bottom:1px solid #ddd;font-size:12px;cursor:pointer;flex-shrink:0;text-align:center}
  .cbt-main{padding:10px 12px}
}
</style>

<div class="cbt-wrapper" style="position:relative">
	<!-- Top bar -->
	<div class="cbt-topbar">
		<div class="cbt-timer"><i class="fas fa-clock"></i>&nbsp;<span class="remain_duration"><?=$exam->duration?></span></div>
		<small id="autosave_indicator" class="text-muted"></small>
		<div style="display:flex;align-items:center;gap:10px">
			<span id="cbt-qnum" style="font-size:12px;color:#666">Question 1 of <?=$totalQuestions?></span>
			<button type="button" onclick="showSubmitSummary()" class="btn btn-danger btn-sm">
				<i class="fas fa-paper-plane"></i> Submit Exam
			</button>
		</div>
	</div>

	<!-- Mobile: question grid drawer toggle -->
	<button class="cbt-drawer-btn" onclick="$('.cbt-sidebar').toggleClass('open')">
		<i class="fas fa-th"></i> Questions &amp; Navigation
	</button>

	<div class="cbt-body">
		<!-- Left: question number grid -->
		<div class="cbt-sidebar">
			<div class="cbt-grid">
				<?php foreach ($questions as $k => $q) { ?>
				<a class="cbt-chip <?= !empty($q->sb_ans) ? 'answered' : '' ?>" id="chip<?=$k+1?>" onclick="navTo(<?=$k+1?>)"><?=$k+1?></a>
				<?php } ?>
			</div>
			<div class="cbt-legend">
				<div class="cbt-legend-row"><span class="cbt-dot" style="background:#5cb85c"></span>Answered</div>
				<div class="cbt-legend-row"><span class="cbt-dot" style="background:#f0ad4e"></span>Flagged</div>
				<div class="cbt-legend-row"><span class="cbt-dot" style="background:#e0e0e0"></span>Unanswered</div>
			</div>
		</div>

		<!-- Right: question content + nav -->
		<div class="cbt-main">
			<?php echo form_open('userrole/onlineexam_submit_answer', array('id' => 'answerForm')); ?>
			<input type="hidden" name="online_exam_id" value="<?=$exam->id?>">

			<?php
			$quesOption = array('opt_1'=>1,'opt_2'=>2,'opt_3'=>3,'opt_4'=>4);
			foreach ($questions as $k => $question) { ?>
			<div class="cbt-qpane <?=$k==0?'active':''?>" id="qpane<?=$k+1?>"
			     data-qid="<?=$question->question_id?>" data-step="<?=$k+1?>">
				<div class="cbt-qheader">Question <?=$k+1?> of <?=$totalQuestions?></div>
				<div class="cbt-qtext"><?=$question->question?></div>

				<?php if ($question->type == 1) {
					foreach ($quesOption as $opt_key => $opt_val) {
						if (!empty($question->{$opt_key})) { ?>
				<div class="radio-custom radio-success mt-md">
					<input type="radio" value="<?=$opt_val?>"
					       name="answer[<?=$question->question_id?>][<?=$question->type?>]"
					       id="opt<?=$k.$opt_val?>"
					       <?= ($question->sb_ans == $opt_val) ? 'checked' : '' ?>>
					<label for="opt<?=$k.$opt_val?>"><?=$question->{$opt_key}?></label>
				</div>
				<?php } } } elseif ($question->type == 2) {
					$cbChecked = !empty($question->sb_ans) ? json_decode($question->sb_ans, true) : array();
					foreach ($quesOption as $opt_key => $opt_val) {
						if (!empty($question->{$opt_key})) { ?>
				<div class="checkbox-replace mt-md">
					<label class="i-checks">
						<input type="checkbox"
						       name="answer[<?=$question->question_id?>][<?=$question->type?>][]"
						       value="<?=$opt_val?>"
						       <?= in_array((string)$opt_val, array_map('strval', (array)$cbChecked)) ? 'checked' : '' ?>>
						<i></i><?=$question->{$opt_key}?>
					</label>
				</div>
				<?php } } } elseif ($question->type == 3) { ?>
				<div class="radio-custom radio-success mt-md">
					<input type="radio" value="1"
					       name="answer[<?=$question->question_id?>][<?=$question->type?>]"
					       id="tf1_<?=$k?>" <?= ($question->sb_ans === '1') ? 'checked' : '' ?>>
					<label for="tf1_<?=$k?>">TRUE</label>
				</div>
				<div class="radio-custom radio-success mt-md">
					<input type="radio" value="0"
					       name="answer[<?=$question->question_id?>][<?=$question->type?>]"
					       id="tf0_<?=$k?>" <?= ($question->sb_ans === '0') ? 'checked' : '' ?>>
					<label for="tf0_<?=$k?>">FALSE</label>
				</div>
				<?php } elseif ($question->type == 4) { ?>
				<div class="form-group">
					<label class="control-label">Answer</label>
					<input type="text" class="form-control"
					       name="answer[<?=$question->question_id?>][<?=$question->type?>]"
					       value="<?= htmlspecialchars($question->sb_ans ?? '') ?>">
				</div>
				<?php } ?>

				<?php if ($exam->marks_display == 1 || $exam->neg_mark == 1) { ?>
				<div class="ques-marks mt-lg">
					<div class="row">
						<?php if ($exam->marks_display == 1) { ?><div class="col-xs-6">Marks: <strong><?=$question->marks?></strong></div><?php } ?>
						<?php if ($exam->neg_mark == 1) { ?><div class="col-xs-6 <?=$exam->marks_display==1?'text-right':''?>">-ve: <strong><?=$question->neg_marks?></strong></div><?php } ?>
					</div>
				</div>
				<?php } ?>
			</div><!-- /.cbt-qpane -->
			<?php } // end foreach questions ?>

			<!-- Navigation bar -->
			<div class="cbt-nav">
				<button type="button" id="btnCbtFlag" onclick="toggleFlag()" class="btn btn-default btn-sm">
					<i class="fas fa-flag"></i> Flag
				</button>
				<div class="cbt-nav-right">
					<button type="button" id="btnCbtPrev" onclick="navPrev()" class="btn btn-default btn-sm" disabled>
						<i class="fa fa-angle-left"></i> Previous
					</button>
					<button type="button" id="btnCbtNext" onclick="navNext()" class="btn btn-default btn-sm">
						Next <i class="fa fa-angle-right"></i>
					</button>
				</div>
			</div>
			<?php echo form_close(); ?>
		</div><!-- /.cbt-main -->
	</div><!-- /.cbt-body -->

	<!-- Submit confirmation overlay -->
	<div class="cbt-confirm" id="cbtConfirm">
		<div class="cbt-confirm-box">
			<h4><i class="fas fa-paper-plane"></i> Submit Exam?</h4>
			<p class="text-muted">Once submitted, you cannot change your answers.</p>
			<div style="margin:16px 0">
				<span class="cbt-stat" style="color:#5cb85c"><span id="cbt-summary-answered">0</span><small>Answered</small></span>
				<span class="cbt-stat" style="color:#d9534f"><span id="cbt-summary-unanswered">0</span><small>Unanswered</small></span>
				<span class="cbt-stat" style="color:#f0ad4e"><span id="cbt-summary-flagged">0</span><small>Flagged</small></span>
			</div>
			<div style="display:flex;gap:10px;justify-content:center">
				<button type="button" onclick="$('#cbtConfirm').removeClass('show')" class="btn btn-default">
					<i class="fas fa-arrow-left"></i> Review
				</button>
				<button type="button" onclick="$('#answerForm').submit()" class="btn btn-danger">
					<i class="fas fa-paper-plane"></i> Submit Now
				</button>
			</div>
		</div>
	</div>
</div><!-- /.cbt-wrapper -->

<?php } else {
	echo '<div class="alert alert-subl mt-lg text-center">' . translate('no_questions_have_been_assigned') . ' !</div>';
} ?>
