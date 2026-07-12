<?php
$currency_symbol = $global_config['currency_symbol'];
$curSessID       = (int)set_value('session_id', isset($session_id) ? $session_id : '');
$selTerm         = isset($term) ? $term : '';
$selDateA        = isset($date_a) ? $date_a : date('Y-m-d', strtotime('-7 days'));
$selDateB        = isset($date_b) ? $date_b : date('Y-m-d');
$selPrevDue      = !empty($include_prev_due);
$schedule        = isset($schedule) ? $schedule : [];
?>
<div class="row">
    <div class="col-md-12">

        <!-- Filter panel -->
        <section class="panel">
            <header class="panel-heading">
                <h4 class="panel-title"><?= translate('select_ground') ?></h4>
            </header>
            <?php echo form_open($this->uri->uri_string(), ['class' => 'validate', 'id' => 'sfs-filter-form']); ?>
            <div class="panel-body">
                <div class="row mb-sm">

                    <?php if (is_superadmin_loggedin()): ?>
                    <div class="col-md-2 mb-sm">
                        <div class="form-group">
                            <label class="control-label"><?= translate('branch') ?> <span class="required">*</span></label>
                            <?php
                                $arrayBranch = $this->app_lib->getSelectList('branch');
                                echo form_dropdown('branch_id', $arrayBranch, set_value('branch_id'),
                                    "class='form-control' required
                                     data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity'");
                            ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="col-md-2 mb-sm">
                        <div class="form-group">
                            <label class="control-label">Session <span class="required">*</span></label>
                            <select name="session_id" class="form-control" required
                                data-plugin-selectTwo data-width="100%" data-minimum-results-for-search="Infinity">
                                <?php foreach ($session_list as $sid => $lbl): ?>
                                    <option value="<?= $sid ?>" <?= ($sid == $curSessID) ? 'selected' : '' ?>><?= htmlspecialchars($lbl) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-2 mb-sm">
                        <div class="form-group">
                            <label class="control-label">Term</label>
                            <select name="term" class="form-control"
                                data-plugin-selectTwo data-width="100%" data-minimum-results-for-search="Infinity">
                                <option value="" <?= empty($selTerm) ? 'selected' : '' ?>>All Terms</option>
                                <option value="1ST TERM" <?= ($selTerm === '1ST TERM') ? 'selected' : '' ?>>1st Term</option>
                                <option value="2ND TERM" <?= ($selTerm === '2ND TERM') ? 'selected' : '' ?>>2nd Term</option>
                                <option value="3RD TERM" <?= ($selTerm === '3RD TERM') ? 'selected' : '' ?>>3rd Term</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-2 mb-sm">
                        <div class="form-group">
                            <label class="control-label">Date A <span class="required">*</span></label>
                            <input type="date" name="date_a" class="form-control" value="<?= htmlspecialchars($selDateA) ?>" required>
                        </div>
                    </div>

                    <div class="col-md-2 mb-sm">
                        <div class="form-group">
                            <label class="control-label">Date B <span class="required">*</span></label>
                            <input type="date" name="date_b" class="form-control" value="<?= htmlspecialchars($selDateB) ?>" required>
                        </div>
                    </div>

                    <div class="col-md-2 mb-sm">
                        <div class="form-group">
                            <label class="control-label">&nbsp;</label>
                            <div class="checkbox-custom mt-xs">
                                <label class="i-checks">
                                    <input type="checkbox" name="include_prev_due" value="1" <?= $selPrevDue ? 'checked' : '' ?>>
                                    <i></i> Include carry-forward
                                </label>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
            <footer class="panel-footer">
                <div class="row">
                    <div class="col-md-offset-10 col-md-2">
                        <button type="submit" name="search" value="1" class="btn btn-default btn-block">
                            <i class="fas fa-filter"></i> <?= translate('filter') ?>
                        </button>
                    </div>
                </div>
            </footer>
            <?php echo form_close(); ?>
        </section>

        <?php if (isset($rows)): ?>

        <!-- Summary widgets -->
        <?php if (!empty($rows) && isset($totals)):
            $rate_a = $totals['expected'] > 0 ? round(($totals['paid_a'] / $totals['expected']) * 100, 1) : 0;
            $rate_b = $totals['expected'] > 0 ? round(($totals['paid_b'] / $totals['expected']) * 100, 1) : 0;
            $delta  = $totals['paid_b'] - $totals['paid_a'];
        ?>
        <div class="row mb-md">
            <div class="col-md-3 col-sm-6">
                <div class="widget-summary widget-summary-sm" style="background:#3d5a80;border-radius:4px;padding:12px">
                    <div class="summary">
                        <h5 style="color:#cce0f5;margin:0 0 4px">Total Expected</h5>
                        <strong style="color:#fff;font-size:1.2em"><?= currencyFormat($totals['expected']) ?></strong>
                        <div style="color:#aad0ef;font-size:0.85em"><?= $totals['enrolled'] ?> students enrolled</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="widget-summary widget-summary-sm" style="background:#457b9d;border-radius:4px;padding:12px">
                    <div class="summary">
                        <h5 style="color:#cce5f5;margin:0 0 4px">Paid as of <?= date('d M', strtotime($selDateA)) ?></h5>
                        <strong style="color:#fff;font-size:1.2em"><?= currencyFormat($totals['paid_a']) ?></strong>
                        <div style="color:#b0d8f0;font-size:0.85em">Balance: <?= currencyFormat($totals['balance_a']) ?> &nbsp;(<?= $rate_a ?>%)</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="widget-summary widget-summary-sm" style="background:#1d8a5a;border-radius:4px;padding:12px">
                    <div class="summary">
                        <h5 style="color:#c0f0d8;margin:0 0 4px">Paid as of <?= date('d M', strtotime($selDateB)) ?></h5>
                        <strong style="color:#fff;font-size:1.2em"><?= currencyFormat($totals['paid_b']) ?></strong>
                        <div style="color:#9fe8c4;font-size:0.85em">Balance: <?= currencyFormat($totals['balance_b']) ?> &nbsp;(<?= $rate_b ?>%)</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="widget-summary widget-summary-sm" style="background:<?= $delta >= 0 ? '#e07b00' : '#c0392b' ?>;border-radius:4px;padding:12px">
                    <div class="summary">
                        <h5 style="color:#ffe4b0;margin:0 0 4px">Collected This Period</h5>
                        <strong style="color:#fff;font-size:1.2em"><?= currencyFormat(abs($delta)) ?></strong>
                        <div style="color:#ffd080;font-size:0.85em"><?= date('d M', strtotime($selDateA)) ?> → <?= date('d M', strtotime($selDateB)) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Main report table -->
        <section class="panel appear-animation" data-appear-animation="<?= $global_config['animations'] ?>" data-appear-animation-delay="100">
            <header class="panel-heading">
                <h4 class="panel-title">
                    <i class="fas fa-layer-group"></i>
                    Section-wise Fees Collection
                    <?php if (!empty($session_label)): ?>
                        &mdash; <span class="text-muted" style="font-size:0.9em"><?= htmlspecialchars($session_label) ?><?= !empty($selTerm) ? ' &mdash; ' . htmlspecialchars($selTerm) : '' ?></span>
                    <?php endif; ?>
                    <div class="panel-btn">
                        <button type="button" class="btn btn-default btn-circle" onclick="printSectionReport()">
                            <i class="fas fa-print"></i> Print
                        </button>
                    </div>
                </h4>
            </header>
            <div class="panel-body" id="sfs-report-body">
                <div class="mb-md mt-md" style="overflow-x:auto">
                    <!-- Print title (hidden on screen, shown in print) -->
                    <div class="hidden-screen" style="display:none">
                        <h3 style="text-align:center;margin-bottom:4px">Fee Collection Progress Report</h3>
                        <p style="text-align:center;margin-bottom:12px">
                            <?= htmlspecialchars($selTerm ?: 'All Terms') ?> — <?= htmlspecialchars($session_label ?? '') ?>
                            <?= $selPrevDue ? ' (including carry-forward)' : '' ?>
                        </p>
                    </div>

                    <div class="export_title">Section-wise Fees Collection<?= !empty($session_label) ? ' — ' . htmlspecialchars($session_label) . (!empty($selTerm) ? ' — ' . htmlspecialchars($selTerm) : '') : '' ?></div>

                    <?php if (empty($rows)): ?>
                        <div class="alert alert-info"><i class="fas fa-info-circle"></i> No data found for the selected filters.</div>
                    <?php else: ?>
                    <table class="table table-bordered table-condensed table-hover mb-none tbr-top table-export" id="sfs-table">
                        <thead>
                            <tr>
                                <th rowspan="2" style="vertical-align:middle">No.<br>Students</th>
                                <th rowspan="2" style="vertical-align:middle">Class</th>
                                <th rowspan="2" style="vertical-align:middle">Section</th>
                                <th rowspan="2" style="vertical-align:middle">Expected</th>
                                <th colspan="2" style="text-align:center;background:#dceeff">
                                    Payment as of <?= date('d/m/Y', strtotime($selDateA)) ?>
                                </th>
                                <th colspan="2" style="text-align:center;background:#d4f0e4">
                                    Payment as of <?= date('d/m/Y', strtotime($selDateB)) ?>
                                </th>
                            </tr>
                            <tr>
                                <th style="background:#dceeff">Payment</th>
                                <th style="background:#dceeff">Balance</th>
                                <th style="background:#d4f0e4">Payment</th>
                                <th style="background:#d4f0e4">Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $prevClass = null;
                        $classTotals = [];
                        foreach ($rows as $r) {
                            $cid = $r['class_id'];
                            if (!isset($classTotals[$cid])) {
                                $classTotals[$cid] = ['name'=>$r['class_name'],'enrolled'=>0,'expected'=>0,'paid_a'=>0,'balance_a'=>0,'paid_b'=>0,'balance_b'=>0];
                            }
                            $classTotals[$cid]['enrolled']  += $r['total_enrolled'];
                            $classTotals[$cid]['expected']  += $r['total_expected'];
                            $classTotals[$cid]['paid_a']    += $r['paid_a'];
                            $classTotals[$cid]['balance_a'] += $r['balance_a'];
                            $classTotals[$cid]['paid_b']    += $r['paid_b'];
                            $classTotals[$cid]['balance_b'] += $r['balance_b'];
                        }

                        foreach ($rows as $row):
                            // Print class subtotal before switching class
                            if ($prevClass !== null && $prevClass !== $row['class_id']):
                                $ct = $classTotals[$prevClass]; ?>
                            <tr style="background:#f0f4f8;font-weight:bold">
                                <td><?= $ct['enrolled'] ?></td>
                                <td colspan="2"><strong><?= htmlspecialchars($ct['name']) ?> &mdash; TOTAL</strong></td>
                                <td><strong><?= currencyFormat($ct['expected']) ?></strong></td>
                                <td style="background:#dceeff"><strong><?= currencyFormat($ct['paid_a']) ?></strong></td>
                                <td style="background:#dceeff"><strong><?= currencyFormat($ct['balance_a']) ?></strong></td>
                                <td style="background:#d4f0e4"><strong><?= currencyFormat($ct['paid_b']) ?></strong></td>
                                <td style="background:#d4f0e4"><strong><?= currencyFormat($ct['balance_b']) ?></strong></td>
                            </tr>
                            <tr><td colspan="8" style="padding:0;border:0;height:4px;background:#e0e8f0"></td></tr>
                            <?php endif;
                            $prevClass = $row['class_id'];
                        ?>
                            <tr>
                                <td><?= (int)$row['total_enrolled'] ?></td>
                                <td><?= htmlspecialchars($row['class_name']) ?></td>
                                <td><strong><?= htmlspecialchars($row['section_name']) ?></strong></td>
                                <td><?= currencyFormat($row['total_expected']) ?></td>
                                <td style="background:#f5f9ff"><?= currencyFormat($row['paid_a']) ?></td>
                                <td style="background:#f5f9ff;color:#c0392b"><?= currencyFormat($row['balance_a']) ?></td>
                                <td style="background:#f0fbf4"><?= currencyFormat($row['paid_b']) ?></td>
                                <td style="background:#f0fbf4;color:#c0392b"><?= currencyFormat($row['balance_b']) ?></td>
                            </tr>
                        <?php endforeach;

                        // Last class subtotal
                        if ($prevClass !== null):
                            $ct = $classTotals[$prevClass]; ?>
                        <tr style="background:#f0f4f8;font-weight:bold">
                            <td><?= $ct['enrolled'] ?></td>
                            <td colspan="2"><strong><?= htmlspecialchars($ct['name']) ?> &mdash; TOTAL</strong></td>
                            <td><strong><?= currencyFormat($ct['expected']) ?></strong></td>
                            <td style="background:#dceeff"><strong><?= currencyFormat($ct['paid_a']) ?></strong></td>
                            <td style="background:#dceeff"><strong><?= currencyFormat($ct['balance_a']) ?></strong></td>
                            <td style="background:#d4f0e4"><strong><?= currencyFormat($ct['paid_b']) ?></strong></td>
                            <td style="background:#d4f0e4"><strong><?= currencyFormat($ct['balance_b']) ?></strong></td>
                        </tr>
                        <?php endif; ?>
                        </tbody>
                        <?php if (!empty($rows) && isset($totals)): ?>
                        <tfoot>
                            <tr style="background:#2c3e50;color:#fff">
                                <th><?= $totals['enrolled'] ?></th>
                                <th colspan="2">GRAND TOTAL</th>
                                <th><?= currencyFormat($totals['expected']) ?></th>
                                <th style="background:#1a3a5c"><?= currencyFormat($totals['paid_a']) ?></th>
                                <th style="background:#1a3a5c"><?= currencyFormat($totals['balance_a']) ?></th>
                                <th style="background:#1a4a2e"><?= currencyFormat($totals['paid_b']) ?></th>
                                <th style="background:#1a4a2e"><?= currencyFormat($totals['balance_b']) ?></th>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <?php endif; // isset($rows) ?>

        <!-- Weekly auto-send schedule -->
        <?php if (is_superadmin_loggedin()): ?>
        <section class="panel">
            <header class="panel-heading" style="cursor:pointer" data-toggle="collapse" data-target="#schedule-panel">
                <h4 class="panel-title">
                    <i class="fas fa-clock"></i> Weekly Auto-Send Schedule
                    <span class="pull-right text-muted" style="font-size:0.85em">
                        <?php if (!empty($schedule['active'])): ?>
                            <span class="label label-success">Active</span>
                            <?= !empty($schedule['last_sent_at']) ? '&mdash; Last sent: ' . date('d M Y', strtotime($schedule['last_sent_at'])) : '' ?>
                        <?php else: ?>
                            <span class="label label-default">Not configured</span>
                        <?php endif; ?>
                    </span>
                </h4>
            </header>
            <div id="schedule-panel" class="collapse <?= !empty($schedule) ? 'in' : '' ?>">
                <?php echo form_open('fees/section_fees_summary_save_schedule', ['class' => 'form-horizontal']); ?>
                <div class="panel-body">
                    <div class="alert alert-info" style="margin-bottom:16px">
                        <i class="fas fa-info-circle"></i>
                        The server cron command for this report is:<br>
                        <code style="display:block;margin-top:6px;word-break:break-all">
                            curl -s "<?= base_url('fees/send_weekly_report') ?>/<?= $branch_id ?>/<?= hash('sha256', $branch_id . config_item('encryption_key')) ?>"
                        </code>
                        Add this to your server's crontab to run on your chosen day, e.g. every Monday at 7:00 AM:<br>
                        <code style="display:block;margin-top:6px">0 7 * * 1 curl -s "..."</code>
                        <br><strong>Note:</strong> WhatsApp delivery requires a WhatsApp Business API account (Termii, Twilio, etc.). Configure the numbers below and contact support to complete the API integration.
                    </div>

                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="control-label">Session</label>
                                <?php
                                    echo form_dropdown('sched_session_id', $session_list, $schedule['session_id'] ?? '',
                                        "class='form-control' data-plugin-selectTwo data-width='100%' data-minimum-results-for-search='Infinity'");
                                ?>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label class="control-label">Term</label>
                                <select name="sched_term" class="form-control"
                                    data-plugin-selectTwo data-width="100%" data-minimum-results-for-search="Infinity">
                                    <option value="" <?= empty($schedule['term']) ? 'selected' : '' ?>>All Terms</option>
                                    <option value="1ST TERM" <?= (($schedule['term'] ?? '') === '1ST TERM') ? 'selected' : '' ?>>1st Term</option>
                                    <option value="2ND TERM" <?= (($schedule['term'] ?? '') === '2ND TERM') ? 'selected' : '' ?>>2nd Term</option>
                                    <option value="3RD TERM" <?= (($schedule['term'] ?? '') === '3RD TERM') ? 'selected' : '' ?>>3rd Term</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label class="control-label">Send on</label>
                                <select name="sched_day_of_week" class="form-control"
                                    data-plugin-selectTwo data-width="100%" data-minimum-results-for-search="Infinity">
                                    <?php
                                    $days = [1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday',6=>'Saturday',0=>'Sunday'];
                                    foreach ($days as $d => $dn): ?>
                                        <option value="<?= $d ?>" <?= (($schedule['day_of_week'] ?? 1) == $d) ? 'selected' : '' ?>><?= $dn ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="form-group">
                                <label class="control-label">&nbsp;</label>
                                <div class="checkbox-custom mt-xs">
                                    <label class="i-checks">
                                        <input type="checkbox" name="sched_include_prev_due" value="1"
                                            <?= !empty($schedule['include_prev_due']) ? 'checked' : '' ?>>
                                        <i></i> Include carry-forward
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label"><i class="fas fa-envelope"></i> Email recipients</label>
                                <input type="text" name="sched_recipients_email"
                                    class="form-control"
                                    placeholder="bursar@school.com, principal@school.com"
                                    value="<?= htmlspecialchars($schedule['recipients_email'] ?? '') ?>">
                                <p class="help-block">Comma-separated email addresses</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label"><i class="fab fa-whatsapp" style="color:#25D366"></i> WhatsApp numbers</label>
                                <input type="text" name="sched_recipients_wa"
                                    class="form-control"
                                    placeholder="+2348012345678, +2348087654321"
                                    value="<?= htmlspecialchars($schedule['recipients_wa'] ?? '') ?>">
                                <p class="help-block">International format. Requires WhatsApp Business API configuration.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <footer class="panel-footer">
                    <div class="row">
                        <div class="col-md-offset-10 col-md-2">
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fas fa-save"></i> Save Schedule
                            </button>
                        </div>
                    </div>
                </footer>
                <?php echo form_close(); ?>
            </div>
        </section>
        <?php endif; ?>

    </div>
</div>

<script>
function printSectionReport() {
    var content = document.getElementById('sfs-report-body').innerHTML;
    var title   = <?= json_encode(
        'Fee Collection Report' .
        (!empty($selTerm) ? ' — ' . $selTerm : '') .
        (!empty($session_label) ? ' — ' . $session_label : '') .
        ' (' . date('d M Y', strtotime($selDateA)) . ' vs ' . date('d M Y', strtotime($selDateB)) . ')'
    ) ?>;
    var w = window.open('', '_blank', 'width=1100,height=800');
    w.document.write(
        '<html><head><title>' + title + '</title>' +
        '<style>' +
        'body{font-family:Arial,sans-serif;font-size:12px;margin:20px}' +
        'h3{text-align:center;margin-bottom:4px}' +
        'p{text-align:center;margin-bottom:12px;color:#555}' +
        'table{width:100%;border-collapse:collapse}' +
        'th,td{border:1px solid #bbb;padding:5px 8px}' +
        'thead th{background:#e0e8f0;font-weight:bold;text-align:center}' +
        'tfoot th{background:#2c3e50;color:#fff}' +
        '.export_title,.panel-btn,.widget-summary{display:none}' +
        '@media print{button{display:none}}' +
        '</style></head><body>' +
        '<h3>' + title + '</h3>' +
        content +
        '<br><button onclick="window.print()">Print</button>' +
        '</body></html>'
    );
    w.document.close();
    setTimeout(function(){ w.print(); }, 400);
}
</script>
