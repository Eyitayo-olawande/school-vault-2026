<?php
$widget    = (is_superadmin_loggedin() ? 4 : 6);
$currency_symbol = $global_config['currency_symbol'];
$selSessID = (int)set_value('session_id', $active_session);
$selTerm   = set_value('term', '');
?>
<div class="row">
	<div class="col-md-12">
		<section class="panel">
			<header class="panel-heading">
				<h4 class="panel-title"><?=translate('select_ground')?></h4>
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
								required data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity'");
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
								required data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity' ");
							?>
						</div>
					</div>
					<div class="col-md-<?php echo $widget; ?> mb-sm">
						<div class="form-group">
							<label class="control-label"><?=translate('section')?></label>
							<?php
								$arraySection = $this->app_lib->getSections(set_value('class_id'), false);
								echo form_dropdown("section_id", $arraySection, set_value('section_id'), "class='form-control' id='section_id'
								data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity' ");
							?>
						</div>
					</div>
					<div class="col-md-<?php echo $widget; ?> mb-sm">
						<div class="form-group">
							<label class="control-label"><?=translate('due_date')?> (<?=translate('on_or_before')?>)</label>
							<div class="input-group">
								<span class="input-group-addon"><i class="fas fa-calendar-check"></i></span>
								<input type="text" class="form-control datepicker" name="due_before"
									value="<?php echo set_value('due_before'); ?>" placeholder="<?=translate('optional')?>" />
							</div>
						</div>
					</div>
				</div>
				<div class="row mb-sm">
					<div class="col-md-4">
						<div class="form-group">
							<label class="control-label">Academic Session</label>
							<select name="session_id" class="form-control"
								data-plugin-selectTwo data-width="100%" data-minimum-results-for-search="Infinity">
								<?php foreach ($session_list as $sid => $lbl): ?>
									<option value="<?=$sid?>" <?=($sid == $selSessID) ? 'selected' : ''?>><?=htmlspecialchars($lbl)?></option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>
					<div class="col-md-4">
						<div class="form-group">
							<label class="control-label">Academic Term</label>
							<select name="term" class="form-control"
								data-plugin-selectTwo data-width="100%" data-minimum-results-for-search="Infinity">
								<option value="" <?=empty($selTerm) ? 'selected' : ''?>>All Terms</option>
								<option value="1ST TERM" <?=($selTerm === '1ST TERM') ? 'selected' : ''?>>1st Term</option>
								<option value="2ND TERM" <?=($selTerm === '2ND TERM') ? 'selected' : ''?>>2nd Term</option>
								<option value="3RD TERM" <?=($selTerm === '3RD TERM') ? 'selected' : ''?>>3rd Term</option>
							</select>
						</div>
					</div>
				</div>
			</div>
			<footer class="panel-footer">
				<div class="row">
					<div class="col-md-offset-10 col-md-2">
						<button type="submit" name="search" value="1" class="btn btn-default btn-block"> <i class="fas fa-filter"></i> <?=translate('filter')?></button>
					</div>
				</div>
			</footer>
			<?php echo form_close();?>
		</section>
<?php if (isset($invoicelist)): ?>
		<section class="panel appear-animation" data-appear-animation="<?php echo $global_config['animations'];?>" data-appear-animation-delay="100">
			<?php echo form_open('fees/invoicePrint', array('class' => 'printIn')); ?>
			<header class="panel-heading">
				<h4 class="panel-title"><i class="fas fa-list-ol"></i> <?=translate('due_fees_report');?>
					<div class="panel-btn">
						<a href="<?=base_url('fees/export_due_report_csv?'
							. 'class_id='   . urlencode(set_value('class_id'))
							. '&section_id=' . urlencode(set_value('section_id'))
							. '&term='       . urlencode(set_value('term'))
							. '&session_id=' . urlencode(set_value('session_id', $active_session))
							. '&due_before=' . urlencode(set_value('due_before')))?>"
							class="btn btn-xs btn-default btn-circle">
							<i class="fas fa-file-csv"></i> CSV Export
						</a>
					</div>
				</h4>
			</header>
			<div class="panel-body">
				<div class="mb-md mt-md">
					<div class="export_title"><?=translate('due_fees_report')?></div>
					<table class="table table-bordered table-condensed table-hover mb-none tbr-top table-export">
						<div class="mb-sm">
							<button type="button" id="send-reminders-btn" class="btn btn-sm btn-warning" disabled>
								<i class="fas fa-sms"></i> Send Reminders (<span id="reminder-count">0</span>)
							</button>
						</div>
						<thead>
							<tr>
								<th class="no-sort no-export">
									<input type="checkbox" id="select-all-due" title="Select all">
								</th>
								<th><?=translate('sl')?></th>
								<th><?=translate('student')?></th>
								<th><?=translate('register_no')?></th>
								<th><?=translate('roll')?></th>
								<th><?=translate('mobile_no')?></th>
								<th><?=translate('total_fees')?></th>
								<th><?=translate('total_paid')?></th>
								<th><?=translate('total_discount')?></th>
								<th><?=translate('total_fine')?></th>
								<th><?=translate('total_balance')?></th>
								<th>Days Overdue</th>
								<th class="no-sort no-export"><?=translate('action')?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							$count = 1;
							$totalfees = 0;
							$totalpaid = 0;
							$totaldiscount = 0;
							$totalfine = 0;
							$totalbalance = 0;
							$today = new DateTime('today');
							foreach($invoicelist as $row):
								$paid = $row['payment']['total_paid'] + $row['payment']['total_discount'];
								if ((float)$row['total_fees'] <= (float)$paid) {
								} else {
									$totalfees += $row['total_fees'];
									$totalpaid += $row['payment']['total_paid'];
									$totaldiscount += $row['payment']['total_discount'];
									$totalfine += $row['payment']['total_fine'];
									$totalbalance += ($row['total_fees'] - $paid);
									$dueDays = '';
									if (!empty($row['due_date'])) {
										$dueDate = new DateTime($row['due_date']);
										$days = (int)$dueDate->diff($today)->format('%R%a');
										if ($days > 0) {
											$ac = $days > 90 ? 'text-danger' : ($days > 60 ? 'text-warning' : 'text-muted');
											$dueDays = '<span class="' . $ac . '">' . $days . 'd</span>';
										}
									}
								?>
							<tr>
								<td class="no-export">
									<input type="checkbox" class="due-cb" value="<?=$row['student_id']?>">
								</td>
								<td><?php echo $count++; ?></td>
								<td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']);?></td>
								<td><?php echo $row['register_no'];?></td>
								<td><?php echo $row['roll'];?></td>
								<td><?php echo $row['mobileno'];?></td>
								<td><?php echo currencyFormat($row['total_fees']);?></td>
								<td><?php echo currencyFormat($row['payment']['total_paid']);?></td>
								<td><?php echo currencyFormat($row['payment']['total_discount']);?></td>
								<td><?php echo currencyFormat($row['payment']['total_fine']);?></td>
								<td><?php echo currencyFormat($row['total_fees'] - $paid);?></td>
								<td><?=$dueDays ?: '<span class="text-muted">—</span>'?></td>
								<td>
									<a href="<?=base_url('fees/student_ledger/' . $row['student_id'])?>"
										class="btn btn-xs btn-default btn-circle" target="_blank">
										<i class="fas fa-book"></i> Ledger
									</a>
								</td>
							</tr>
							<?php } endforeach; ?>
						</tbody>
						<tfoot>
							<tr>
								<th></th>
								<th></th>
								<th></th>
								<th></th>
								<th></th>
								<th></th>
								<th><?php echo currencyFormat($totalfees); ?></th>
								<th><?php echo currencyFormat($totalpaid); ?></th>
								<th><?php echo currencyFormat($totaldiscount); ?></th>
								<th><?php echo currencyFormat($totalfine); ?></th>
								<th><?php echo currencyFormat($totalbalance); ?></th>
								<th></th>
								<th></th>
							</tr>
						</tfoot>
					</table>
				</div>
			</div>
			<?php echo form_close(); ?>
		</section>
