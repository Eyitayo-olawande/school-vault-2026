<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>

<section class="panel">
    <header class="panel-heading">
        <h2 class="panel-title">Outstanding Balances by Session &amp; Term</h2>
    </header>
    <div class="panel-body">

        <!-- Filter Form -->
        <form method="POST" action="<?=base_url('fees/session_outstanding_report')?>" id="filterForm">
            <?php $this->load->helper('form'); echo form_hidden($this->security->get_csrf_token_name(), $this->security->get_csrf_hash()); ?>
            <div class="row">
                <?php if (is_superadmin_loggedin()): ?>
                <div class="col-sm-2 col-xs-12">
                    <div class="form-group">
                        <label>Branch <span class="required">*</span></label>
                        <select name="branch_id" class="form-control" required>
                            <option value="">— Select —</option>
                            <?php foreach ($branches as $br): ?>
                            <option value="<?=$br['id']?>"
                                <?php if ($this->input->post('branch_id') == $br['id']) echo 'selected'; ?>>
                                <?=html_escape($br['name'])?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <?php endif; ?>
                <div class="col-sm-<?=is_superadmin_loggedin() ? 2 : 3?> col-xs-12">
                    <div class="form-group">
                        <label>Session</label>
                        <select name="session_id" class="form-control">
                            <option value="">— All Sessions —</option>
                            <?php foreach ($sessions as $sy): ?>
                            <option value="<?=$sy['id']?>"
                                <?php if ($this->input->post('session_id') == $sy['id']) echo 'selected'; ?>>
                                <?=html_escape($sy['school_year'])?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-sm-2 col-xs-12">
                    <div class="form-group">
                        <label>Term</label>
                        <select name="term" class="form-control">
                            <option value="">— All Terms —</option>
                            <option value="1ST TERM" <?php if ($this->input->post('term') === '1ST TERM') echo 'selected'; ?>>1st Term</option>
                            <option value="2ND TERM" <?php if ($this->input->post('term') === '2ND TERM') echo 'selected'; ?>>2nd Term</option>
                            <option value="3RD TERM" <?php if ($this->input->post('term') === '3RD TERM') echo 'selected'; ?>>3rd Term</option>
                        </select>
                    </div>
                </div>
                <div class="col-sm-3 col-xs-12">
                    <div class="form-group">
                        <label>Class</label>
                        <select name="class_id" class="form-control">
                            <option value="">— All Classes —</option>
                            <?php foreach ($classes as $cl): ?>
                            <option value="<?=$cl['id']?>"
                                <?php if ($this->input->post('class_id') == $cl['id']) echo 'selected'; ?>>
                                <?=html_escape($cl['name'])?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-sm-2 col-xs-12">
                    <div class="form-group">
                        <label>Section</label>
                        <select name="section_id" class="form-control">
                            <option value="">— All Sections —</option>
                            <?php foreach ($sections as $sec): ?>
                            <option value="<?=$sec['id']?>"
                                <?php if ($this->input->post('section_id') == $sec['id']) echo 'selected'; ?>>
                                <?=html_escape($sec['name'])?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-sm-2 col-xs-12">
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <div>
                            <button type="submit" name="search" value="1" class="btn btn-primary btn-block">
                                <i class="fa fa-search"></i> Search
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <?php if ($searched): ?>

        <!-- Summary Widgets -->
        <div class="row widget-row-in mb-md">
            <div class="col-lg-3 col-sm-6 widget-row-d-br">
                <div class="widget-col-in row">
                    <div class="col-xs-7"><h5 class="text-muted">Students</h5></div>
                    <div class="col-xs-5">
                        <h3 class="text-right mt-sm text-primary"><?=count(array_unique(array_column($rows, 'student_id')))?></h3>
                    </div>
                    <div class="col-xs-12"><div class="box-top-line line-color-primary"><span class="text-uppercase text-muted">with balance</span></div></div>
                </div>
            </div>
            <div class="col-lg-3 col-sm-6 widget-row-d-br">
                <div class="widget-col-in row">
                    <div class="col-xs-7"><h5 class="text-muted">Total Charged</h5></div>
                    <div class="col-xs-5">
                        <h4 class="text-right mt-sm text-info">₦<?=number_format($totals['fee_charged'] + $totals['carried_forward'])?></h4>
                    </div>
                    <div class="col-xs-12"><div class="box-top-line" style="background:#5bc0de"><span class="text-uppercase text-muted">incl. carry-forward</span></div></div>
                </div>
            </div>
            <div class="col-lg-3 col-sm-6 widget-row-d-br">
                <div class="widget-col-in row">
                    <div class="col-xs-7"><h5 class="text-muted">Total Paid</h5></div>
                    <div class="col-xs-5">
                        <h4 class="text-right mt-sm text-success">₦<?=number_format($totals['total_paid'])?></h4>
                    </div>
                    <div class="col-xs-12"><div class="box-top-line" style="background:#5cb85c"><span class="text-uppercase text-muted">received</span></div></div>
                </div>
            </div>
            <div class="col-lg-3 col-sm-6">
                <div class="widget-col-in row">
                    <div class="col-xs-7"><h5 class="text-muted">Total Outstanding</h5></div>
                    <div class="col-xs-5">
                        <h4 class="text-right mt-sm text-danger">₦<?=number_format($totals['outstanding'])?></h4>
                    </div>
                    <div class="col-xs-12"><div class="box-top-line" style="background:#d9534f"><span class="text-uppercase text-muted">balance due</span></div></div>
                </div>
            </div>
        </div>

        <?php if (empty($rows)): ?>
            <div class="alert alert-success"><i class="fa fa-check-circle"></i> No outstanding balances found for the selected filters.</div>
        <?php else: ?>

        <div class="text-right mb-sm">
            <button onclick="printOutstanding()" class="btn btn-default btn-sm">
                <i class="fa fa-print"></i> Print
            </button>
        </div>

        <div class="table-responsive" id="outstandingTableWrap">
            <table class="table table-bordered table-condensed table-striped" id="outstandingTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student Name</th>
                        <th>Reg No</th>
                        <th>Class</th>
                        <th>Section</th>
                        <th>Session</th>
                        <th>Term</th>
                        <th class="text-right">Fee Charged (₦)</th>
                        <th class="text-right">Carry-Fwd (₦)</th>
                        <th class="text-right">Paid (₦)</th>
                        <th class="text-right">Outstanding (₦)</th>
                    </tr>
                </thead>
                <tbody>
                <?php $i = 1; foreach ($rows as $r): ?>
                <tr>
                    <td><?=$i++?></td>
                    <td><?=html_escape($r['student_name'])?></td>
                    <td><?=html_escape($r['register_no'])?></td>
                    <td><?=html_escape($r['class_name'])?></td>
                    <td><?=html_escape($r['section_name'])?></td>
                    <td><?=html_escape($r['session_label'])?></td>
                    <td><?=html_escape($r['term'])?></td>
                    <td class="text-right"><?=number_format($r['fee_charged'])?></td>
                    <td class="text-right"><?=number_format($r['carried_forward'])?></td>
                    <td class="text-right"><?=number_format($r['total_paid'])?></td>
                    <td class="text-right text-danger"><strong><?=number_format($r['outstanding'])?></strong></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="info">
                        <th colspan="7" class="text-right">TOTALS</th>
                        <th class="text-right">₦<?=number_format($totals['fee_charged'])?></th>
                        <th class="text-right">₦<?=number_format($totals['carried_forward'])?></th>
                        <th class="text-right">₦<?=number_format($totals['total_paid'])?></th>
                        <th class="text-right text-danger">₦<?=number_format($totals['outstanding'])?></th>
                    </tr>
                </tfoot>
            </table>
        </div>

        <?php endif; ?>
        <?php endif; ?>

        <!-- Production SQL block (superadmin only) -->
        <?php if (is_superadmin_loggedin()): ?>
        <div class="panel panel-default mt-md">
            <div class="panel-heading">
                <h4 class="panel-title">
                    <a data-toggle="collapse" href="#sqlBlock">Production SQL Query <small>(click to expand)</small></a>
                </h4>
            </div>
            <div id="sqlBlock" class="panel-collapse collapse">
                <div class="panel-body">
                    <p class="text-muted">Run this directly on production MySQL to export outstanding balances. Adjust <code>-- SET branch/session/term</code> lines at the top before running.</p>
                    <pre style="font-size:11px;max-height:400px;overflow:auto;">-- ============================================================
