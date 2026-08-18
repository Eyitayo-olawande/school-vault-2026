<style>
.rating-select { width: 90px; }
.domain-table th, .domain-table td { white-space: nowrap; vertical-align: middle; }
</style>

<div class="row">
    <div class="col-md-12">
        <ul class="nav nav-tabs" style="margin-bottom:20px;">
            <li class="<?=$type==='affective'?'active':''?>"><a href="<?=base_url('domain/entry/affective')?>">Affective Domain</a></li>
            <li class="<?=$type==='psychomotor'?'active':''?>"><a href="<?=base_url('domain/entry/psychomotor')?>">Psychomotor Domain</a></li>
        </ul>
    </div>
</div>

<section class="panel">
    <header class="panel-heading"><h4 class="panel-title">Filter</h4></header>
    <?=form_open("domain/entry/$type", ['method'=>'get'])?>
    <div class="panel-body">
        <div class="row">
            <?php if (is_superadmin_loggedin()): ?>
            <div class="col-md-3 mb-sm">
                <label class="control-label">Branch</label>
                <?php
                $ab = $this->app_lib->getSelectList('branch');
                echo form_dropdown('branch_id', $ab, $branchID, "class='form-control' id='branch_id' data-plugin-selectTwo data-width='100%'");
                ?>
            </div>
            <?php endif; ?>
            <div class="col-md-3 mb-sm">
                <label class="control-label">Class <span class="required">*</span></label>
                <?php
                $ac = $this->app_lib->getClass($branchID);
                echo form_dropdown('class_id', $ac, $classID, "class='form-control' id='class_id' onchange='getSectionByClass(this.value,0)' data-plugin-selectTwo data-width='100%'");
                ?>
            </div>
            <div class="col-md-3 mb-sm">
                <label class="control-label">Section <span class="required">*</span></label>
                <?php
                $as = $this->app_lib->getSections($classID);
                echo form_dropdown('section_id', $as, $sectionID, "class='form-control' id='section_id' data-plugin-selectTwo data-width='100%'");
                ?>
            </div>
            <div class="col-md-3 mb-sm">
                <label class="control-label">Exam <span class="required">*</span></label>
                <?php
                $ae = ['' => 'Select'];
                foreach ($exams as $ex) $ae[$ex['id']] = $ex['name'];
                echo form_dropdown('exam_id', $ae, $examID, "class='form-control' data-plugin-selectTwo data-width='100%'");
                ?>
            </div>
        </div>
    </div>
    <div class="panel-footer">
        <button type="submit" class="btn btn-default pull-right"><i class="fas fa-filter"></i> Filter</button>
    </div>
    <?=form_close()?>
</section>

<?php if (!empty($students) && !empty($traits) && !empty($examID)): ?>
<section class="panel">
    <header class="panel-heading">
        <h4 class="panel-title"><?=ucfirst($type)?> Domain Ratings</h4>
    </header>
    <?=form_open("domain/entry/$type")?>
    <input type="hidden" name="class_id"   value="<?=$classID?>">
    <input type="hidden" name="section_id" value="<?=$sectionID?>">
    <input type="hidden" name="exam_id"    value="<?=$examID?>">
    <div class="panel-body">
        <div class="table-responsive">
            <table class="table table-bordered table-condensed domain-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student</th>
                        <th>Reg No.</th>
                        <?php foreach ($traits as $trait): ?>
                        <th><?=htmlspecialchars($trait['name'])?></th>
                        <?php endforeach; ?>
                    </tr>
                    <tr class="text-muted" style="font-size:11px;">
                        <th></th><th></th><th></th>
                        <?php foreach ($traits as $trait): ?>
                        <th>1=VPoor &rarr; 5=Excel</th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($students as $i => $s): ?>
                    <tr>
                        <td><?=$i+1?></td>
                        <td><?=htmlspecialchars($s['fullname'])?></td>
                        <td><?=htmlspecialchars($s['register_no'])?></td>
                        <?php foreach ($traits as $trait):
                            $val = $ratingMap[$s['enroll_id']][$trait['id']] ?? 0;
                        ?>
                        <td>
                            <select name="ratings[<?=$s['enroll_id']?>][<?=$trait['id']?>]" class="form-control rating-select">
                                <option value="0">—</option>
                                <?php for ($r=1; $r<=5; $r++): ?>
                                <option value="<?=$r?>" <?=$val==$r?'selected':''?>><?=$r?></option>
                                <?php endfor; ?>
                            </select>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="panel-footer">
        <?php if (get_permission('domain_entry','is_add')): ?>
        <button type="submit" class="btn btn-default pull-right"><i class="fas fa-save"></i> Save Ratings</button>
        <?php endif; ?>
    </div>
    <?=form_close()?>
</section>
<?php elseif (!empty($classID) && !empty($sectionID) && empty($traits)): ?>
<div class="alert alert-warning">
    No <?=$type?> traits configured for this branch yet.
    <a href="<?=base_url("domain/traits/$type")?>">Set up traits first &rarr;</a>
</div>
<?php endif; ?>
