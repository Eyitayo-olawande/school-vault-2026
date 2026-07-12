<?php
$currency_symbol = $global_config['currency_symbol'];
$selTerm = isset($term) ? $term : '';
?>
<div class="row">
    <div class="col-md-12">

        <!-- Filter form -->
        <section class="panel">
            <header class="panel-heading">
                <h4 class="panel-title"><?= translate('select_ground') ?></h4>
            </header>
            <?php echo form_open($this->uri->uri_string(), ['class' => 'validate']); ?>
            <div class="panel-body">
                <div class="row mb-sm">

                    <?php if (is_superadmin_loggedin()): ?>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="control-label"><?= translate('branch') ?> <span class="required">*</span></label>
                            <?php
                                $arrayBranch = $this->app_lib->getSelectList('branch');
                                echo form_dropdown('branch_id', $arrayBranch, set_value('branch_id'),
                                    "class='form-control' onchange='getClassByBranch(this.value)'
                                     required data-plugin-selectTwo data-width='100%'
                                     data-minimum-results-for-search='Infinity'");
                            ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="control-label">Academic Session <span class="required">*</span></label>
                            <?php
                                echo form_dropdown('session_id', $session_list, set_value('session_id'),
                                    "class='form-control' required
                                     data-plugin-selectTwo data-width='100%'
                                     data-minimum-results-for-search='Infinity'");
                            ?>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="control-label">Academic Term</label>
                            <select name="term" class="form-control"
                                data-plugin-selectTwo data-width="100%" data-minimum-results-for-search="Infinity">
                                <option value="" <?= empty($selTerm) ? 'selected' : '' ?>>All Terms</option>
                                <option value="1ST TERM" <?= ($selTerm === '1ST TERM') ? 'selected' : '' ?>>1st Term</option>
                                <option value="2ND TERM" <?= ($selTerm === '2ND TERM') ? 'selected' : '' ?>>2nd Term</option>
                                <option value="3RD TERM" <?= ($selTerm === '3RD TERM') ? 'selected' : '' ?>>3rd Term</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="control-label"><?= translate('class') ?> <small class="text-muted">(<?= translate('optional') ?>)</small></label>
                            <?php
                                $arrayClass = $this->app_lib->getClass($branch_id);
                                echo form_dropdown('class_id', $arrayClass, set_value('class_id'),
                                    "class='form-control' id='class_id'
                                     data-plugin-selectTwo data-width='100%'
                                     data-minimum-results-for-search='Infinity'");
                            ?>
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

        <!-- KPI summary cards -->
        <?php if (isset($totals) && !empty($rows)):
            $overall_rate = $totals['expected'] > 0
                ? round(($totals['collected'] / $totals['expected']) * 100, 1) : 0;
        ?>
        <div class="row mb-md">
            <div class="col-md-2 col-sm-4">
                <div class="widget-summary widget-summary-sm">
                    <div class="widget-summary-col">
                        <div class="summary">
                            <h4 class="title">Total Classes</h4>
                            <div class="info"><strong class="amount"><?= count($rows) ?></strong></div>
                        </div>
                        <div class="summary-footer"><span class="text-muted"><?= $totals['enrolled'] ?> enrolled</span></div>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-4">
                <div class="widget-summary widget-summary-sm bg-primary text-light" style="border-radius:4px;padding:10px">
                    <div class="widget-summary-col">
                        <div class="summary">
                            <h4 class="title" style="color:#fff">Total Expected</h4>
                            <div class="info"><strong class="amount" style="color:#fff"><?= currencyFormat($totals['expected']) ?></strong></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-4">
                <div class="widget-summary widget-summary-sm bg-success text-light" style="border-radius:4px;padding:10px">
                    <div class="widget-summary-col">
                        <div class="summary">
                            <h4 class="title" style="color:#fff">Collected</h4>
                            <div class="info"><strong class="amount" style="color:#fff"><?= currencyFormat($totals['collected']) ?></strong></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-4">
                <div class="widget-summary widget-summary-sm bg-danger text-light" style="border-radius:4px;padding:10px">
                    <div class="widget-summary-col">
                        <div class="summary">
                            <h4 class="title" style="color:#fff">Outstanding</h4>
                            <div class="info"><strong class="amount" style="color:#fff"><?= currencyFormat($totals['outstanding']) ?></strong></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-4">
                <div class="widget-summary widget-summary-sm bg-warning text-light" style="border-radius:4px;padding:10px">
                    <div class="widget-summary-col">
                        <div class="summary">
                            <h4 class="title" style="color:#fff">Collection Rate</h4>
                            <div class="info"><strong class="amount" style="color:#fff"><?= $overall_rate ?>%</strong></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-2 col-sm-4">
                <div class="widget-summary widget-summary-sm">
                    <div class="widget-summary-col">
                        <div class="summary">
                            <h4 class="title">Students Paid</h4>
                            <div class="info"><strong class="amount text-success"><?= $totals['students_paid'] ?></strong></div>
                        </div>
                        <div class="summary-footer"><span class="text-danger"><?= $totals['students_not_paid'] ?> unpaid</span></div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Results table -->
        <section class="panel appear-animation" data-appear-animation="<?= $global_config['animations'] ?>" data-appear-animation-delay="100">
            <header class="panel-heading">
                <h4 class="panel-title">
                    <i class="fas fa-table"></i>
                    Class-wise Fees Summary
                    <?php if (!empty($session_label)): ?>
                        &mdash; <span class="text-muted" style="font-size:0.9em"><?= htmlspecialchars($session_label) ?><?= !empty($selTerm) ? ' &mdash; ' . htmlspecialchars($selTerm) : '' ?></span>
                    <?php endif; ?>
                    <div class="panel-btn">
                        <a href="<?= base_url('fees/export_classwise_fees_csv?session_id=' . (isset($session_id) ? $session_id : '') . '&class_id=' . (isset($class_id) ? urlencode($class_id) : '')) ?>"
                            class="btn btn-xs btn-default btn-circle">
                            <i class="fas fa-file-csv"></i> CSV Export
                        </a>
                    </div>
                </h4>
            </header>
            <div class="panel-body">
                <div class="mb-md mt-md">
                    <div class="export_title">Class-wise Fees Summary<?= !empty($session_label) ? ' — ' . htmlspecialchars($session_label) : '' ?></div>
                    <table class="table table-bordered table-condensed table-hover mb-none tbr-top table-export">
                        <thead>
                            <tr>
                                <th><?= translate('sl') ?></th>
                                <th><?= translate('class') ?></th>
                                <th><?= translate('total_students') ?></th>
                                <th>Expected Amount</th>
                                <th>Collected</th>
                                <th>Outstanding Balance</th>
                                <th>Collection Rate</th>
                                <th>Students Paid</th>
                                <th>Students Unpaid</th>
                                <th class="no-sort no-export"><?= translate('action') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($rows)): ?>
                            <tr>
                                <td colspan="10" class="text-center text-muted">No data found for the selected term.</td>
                            </tr>
                            <?php else: ?>
                            <?php $sl = 1; foreach ($rows as $row):
                                $rate = $row['total_expected'] > 0
                                    ? round(($row['total_collected'] / $row['total_expected']) * 100, 1) : 0;
                                if ($rate >= 80) {
                                    $rate_class = 'text-success';
                                } elseif ($rate >= 50) {
                                    $rate_class = 'text-warning';
                                } else {
                                    $rate_class = 'text-danger';
                                }
                            ?>
                            <tr>
                                <td><?= $sl++ ?></td>
                                <td><strong><?= htmlspecialchars($row['class_name']) ?></strong></td>
                                <td><?= $row['total_enrolled'] ?></td>
                                <td><?= currencyFormat($row['total_expected']) ?></td>
                                <td><?= currencyFormat($row['total_collected']) ?></td>
                                <td><?= currencyFormat($row['total_outstanding']) ?></td>
                                <td>
                                    <span class="<?= $rate_class ?>"><strong><?= $rate ?>%</strong></span>
                                    <div class="progress progress-xs mt-xs mb-none" style="height:4px">
                                        <div class="progress-bar <?= $rate >= 80 ? 'progress-bar-success' : ($rate >= 50 ? 'progress-bar-warning' : 'progress-bar-danger') ?>"
                                            style="width:<?= $rate ?>%"></div>
                                    </div>
                                </td>
                                <td>
                                    <span class="text-success"><?= $row['students_paid'] ?></span>
                                    <?php if ($row['total_enrolled'] > 0): ?>
                                    <small class="text-muted">/ <?= $row['total_enrolled'] ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['students_not_paid'] > 0): ?>
                                    <span class="text-danger"><?= $row['students_not_paid'] ?></span>
                                    <?php else: ?>
                                    <span class="text-success">0</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?= base_url('fees/due_report') ?>"
                                        class="btn btn-xs btn-default btn-circle"
                                        title="View outstanding students in this class"
                                        onclick="document.getElementById('due_class_id_<?= $row['class_id'] ?>').click(); return false;">
                                        <i class="fas fa-list"></i> Due
                                    </a>
                                    <a href="<?= base_url('fees/student_fees_report') ?>"
                                        class="btn btn-xs btn-primary btn-circle"
                                        title="Detailed fees report for this class">
                                        <i class="fas fa-search"></i> Detail
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                        <?php if (!empty($rows) && isset($totals)):
                            $tot_rate = $totals['expected'] > 0
                                ? round(($totals['collected'] / $totals['expected']) * 100, 1) : 0;
                        ?>
                        <tfoot>
                            <tr class="active">
                                <th></th>
                                <th>TOTAL</th>
                                <th><?= $totals['enrolled'] ?></th>
                                <th><?= currencyFormat($totals['expected']) ?></th>
                                <th><?= currencyFormat($totals['collected']) ?></th>
                                <th><?= currencyFormat($totals['outstanding']) ?></th>
                                <th><?= $tot_rate ?>%</th>
                                <th><?= $totals['students_paid'] ?></th>
                                <th><?= $totals['students_not_paid'] ?></th>
                                <th></th>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </section>

        <?php endif; ?>

    </div>
</div>
