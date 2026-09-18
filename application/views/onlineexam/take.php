<style type="text/css">
	.radio-custom p {
		margin: 0;
	}
</style>
<section class="panel">
	<header class="panel-heading">
		<h4 class="panel-title"><i class="fas fa-list-ul"></i> <?=translate('online_exam') ." ". translate('list')?></h4>
	</header>
	<div class="panel-body">
		<h4 class="text-center mb-lg mt-lg"><span class="text-weight-bold"><?=translate('exam') ." ". translate('name')?> </span> : <?php echo $exam->title; ?></h4>
		<div class="table-responsive mb-md">
			<table class="table table-striped table-condensed mb-none">
				<tbody>
					<tr>
						<th><?=translate('start_time')?></th>
						<td><?php echo _d($exam->exam_start) . "<p class='text-muted'>" . date("h:i A", strtotime($exam->exam_start)); ?></p></td>
						<th><?=translate('end_time')?></th>
						<td><?php echo _d($exam->exam_end) . "<p class='text-muted'>" . date("h:i A", strtotime($exam->exam_end)); ?></p></td>
					</tr>
					<tr>
						<th><?=translate('class')?></th>
						<td><?php echo $exam->class_name; ?> (<?php echo $this->onlineexam_model->getSectionDetails($exam->section_id); ?>)</td>
						<th><?=translate('subject')?></th>
						<td><?php echo str_replace('<br>', ' ', $this->onlineexam_model->getSubjectDetails($exam->subject_id)); ?></td>
					</tr>
					<tr>
						<th><?=translate('total') . " " . translate('question')?></th>
						<td><?php echo $exam->questions_qty; ?></td>
						<th><?=translate('duration')?></th>
						<td><?php echo $exam->duration; ?></td>
					</tr>
					<tr>
						<th><?=translate('exam') . " " . translate('total_attempt')?></th>
						<td><?php echo $exam->limits_participation; ?></td>
						<th><?=translate('your') . " " . translate('total_attempt')?></th>
						<td><?php echo $this->onlineexam_model->getStudentAttempt($exam->id); ?></td>
					</tr>
					<tr>
						<th><?=translate('passing_mark')  ?> </th>
						<td><?php echo $exam->passing_mark . ($exam->mark_type == 1 ? ' (%)' : ''); ?></td>
						<th><?=translate('negative_mark')?></th>
						<td><?php echo ($exam->neg_mark == 1) ? translate('yes') : translate('no'); ?></td>
					</tr>
					<?php if ($exam->exam_type == 1) { ?>
					<tr>
						<th><?=translate('exam') . " " . translate('fees')  ?> </th>
						<td><?php echo currencyFormat($exam->fee); ?></td>
						<th><?=translate('payment_informations')?></th>
						<td width="270"><?php echo $this->db->select('transaction_id')->where(array('student_id' => get_loggedin_user_id(), 'exam_id' => $exam->id))->get('online_exam_payment')->row()->transaction_id; ?></td>
					</tr>
					<?php } ?>
				</tbody>
			</table>
		</div>
		<span class="text-weight-bold"><?=translate('instruction')?> :</span>
		<p><?php echo $exam->instruction; ?></p>
<?php 
$startTime = strtotime($exam->exam_start);
$endTime = strtotime($exam->exam_end);
$now =  strtotime("now");
if (empty($studentSubmitted)) {
	if (($startTime <= $now && $now <= $endTime) && $exam->publish_status == 1) {
	?>
		<div class="text-center">
			<button class="btn btn-default btn-lg mt-lg start_btn" data-examid="<?php echo $exam->id; ?>" data-loading-text="<i class='fas fa-spinner fa-spin'></i> Processing"><i class="fas fa-computer-mouse"></i> <?=translate('start_exam')?></button>
		</div>
	<?php 
	}
} elseif($exam->publish_result == 1) {
	echo '<div class="alert alert-subl mt-lg text-center">Exam Results Published.</div>';
} else { 
	echo '<div class="alert alert-subl mt-lg text-center">You have already submitted.</div>'; 	
} ?>
	</div>
</section>
<!-- Fullscreen violation warning (CBT anti-cheat) -->
<div id="fs_warning" style="display:none;position:fixed;top:0;left:0;width:100%;z-index:10000;background:#d9534f;color:#fff;padding:12px 20px;font-size:14px;text-align:center;">
    <strong><i class="fas fa-triangle-exclamation"></i> Exam Violation:</strong>
    <span class="fs-warning-text"></span>
    <button class="btn btn-sm btn-light" style="margin-left:12px;" onclick="requestExamFullscreen();$('#fs_warning').hide();">Return to Fullscreen</button>
