<style>
.ts-year-block { margin-bottom: 28px; }
.ts-year-label { font-weight:700; font-size:14px; padding:6px 12px; background:#f0f0f0; border-left:4px solid #1a6b3c; margin-bottom:8px; }
.ts-exam-label { font-weight:600; font-size:12px; color:#555; margin:8px 0 4px; padding-left:6px; }
.ts-table { font-size:12.5px; }
.ts-table th { background:#fafafa; }
@media print {
    .filter-panel, .panel-footer { display:none !important; }
    .ts-table { font-size:10pt; }
}
</style>

<section class="panel filter-panel">
    <header class="panel-heading"><h4 class="panel-title">Select Student</h4></header>
    <?=form_open('transcript', ['method'=>'get'])?>
    <div class="panel-body">
        <div class="row">
            <?php if (is_superadmin_loggedin()): ?>
            <div class="col-md-3 mb-sm">
                <label>Branch</label>
                <?=form_dropdown('branch_id', $this->app_lib->getSelectList('branch'), $branchID, "class='form-control' data-plugin-selectTwo data-width='100%'")?>
            </div>
            <?php endif; ?>
            <div class="col-md-5 mb-sm">
                <label>Student <span class="required">*</span></label>
                <?php
                $stuList = ['' => '— Select Student —'];
                $allStu = $this->db->select('id, CONCAT_WS(" ",first_name,last_name," (",register_no,")") as label')->get('student')->result_array();
                foreach ($allStu as $s) $stuList[$s['id']] = $s['label'];
                echo form_dropdown('student_id', $stuList, $studentID, "class='form-control' data-plugin-selectTwo data-width='100%'");
                ?>
            </div>
            <div class="col-md-2 mb-sm" style="padding-top:23px;">
                <button type="submit" class="btn btn-default btn-block"><i class="fas fa-search"></i> View</button>
            </div>
        </div>
    </div>
    <?=form_close()?>
</section>

<?php if ($student && !empty($transcriptData)): ?>
<section class="panel">
    <header class="panel-heading" style="display:flex; align-items:center; justify-content:space-between;">
        <h4 class="panel-title"><i class="fas fa-scroll"></i> Academic Transcript &mdash; <?=htmlspecialchars($student['fullname'])?></h4>
        <button onclick="window.print()" class="btn btn-default btn-sm"><i class="fas fa-print"></i> Print</button>
    </header>
    <div class="panel-body">
        <div class="visible-print" style="text-align:center; margin-bottom:12px;">
            <h4>OFFICIAL ACADEMIC TRANSCRIPT</h4>
            <p><strong>Name:</strong> <?=htmlspecialchars($student['fullname'])?> &nbsp;|&nbsp;
               <strong>Reg No.:</strong> <?=htmlspecialchars($student['register_no'])?></p>
            <hr>
        </div>

        <div class="row" style="margin-bottom:16px;">
            <div class="col-md-4"><strong>Name:</strong> <?=htmlspecialchars($student['fullname'])?></div>
            <div class="col-md-4"><strong>Reg No.:</strong> <?=htmlspecialchars($student['register_no'])?></div>
            <div class="col-md-4"><strong>Category:</strong> <?=htmlspecialchars($student['category'] ?? '—')?></div>
        </div>

        <?php foreach ($transcriptData as $year => $exams): ?>
        <div class="ts-year-block">
            <div class="ts-year-label">Academic Year: <?=htmlspecialchars($year)?></div>
            <?php foreach ($exams as $examName => $subjects):
                $totalObt = 0; $totalFull = 0; $subCount = 0;
                foreach ($subjects as $s) { $totalObt += $s['score']; $totalFull += $s['full_mark']; if ($s['full_mark']>0) $subCount++; }
            ?>
            <div class="ts-exam-label"><?=htmlspecialchars($examName)?> <small class="text-muted">(<?=htmlspecialchars($subjects[0]['class_name'] ?? '')?>)</small></div>
            <div class="table-responsive">
                <table class="table table-bordered table-condensed ts-table">
                    <thead>
                        <tr>
                            <th style="width:40%;">Subject</th>
                            <th class="text-center">Score</th>
                            <th class="text-center">Full Mark</th>
                            <th class="text-center">%</th>
                            <th class="text-center">Grade</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($subjects as $sub): ?>
                        <tr>
                            <td><?=htmlspecialchars($sub['subject_name'])?></td>
                            <td class="text-center" style="font-variant-numeric:tabular-nums;"><?=number_format($sub['score'],1)?></td>
                            <td class="text-center" style="font-variant-numeric:tabular-nums;"><?=number_format($sub['full_mark'],1)?></td>
                            <td class="text-center">
                                <?=$sub['full_mark']>0 ? number_format(($sub['score']/$sub['full_mark'])*100,1).'%' : '—'?>
                            </td>
                            <td class="text-center"><strong><?=htmlspecialchars($sub['grade'])?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background:#f8f8f8; font-weight:700;">
                            <td>TOTAL</td>
                            <td class="text-center"><?=number_format($totalObt,1)?></td>
                            <td class="text-center"><?=number_format($totalFull,1)?></td>
                            <td class="text-center"><?=$totalFull>0?number_format(($totalObt/$totalFull)*100,1).'%':'—'?></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>

        <div class="visible-print" style="margin-top:40px; text-align:center; font-size:11pt;">
            <p>This transcript is an official record generated by SchoolVault.</p>
            <p>Printed: <?=date('d F Y')?></p>
            <div style="margin-top:30px; display:flex; justify-content:space-around;">
                <div>_________________________<br>Principal's Signature</div>
                <div>_________________________<br>School Stamp</div>
            </div>
        </div>
    </div>
</section>
<?php elseif (!empty($studentID)): ?>
<div class="alert alert-warning">No transcript records found. Transcript is populated automatically when results are published via Result Approval.</div>
<?php endif; ?>
