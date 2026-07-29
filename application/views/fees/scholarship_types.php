<div class="row">
	<div class="col-md-4">
		<section class="panel">
			<header class="panel-heading">
				<h4 class="panel-title"><i class="fas fa-graduation-cap"></i> Add Scholarship Type</h4>
			</header>
			<?php echo form_open('fees/scholarship_types', array('class' => 'form-horizontal frm-submit')); ?>
			<div class="panel-body">
				<div class="form-group">
					<label class="col-md-4 control-label">Name <span class="required">*</span></label>
					<div class="col-md-8">
						<input type="text" name="name" class="form-control" required placeholder="e.g. Proprietress Scholarship" value="<?=set_value('name')?>">
						<span class="error"></span>
					</div>
				</div>
				<div class="form-group">
					<label class="col-md-4 control-label">Description</label>
					<div class="col-md-8">
						<textarea name="description" rows="3" class="form-control" placeholder="Optional description"><?=set_value('description')?></textarea>
					</div>
				</div>
				<input type="hidden" name="action" value="save">
				<input type="hidden" name="branch_id" value="0">
			</div>
			<footer class="panel-footer">
				<div class="row">
					<div class="col-md-offset-4 col-md-8">
						<button type="submit" class="btn btn-default" data-loading-text="<i class='fas fa-spinner fa-spin'></i> Saving">
							<i class="fas fa-save"></i> Save Type
						</button>
					</div>
				</div>
			</footer>
			<?php echo form_close(); ?>
		</section>
	</div>
	<div class="col-md-8">
		<section class="panel">
			<header class="panel-heading">
				<h4 class="panel-title"><i class="fas fa-list"></i> Scholarship Types</h4>
			</header>
			<div class="panel-body">
				<table class="table table-bordered table-hover">
					<thead>
						<tr>
							<th>#</th>
							<th>Name</th>
							<th>Description</th>
							<th>Action</th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ($types as $i => $t): ?>
						<tr>
							<td><?=$i + 1?></td>
							<td><?=htmlspecialchars($t['name'])?></td>
							<td><?=htmlspecialchars($t['description'])?></td>
							<td>
								<a class="btn btn-danger btn-circle icon"
									onclick="confirm_modal('<?=base_url('fees/scholarship_type_delete/' . $t['id'])?>')">
									<i class="fas fa-trash-alt"></i>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
					<?php if (empty($types)): ?>
						<tr><td colspan="4" class="text-center text-muted">No scholarship types defined yet.</td></tr>
					<?php endif; ?>
					</tbody>
				</table>
			</div>
		</section>
	</div>
</div>
