<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<section class="panel">
    <header class="panel-heading">
        <h2 class="panel-title">Branch Fees Collection Report</h2>
    </header>
    <div class="panel-body">

        <!-- Filter Form -->
        <form method="POST" action="<?=base_url('fees/branch_fees_report')?>" id="filterForm">
            <?php $this->load->helper('form'); echo form_hidden($this->security->get_csrf_token_name(), $this->security->get_csrf_hash()); ?>
            <div class="row">
                <div class="col-sm-4 col-xs-12">
                    <div class="form-group">
                        <label>Session</label>
                        <select name="session_id" class="form-control">
                            <?php foreach ($sessions as $sy): ?>
                            <option value="<?=$sy['id']?>"
                                <?php if ($sy['id'] == $sessionID) echo 'selected'; ?>>
                                <?=html_escape($sy['school_year'])?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-sm-4 col-xs-12">
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <div class="checkbox mt-xs">
                            <label>
                                <input type="checkbox" name="outstanding_only" value="1"
                                    <?php if ($outstandingOnly) echo 'checked'; ?>>
                                Show only students with outstanding balance
                            </label>
                        </div>
                    </div>
                </div>
                <div class="col-sm-4 col-xs-12">
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <div>
                            <button type="submit" name="generate" value="1" class="btn btn-primary btn-block">
                                <i class="fa fa-file-text-o"></i> Generate Report
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <?php if ($searched): ?>

        <?php if (empty($allRows)): ?>
            <div class="alert alert-info"><i class="fa fa-info-circle"></i> No data found for the selected session.</div>
        <?php else: ?>

        <div class="text-right mb-sm">
            <button onclick="printAll()" class="btn btn-default btn-sm">
                <i class="fa fa-print"></i> Print All Branches
            </button>
        </div>

        <?php foreach ($allRows as $branchID => $branchData):
            $t = $branchData['totals'];
            $students = $branchData['students'];
            $totalStudents = count($students);
            $studentsWithDebt = count(array_filter($students, function($r){ return $r['grand_outstanding'] > 0; }));
            $tableID = 'brTable_' . $branchID;
        ?>

        <div class="branch-block" id="branch_<?=$branchID?>" style="margin-bottom:40px;">
            <div class="panel panel-default">
                <div class="panel-heading" style="background:#f5f5f5;border-bottom:2px solid #ddd;">
                    <div class="row">
                        <div class="col-sm-8">
                            <h3 class="panel-title" style="font-size:16px;font-weight:bold;">
                                <i class="fa fa-building-o"></i> <?=html_escape($branchData['branch_name'])?>
                            </h3>
                        </div>
                        <div class="col-sm-4 text-right">
                            <button onclick="printBranch(<?=$branchID?>)" class="btn btn-default btn-xs">
                                <i class="fa fa-print"></i> Print This Branch
                            </button>
                        </div>
                    </div>
                </div>
                <div class="panel-body">

                    <!-- Branch Summary Widgets -->
                    <div class="row widget-row-in mb-md">
                        <div class="col-lg-3 col-sm-6 widget-row-d-br">
                            <div class="widget-col-in row">
                                <div class="col-xs-7"><h5 class="text-muted">Students</h5></div>
                                <div class="col-xs-5">
                                    <h3 class="text-right mt-sm text-primary"><?=$totalStudents?></h3>
                                </div>
                                <div class="col-xs-12">
                                    <div class="box-top-line line-color-primary">
                                        <span class="text-uppercase text-muted"><?=$studentsWithDebt?> with balance</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-sm-6 widget-row-d-br">
                            <div class="widget-col-in row">
                                <div class="col-xs-6"><h5 class="text-muted">Total Charged</h5></div>
                                <div class="col-xs-6">
                                    <h5 class="text-right mt-sm text-info">
                                        ₦<?=number_format($t['t1_charged'] + $t['t2_charged'] + $t['t3_charged'])?>
                                    </h5>
                                </div>
                                <div class="col-xs-12"><div class="box-top-line" style="background:#5bc0de"><span class="text-uppercase text-muted">all 3 terms</span></div></div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-sm-6 widget-row-d-br">
                            <div class="widget-col-in row">
                                <div class="col-xs-6"><h5 class="text-muted">Total Paid</h5></div>
                                <div class="col-xs-6">
                                    <h5 class="text-right mt-sm text-success">
                                        ₦<?=number_format($t['t1_paid'] + $t['t2_paid'] + $t['t3_paid'])?>
                                    </h5>
                                </div>
                                <div class="col-xs-12"><div class="box-top-line" style="background:#5cb85c"><span class="text-uppercase text-muted">received</span></div></div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-sm-6">
                            <div class="widget-col-in row">
                                <div class="col-xs-6"><h5 class="text-muted">Outstanding</h5></div>
                                <div class="col-xs-6">
                                    <h5 class="text-right mt-sm text-danger">
                                        ₦<?=number_format($t['grand_outstanding'])?>
                                    </h5>
                                </div>
                                <div class="col-xs-12"><div class="box-top-line" style="background:#d9534f"><span class="text-uppercase text-muted">balance due</span></div></div>
                            </div>
                        </div>
                    </div>

                    <!-- Per-term sub-totals bar -->
                    <div class="row mb-md" style="font-size:12px;">
                        <?php foreach ([
                            ['1st Term', $t['t1_charged'], $t['t1_paid'], $t['t1_outstanding']],
                            ['2nd Term', $t['t2_charged'], $t['t2_paid'], $t['t2_outstanding']],
                            ['3rd Term', $t['t3_charged'], $t['t3_paid'], $t['t3_outstanding']],
                        ] as [$label, $chg, $paid, $owed]): ?>
                        <div class="col-sm-4">
                            <table class="table table-condensed table-bordered" style="margin-bottom:0">
                                <thead><tr><th colspan="2" class="text-center" style="background:#f9f9f9"><?=$label?></th></tr></thead>
                                <tbody>
                                    <tr><td>Charged</td><td class="text-right">₦<?=number_format($chg)?></td></tr>
                                    <tr><td>Paid</td><td class="text-right text-success">₦<?=number_format($paid)?></td></tr>
                                    <tr><td><strong>Outstanding</strong></td><td class="text-right text-danger"><strong>₦<?=number_format($owed)?></strong></td></tr>
                                </tbody>
                            </table>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Student Table -->
                    <div class="table-responsive">
                        <table class="table table-bordered table-condensed table-striped" id="<?=$tableID?>" style="font-size:12px;">
                            <thead>
                                <tr>
                                    <th rowspan="2">#</th>
                                    <th rowspan="2">Student</th>
                                    <th rowspan="2">Reg No</th>
                                    <th rowspan="2">Class</th>
                                    <th rowspan="2">Section</th>
                                    <th colspan="3" class="text-center" style="background:#e8f4fd;">1st Term (₦)</th>
                                    <th colspan="3" class="text-center" style="background:#e8f9ed;">2nd Term (₦)</th>
                                    <th colspan="3" class="text-center" style="background:#fef9e7;">3rd Term (₦)</th>
                                    <th rowspan="2" class="text-right text-danger">Total Owed (₦)</th>
                                </tr>
                                <tr>
                                    <th class="text-right" style="background:#e8f4fd;">Charged</th>
                                    <th class="text-right" style="background:#e8f4fd;">Paid</th>
                                    <th class="text-right" style="background:#e8f4fd;">Owed</th>
                                    <th class="text-right" style="background:#e8f9ed;">Charged</th>
                                    <th class="text-right" style="background:#e8f9ed;">Paid</th>
                                    <th class="text-right" style="background:#e8f9ed;">Owed</th>
                                    <th class="text-right" style="background:#fef9e7;">Charged</th>
                                    <th class="text-right" style="background:#fef9e7;">Paid</th>
                                    <th class="text-right" style="background:#fef9e7;">Owed</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php $i = 1; foreach ($students as $r):
                                $t1total = $r['t1_charged'] + $r['t1_prev_due'];
                                $t2total = $r['t2_charged'] + $r['t2_prev_due'];
                                $t3total = $r['t3_charged'] + $r['t3_prev_due'];
                            ?>
                            <tr<?php if ($r['grand_outstanding'] > 0) echo ' class="danger"'; ?>>
                                <td><?=$i++?></td>
                                <td><?=html_escape($r['student_name'])?></td>
                                <td><?=html_escape($r['register_no'])?></td>
                                <td><?=html_escape($r['class_name'])?></td>
                                <td><?=html_escape($r['section_name'])?></td>
                                <!-- 1st Term -->
                                <td class="text-right"><?=$t1total > 0 ? number_format($t1total) : '—'?></td>
                                <td class="text-right"><?=$r['t1_paid'] > 0 ? number_format($r['t1_paid']) : '—'?></td>
                                <td class="text-right"><?=$r['t1_outstanding'] > 0 ? '<strong>'.number_format($r['t1_outstanding']).'</strong>' : '<span class="text-success">0</span>'?></td>
                                <!-- 2nd Term -->
                                <td class="text-right"><?=$t2total > 0 ? number_format($t2total) : '—'?></td>
                                <td class="text-right"><?=$r['t2_paid'] > 0 ? number_format($r['t2_paid']) : '—'?></td>
                                <td class="text-right"><?=$r['t2_outstanding'] > 0 ? '<strong>'.number_format($r['t2_outstanding']).'</strong>' : '<span class="text-success">0</span>'?></td>
                                <!-- 3rd Term -->
                                <td class="text-right"><?=$t3total > 0 ? number_format($t3total) : '—'?></td>
                                <td class="text-right"><?=$r['t3_paid'] > 0 ? number_format($r['t3_paid']) : '—'?></td>
                                <td class="text-right"><?=$r['t3_outstanding'] > 0 ? '<strong>'.number_format($r['t3_outstanding']).'</strong>' : '<span class="text-success">0</span>'?></td>
                                <!-- Grand -->
                                <td class="text-right<?php if ($r['grand_outstanding'] > 0) echo ' text-danger'; ?>">
                                    <?php if ($r['grand_outstanding'] > 0): ?>
                                        <strong><?=number_format($r['grand_outstanding'])?></strong>
                                    <?php else: ?>
                                        <span class="text-success"><i class="fa fa-check"></i></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="info">
                                    <th colspan="5" class="text-right">TOTALS</th>
                                    <th class="text-right">₦<?=number_format($t['t1_charged'])?></th>
                                    <th class="text-right">₦<?=number_format($t['t1_paid'])?></th>
                                    <th class="text-right text-danger">₦<?=number_format($t['t1_outstanding'])?></th>
                                    <th class="text-right">₦<?=number_format($t['t2_charged'])?></th>
                                    <th class="text-right">₦<?=number_format($t['t2_paid'])?></th>
                                    <th class="text-right text-danger">₦<?=number_format($t['t2_outstanding'])?></th>
                                    <th class="text-right">₦<?=number_format($t['t3_charged'])?></th>
                                    <th class="text-right">₦<?=number_format($t['t3_paid'])?></th>
                                    <th class="text-right text-danger">₦<?=number_format($t['t3_outstanding'])?></th>
                                    <th class="text-right text-danger">₦<?=number_format($t['grand_outstanding'])?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                </div><!-- /.panel-body -->
            </div><!-- /.panel -->
        </div><!-- /.branch-block -->

        <?php endforeach; ?>
        <?php endif; ?>
        <?php endif; ?>

    </div><!-- /.panel-body -->
