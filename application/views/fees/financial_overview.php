<?php $currency_symbol = $global_config['currency_symbol']; ?>

<section class="panel mb-md">
    <header class="panel-heading">
        <h4 class="panel-title"><?= translate('select_ground') ?></h4>
    </header>
    <form method="get" action="<?= base_url('fees/financial_overview') ?>">
        <div class="panel-body">
            <div class="row">
                <?php if (is_superadmin_loggedin()): ?>
                <div class="col-md-2">
                    <div class="form-group">
                        <label class="control-label"><?= translate('branch') ?></label>
                        <select name="branch_id" class="form-control"
                            data-plugin-selectTwo data-width="100%" data-minimum-results-for-search="Infinity">
                            <?php foreach ($this->app_lib->getSelectList('branch') as $id => $name): ?>
                                <?php if (!$id) continue; ?>
                                <option value="<?= $id ?>" <?= ($id == $branch_id) ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <?php endif; ?>
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="control-label">Academic Session</label>
                        <select name="session_id" class="form-control"
                            data-plugin-selectTwo data-width="100%" data-minimum-results-for-search="Infinity">
                            <?php foreach ($session_list as $sid => $label): ?>
                                <option value="<?= $sid ?>" <?= ($sid == $session_id) ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label class="control-label">Academic Term</label>
                        <select name="term" class="form-control"
                            data-plugin-selectTwo data-width="100%" data-minimum-results-for-search="Infinity">
                            <option value="" <?= empty($term) ? 'selected' : '' ?>>All Terms</option>
                            <option value="1ST TERM" <?= ($term === '1ST TERM') ? 'selected' : '' ?>>1st Term</option>
                            <option value="2ND TERM" <?= ($term === '2ND TERM') ? 'selected' : '' ?>>2nd Term</option>
                            <option value="3RD TERM" <?= ($term === '3RD TERM') ? 'selected' : '' ?>>3rd Term</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-group">
                        <label class="control-label">Date Range <small class="text-muted">(optional)</small></label>
                        <div class="input-group">
                            <span class="input-group-addon"><i class="fas fa-calendar-check"></i></span>
                            <input type="text" class="form-control daterange" name="daterange"
                                value="<?= htmlspecialchars($daterange) ?>"
                                placeholder="All dates" />
                        </div>
                    </div>
                </div>
                <div class="col-md-1" style="padding-top:25px">
                    <button type="submit" class="btn btn-default btn-block">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                </div>
                <?php if ($daterange): ?>
                <div class="col-md-1" style="padding-top:25px">
                    <a href="<?= base_url('fees/financial_overview?session_id=' . $session_id . '&term=' . urlencode($term) . (is_superadmin_loggedin() ? '&branch_id=' . $branch_id : '')) ?>"
                        class="btn btn-link text-muted">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </form>
</section>

<?php
$activeSessionID = get_session_id();
if ($session_id != $activeSessionID && isset($session_list[$session_id])):
?>
<div class="alert alert-warning alert-dismissible">
    <button type="button" class="close" data-dismiss="alert">&times;</button>
    <i class="fas fa-history"></i>
    <strong>Viewing historical session — <?= htmlspecialchars($session_list[$session_id]) ?></strong>
    &mdash; figures below reflect that session only.
    <a href="<?= base_url('fees/financial_overview') ?>" class="btn btn-xs btn-warning ml-sm">Reset to Active</a>
</div>
<?php endif; ?>

<div class="row">

    <!-- KPI Cards -->
    <div class="col-md-3 col-sm-6">
        <div class="widget-summary">
            <div class="widget-summary-col widget-summary-col-icon">
                <div class="summary-icon bg-primary"><i class="fas fa-file-invoice-dollar"></i></div>
            </div>
            <div class="widget-summary-col">
                <div class="summary">
                    <h4 class="title">Total Billed</h4>
                    <div class="info"><strong class="amount text-primary"><?=currencyFormat($overview['expected'])?></strong></div>
                </div>
                <div class="summary-footer"><span class="text-muted">Full term</span></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="widget-summary">
            <div class="widget-summary-col widget-summary-col-icon">
                <div class="summary-icon bg-success"><i class="fas fa-check-circle"></i></div>
            </div>
            <div class="widget-summary-col">
                <div class="summary">
                    <h4 class="title"><?= $overview['date_filtered'] ? 'Collected (Period)' : 'Collected' ?></h4>
                    <div class="info"><strong class="amount text-success"><?=currencyFormat($overview['collected'])?></strong></div>
                </div>
                <?php if ($overview['date_filtered']): ?>
                <div class="summary-footer">
                    <span class="text-muted">Full term: <?=currencyFormat($overview['collected_full'])?></span>
                </div>
                <?php else: ?>
                <div class="summary-footer"><span class="text-muted">Full term</span></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="widget-summary">
            <div class="widget-summary-col widget-summary-col-icon">
                <div class="summary-icon bg-danger"><i class="fas fa-exclamation-circle"></i></div>
            </div>
            <div class="widget-summary-col">
                <div class="summary">
                    <h4 class="title">Outstanding</h4>
                    <div class="info"><strong class="amount text-danger"><?=currencyFormat($overview['outstanding'])?></strong></div>
                </div>
                <div class="summary-footer"><span class="text-muted">Full term</span></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="widget-summary">
            <div class="widget-summary-col widget-summary-col-icon">
                <div class="summary-icon bg-warning"><i class="fas fa-percentage"></i></div>
            </div>
            <div class="widget-summary-col">
                <div class="summary">
                    <h4 class="title">Collection Rate</h4>
                    <div class="info"><strong class="amount"><?=number_format($overview['collection_rate'], 1)?>%</strong></div>
                </div>
                <div class="summary-footer"><span class="text-muted">Full term</span></div>
            </div>
        </div>
    </div>
