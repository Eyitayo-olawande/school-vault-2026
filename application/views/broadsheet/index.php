<style>
.bs-table { font-size:11.5px; }
.bs-table th, .bs-table td { white-space: nowrap; vertical-align: middle; }
.bs-table thead th { background:#f5f5f5; }
.bs-sub-header { font-size:10px; color:#888; text-align:center; }
@media print {
    .panel-footer, .filter-panel, .nav-section { display:none !important; }
    .bs-table { font-size:9pt; }
}
</style>

<section class="panel filter-panel">
    <header class="panel-heading"><h4 class="panel-title">Filter</h4></header>
    <?=form_open('broadsheet', ['method'=>'get'])?>
    <div class="panel-body">
        <div class="row">
            <?php if (is_superadmin_loggedin()): ?>
            <div class="col-md-2 mb-sm">
                <label class="control-label">Branch</label>
                <?php echo form_dropdown('branch_id', $this->app_lib->getSelectList('branch'), $branchID, "class='form-control' id='branch_id' data-plugin-selectTwo data-width='100%'"); ?>
            </div>
            <?php endif; ?>
            <div class="col-md-2 mb-sm">
                <label class="control-label">Session</label>
                <?php
                $syears = ['' => 'Select'];
                foreach ($this->db->get('schoolyear')->result() as $y) $syears[$y->id] = $y->school_year;
                echo form_dropdown('session_id', $syears, $sessionID, "class='form-control' data-plugin-selectTwo data-width='100%'");
                ?>
            </div>
            <div class="col-md-3 mb-sm">
                <label class="control-label">Class <span class="required">*</span></label>
                <?=form_dropdown('class_id', $this->app_lib->getClass($branchID), $classID, "class='form-control' id='class_id' onchange='getSectionByClass(this.value,0)' data-plugin-selectTwo data-width='100%'")?>
            </div>
            <div class="col-md-3 mb-sm">
                <label class="control-label">Section <span class="required">*</span></label>
                <?=form_dropdown('section_id', $this->app_lib->getSections($classID), $sectionID, "class='form-control' id='section_id' data-plugin-selectTwo data-width='100%'")?>
            </div>
            <div class="col-md-2 mb-sm" style="padding-top:23px;">
                <button type="submit" name="submit" value="1" class="btn btn-default btn-block"><i class="fas fa-filter"></i> Generate</button>
            </div>
        </div>
    </div>
    <?=form_close()?>
</section>

<?php if ($submitted && !empty($rows)): ?>
<section class="panel">
    <header class="panel-heading" style="display:flex; align-items:center; justify-content:space-between;">
        <h4 class="panel-title">
            <i class="fas fa-table"></i>
            Broadsheet &mdash; <?=htmlspecialchars($branch['name'] ?? '')?> &mdash;
            <?=htmlspecialchars(get_type_name_by_id('class',$classID))?>
            (<?=htmlspecialchars(get_type_name_by_id('section',$sectionID))?>)
        </h4>
        <div>
            <button onclick="window.print()" class="btn btn-default btn-sm"><i class="fas fa-print"></i> Print</button>
        </div>
    </header>
    <div class="panel-body">
        <div class="visible-print" style="text-align:center; margin-bottom:10px;">
            <h4><?=htmlspecialchars($branch['name'] ?? '')?></h4>
            <h5><?=htmlspecialchars($branch['address'] ?? '')?></h5>
            <h5><strong>Broadsheet &mdash; <?=htmlspecialchars(get_type_name_by_id('class',$classID))?> (<?=htmlspecialchars(get_type_name_by_id('section',$sectionID))?>)</strong></h5>
            <hr>
        </div>
        <div style="overflow-x:auto;">
            <table class="table table-bordered table-condensed bs-table" id="bsTable">
                <thead>
                    <tr>
                        <th rowspan="2">Pos.</th>
                        <th rowspan="2">Student</th>
                        <th rowspan="2">Reg No.</th>
                        <?php foreach ($subjects as $sub): ?>
                        <th colspan="<?=count($exams)+2?>" class="text-center"><?=htmlspecialchars($sub['subject_name'])?></th>
                        <?php endforeach; ?>
                        <th rowspan="2" class="text-center">Grand Total</th>
                        <th rowspan="2" class="text-center">Avg %</th>
                    </tr>
                    <tr>
                        <?php foreach ($subjects as $sub): ?>
                            <?php foreach ($exams as $ex): ?>
                            <th class="bs-sub-header"><?=htmlspecialchars($ex['name'])?></th>
                            <?php endforeach; ?>
                            <th class="bs-sub-header">Total</th>
                            <th class="bs-sub-header">Grade</th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td style="font-weight:700; text-align:center;"><?=$row['position']?></td>
                        <td><?=htmlspecialchars($row['fullname'])?></td>
                        <td><?=htmlspecialchars($row['register_no'])?></td>
                        <?php foreach ($subjects as $sub):
                            $sd = $row['subjects'][$sub['subject_id']] ?? null;
                        ?>
                            <?php foreach ($exams as $ex): ?>
                            <td class="text-center">
                                <?php
                                $ts = $sd['term_scores'][$ex['id']] ?? null;
                                if ($ts === null) echo '<span class="text-muted">—</span>';
                                elseif ($ts === 'ABS') echo '<span class="label label-warning">ABS</span>';
                                else echo $ts;
                                ?>
                            </td>
                            <?php endforeach; ?>
                            <td class="text-center" style="font-weight:600;">
                                <?=$sd ? number_format($sd['total_obt'],1).'/'.$sd['total_full'] : '—'?>
                            </td>
                            <td class="text-center">
                                <?=$sd ? htmlspecialchars($sd['grade']) : '—'?>
                            </td>
                        <?php endforeach; ?>
                        <td class="text-center" style="font-weight:700;">
                            <?=number_format($row['grand_obtained'],1)?>/<?=$row['grand_full']?>
                        </td>
                        <td class="text-center" style="font-weight:700;">
                            <?=number_format($row['grand_pct'],1)?>%
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<?php elseif ($submitted): ?>
<div class="alert alert-warning">No data found for the selected filters. Ensure marks have been entered.</div>
<?php endif; ?>
