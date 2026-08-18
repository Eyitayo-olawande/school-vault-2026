<div class="row">
    <div class="col-md-12">
        <ul class="nav nav-tabs" style="margin-bottom:20px;">
            <li class="<?=$type==='affective'?'active':''?>"><a href="<?=base_url('domain/traits/affective')?>">Affective Domain Traits</a></li>
            <li class="<?=$type==='psychomotor'?'active':''?>"><a href="<?=base_url('domain/traits/psychomotor')?>">Psychomotor Domain Traits</a></li>
        </ul>
    </div>
</div>
<div class="row">
<?php if (get_permission('domain_traits', 'is_add')): ?>
    <div class="col-md-4">
        <section class="panel">
            <header class="panel-heading">
                <h4 class="panel-title"><i class="fas fa-plus-circle"></i> Add <?=ucfirst($type)?> Trait</h4>
            </header>
            <?=form_open("domain/traits/$type")?>
            <div class="panel-body">
                <div class="form-group mb-md">
                    <label class="control-label">Trait Name <span class="required">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Punctuality" required />
                </div>
                <div class="form-group mb-md">
                    <label class="control-label">Sort Order</label>
                    <input type="number" name="sort_order" class="form-control" value="<?=count($traitList)+1?>" min="0" />
                </div>
            </div>
            <div class="panel-footer">
                <button type="submit" class="btn btn-default pull-right"><i class="fas fa-save"></i> Save</button>
            </div>
            <?=form_close()?>
        </section>
    </div>
<?php endif; ?>
    <div class="col-md-<?=get_permission('domain_traits','is_add')?'8':'12'?>">
        <section class="panel">
            <header class="panel-heading">
                <h4 class="panel-title"><i class="fas fa-list"></i> <?=ucfirst($type)?> Traits</h4>
            </header>
            <div class="panel-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-condensed">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Trait Name</th>
                                <th>Sort</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if ($traitList): ?>
                            <?php foreach ($traitList as $i => $trait): ?>
                            <tr>
                                <td><?=$i+1?></td>
                                <td><?=htmlspecialchars($trait['name'])?></td>
                                <td><?=$trait['sort_order']?></td>
                                <td>
                                    <?php if (get_permission('domain_traits','is_edit')): ?>
                                    <a class="btn btn-default btn-circle icon" href="javascript:void(0);"
                                       onclick="editTrait(<?=$trait['id']?>, '<?=addslashes($trait['name'])?>', <?=$trait['sort_order']?>)">
                                        <i class="fas fa-pen-nib"></i>
                                    </a>
                                    <?php endif; ?>
                                    <?php if (get_permission('domain_traits','is_delete')): ?>
                                    <?=btn_delete("domain/trait_delete/$type/".$trait['id'])?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center text-muted">No traits configured yet.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</div>

<?php if (get_permission('domain_traits','is_edit')): ?>
<div class="zoom-anim-dialog modal-block modal-block-primary mfp-hide" id="editModal">
    <section class="panel">
        <?=form_open("domain/traits/$type", ['class'=>'frm-submit'])?>
        <header class="panel-heading"><h4 class="panel-title">Edit Trait</h4></header>
        <div class="panel-body">
            <input type="hidden" name="trait_id" id="edit_trait_id" />
            <div class="form-group mb-md">
                <label class="control-label">Trait Name</label>
                <input type="text" name="name" id="edit_name" class="form-control" required />
            </div>
            <div class="form-group mb-md">
                <label class="control-label">Sort Order</label>
                <input type="number" name="sort_order" id="edit_sort" class="form-control" min="0" />
            </div>
        </div>
        <footer class="panel-footer text-right">
            <button type="submit" class="btn btn-default">Update</button>
            <button type="button" class="btn btn-default modal-dismiss">Cancel</button>
        </footer>
        <?=form_close()?>
    </section>
</div>
<script>
function editTrait(id, name, sort) {
    $('#edit_trait_id').val(id);
    $('#edit_name').val(name);
    $('#edit_sort').val(sort);
    $.magnificPopup.open({ items: { src: '#editModal' }, type: 'inline' });
}
</script>
<?php endif; ?>
