<?php $widget = (is_superadmin_loggedin() ? 4 : 6); ?>
<div class="row">
	<div class="col-md-12">
		<section class="panel">
			<header class="panel-heading">
				<h4 class="panel-title"><?php echo translate('select_ground'); ?></h4>
			</header>
			<?php echo form_open($this->uri->uri_string(), array('class' => 'validate'));?>
			<div class="panel-body">
				<div class="row mb-sm">
				<?php if (is_superadmin_loggedin() ): ?>
					<div class="col-md-4">
						<div class="form-group">
							<label class="control-label"><?=translate('branch')?> <span class="required">*</span></label>
							<?php
								$arrayBranch = $this->app_lib->getSelectList('branch');
								echo form_dropdown("branch_id", $arrayBranch, set_value('branch_id'), "class='form-control' onchange='getClassByBranch(this.value)'
								data-plugin-selectTwo data-width='100%'");
							?>
						</div>
					</div>
				<?php endif; ?>
					<div class="col-md-<?php echo $widget; ?> mb-sm">
						<div class="form-group">
							<label class="control-label"><?=translate('class')?> <span class="required">*</span></label>
							<?php
								$arrayClass = $this->app_lib->getClass($branch_id);
								echo form_dropdown("class_id", $arrayClass, set_value('class_id'), "class='form-control' id='class_id' onchange='getSectionByClass(this.value,0)'
								required data-plugin-selectTwo data-width='100%' ");
							?>
						</div>
					</div>
					<div class="col-md-<?php echo $widget; ?> mb-sm">
						<div class="form-group">
							<label class="control-label"><?=translate('section')?> <span class="required">*</span></label>
							<?php
								$arraySection = $this->app_lib->getSections(set_value('class_id'));
								echo form_dropdown("section_id", $arraySection, set_value('section_id'), "class='form-control' id='section_id' required
								data-plugin-selectTwo data-width='100%' ");
							?>
						</div>
					</div>
				</div>
			</div>
			<footer class="panel-footer">
				<div class="row">
					<div class="col-md-offset-10 col-md-2">
						<button type="submit" name="submit" value="search" class="btn btn-default btn-block"> <i class="fas fa-filter"></i> <?=translate('filter')?></button>
					</div>
				</div>
			</footer>
		<?php echo form_close();?>
		</section>

		<?php if (isset($students)):?>
			<section class="panel" >
			<?php echo form_open('student_promotion/transfersave', array('class' => 'frm-submit'));?>
				<header class="panel-heading">
					<h4 class="panel-title"><?=translate('the_next_session_was_transferred_to_the_students')?></h4>
				</header>
				<div class="panel-body">
					<div class="alert alert-success hidden-div" id="msgAlert"></div>

					<!-- E: Same-session warning -->
					<div class="alert alert-warning hidden" id="sameSessionWarning">
						<i class="fas fa-exclamation-triangle"></i>
						<strong>Warning:</strong> You have selected the <strong>current session</strong> as the promotion target.
						This is valid for mid-year class transfers, but make sure this is intentional — it will not move students to a new academic year.
					</div>

					<div class="row mb-lg mt-md">
						<div class="col-md-12">
							<div class="alert alert-subl mb-lg">
								<strong>Instructions:</strong><br/>
								1. Roll field shows previous roll — enter a new roll for the promoted session if needed.<br/>
								2. Outstanding fees are recalculated from the database at promotion time — per-term breakdown is carried forward automatically.<br/>
								3. Transport fee assignments are copied to the new session automatically.<br/>
								4. Uncheck <em>Carry Forward Due</em> to waive all outstanding balances (not reversible).<br/>
								5. <em>Running</em> keeps the student in the same class — only the session changes.<br/>
								6. <em>Leave / Add Alumni</em> marks the student as graduated.
							</div>
						</div>

						<div class="col-md-12 mb-md">
							<div class="checkbox-replace">
								<label class="i-checks"><input type="checkbox" name="due_forward" id="due_forward" checked><i></i> Carry Forward Due in Next Session</label>
							</div>
						</div>

						<div class="col-md-4">
							<div class="form-group">
								<label class="control-label"><?=translate('promote_to_session')?> <span class="required">*</span></label>
								<?php
									$currentSessionID = get_session_id();
									$arraySession = array("" => translate('select'));
									$years = $this->db->get('schoolyear')->result();
									foreach ($years as $year){
										$arraySession[$year->id] = $year->school_year;
									}
									echo form_dropdown("promote_session_id", $arraySession, set_value('promote_session_id'), "class='form-control' id='session_id' data-current-session='{$currentSessionID}' data-plugin-selectTwo data-width='100%'");
								?>
								<span class="error"></span>
							</div>
						</div>
						<div class="col-md-4">
							<div class="form-group">
								<label class="control-label"><?=translate('promote_to_class')?> <span class="required">*</span></label>
								<?php
									$arrayClass = $this->app_lib->getClass($branch_id);
									echo form_dropdown("promote_class_id", $arrayClass, set_value('promote_class_id'), "class='form-control' id='class_promote_id' data-plugin-selectTwo data-width='100%'");
								?>
								<span class="error"></span>
							</div>
						</div>
						<div class="col-md-4">
							<div class="form-group">
								<label class="control-label"><?=translate('promote_to_section')?> <span class="required">*</span></label>
								<?php
									$arraySection = array("" => translate('select'));
									echo form_dropdown("promote_section_id", $arraySection, set_value('promote_section_id'), "class='form-control' id='section_promote_id' data-plugin-selectTwo data-width='100%'");
								?>
								<span class="error"></span>
							</div>
						</div>
					</div>

					<div class="table-responsive mb-md">
						<table class="table table-condensed nowrap table-hover table-bordered tbr-top">
							<thead>
								<tr>
									<th class="center">
										<div class="checkbox-replace">
											<label class="i-checks" data-toggle="tooltip" data-original-title="Promotion"><input type="checkbox" id="selectAllchkbox" checked><i></i></label>
										</div>
									</th>
									<th width="50">#</th>
									<th><?=translate('student_name')?></th>
									<th><?=translate('register_no')?></th>
									<th><?=translate('guardian_name')?></th>
									<th><?=translate('mark_summary')?></th>
									<th><?=translate('class')?></th>
									<th><?=translate('roll')?></th>
									<th>Outstanding Fees <small class="text-muted">(auto-calculated)</small></th>
									<th><?=translate('status')?></th>
								</tr>
							</thead>
							<tbody>
								<?php
								$count = 1;
								// Use batch-loaded balances (A: no N+1 queries)
								$outstandingBalances = $outstanding_balances ?? [];

								if (count($students)) {
									foreach($students as $key => $row):
										$enrollID   = $row['student_id'];
										$balanceData = $outstandingBalances[$enrollID] ?? ['total' => 0, 'breakdown' => []];
										$dueAmount   = $balanceData['total'];
										$breakdown   = $balanceData['breakdown'];
								?>
								<tr>
									<input type="hidden" name="promote[<?=$key?>][student_id]" value="<?=$row['student_id']?>" />
									<td class="center checked-area">
										<div class="pt-csm checkbox-replace">
											<label class="i-checks"><input type="checkbox" checked name="promote[<?=$key?>][enroll_id]" value="<?=$row['id']?>"><i></i></label>
										</div>
									</td>
									<td><?php echo $count++;?></td>
									<td><?php echo $row['fullname'];?> <span class="promoted" id="pstu<?php echo $row['student_id']; ?>"></span></td>
									<td><?php echo $row['register_no'];?></td>
									<td><?php echo (!empty($row['parent_id']) ? get_type_name_by_id('parent', $row['parent_id']) : 'N/A');?></td>
									<td>
										<a target="_blank" href="<?php echo base_url('student/profile/' . $row['student_id']);?>" class="btn btn-default btn-circle">
											<i class="fas fa-eye"></i> <?=translate('view')?>
										</a>
									</td>
									<td>
										<div class="radio-custom radio-success radio-inline mt-xs">
											<input type="radio" class="swa" value="running" name="promote[<?=$key?>][class_status]" id="running<?=$key?>">
											<label for="running<?=$key?>"><?php echo translate('running') ?></label>
										</div>
										<div class="radio-custom radio-success radio-inline mt-xs">
											<input type="radio" class="swa" checked value="promoted" name="promote[<?=$key?>][class_status]" id="promoted<?=$key?>">
											<label for="promoted<?=$key?>"><?php echo translate('promoted') ?></label>
										</div>
									</td>
									<td>
										<div class="form-group">
											<input type="number" class="form-control swa" name="promote[<?=$key?>][roll]" value="<?=$row['roll']?>" />
											<span class="error"></span>
										</div>
									</td>
									<td>
										<?php if ($dueAmount > 0): ?>
											<!-- C: Read-only outstanding with breakdown toggle -->
											<div class="due-amount-cell">
												<strong class="text-danger"><?= number_format($dueAmount, 2) ?></strong>
												<?php if (!empty($breakdown)): ?>
												<a href="#" class="btn btn-xs btn-link toggle-breakdown" data-key="<?=$key?>" title="View breakdown">
													<i class="fas fa-list-ul"></i>
												</a>
												<div id="breakdown-<?=$key?>" class="breakdown-table" style="display:none; margin-top:6px;">
													<table class="table table-condensed table-bordered" style="font-size:11px; margin:0;">
														<tbody>
															<?php foreach ($breakdown as $bItem): ?>
															<tr>
																<td><?= html_escape($bItem['group_name']) ?></td>
																<td class="text-right text-danger"><strong><?= number_format($bItem['outstanding'], 2) ?></strong></td>
															</tr>
															<?php endforeach; ?>
														</tbody>
													</table>
												</div>
												<?php endif; ?>
											</div>
										<?php else: ?>
											<span class="text-success"><i class="fas fa-check-circle"></i> No outstanding</span>
										<?php endif; ?>
									</td>
									<td class="leave">
										<div class="pt-csm checkbox-replace">
											<label class="i-checks"><input type="checkbox" name="promote[<?=$key?>][leave]" value="<?=$row['id']?>"><i></i> Leave / Add Alumni</label>
										</div>
									</td>
								</tr>
								<?php
									endforeach;
								} else {
									echo '<tr><td colspan="10"><h5 class="text-danger text-center">'.translate('no_information_available').'</td></tr>';
								}
							?>
							</tbody>
						</table>
					</div>
				</div>
				<div class="panel-footer">
					<div class="row">
						<div class="col-md-offset-10 col-md-2">
							<button type="submit" name="submit" value="promote" class="btn btn-default btn-block" data-loading-text="<i class='fas fa-spinner fa-spin'></i> Processing">
								<i class="fab fa-nintendo-switch"></i> <?=translate('promotion')?>
							</button>
						</div>
					</div>
				</div>
				<?php
				echo form_hidden(array(
					'branch_id'  => $branch_id,
					'class_id'   => $class_id,
					'section_id' => $section_id,
				));
				echo form_close();
				?>
			</section>
		<?php endif; ?>
	</div>
</div>

<script type="text/javascript">
$(document).ready(function () {

	// Leave checkbox disables other controls in that row
	$(document).on('change', ".leave input[type='checkbox']", function() {
		$(this).closest('tr').find('.swa').prop('disabled', this.checked);
	});

	// B: Toggle per-term fee breakdown
	$(document).on('click', '.toggle-breakdown', function(e) {
		e.preventDefault();
		var key = $(this).data('key');
		$('#breakdown-' + key).slideToggle(150);
	});

	// E: Warn when same session is selected as promotion target
	var currentSession = $('#session_id').data('current-session');
	function checkSameSession() {
		var selected = $('#session_id').val();
		if (selected && selected == currentSession) {
			$('#sameSessionWarning').removeClass('hidden');
		} else {
			$('#sameSessionWarning').addClass('hidden');
		}
	}
	$('#session_id').on('change', checkSameSession);
	checkSameSession(); // run on load in case session is pre-selected

	// Promotion status check (already-promoted indicator)
	$("#session_id, #class_promote_id, #section_promote_id").on('change', function() {
		var classID   = $('#class_promote_id').val();
		var sectionID = $('#section_promote_id').val();
		var sessionID = $('#session_id').val();
		$('.promoted').html("");
		$.ajax({
			url: "<?=base_url('student_promotion/getPromotionStatus')?>",
			type: 'POST',
			dataType: 'json',
			data: { 'class_id': classID, 'section_id': sectionID, 'session_id': sessionID },
			success: function (data) {
				if (data.status == 1) {
					$.each(data.stu_arr, function (index, value) {
						if ($("#pstu" + value).length) {
							$("#pstu" + value).html('<i class="far fa-check-circle text-success" title="Already promoted"></i>');
						}
					});
					$('#msgAlert').html(data.msg).show(300);
				} else {
					$('#msgAlert').html("").hide(300);
				}
			}
		});
	});

	// Load sections when class changes
	$('#class_promote_id').on('change', function() {
		var classID = $(this).val();
		$.ajax({
			url: "<?=base_url('ajax/getSectionByClass')?>",
			type: 'POST',
			data: { class_id: classID },
			success: function (data) {
				$('#section_promote_id').html(data);
			}
		});
	});
});
</script>