</div>

<?php if ($overview['open_exceptions'] > 0): ?>
<div class="row mb-md">
    <div class="col-md-12">
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <strong><?=$overview['open_exceptions']?></strong> open financial exception(s) require attention.
            <a href="<?=base_url('fees/financial_exceptions')?>" class="btn btn-xs btn-warning ml-sm">View Exceptions</a>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row">
    <!-- Monthly Collection Chart -->
    <div class="col-md-8">
        <section class="panel">
            <header class="panel-heading">
                <h4 class="panel-title"><i class="fas fa-chart-bar"></i> Monthly Collection
                    <?= $daterange ? '(' . htmlspecialchars($daterange) . ')' : '(Last 6 Months)' ?>
                </h4>
            </header>
            <div class="panel-body">
                <canvas id="monthlyChart" height="120"></canvas>
            </div>
        </section>
    </div>

    <!-- Collection Rate Gauge -->
    <div class="col-md-4">
        <section class="panel">
            <header class="panel-heading">
                <h4 class="panel-title"><i class="fas fa-tachometer-alt"></i> Collection Rate</h4>
            </header>
            <div class="panel-body text-center">
                <div style="font-size:56px;font-weight:700;color:<?=($overview['collection_rate']>=80?'#27ae60':($overview['collection_rate']>=50?'#f39c12':'#e74c3c'))?>">
                    <?=number_format($overview['collection_rate'],1)?>%
                </div>
                <div class="progress mt-sm">
                    <div class="progress-bar <?=($overview['collection_rate']>=80?'progress-bar-success':($overview['collection_rate']>=50?'progress-bar-warning':'progress-bar-danger'))?>"
                        role="progressbar" style="width:<?=min($overview['collection_rate'],100)?>%">
                    </div>
                </div>
                <small class="text-muted"><?=currencyFormat($overview['collected'])?> of <?=currencyFormat($overview['expected'])?></small>
            </div>
        </section>
    </div>
</div>

<div class="row">
    <!-- Top Outstanding Students -->
    <div class="col-md-6">
        <section class="panel">
            <header class="panel-heading">
                <h4 class="panel-title"><i class="fas fa-exclamation-circle text-danger"></i> Top 10 Outstanding</h4>
            </header>
            <div class="panel-body">
                <table class="table table-condensed table-hover mb-none">
                    <thead><tr><th>Student</th><th>Class</th><th class="text-right">Balance</th></tr></thead>
                    <tbody>
                        <?php foreach ($overview['top_owed'] as $row): ?>
                        <tr>
                            <td>
                                <a href="<?=base_url('fees/student_ledger/'.$row['student_id'])?>" target="_blank">
                                    <?=htmlspecialchars($row['student_name'])?>
                                </a>
                                <br><small class="text-muted"><?=htmlspecialchars($row['register_no'])?></small>
                            </td>
                            <td><?=htmlspecialchars($row['class_name'])?></td>
                            <td class="text-right text-danger"><?=currencyFormat($row['balance'])?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($overview['top_owed'])): ?>
                        <tr><td colspan="3" class="text-success text-center">All fees cleared!</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <!-- Recent Payments -->
    <div class="col-md-6">
        <section class="panel">
            <header class="panel-heading">
                <h4 class="panel-title"><i class="fas fa-receipt text-success"></i> Recent Payments</h4>
            </header>
            <div class="panel-body">
                <table class="table table-condensed table-hover mb-none">
                    <thead><tr><th>Date</th><th>Student</th><th>Type</th><th class="text-right">Amount</th></tr></thead>
                    <tbody>
                        <?php foreach ($overview['recent_payments'] as $row): ?>
                        <tr>
                            <td><?=_d($row['date'])?></td>
                            <td>
                                <?=htmlspecialchars($row['student_name'])?>
                                <br><small class="text-muted"><?=htmlspecialchars($row['register_no'])?></small>
                            </td>
                            <td><?=htmlspecialchars($row['type_name'])?></td>
                            <td class="text-right text-success"><?=currencyFormat($row['amount'])?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($overview['recent_payments'])): ?>
                        <tr><td colspan="4" class="text-muted text-center">No payments recorded yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>

<script src="<?= base_url('assets/vendor/chartjs/chart.min.js') ?>"></script>
<script>
$(document).ready(function() {
    var monthlyData = <?php
        $labels = array_column($overview['monthly'], 'month');
        $values = array_column($overview['monthly'], 'net');
        echo json_encode(['labels' => $labels, 'values' => $values]);
    ?>;

    if (typeof Chart !== 'undefined' && monthlyData.labels.length > 0) {
        new Chart(document.getElementById('monthlyChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: monthlyData.labels,
                datasets: [{
                    label: 'Net Collection',
                    data: monthlyData.values,
                    backgroundColor: 'rgba(39,174,96,0.7)',
                    borderColor: '#27ae60',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: { y: { beginAtZero: true } }
            }
        });
    } else if (monthlyData.labels.length === 0) {
        $('#monthlyChart').replaceWith('<p class="text-muted text-center mt-md">No payment data for the last 6 months.</p>');
    }
});
</script>