</section>

<script>
<?php if ($searched && !empty($allRows)): ?>
$(document).ready(function () {
    <?php foreach (array_keys($allRows) as $bid): ?>
    $('#brTable_<?=$bid?>').DataTable({
        paging:    true,
        pageLength: 50,
        searching: true,
        ordering:  true,
        order:     [],
        columnDefs: [{ orderable: false, targets: [0] }],
        dom: '<"row"<"col-sm-6"l><"col-sm-6"f>>rtip'
    });
    <?php endforeach; ?>
});
<?php endif; ?>

function printBranch(branchID) {
    var el = document.getElementById('branch_' + branchID);
    if (!el) return;
    var w = window.open('', '_blank', 'width=1200,height=900');
    w.document.write('<html><head><title>Branch Fees Report</title>');
    w.document.write('<style>');
    w.document.write('body{font-family:Arial,sans-serif;font-size:11px}');
    w.document.write('table{border-collapse:collapse;width:100%}');
    w.document.write('th,td{border:1px solid #ccc;padding:3px 5px}');
    w.document.write('th{background:#f0f0f0}');
    w.document.write('.text-right{text-align:right}.text-center{text-align:center}');
    w.document.write('.text-danger{color:#c00}.text-success{color:#060}');
    w.document.write('tfoot th{background:#dde;font-weight:bold}');
    w.document.write('.widget-row-in{display:none}'); // hide widgets in print — table is enough
    w.document.write('@media print{button,input,select,form{display:none}}');
    w.document.write('</style></head><body>');
    w.document.write(el.innerHTML);
    w.document.write('</body></html>');
    w.document.close();
    w.focus();
    setTimeout(function(){ w.print(); }, 400);
}