-- CLEMMY SCHOOLS — Outstanding Balances Report
-- Generated: <?=date('Y-m-d H:i')?> | Session: 2025-2026
-- Adjust the @branch_id and @session_id variables below.
-- Set @term_filter = '' to include all terms.
-- ============================================================

SET @branch_id   = 1;          -- 1=CLEMMY HIGH, 2=BAKINSON, 3=BUSURA&OREMEJI
SET @session_id  = 6;          -- 6 = 2025-2026  |  7 = 2026-2027  (0 = ALL)
SET @term_filter = '1ST TERM'; -- '1ST TERM', '2ND TERM', '3RD TERM', or '' for all

SELECT
    sy.school_year                             AS Session,
    CASE
        WHEN fg.name LIKE '1ST TERM%' OR fg.name LIKE '1st TERM%' THEN '1st Term'
        WHEN fg.name LIKE '2ND TERM%' OR fg.name LIKE '2nd TERM%' THEN '2nd Term'
        WHEN fg.name LIKE '3RD TERM%' OR fg.name LIKE '3rd TERM%' THEN '3rd Term'
        ELSE 'Full Year'
    END                                        AS Term,
    cl.name                                    AS Class,
    sec.name                                   AS Section,
    CONCAT(s.first_name, ' ', s.last_name)    AS Student,
    s.register_no                              AS `Reg No`,
    ROUND(COALESCE(fgd_sum.total_charged, 0), 2) AS `Fee Charged (NGN)`,
    ROUND(fa.prev_due, 2)                      AS `Carry-Forward (NGN)`,
    ROUND(COALESCE(fph_sum.total_paid, 0), 2) AS `Total Paid (NGN)`,
    ROUND(
        COALESCE(fgd_sum.total_charged, 0)
        + fa.prev_due
        - COALESCE(fph_sum.total_paid, 0)
    , 2)                                       AS `Outstanding (NGN)`
