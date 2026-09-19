<div class="row">
	<div class="col-md-12">
		<section class="panel">
		<?php echo form_open_multipart('onlineexam/questionCsvImport', array( 'class' => 'form-horizontal form-bordered frm-submit-data'));?>	
			<header class="panel-heading">
				<h4 class="panel-title">
					<i class="fas fa-file-archive"></i> <?=translate('import')?>
				</h4>
				<div class="panel-btn">
					<a href="<?=base_url('onlineexam/question')?>" class="btn btn-default btn-circle">
						<i class="fas fa-list"></i> <?=translate('question') . " " . translate('list')?>
					</a>
				</div>
			</header>
			<div class="panel-body">
			<?php if ($this->session->flashdata('csvimport')): ?>
				<div class="alert-danger p-sm"><?php echo $this->session->flashdata('csvimport'); ?></div>
			<?php endif; ?>
				<div class="form-group mt-md">
					<div class="col-md-12 mb-md">
						<a class="btn btn-default pull-right" href="<?=base_url('onlineexam/csv_Sampledownloader')?>">
							<i class='fas fa-file-download'></i> Download Sample Import File
						</a>
					</div>
					<div class="col-md-12">
						<div class="alert alert-subl">
							<strong>Instructions :</strong><br/>
							1. Download the first sample file.<br/>
							2. Open the downloaded 'csv' file and carefully fill the details of the question. <br/>
							3. Question Type should be used from within these Example : <strong class="text-dark">single_choice, multi_choice, true_false, descriptive</strong><br/>
							4. Question Level should be used from within these Example : <strong class="text-dark">easy, medium, hard</strong><br/>
							5. The Question Group comes from another table, so for the "Group Name", enter Group ID (can be found on the <strong class="text-dark">Question Group</strong> page). <br/>
							6. For Single Choice type questions for answer should be <strong class="text-dark">option_1</strong><br/>
							7. For Multi Choice type questions for answer should be <strong class="text-dark">["option_1","option_2"]</strong>
						</div>
					</div>
				</div>
			<?php if (is_superadmin_loggedin()): ?>
				<div class="form-group">
					<label class="control-label col-md-3"><?php echo translate('branch');?> <span class="required">*</span></label>
					<div class="col-md-6">
						<?php
							$arrayBranch = $this->app_lib->getSelectList('branch');
							echo form_dropdown("branch_id", $arrayBranch, set_value('branch_id'), "class='form-control' onchange='getClassByBranch(this.value)'
							data-plugin-selectTwo data-width='100%'");
						?>
						<span class="error"><?=form_error('branch_id')?></span>
					</div>
				</div>
			<?php endif; ?>
				<div class="form-group">
					<label class="control-label col-md-3"><?=translate('class')?> <span class="required">*</span></label>
					<div class="col-md-6">
						<?php
							$arrayClass = $this->app_lib->getClass($branch_id);
							echo form_dropdown("class_id", $arrayClass, set_value('class_id'), "class='form-control' id='class_id' onchange='getSectionByClass(this.value,0)'
							data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity' ");
						?>
						<span class="error"></span>
					</div>
				</div>
				<div class="form-group">
					<label class="control-label col-md-3"><?=translate('section')?> <span class="required">*</span></label>
					<div class="col-md-6">
						<?php
							$arraySection = $this->app_lib->getSections(set_value('class_id'));
							echo form_dropdown("section_id", $arraySection, set_value('section_id'), "class='form-control' id='section_id' 
							data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity' ");
						?>
						<span class="error"></span>
					</div>
				</div>
				<div class="form-group">
					<label class="control-label col-md-3"><?=translate('subject')?> <span class="required">*</span></label>
					<div class="col-md-6">
						<?php
							$arraySubject = array("" => translate('select_class_first'));
							echo form_dropdown("subject_id", $arraySubject, set_value('subject_id'), "class='form-control' id='subject_id'
							data-plugin-selectTwo data-width='100%' ");
						?>
						<span class="error"></span>
					</div>
				</div>
				<div class="form-group">
					<label class="control-label col-md-3">CSV File <span class="required">*</span></label>
					<div class="col-md-6 mb-lg">
						<input type="file" name="userfile" class="dropify" data-height="140" data-allowed-file-extensions="csv" />
						<span class="error"></span>
					</div>
				</div>
			</div>
			<footer class="panel-footer">
				<div class="row">
					<div class="col-md-offset-3 col-md-2">
						<button type="submit" class="btn btn btn-default btn-block" data-loading-text="<i class='fas fa-spinner fa-spin'></i> Processing">
							<i class="fas fa-plus-circle"></i> <?=translate('import')?>
						</button>
					</div>
				</div>
			</footer>
			<?php echo form_close();?>
		</section>
	</div>
</div>

<script type="text/javascript">
	$('#section_id').on('change', function() {
		var classID = $('#class_id').val();
		var sectionID =$(this).val();
		$.ajax({
			url: base_url + 'subject/getByClassSection',
			type: 'POST',
			data: {
				classID: classID,
				sectionID: sectionID
			},
			success: function (data) {
				$('#subject_id').html(data);
			}
		});
	});
</script>

<!-- DOCX Import Panel -->
<div class="row mt-lg">
	<div class="col-md-12">
		<section class="panel">
		<?php echo form_open_multipart('onlineexam/questionDocxImport', array('id' => 'docxImportForm', 'class' => 'form-horizontal form-bordered'));?>
			<header class="panel-heading">
				<h4 class="panel-title">
					<i class="fas fa-file-word"></i> Import Questions from Word (.docx)
				</h4>
			</header>
			<div class="panel-body">
				<div id="docx-alert-area"></div>
				<div class="form-group mt-md">
					<div class="col-md-12">
						<div class="alert alert-subl">
							<strong>DOCX Format Instructions:</strong><br/>
							Each question block is separated by <code>---</code> on its own line. Fields per block:<br/>
							<code>TYPE:</code> single_choice | multi_choice | true_false | descriptive<br/>
							<code>LEVEL:</code> easy | medium | hard<br/>
							<code>MARKS:</code> numeric (e.g. 2)<br/>
							<code>TERM:</code> 1ST | 2ND | 3RD <em>(optional)</em><br/>
							<code>CA_TYPE:</code> CA1 | CA2 | EXAM | GENERAL <em>(optional, default: GENERAL)</em><br/>
							<code>GROUP:</code> Group ID number <em>(optional)</em><br/>
							<code>Q:</code> Question text<br/>
							<code>A: B: C: D:</code> Options (for single/multi choice)<br/>
							<code>ANS:</code> B (single) | A,C (multi) | TRUE/FALSE | answer text (descriptive)
						</div>
					</div>
				</div>
			<?php if (is_superadmin_loggedin()): ?>
				<div class="form-group">
					<label class="control-label col-md-3"><?php echo translate('branch');?> <span class="required">*</span></label>
					<div class="col-md-6">
						<?php
							$arrayBranch = $this->app_lib->getSelectList('branch');
							echo form_dropdown("docx_branch_id", $arrayBranch, set_value('docx_branch_id'), "class='form-control' onchange='getDocxClassByBranch(this.value)' data-plugin-selectTwo data-width='100%'");
						?>
					</div>
				</div>
			<?php endif; ?>
				<div class="form-group">
					<label class="control-label col-md-3"><?=translate('class')?> <span class="required">*</span></label>
					<div class="col-md-6">
						<?php
							$arrayClass2 = $this->app_lib->getClass($branch_id);
							echo form_dropdown("class_id", $arrayClass2, '', "class='form-control' id='docx_class_id' onchange='getDocxSectionByClass(this.value)' data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity'");
						?>
						<span class="error" id="err_docx_class"></span>
					</div>
				</div>
				<div class="form-group">
					<label class="control-label col-md-3"><?=translate('section')?> <span class="required">*</span></label>
					<div class="col-md-6">
						<?php
							echo form_dropdown("section_id", array('' => translate('select')), '', "class='form-control' id='docx_section_id' data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity'");
						?>
						<span class="error" id="err_docx_section"></span>
					</div>
				</div>
				<div class="form-group">
					<label class="control-label col-md-3"><?=translate('subject')?> <span class="required">*</span></label>
					<div class="col-md-6">
						<?php
							echo form_dropdown("subject_id", array('' => translate('select_class_first')), '', "class='form-control' id='docx_subject_id' data-plugin-selectTwo data-width='100%'");
						?>
						<span class="error" id="err_docx_subject"></span>
					</div>
				</div>
				<div class="form-group">
					<label class="control-label col-md-3">Word File (.docx) <span class="required">*</span></label>
					<div class="col-md-6 mb-lg">
						<input type="file" name="docxfile" id="docxfile" class="dropify" data-height="140" data-allowed-file-extensions="docx" />
						<span class="error" id="err_docxfile"></span>
					</div>
				</div>
			</div>
			<footer class="panel-footer">
				<div class="row">
					<div class="col-md-offset-3 col-md-2">
						<button type="button" onclick="submitDocxImport()" class="btn btn-default btn-block" id="btnDocxImport">
							<i class="fas fa-plus-circle"></i> Import
						</button>
					</div>
				</div>
			</footer>
			<?php echo form_close();?>
		</section>
	</div>
</div>

<script type="text/javascript">
function getDocxSectionByClass(classID) {
	if (!classID) return;
	$.ajax({
		url: base_url + 'ajax/getSectionByClass',
		type: 'POST',
		data: { class_id: classID, all: 0, multi: 0 },
		success: function(response) {
			$('#docx_section_id').html(response).trigger('change');
			// try to init select2 if already destroyed
			if ($.fn.select2) {
				$('#docx_section_id').select2({ width: '100%' });
			}
		}
	});
	$('#docx_subject_id').html('<option value=""><?=translate('select')?></option>');
}

$('#docx_section_id').on('change', function() {
	var classID   = $('#docx_class_id').val();
	var sectionID = $(this).val();
	if (!classID || !sectionID) return;
	$.ajax({
		url: base_url + 'subject/getByClassSection',
		type: 'POST',
		data: { classID: classID, sectionID: sectionID },
		success: function(data) {
			$('#docx_subject_id').html(data);
			if ($.fn.select2) {
				$('#docx_subject_id').select2({ width: '100%' });
			}
		}
	});
});

function submitDocxImport() {
	$('#docx-alert-area').html('');
	var formData = new FormData($('#docxImportForm')[0]);
	formData.append('class_id',   $('#docx_class_id').val());
	formData.append('section_id', $('#docx_section_id').val());
	formData.append('subject_id', $('#docx_subject_id').val());

	$('#btnDocxImport').prop('disabled', true).html("<i class='fas fa-spinner fa-spin'></i> Processing");
	$.ajax({
		url: base_url + 'onlineexam/questionDocxImport',
		type: 'POST',
		data: formData,
		processData: false,
		contentType: false,
		success: function(res) {
			$('#btnDocxImport').prop('disabled', false).html("<i class='fas fa-plus-circle'></i> Import");
			try { res = JSON.parse(res); } catch(e) {}
			if (res && res.status === 'success') {
				window.location.href = res.url;
			} else if (res && res.error) {
				var msgs = '';
				$.each(res.error, function(k, v) { msgs += '<p class="mb-0">' + v + '</p>'; });
				$('#docx-alert-area').html('<div class="alert alert-danger">' + msgs + '</div>');
			} else {
				$('#docx-alert-area').html('<div class="alert alert-danger">An unexpected error occurred.</div>');
			}
		},
		error: function() {
			$('#btnDocxImport').prop('disabled', false).html("<i class='fas fa-plus-circle'></i> Import");
			$('#docx-alert-area').html('<div class="alert alert-danger">Request failed. Please try again.</div>');
		}
	});
}
</script>