<?php endif; ?>
	</div>
</div>
<?php if (isset($invoicelist)): ?>
<script>
$(document).ready(function() {
	var $btn = $('#send-reminders-btn');
	var $count = $('#reminder-count');

	$(document).on('change', '.due-cb, #select-all-due', function() {
		if ($(this).attr('id') === 'select-all-due') {
			$('.due-cb').prop('checked', this.checked);
		}
		var n = $('.due-cb:checked').length;
		$count.text(n);
		$btn.prop('disabled', n === 0);
	});

	$btn.on('click', function() {
		var ids = [];
		$('.due-cb:checked').each(function() { ids.push($(this).val()); });
		if (!ids.length) return;
		if (!confirm('Send fee payment reminders to ' + ids.length + ' student(s)?')) return;
		$btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Sending...');
		$.post(base_url + 'fees/send_fee_reminders', {
			student_ids: ids,
			<?=$this->security->get_csrf_token_name()?>: '<?=$this->security->get_csrf_hash()?>'
		}, function(r) {
			alert(r.message || 'Done');
			$btn.html('<i class="fas fa-sms"></i> Send Reminders (<span id="reminder-count">' + ids.length + '</span>)').prop('disabled', true);
		}, 'json').fail(function() {
			alert('Request failed. Please try again.');
			$btn.prop('disabled', false);
		});
	});
});
</script>
<?php endif; ?>
