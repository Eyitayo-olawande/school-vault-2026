<div class="row">
<?php if (get_permission('exam_term', 'is_add')): ?>
	<div class="col-md-5">
		<section class="panel">
			<header class="panel-heading">
				<h4 class="panel-title"><i class="far fa-edit"></i> <?=translate('add') . " " . translate('exam_term')?></h4>
			</header>
			<?php echo form_open($this->uri->uri_string());?>
				<div class="panel-body">
					<?php if (is_superadmin_loggedin()): ?>
					<div class="form-group">
						<label class="control-label"><?=translate('branch')?> <span class="required">*</span></label>
						<?php
							$arrayBranch = $this->app_lib->getSelectList('branch');
							echo form_dropdown("branch_id", $arrayBranch, set_value('branch_id'), "class='form-control' id='branch_id' data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity'");
						?>
						<span class="error"><?=form_error('branch_id')?></span>
					</div>
					<?php endif; ?>
					<div class="form-group mb-sm">
						<label class="control-label"><?=translate('name')?> <span class="required">*</span></label>
						<input type="text" class="form-control" name="term_name" value="<?=set_value('term_name')?>" />
						<span class="error"><?=form_error('term_name')?></span>
					</div>
					<div class="form-group">
						<label class="control-label">Term Start Date</label>
						<input type="date" class="form-control" name="term_start_date" value="<?=set_value('term_start_date')?>" />
					</div>
					<div class="form-group">
						<label class="control-label">Term End Date</label>
						<input type="date" class="form-control" name="term_end_date" value="<?=set_value('term_end_date')?>" />
					</div>
					<div class="form-group">
						<label class="control-label">Resumption Date (Next Term)</label>
						<input type="date" class="form-control" name="resumption_date" value="<?=set_value('resumption_date')?>" />
					</div>
					<div class="form-group mb-md">
						<label class="control-label">Next Term Information</label>
						<textarea class="form-control" name="next_term_info" rows="2" placeholder="e.g. Second Term begins 13 Jan 2025"><?=set_value('next_term_info')?></textarea>
					</div>
				</div>
				<div class="panel-footer">
					<div class="row">
						<div class="col-md-12">
							<button class="btn btn-default pull-right" type="submit" name="save" value="1">
								<i class="fas fa-plus-circle"></i> <?=translate('save')?>
							</button>
						</div>
					</div>
				</div>
			<?php echo form_close();?>
		</section>
	</div>
<?php endif; ?>
<?php if (get_permission('exam_term', 'is_view')): ?>
	<div class="col-md-<?php if (get_permission('exam_term', 'is_add')){ echo "7"; }else{ echo "12"; } ?>">
		<section class="panel">
			<header class="panel-heading">
				<h4 class="panel-title"><i class="fas fa-list-ul"></i>  <?=translate('exam_term') . " " . translate('list')?></h4>
			</header>
			<div class="panel-body">
				<div class="table-responsive">
					<table class="table table-bordered table-hover table-condensed mb-none">
						<thead>
							<tr>
								<th><?=translate('sl')?></th>
								<th><?=translate('branch')?></th>
								<th><?=translate('name')?></th>
								<th>Start Date</th>
								<th>End Date</th>
								<th>Resumption</th>
								<th><?=translate('action')?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							$count = 1;
							if (count($termlist)){
								foreach ($termlist as $row):
							?>
							<tr>
								<td><?php echo $count++;?></td>
								<td><?php echo $row['branch_name']; ?></td>
								<td><?php echo $row['name']; ?></td>
								<td><?php echo !empty($row['term_start_date']) ? _d($row['term_start_date']) : '—'; ?></td>
								<td><?php echo !empty($row['term_end_date'])   ? _d($row['term_end_date'])   : '—'; ?></td>
								<td><?php echo !empty($row['resumption_date']) ? _d($row['resumption_date']) : '—'; ?></td>
								<td>
								<?php if (get_permission('exam_term', 'is_edit')): ?>
									<a class="btn btn-default btn-circle icon" href="javascript:void(0);" onclick="getCategoryModal(this)"
										data-id="<?=$row['id']?>"
										data-name="<?=html_escape($row['name'])?>"
										data-branch="<?=$row['branch_id']?>"
										data-start="<?=html_escape($row['term_start_date']??'')?>"
										data-end="<?=html_escape($row['term_end_date']??'')?>"
										data-resumption="<?=html_escape($row['resumption_date']??'')?>"
										data-nextterm="<?=html_escape($row['next_term_info']??'')?>">
										<i class="fas fa-pen-nib"></i>
									</a>
								<?php endif; if (get_permission('exam_term', 'is_delete')): ?>
									<?php echo btn_delete('exam/term_delete/' . $row['id']);?>
								<?php endif; ?>
								</td>
							</tr>
							<?php
								endforeach;
							}else{
								echo '<tr><td colspan="7"><h5 class="text-danger text-center">' . translate('no_information_available') . '</td></tr>';
							}
							?>
						</tbody>
					</table>
				</div>
			</div>
		</section>
	</div>