function printAll() {
    <?php $branchIDs = array_keys($allRows ?? []); ?>
    var ids = <?=json_encode($branchIDs)?>;
    var w = window.open('', '_blank', 'width=1200,height=900');
    w.document.write('<html><head><title>All Branch Fees Report</title>');
    w.document.write('<style>');
    w.document.write('body{font-family:Arial,sans-serif;font-size:11px}');
    w.document.write('table{border-collapse:collapse;width:100%}');
    w.document.write('th,td{border:1px solid #ccc;padding:3px 5px}');
    w.document.write('th{background:#f0f0f0}');
    w.document.write('.text-right{text-align:right}.text-center{text-align:center}');
    w.document.write('.text-danger{color:#c00}.text-success{color:#060}');
    w.document.write('tfoot th{background:#dde;font-weight:bold}');
    w.document.write('.branch-divider{page-break-before:always;padding-top:20px}');
    w.document.write('.widget-row-in{display:none}');
    w.document.write('@media print{button,input,select,form{display:none}}');
    w.document.write('</style></head><body>');
    for (var i = 0; i < ids.length; i++) {
        var el = document.getElementById('branch_' + ids[i]);
        if (!el) continue;
        if (i > 0) w.document.write('<div class="branch-divider"></div>');
        w.document.write(el.innerHTML);
    }
    w.document.write('</body></html>');
    w.document.close();
    w.focus();
    setTimeout(function(){ w.print(); }, 400);
}
</script>