</div>
<div class="questionmodal">
      <div id="ans_modalBox" class="modal fade" role="dialog">
         <div class="modal-dialog modal-dialogfullwidth">
            <!-- Modal content-->
            <div class="modal-content modal-contentfull">
               <div class="modal-header">
                  <button type="button" class="close questionclose" data-dismiss="modal">&times;</button>
                  <h4 class="modal-title"><i class="fas fa-users-between-lines"></i> <?php echo $exam->title ?></h4>
               </div>
               <div class="modal-body">
               	<div id="online_questions"></div>
               </div>
            </div>
         </div>
      </div>
</div>

<script type="text/javascript">
    var examDuration   = "<?php echo $exam->duration; ?>";
    var totalQuestions = 0;
    var currentQ       = 1;
    var flaggedQs      = {};
    var examID;

    // ── Navigation ─────────────────────────────────────────────────
    function navTo(n) {
        $('#chip' + currentQ).removeClass('current');
        currentQ = n;
        $('.cbt-qpane').removeClass('active');
        $('#qpane' + n).addClass('active');
        $('#chip' + n).addClass('current');
        $('#cbt-qnum').text('Question ' + n + ' of ' + totalQuestions);
        updateNavButtons();
        updateFlagBtn();
        $('.cbt-main').scrollTop(0);
    }
    function navPrev() { if (currentQ > 1)              navTo(currentQ - 1); }
    function navNext() { if (currentQ < totalQuestions) navTo(currentQ + 1); }
    function updateNavButtons() {
        $('#btnCbtPrev').prop('disabled', currentQ === 1);
        $('#btnCbtNext').prop('disabled', currentQ === totalQuestions);
    }

    // ── Flagging ───────────────────────────────────────────────────
    function toggleFlag() {
        var qid = $('#qpane' + currentQ).data('qid');
        if (flaggedQs[qid]) { delete flaggedQs[qid]; } else { flaggedQs[qid] = true; }
        updateChip(currentQ);
        updateFlagBtn();
    }
    function updateFlagBtn() {
        var qid = $('#qpane' + currentQ).data('qid');
        if (flaggedQs[qid]) {
            $('#btnCbtFlag').addClass('btn-warning').html('<i class="fas fa-flag-checkered"></i> Unflag');
        } else {
            $('#btnCbtFlag').removeClass('btn-warning').html('<i class="fas fa-flag"></i> Flag');
        }
    }

    // ── Chip colours ───────────────────────────────────────────────
    function updateChip(step) {
        var qid  = $('#qpane' + step).data('qid');
        var chip = $('#chip' + step);
        chip.removeClass('answered flagged');
        if (flaggedQs[qid])        { chip.addClass('flagged');  }
        else if (isAnswered(step)) { chip.addClass('answered'); }
    }
    function isAnswered(step) {
        var answered = false;
        var pane = $('#qpane' + step);
        pane.find('input[type="radio"]:checked, input[type="checkbox"]:checked').each(function() { answered = true; });
        pane.find('input[type="text"]').each(function() { if ($(this).val().trim()) answered = true; });
        return answered;
    }
    function makeAnswered(step) { updateChip(step); }

    // ── Submit summary ─────────────────────────────────────────────
    function showSubmitSummary() {
        var answered = 0, flagged = 0, unanswered = 0;
        for (var i = 1; i <= totalQuestions; i++) {
            var qid = $('#qpane' + i).data('qid');
            if (flaggedQs[qid])     { flagged++;    }
            else if (isAnswered(i)) { answered++;   }
            else                    { unanswered++; }
        }
        $('#cbt-summary-answered').text(answered);
        $('#cbt-summary-unanswered').text(unanswered);
        $('#cbt-summary-flagged').text(flagged);
        $('#cbtConfirm').addClass('show');
    }

    // ── Auto-save ──────────────────────────────────────────────────
    function autoSaveAnswer(eID, $input) {
        var name    = $input.attr('name');
        var matches = name.match(/answer\[(\d+)\]\[(\d+)\]/);
        if (!matches) return;
        var questionID = matches[1];
        var ansType    = matches[2];
        var answer;
        if ($input.attr('type') === 'checkbox') {
            var checked = [];
            $('input[name="answer[' + questionID + '][' + ansType + '][]"]:checked').each(function() {
                checked.push($(this).val());
            });
            answer = JSON.stringify(checked);
        } else {
            answer = $input.val();
        }
        $.post(base_url + 'userrole/autosave_answer', {
            exam_id: eID, question_id: questionID, answer_type: ansType, answer: answer
        }, function(res) {
            if (res && res.status == 1) {
                $('#autosave_indicator').text('Saved at ' + res.ts);
            }
        }, 'json');
    }

    // ── Start exam ─────────────────────────────────────────────────
    $(document).on('click', '.start_btn', function() {
        var $this = $(this);
        examID    = $this.attr('data-examid');
        $.ajax({
            type:     'POST',
            url:      base_url + 'userrole/ajaxQuestions',
            data:     { 'exam_id': examID },
            dataType: 'JSON',
            beforeSend: function() { $this.button('loading'); clearInterval(interval); },
            success: function(data) {
                if (data.status == 1) {
                    if ($('#online_questions').length) {
                        totalQuestions = parseInt(data.total_questions);
                        $('#online_questions').html(data.page);

                        var remSecs = parseInt(data.remaining_seconds) || 0;
                        if (remSecs <= 0) { $('#answerForm').submit(); return; }
                        var h = Math.floor(remSecs / 3600),
                            m = Math.floor((remSecs % 3600) / 60),
                            s = remSecs % 60;
                        examDuration = (h<10?'0':'')+h+':'+(m<10?'0':'')+m+':'+(s<10?'0':'')+s;

                        currentQ  = 1;
                        flaggedQs = {};
                        navTo(1);

                        requestExamFullscreen();
                        fsViolations = 0;
                        timer();

                        $('#answerForm').off('change.autosave').on('change.autosave', 'input[type="radio"], input[type="checkbox"]', function() {
                            autoSaveAnswer(examID, $(this));
                            updateChip(currentQ);
                        });
                        $('#answerForm').off('input.autosave').on('input.autosave', 'input[type="text"]', function() {
                            clearTimeout($(this).data('asTimer'));
                            var $el = $(this);
                            $el.data('asTimer', setTimeout(function() {
                                autoSaveAnswer(examID, $el);
                                updateChip(currentQ);
                            }, 800));
                        });

                        $('#ans_modalBox').modal({ show: true, backdrop: 'static', keyboard: false });
                    }
                } else {
                    alertMsg(data.message, 'error', '<?php echo translate("error") ?>', '');
                }
            },
            error:    function() { alert('Error occurred. Please try again.'); $this.button('reset'); },
            complete: function() { $this.button('reset'); }
        });
    });

    // ── Anti-cheat & fullscreen lockdown ───────────────────────────
    var fsViolations      = 0;
    var MAX_FS_VIOLATIONS = 3;

    function requestExamFullscreen() {
        var el  = document.documentElement;
        var req = el.requestFullscreen || el.webkitRequestFullscreen || el.mozRequestFullScreen || el.msRequestFullscreen;
        if (req) req.call(el).catch(function() {});
    }

    function handleFsExit() {
        if (!$('#ans_modalBox').hasClass('in')) return;
        fsViolations++;
        var msg;
        if (fsViolations >= MAX_FS_VIOLATIONS) {
            msg = 'Maximum violations reached. Your exam is being submitted.';
            $('#fs_warning .fs-warning-text').text(msg);
            $('#fs_warning button').hide();
            $('#fs_warning').show();
            setTimeout(function() { $('#answerForm').submit(); }, 2000);
        } else {
            var rem = MAX_FS_VIOLATIONS - fsViolations;
            msg = 'Warning ' + fsViolations + ' of ' + MAX_FS_VIOLATIONS + ': You exited fullscreen. ' +
                  rem + ' violation(s) remaining before auto-submit.';
            $('#fs_warning .fs-warning-text').text(msg);
            $('#fs_warning button').show();
            $('#fs_warning').show();
        }
    }

    $(document).on('fullscreenchange webkitfullscreenchange mozfullscreenchange msfullscreenchange', function() {
        if (!document.fullscreenElement && !document.webkitFullscreenElement && !document.mozFullScreenElement) {
            handleFsExit();
        } else {
            $('#fs_warning').hide();
        }
    });

    document.addEventListener('visibilitychange', function() {
        if (document.hidden && $('#ans_modalBox').hasClass('in')) {
            $('#autosave_indicator').css('color', 'red').text('Warning: tab switch detected!');
            setTimeout(function() { $('#autosave_indicator').css('color', ''); }, 4000);
        }
    });

    document.addEventListener('contextmenu', function(e) {
        if ($('#ans_modalBox').hasClass('in')) e.preventDefault();
    });

    document.addEventListener('keydown', function(e) {
        if (!$('#ans_modalBox').hasClass('in')) return;
        var k = e.key ? e.key.toLowerCase() : '';
        if ((e.ctrlKey || e.metaKey) && ['c', 'x', 'a'].includes(k)) e.preventDefault();
        if (k === 'f12') e.preventDefault();
        if ((e.ctrlKey || e.metaKey) && e.shiftKey && k === 'i') e.preventDefault();
    });

    // ── Timer ──────────────────────────────────────────────────────
    var interval;
    var timer = function() {
        interval = setInterval(function() {
            var disp  = durationUpdate();
            $('.remain_duration').text(disp);
            var parts = disp.split(':');
            var secs  = (+parts[0]) * 3600 + (+parts[1]) * 60 + (+parts[2]);
            if (secs <= 300) { $('.cbt-timer').addClass('cbt-timer-danger'); }
        }, 1000);
    };
</script>
