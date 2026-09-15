<?php $widget = (is_superadmin_loggedin() ? 3 : 4); ?>
<div class="row">
	<div class="col-md-12">
		<section class="panel">
			<header class="panel-heading">
				<h4 class="panel-title"><i class="fas fa-file-circle-question"></i> <?=translate('question') . " " . translate('list')?></h4>
			<?php if(get_permission('question_bank', 'is_add')) { ?>
				<div class="panel-btn">
					<a href="<?=base_url('onlineexam/question_add')?>" class="btn btn-default btn-circle">
						<i class="fas fa-circle-question"></i> <?=translate('add') . " " . translate('question')?>
					</a>
					<a href="<?=base_url('onlineexam/question_import')?>" class="btn btn-default btn-circle">
						<i class="fas fa-plus"></i> <?=translate('question') . " " . translate('import')?>
					</a>
				</div>
			<?php } ?>
			</header>
			<div class="panel-body">
				<div class="row mb-sm">
					<div class="col-md-3">
						<div class="form-group mb-xs">
							<label class="control-label"><?=translate('term')?></label>
							<select id="filter_term" class="form-control input-sm">
								<option value="">— All Terms —</option>
								<option value="1st">1st Term</option>
								<option value="2nd">2nd Term</option>
								<option value="3rd">3rd Term</option>
							</select>
						</div>
					</div>
					<div class="col-md-3">
						<div class="form-group mb-xs">
							<label class="control-label">CA Type</label>
							<select id="filter_ca_type" class="form-control input-sm">
								<option value="">— All Types —</option>
								<option value="GENERAL">General</option>
								<option value="CA1">CA 1</option>
								<option value="CA2">CA 2</option>
								<option value="EXAM">Exam</option>
							</select>
						</div>
					</div>
					<div class="col-md-2" style="padding-top:5px;">
						<label class="control-label">&nbsp;</label><br>
						<button id="btn_filter_questions" class="btn btn-default btn-sm"><i class="fas fa-filter"></i> Filter</button>
					</div>
				</div>
				<table class="table table-bordered table-hover table-condensed table-question" cellpadding="0" cellspacing="0" width="100%">
					<thead>
						<tr>
							<th class="no-sort"><?=translate('sl')?></th>
<?php if (is_superadmin_loggedin()): ?>
							<th><?=translate('branch')?></th>
<?php endif; ?>
							<th><?=translate('question')?></th>
							<th><?=translate('group')?></th>
							<th><?=translate('class')?></th>
							<th><?=translate('subject')?></th>
							<th><?=translate('type')?></th>
							<th><?=translate('level')?></th>
							<th>Term</th>
							<th>CA Type</th>
							<th><?=translate('action')?></th>
						</tr>
					</thead>
				</table>
			</div>
		</section>
	</div>
</div>

<div class="zoom-anim-dialog modal-block modal-block-lg mfp-hide payroll-t-modal" id="modal">
	<section class="panel">
		<header class="panel-heading">
			<h4 class="panel-title"><i class="fas fa-file-circle-question"></i> <?php echo translate('question') . " " . translate('view'); ?></h4>
		</header>
		<div class="panel-body">
			<div id="quick_view"></div>
		</div>
		<footer class="panel-footer">
			<div class="row">
				<div class="col-md-12 text-right">
					<button class="btn btn-default modal-dismiss"><?php echo translate('close'); ?></button>
				</div>
			</div>
		</footer>
	</section>
</div>

<script type="text/javascript">
	$(document).ready(function() {
		var questionTable = $('.table-question').DataTable({
			"dom": '<"row"<"col-sm-6 mb-xs"B><"col-sm-6"f>><"table-responsive"tr>p',
			"lengthChange": false,
			"order": [],
			"pageLength": 20,
			"columnDefs": [
				{"orderable": false, "targets": 'no-sort'},
				{"orderable": false, "targets": [-1], "class": "action"}
			],
			"buttons": [
				{extend: 'copyHtml5',  text: '<i class="far fa-copy"></i>',     titleAttr: 'Copy',  exportOptions: {columns: ':visible'}},
				{extend: 'excelHtml5', text: '<i class="fa fa-file-excel"></i>', titleAttr: 'Excel', exportOptions: {columns: ':visible'}},
				{extend: 'csvHtml5',   text: '<i class="fa fa-file-alt"></i>',   titleAttr: 'CSV',   exportOptions: {columns: ':visible'}},
				{extend: 'pdfHtml5',   text: '<i class="fa fa-file-pdf"></i>',   titleAttr: 'PDF',   footer: true,
					customize: function(win) {
						win.styles.tableHeader.fontSize = 10;
						win.styles.tableFooter.fontSize = 10;
						win.styles.tableHeader.alignment = 'left';
					},
					exportOptions: {columns: ':visible'}
				},
				{extend: 'print', text: '<i class="fa fa-print"></i>', titleAttr: 'Print', footer: true,
					customize: function(win) {
						$(win.document.body).css('font-size', '9pt');
						$(win.document.body).find('table').addClass('compact').css('font-size', 'inherit');
						$(win.document.body).find('h1').css('font-size', '14pt');
					},
					exportOptions: {columns: ':visible'}
				},
				{extend: 'colvis', text: '<i class="fas fa-columns"></i>', titleAttr: 'Columns', postfixButtons: ['colvisRestore']}
			],
			'processing': true,
			'serverSide': true,
			'dataSrc': 'data',
			'serverMethod': 'POST',
			'ajax': {
				'url': base_url + 'onlineexam/getQuestionListDT',
				'data': function(d) {
					d.term    = $('#filter_term').val();
					d.ca_type = $('#filter_ca_type').val();
				}
			},
			"fnDrawCallback": function() {
				$('[data-toggle="tooltip"]').tooltip();
			}
		});

		$('#btn_filter_questions').on('click', function(e) {
			e.preventDefault();
			questionTable.ajax.reload();
		});
	});
</script>
