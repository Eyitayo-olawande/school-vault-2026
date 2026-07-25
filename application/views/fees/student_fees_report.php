<?php
$widget = (is_superadmin_loggedin() ? 2 : 3);
$selectedSessID = (int)set_value('session_id', $active_session);
$selectedTerm   = set_value('term', '');
?>
<div class="row">
	<div class="col-md-12">
		<section class="panel">
			<header class="panel-heading">
				<h4 class="panel-title"><?=translate('select_ground')?></h4>
			</header>
			<?php echo form_open($this->uri->uri_string(), array('class' => 'validate'));?>
			<div class="panel-body">
				<div class="row">
				<?php if (is_superadmin_loggedin()): ?>
					<div class="col-md-2 mb-sm">
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
					<div class="col-md-<?=$widget?> mb-sm">
						<div class="form-group">
							<label class="control-label"><?=translate('class')?> <small class="text-muted">(<?=translate('optional')?>)</small></label>
							<?php
								$arrayClass = $this->app_lib->getClass($branch_id);
								echo form_dropdown("class_id", $arrayClass, set_value('class_id'), "class='form-control' id='class_id' onchange='getSectionByClass(this.value,0)'
								data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity'");
							?>
						</div>
					</div>
					<div class="col-md-<?=$widget?> mb-sm">
						<div class="form-group">
							<label class="control-label"><?=translate('section')?></label>
							<?php
								$arraySection = $this->app_lib->getSections(set_value('class_id'), false);
								echo form_dropdown("section_id", $arraySection, set_value('section_id'), "class='form-control' id='section_id'
								data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity'");
							?>
						</div>
					</div>
					<div class="col-md-<?=$widget?> mb-sm">
						<div class="form-group">
							<label class="control-label">Academic Session <span class="required">*</span></label>
							<select name="session_id" class="form-control" required
								data-plugin-selectTwo data-width="100%" data-minimum-results-for-search="Infinity">
								<?php foreach ($session_list as $sid => $label): ?>
									<option value="<?=$sid?>" <?=($sid == $selectedSessID) ? 'selected' : ''?>><?=htmlspecialchars($label)?></option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>
					<div class="col-md-<?=$widget?> mb-sm">
						<div class="form-group">
							<label class="control-label">Academic Term</label>
							<select name="term" class="form-control"
								data-plugin-selectTwo data-width="100%" data-minimum-results-for-search="Infinity">
								<option value="" <?=empty($selectedTerm) ? 'selected' : ''?>>All Terms</option>
								<option value="1ST TERM" <?=($selectedTerm === '1ST TERM') ? 'selected' : ''?>>1st Term</option>
								<option value="2ND TERM" <?=($selectedTerm === '2ND TERM') ? 'selected' : ''?>>2nd Term</option>
								<option value="3RD TERM" <?=($selectedTerm === '3RD TERM') ? 'selected' : ''?>>3rd Term</option>
							</select>
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
			<?php echo form_close();?>
		</section>

<?php if (isset($invoicelist)): ?>
		<section class="panel appear-animation" data-appear-animation="<?=$global_config['animations']?>" data-appear-animation-delay="100">
			<header class="panel-heading">
				<h4 class="panel-title"><i class="fas fa-list-ol"></i> <?=translate('student_fees_reports')?></h4>
			</header>
			<?php if (isset($summary)): ?>
			<div class="panel-body pb-none">
				<div class="row mb-md">
					<div class="col-md-2 col-sm-6">
						<div class="widget-summary widget-summary-sm">
							<div class="widget-summary-col widget-summary-col-icon">
								<div class="summary-icon bg-primary"><i class="fas fa-file-invoice-dollar"></i></div>
							</div>
							<div class="widget-summary-col">
								<div class="summary">
									<h4 class="title"><?=translate('total_fees')?></h4>
									<div class="info"><strong class="amount"><?=currencyFormat($summary['expected'])?></strong></div>
								</div>
							</div>
						</div>
					</div>
					<div class="col-md-2 col-sm-6">
						<div class="widget-summary widget-summary-sm">
							<div class="widget-summary-col widget-summary-col-icon">
								<div class="summary-icon bg-success"><i class="fas fa-check-circle"></i></div>
							</div>
							<div class="widget-summary-col">
								<div class="summary">
									<h4 class="title"><?=translate('collected')?></h4>
									<div class="info"><strong class="amount"><?=currencyFormat($summary['collected'])?></strong></div>
								</div>
							</div>
						</div>
					</div>
					<div class="col-md-2 col-sm-6">
						<div class="widget-summary widget-summary-sm">
							<div class="widget-summary-col widget-summary-col-icon">
								<div class="summary-icon bg-danger"><i class="fas fa-exclamation-circle"></i></div>
							</div>
							<div class="widget-summary-col">
								<div class="summary">
									<h4 class="title"><?=translate('outstanding')?></h4>
									<div class="info"><strong class="amount"><?=currencyFormat($summary['outstanding'])?></strong></div>
								</div>
							</div>
						</div>
					</div>
					<div class="col-md-2 col-sm-6">
						<div class="widget-summary widget-summary-sm">
							<div class="widget-summary-col widget-summary-col-icon">
								<div class="summary-icon bg-warning"><i class="fas fa-percentage"></i></div>
							</div>
							<div class="widget-summary-col">
								<div class="summary">
									<h4 class="title"><?=translate('collection_rate')?></h4>
									<div class="info"><strong class="amount"><?=number_format($summary['collection_rate'], 1)?>%</strong></div>
								</div>
							</div>
						</div>
					</div>
					<div class="col-md-4 col-sm-6">
						<div class="widget-summary widget-summary-sm">
							<div class="widget-summary-col widget-summary-col-icon">
								<div class="summary-icon bg-info"><i class="fas fa-users"></i></div>
							</div>
							<div class="widget-summary-col">
								<div class="summary">
									<h4 class="title">Students</h4>
									<div class="info">
										<strong class="amount text-success"><?=$summary['count_paid']?> Paid</strong>
										&nbsp;|&nbsp;
										<strong class="amount text-warning"><?=$summary['count_partial']?> Partial</strong>
										&nbsp;|&nbsp;
										<strong class="amount text-danger"><?=$summary['count_owing']?> Owing</strong>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
			<?php endif; ?>
			<div class="panel-body">
				<div class="mb-md mt-md">
					<div class="export_title"><?=translate('student_fees_reports')?></div>
					<table class="table table-bordered table-condensed mb-none tbr-top" id="sfr-table">
						<thead>
							<tr>
								<th><?=translate('student')?></th>
								<th><?=translate('register_no')?></th>
								<th><?=translate('roll')?></th>
								<th><?=translate('section')?></th>
								<th><?=translate('total_fees')?></th>
								<th>Net Paid</th>
								<th><?=translate('outstanding')?></th>
								<th>Status</th>
								<th><?=translate('action')?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($invoicelist as $row):
								if ($row['status'] === 'paid') {
									$badge = '<span class="label label-success-custom">Paid</span>';
								} elseif ($row['status'] === 'partial') {
									$badge = '<span class="label label-warning-custom">Partial</span>';
								} else {
									$badge = '<span class="label label-danger-custom">Owing</span>';
								}
							?>
							<tr>
								<td><?=htmlspecialchars($row['first_name'] . ' ' . $row['last_name'])?></td>
								<td><?=htmlspecialchars($row['register_no'])?></td>
								<td><?=htmlspecialchars($row['roll'])?></td>
								<td><?=htmlspecialchars($row['section_name'])?></td>
								<td><?=currencyFormat($row['expected'])?></td>
								<td><?=currencyFormat($row['net_paid'])?></td>
								<td><?=currencyFormat($row['balance'])?></td>
								<td><?=$badge?></td>
								<td>
									<a href="<?=base_url('fees/student_ledger/' . $row['student_id'])?>"
										class="btn btn-circle btn-default btn-xs" target="_blank"
										data-toggle="tooltip" data-original-title="View Ledger">
										<i class="fas fa-book-open"></i>
									</a>
								</td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
		</section>
<?php endif; ?>
	</div>
</div>

<script type="text/javascript">
	$(document).ready(function () {
		$('#sfr-table').DataTable({
			dom: '<"row"<"col-sm-6 mb-xs"B><"col-sm-6"f>><"table-responsive"t>p',
			autoWidth: false,
			pageLength: 50,
			order: [[3, 'asc'], [2, 'asc']],
			"buttons": [
				{ extend: 'copyHtml5',  text: '<i class="far fa-copy"></i>',    titleAttr: 'Copy',  title: $('.export_title').html(), exportOptions: { columns: ':not(:last-child)' } },
				{ extend: 'excelHtml5', text: '<i class="fa fa-file-excel"></i>', titleAttr: 'Excel', title: $('.export_title').html(), exportOptions: { columns: ':not(:last-child)' } },
				{ extend: 'csvHtml5',   text: '<i class="fa fa-file-alt"></i>',  titleAttr: 'CSV',   title: $('.export_title').html(), exportOptions: { columns: ':not(:last-child)' } },
				{ extend: 'pdfHtml5',   text: '<i class="fa fa-file-pdf"></i>',  titleAttr: 'PDF',   title: $('.export_title').html(), footer: true, exportOptions: { columns: ':not(:last-child)' } },
				{ extend: 'print',      text: '<i class="fa fa-print"></i>',     titleAttr: 'Print', title: $('.export_title').html(), footer: true, exportOptions: { columns: ':not(:last-child)' } },
				{ extend: 'colvis',     text: '<i class="fas fa-columns"></i>',  titleAttr: 'Columns', postfixButtons: ['colvisRestore'] }
			]
		});
	});
</script>
