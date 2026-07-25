<?php
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
				<?php if (is_superadmin_loggedin()): ?>
					<div class="col-md-2">
						<div class="form-group">
							<label class="control-label"><?=translate('branch')?> <span class="required">*</span></label>
							<?php
								$arrayBranch = $this->app_lib->getSelectList('branch');
								echo form_dropdown("branch_id", $arrayBranch, set_value('branch_id'), "class='form-control' id='branch_id' onchange='getClassByBranch(this.value)'
								required data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity'");
							?>
						</div>
					</div>
				<?php endif; ?>
					<div class="col-md-2 mb-sm">
						<div class="form-group">
							<label class="control-label"><?=translate('class')?></label>
							<?php
								$arrayClass = $this->app_lib->getClass($branch_id);
								echo form_dropdown("class_id", $arrayClass, set_value('class_id'), "class='form-control' id='class_id' onchange='getSectionByClass(this.value,0)'
								data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity'");
							?>
						</div>
					</div>
					<div class="col-md-2 mb-sm">
						<div class="form-group">
							<label class="control-label"><?=translate('section')?></label>
							<?php
								$arraySection = $this->app_lib->getSections(set_value('class_id'), false);
								echo form_dropdown("section_id", $arraySection, set_value('section_id'), "class='form-control' id='section_id'
								data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity'");
							?>
						</div>
					</div>
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
					<div class="col-md-2 mb-sm">
						<div class="form-group">
							<label class="control-label"><?=translate('payment_via')?> <span class="required">*</span></label>
							<?php
								$arrayVia = array(
									'' => translate('select'),
									'all' => translate('both'),
									'online' => "Online",
									'cash' => "Cash",
								);
								echo form_dropdown("payment_via", $arrayVia, set_value('payment_via'), "class='form-control' required
								data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity'");
							?>
						</div>
					</div>
				</div>
				<div class="row mb-sm">
					<div class="col-md-4">
						<div class="form-group">
							<label class="control-label"><?=translate('date')?> <span class="required">*</span></label>
							<div class="input-group">
								<span class="input-group-addon"><i class="fas fa-calendar-check"></i></span>
								<input type="text" class="form-control daterange" name="daterange" value="<?=set_value('daterange', date('Y/m/d') . ' - ' . date('Y/m/d'))?>" required />
							</div>
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
			<header class="panel-heading">
				<h4 class="panel-title"><i class="fas fa-list-ol"></i> <?=translate('fees_payment_history');?>
					<div class="panel-btn">
						<a href="<?php
							$dr = explode(' - ', set_value('daterange', date('Y/m/d').' - '.date('Y/m/d')));
							$s = date('Y-m-d', strtotime($dr[0]));
							$e = date('Y-m-d', strtotime($dr[1] ?? $dr[0]));
							echo base_url('fees/export_payment_history_csv?'
								. 'class_id=' . urlencode(set_value('class_id'))
								. '&section_id=' . urlencode(set_value('section_id'))
								. '&payment_via=' . urlencode(set_value('payment_via'))
								. '&term=' . urlencode(set_value('term'))
								. '&session_id=' . urlencode(set_value('session_id', $active_session))
								. '&start=' . $s . '&end=' . $e);
						?>" class="btn btn-xs btn-default btn-circle">
							<i class="fas fa-file-csv"></i> CSV Export
						</a>
					</div>
				</h4>
			</header>
			<div class="panel-body">
				<div class="mb-md mt-md">
					<div class="export_title"><?=translate('fees_payment_history')?></div>
					<table class="table table-bordered table-condensed table-hover mb-none tbr-top table-export">
						<thead>
							<tr>
								<th><?=translate('sl')?></th>
								<th>Receipt #</th>
								<th><?=translate('student')?></th>
								<th><?=translate('register_no')?></th>
								<th><?=translate('roll')?></th>
								<th><?=translate('date')?></th>
								<th><?=translate('class')?></th>
								<th><?=translate('collect_by')?></th>
								<th><?=translate('payment_via')?></th>
								<th><?=translate('fees_type')?></th>
								<th><?=translate('amount')?></th>
								<th><?=translate('discount')?></th>
								<th><?=translate('fine')?></th>
								<th><?=translate('total')?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							$count = 1;
							foreach($invoicelist as $row):
								$totalp = ($row['amount'] + $row['fine']) - $row['discount'];
								?>
							<tr>
								<td><?php echo $count++; ?></td>
								<td><small class="text-muted">#<?=htmlspecialchars($row['receipt_no'])?></small></td>
								<td><?php echo $row['first_name'] . ' ' . $row['last_name'];?></td>
								<td><?php echo $row['register_no'];?></td>
								<td><?php echo $row['roll'];?></td>
								<td><?php echo _d($row['date']);?></td>
								<td><?php echo $row['class_name'] ." (" . $row['section_name'] . ")";?></td>
								<td><?php
								if ($row['collect_by'] == 'online') {
									echo "Online";
								} elseif ($row['collect_by'] == 'wallet') {
									echo "DVA Wallet";
								} else {
									echo get_type_name_by_id('staff', $row['collect_by']);
								} ?></td>
								<td><?php echo $row['pay_via'];?></td>
								<td><?php echo $row['type_name'];?></td>
								<td><?php echo currencyFormat($row['amount']);?></td>
								<td><?php echo currencyFormat($row['discount']);?></td>
								<td><?php echo currencyFormat($row['fine']);?></td>
								<td><?php echo currencyFormat($totalp);?></td>

							</tr>
							<?php endforeach; ?>
						</tbody>
						<tfoot>
							<tr>
								<th></th>
								<th></th>
								<th></th>
								<th></th>
								<th></th>
								<th></th>
								<th></th>
								<th></th>
								<th></th>
								<th></th>
								<th id="ph-total-amount"><?php echo isset($totals) ? currencyFormat($totals['amount']) : '—'; ?></th>
								<th id="ph-total-discount"><?php echo isset($totals) ? currencyFormat($totals['discount']) : '—'; ?></th>
								<th id="ph-total-fine"><?php echo isset($totals) ? currencyFormat($totals['fine']) : '—'; ?></th>
								<th id="ph-total-net"><?php echo isset($totals) ? currencyFormat($totals['net']) : '—'; ?></th>
							</tr>
						</tfoot>
					</table>
				</div>
			</div>
		</section>
<?php endif; ?>
	</div>
</div>
