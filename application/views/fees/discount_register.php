<?php
$currency_symbol = $global_config['currency_symbol'];
$selSessID = (int)set_value('session_id', $active_session);
?>
<div class="row">
	<div class="col-md-12">
		<section class="panel">
			<header class="panel-heading">
				<h4 class="panel-title"><?=translate('select_ground')?></h4>
			</header>
			<?php echo form_open($this->uri->uri_string(), ['class' => 'validate']); ?>
			<div class="panel-body">
				<div class="row mb-sm">
				<?php if (is_superadmin_loggedin()): ?>
					<div class="col-md-2">
						<div class="form-group">
							<label class="control-label"><?=translate('branch')?> <span class="required">*</span></label>
							<?php
								$arrayBranch = $this->app_lib->getSelectList('branch');
								echo form_dropdown('branch_id', $arrayBranch, set_value('branch_id'),
									"class='form-control' id='branch_id' onchange='getClassByBranch(this.value)'
									required data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity'");
							?>
						</div>
					</div>
				<?php endif; ?>
					<div class="col-md-2 mb-sm">
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
					<div class="col-md-2 mb-sm">
						<div class="form-group">
							<label class="control-label"><?=translate('class')?></label>
							<?php
								$arrayClass = $this->app_lib->getClass($branch_id);
								echo form_dropdown('class_id', $arrayClass, set_value('class_id'),
									"class='form-control' id='class_id' onchange='getSectionByClass(this.value,0)'
									data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity'");
							?>
						</div>
					</div>
					<div class="col-md-2 mb-sm">
						<div class="form-group">
							<label class="control-label"><?=translate('section')?></label>
							<?php
								$arraySection = $this->app_lib->getSections(set_value('class_id'), false);
								echo form_dropdown('section_id', $arraySection, set_value('section_id'),
									"class='form-control' id='section_id'
									data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity'");
							?>
						</div>
					</div>
					<div class="col-md-4 mb-sm">
						<div class="form-group">
							<label class="control-label"><?=translate('date')?> <span class="required">*</span></label>
							<div class="input-group">
								<span class="input-group-addon"><i class="fas fa-calendar-check"></i></span>
								<input type="text" class="form-control daterange" name="daterange"
									value="<?=set_value('daterange', date('Y/m/d') . ' - ' . date('Y/m/d'))?>" required />
							</div>
						</div>
					</div>
				</div>
			</div>
			<footer class="panel-footer">
				<div class="row">
					<div class="col-md-offset-10 col-md-2">
						<button type="submit" name="search" value="1" class="btn btn-default btn-block">
							<i class="fas fa-filter"></i> <?=translate('filter')?>
						</button>
					</div>
				</div>
			</footer>
			<?php echo form_close(); ?>
		</section>

<?php if (isset($rows)): ?>
		<section class="panel appear-animation" data-appear-animation="<?=$global_config['animations']?>" data-appear-animation-delay="100">
			<header class="panel-heading">
				<h4 class="panel-title"><i class="fas fa-tags"></i> Discount Register
					<?php if (isset($total_discount)): ?>
					<span class="text-muted" style="font-size:0.85em">&mdash; Total Discount: <?=currencyFormat($total_discount)?></span>
					<?php endif; ?>
				</h4>
			</header>
			<div class="panel-body">
				<div class="mb-md mt-md">
					<div class="export_title">Discount Register</div>
					<table class="table table-bordered table-condensed table-hover mb-none tbr-top table-export">
						<thead>
							<tr>
								<th><?=translate('sl')?></th>
								<th>Receipt #</th>
								<th><?=translate('date')?></th>
								<th><?=translate('student')?></th>
								<th><?=translate('register_no')?></th>
								<th><?=translate('class')?></th>
								<th><?=translate('fees_type')?></th>
								<th>Fee Amount</th>
								<th>Discount Given</th>
								<th>Collected By</th>
								<th>Remarks</th>
							</tr>
						</thead>
						<tbody>
							<?php if (empty($rows)): ?>
							<tr><td colspan="11" class="text-center text-muted">No discounts found for the selected period.</td></tr>
							<?php else: ?>
							<?php $sl = 1; foreach ($rows as $row): ?>
							<tr>
								<td><?=$sl++?></td>
								<td><small class="text-muted">#<?=htmlspecialchars($row['receipt_no'])?></small></td>
								<td><?=_d($row['date'])?></td>
								<td><?=htmlspecialchars($row['first_name'] . ' ' . $row['last_name'])?></td>
								<td><?=htmlspecialchars($row['register_no'])?></td>
								<td><?=htmlspecialchars($row['class_name'] . ' (' . $row['section_name'] . ')')?></td>
								<td><?=htmlspecialchars($row['type_name'])?></td>
								<td><?=currencyFormat($row['amount'])?></td>
								<td class="text-success"><strong><?=currencyFormat($row['discount'])?></strong></td>
								<td><?=htmlspecialchars($row['collected_by'])?></td>
								<td><small class="text-muted"><?=htmlspecialchars($row['remarks'])?></small></td>
							</tr>
							<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
						<tfoot>
							<tr class="active">
								<th colspan="8" class="text-right">Total Discount:</th>
								<th class="text-success"><?=currencyFormat($total_discount ?? 0)?></th>
								<th></th>
								<th></th>
							</tr>
						</tfoot>
					</table>
				</div>
			</div>
		</section>
<?php endif; ?>
	</div>
</div>
