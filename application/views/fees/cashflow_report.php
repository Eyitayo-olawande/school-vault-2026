<?php
$currency_symbol = $global_config['currency_symbol'];
$selSessID  = (int)set_value('session_id', $active_session);
$selGroupBy = set_value('group_by', 'day');
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
									"class='form-control' required
									data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity'");
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
							<label class="control-label">Group By</label>
							<select name="group_by" class="form-control"
								data-plugin-selectTwo data-width="100%" data-minimum-results-for-search="Infinity">
								<option value="day" <?=($selGroupBy === 'day') ? 'selected' : ''?>>Daily</option>
								<option value="month" <?=($selGroupBy === 'month') ? 'selected' : ''?>>Monthly</option>
							</select>
						</div>
					</div>
					<div class="col-md-4 mb-sm">
						<div class="form-group">
							<label class="control-label"><?=translate('date')?> <span class="required">*</span></label>
							<div class="input-group">
								<span class="input-group-addon"><i class="fas fa-calendar-check"></i></span>
								<input type="text" class="form-control daterange" name="daterange"
									value="<?=set_value('daterange', date('Y/m/01') . ' - ' . date('Y/m/d'))?>" required />
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
		<?php if (isset($totals)): ?>
		<div class="row mb-md">
			<div class="col-md-3 col-sm-6">
				<div class="widget-summary widget-summary-sm bg-success text-light" style="border-radius:4px;padding:10px">
					<div class="widget-summary-col">
						<div class="summary">
							<h4 class="title" style="color:#fff">Cash Total</h4>
							<div class="info"><strong class="amount" style="color:#fff"><?=currencyFormat($totals['cash'])?></strong></div>
						</div>
					</div>
				</div>
			</div>
			<div class="col-md-3 col-sm-6">
				<div class="widget-summary widget-summary-sm bg-primary text-light" style="border-radius:4px;padding:10px">
					<div class="widget-summary-col">
						<div class="summary">
							<h4 class="title" style="color:#fff">Online Total</h4>
							<div class="info"><strong class="amount" style="color:#fff"><?=currencyFormat($totals['online'])?></strong></div>
						</div>
					</div>
				</div>
			</div>
			<div class="col-md-3 col-sm-6">
				<div class="widget-summary widget-summary-sm bg-info text-light" style="border-radius:4px;padding:10px">
					<div class="widget-summary-col">
						<div class="summary">
							<h4 class="title" style="color:#fff">DVA Wallet Total</h4>
							<div class="info"><strong class="amount" style="color:#fff"><?=currencyFormat($totals['dva'])?></strong></div>
						</div>
					</div>
				</div>
			</div>
			<div class="col-md-3 col-sm-6">
				<div class="widget-summary widget-summary-sm" style="border-radius:4px;padding:10px">
					<div class="widget-summary-col">
						<div class="summary">
							<h4 class="title">Grand Total</h4>
							<div class="info"><strong class="amount"><?=currencyFormat($totals['grand'])?></strong></div>
						</div>
						<div class="summary-footer"><span class="text-muted"><?=$totals['tx_count']?> transactions</span></div>
					</div>
				</div>
			</div>
		</div>
		<?php endif; ?>

		<section class="panel appear-animation" data-appear-animation="<?=$global_config['animations']?>" data-appear-animation-delay="100">
			<header class="panel-heading">
				<h4 class="panel-title">
					<i class="fas fa-money-bill-wave"></i> Cashflow — <?=isset($group_by) && $group_by === 'month' ? 'Monthly' : 'Daily'?> Breakdown
				</h4>
			</header>
			<div class="panel-body">
				<div class="mb-md mt-md">
					<div class="export_title">Cashflow Report</div>
					<table class="table table-bordered table-condensed table-hover mb-none tbr-top table-export">
						<thead>
							<tr>
								<th>Period</th>
								<th>Cash</th>
								<th>Online</th>
								<th>DVA Wallet</th>
								<th>Total</th>
								<th>Transactions</th>
							</tr>
						</thead>
						<tbody>
							<?php if (empty($rows)): ?>
							<tr><td colspan="6" class="text-center text-muted">No payment data found for the selected period.</td></tr>
							<?php else: ?>
							<?php foreach ($rows as $row): ?>
							<tr>
								<td><strong><?=htmlspecialchars($row['period'])?></strong></td>
								<td><?=$row['cash_total'] > 0 ? currencyFormat($row['cash_total']) : '<span class="text-muted">—</span>'?></td>
								<td><?=$row['online_total'] > 0 ? currencyFormat($row['online_total']) : '<span class="text-muted">—</span>'?></td>
								<td><?=$row['dva_total'] > 0 ? currencyFormat($row['dva_total']) : '<span class="text-muted">—</span>'?></td>
								<td><strong><?=currencyFormat($row['grand_total'])?></strong></td>
								<td><?=$row['transaction_count']?></td>
							</tr>
							<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
						<?php if (!empty($rows) && isset($totals)): ?>
						<tfoot>
							<tr class="active">
								<th>TOTAL</th>
								<th><?=currencyFormat($totals['cash'])?></th>
								<th><?=currencyFormat($totals['online'])?></th>
								<th><?=currencyFormat($totals['dva'])?></th>
								<th><?=currencyFormat($totals['grand'])?></th>
								<th><?=$totals['tx_count']?></th>
							</tr>
						</tfoot>
						<?php endif; ?>
					</table>
				</div>
			</div>
		</section>
<?php endif; ?>
	</div>
</div>