FROM fee_allocation fa
JOIN fee_groups fg
    ON fg.id = fa.group_id
JOIN (
    SELECT fee_groups_id, SUM(amount) AS total_charged
    FROM fee_groups_details
    GROUP BY fee_groups_id
) fgd_sum ON fgd_sum.fee_groups_id = fa.group_id
LEFT JOIN (
    SELECT allocation_id, SUM(amount - discount) AS total_paid
    FROM fee_payment_history
    WHERE status = 'paid'
    GROUP BY allocation_id
) fph_sum ON fph_sum.allocation_id = fa.id
JOIN schoolyear sy  ON sy.id  = fa.session_id
JOIN student s      ON s.id   = fa.student_id
JOIN enroll e       ON e.student_id  = fa.student_id
                   AND e.session_id  = fa.session_id
JOIN class cl       ON cl.id  = e.class_id
JOIN section sec    ON sec.id = e.section_id
WHERE fa.branch_id = @branch_id
  AND (@session_id  = 0 OR fa.session_id  = @session_id)
  AND (@term_filter = '' OR fg.name LIKE CONCAT(@term_filter, '%'))
HAVING `Outstanding (NGN)` > 0
ORDER BY
    sy.school_year,
    FIELD(
        CASE WHEN fg.name LIKE '1ST TERM%' THEN '1ST TERM'
             WHEN fg.name LIKE '2ND TERM%' THEN '2ND TERM'
             WHEN fg.name LIKE '3RD TERM%' THEN '3RD TERM'
             ELSE 'ZZZ' END,
        '1ST TERM', '2ND TERM', '3RD TERM', 'ZZZ'
    ),
    cl.name, sec.name, s.last_name, s.first_name;
</pre>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /.panel-body -->
</section>

<script>
<?php if (!empty($rows)): ?>
$(document).ready(function () {
    $('#outstandingTable').DataTable({
        paging:  true,
        pageLength: 50,
        searching: true,
        ordering: true,
        order: [],
        columnDefs: [{ orderable: false, targets: 0 }],
        dom: '<"row"<"col-sm-6"l><"col-sm-6"f>>rtip'
    });
});
<?php endif; ?>

function printOutstanding() {
    var w = window.open('', '_blank', 'width=1100,height=800');
    w.document.write('<html><head><title>Outstanding Balances</title>');
    w.document.write('<style>body{font-family:Arial,sans-serif;font-size:12px}table{border-collapse:collapse;width:100%}th,td{border:1px solid #ccc;padding:4px 6px}th{background:#f5f5f5}tfoot th{background:#e0e0e0;font-weight:bold}.text-right{text-align:right}.text-danger{color:#c00}@media print{button{display:none}}</style>');
    w.document.write('</head><body>');
    w.document.write('<h3>Outstanding Balances — <?=html_escape($this->input->post('session_id') ? array_column($sessions, 'school_year', 'id')[$this->input->post('session_id')] ?? 'All Sessions' : 'All Sessions')?></h3>');
    w.document.write('<p>Term: <?=html_escape($this->input->post('term') ?: 'All')?> &nbsp;|&nbsp; Generated: <?=date('d M Y H:i')?></p>');
    w.document.write(document.getElementById('outstandingTableWrap').innerHTML);
    w.document.write('</body></html>');
    w.document.close();
    w.focus();
    setTimeout(function(){ w.print(); }, 300);
}
</script>