</div>
<?php endif; ?>
<?php if (get_permission('exam_term', 'is_edit')): ?>
<div class="zoom-anim-dialog modal-block modal-block-primary mfp-hide" id="modal">
	<section class="panel">
		<?php echo form_open('exam/term_edit', array('class' => 'frm-submit')); ?>
			<header class="panel-heading">
				<h4 class="panel-title"><i class="far fa-edit"></i> <?=translate('edit') . " " . translate('exam_term')?></h4>
			</header>
			<div class="panel-body">
				<input type="hidden" name="term_id" id="ecategory_id" value="" />
				<?php if (is_superadmin_loggedin()): ?>
				<div class="form-group">
					<label class="control-label"><?=translate('branch')?> <span class="required">*</span></label>
					<?php
						$arrayBranch = $this->app_lib->getSelectList('branch');
						echo form_dropdown("branch_id", $arrayBranch, set_value('branch_id'), "class='form-control' id='ebranch_id' data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity'");
					?>
					<span class="error"></span>
				</div>
				<?php endif; ?>
				<div class="form-group mb-sm">
					<label class="control-label"><?=translate('name')?> <span class="required">*</span></label>
					<input type="text" class="form-control" name="term_name" id="ename" value="" />
					<span class="error"></span>
				</div>
				<div class="form-group">
					<label class="control-label">Term Start Date</label>
					<input type="date" class="form-control" name="term_start_date" id="eterm_start" value="" />
				</div>
				<div class="form-group">
					<label class="control-label">Term End Date</label>
					<input type="date" class="form-control" name="term_end_date" id="eterm_end" value="" />
				</div>
				<div class="form-group">
					<label class="control-label">Resumption Date (Next Term)</label>
					<input type="date" class="form-control" name="resumption_date" id="eresumption" value="" />
				</div>
				<div class="form-group mb-md">
					<label class="control-label">Next Term Information</label>
					<textarea class="form-control" name="next_term_info" id="enextterm" rows="2"></textarea>
				</div>
			</div>
			<footer class="panel-footer">
				<div class="row">
					<div class="col-md-12 text-right">
						<button type="submit" class="btn btn-default" data-loading-text="<i class='fas fa-spinner fa-spin'></i> Processing">
							<i class="fas fa-plus-circle"></i> <?=translate('update')?>
						</button>
						<button class="btn btn-default modal-dismiss"><?=translate('cancel')?></button>
					</div>
				</div>
			</footer>
		<?php echo form_close();?>
	</section>
</div>
<script>
function getCategoryModal(el) {
	var $el = $(el);
	$('#ecategory_id').val($el.data('id'));
	$('#ename').val($el.data('name'));
	$('#eterm_start').val($el.data('start') || '');
	$('#eterm_end').val($el.data('end') || '');
	$('#eresumption').val($el.data('resumption') || '');
	$('#enextterm').val($el.data('nextterm') || '');
	<?php if (is_superadmin_loggedin()): ?>
	$('#ebranch_id').val($el.data('branch')).trigger('change');
	<?php endif; ?>
	$.magnificPopup.open({ items: { src: '#modal', type: 'inline' } });
}
</script>
<?php endif; ?>
