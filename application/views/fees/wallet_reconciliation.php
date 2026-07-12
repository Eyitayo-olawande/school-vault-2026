<?php
$total_balance        = array_sum(array_column($wallets, 'wallet_balance'));
$grand_count          = count($wallets);
$parent_wallets       = isset($parent_wallets) ? $parent_wallets : [];
$parent_total_balance = array_sum(array_column($parent_wallets, 'wallet_balance'));
?>
<div class="row">
    <div class="col-md-12">
        <section class="panel">
            <header class="panel-heading">
                <h4 class="panel-title">DVA Wallet Reconciliation — Unspent Balances</h4>
                <span class="text-muted" style="font-size:13px;">
                    <?php echo $grand_count; ?> student(s) &nbsp;|&nbsp;
                    Total unspent: <strong><?php echo currencyFormat($total_balance); ?></strong>
                    &nbsp;|&nbsp; Session: <?php echo get_session_id(); ?>
                </span>
            </header>
            <div class="panel-body">
                <?php if (empty($wallets)): ?>
                    <div class="alert alert-success">
                        <i class="fa fa-check-circle"></i> No unspent wallet balances — all DVA payments have been applied.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" id="wallet-recon-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Student</th>
                                    <th>Reg No</th>
                                    <th>Email</th>
                                    <th class="text-right">Wallet Balance</th>
                                    <th class="text-right">Applied This Session</th>
                                    <th class="text-center">Allocs (Session)</th>
                                    <th>Last Updated</th>
                                    <th>DVA References</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($wallets as $i => $w): ?>
                                <tr class="<?php echo ($w['allocs_in_session'] == 0) ? 'danger' : ''; ?>">
                                    <td><?php echo $i + 1; ?></td>
                                    <td><?php echo html_escape($w['student_name']); ?></td>
                                    <td><?php echo html_escape($w['register_no']); ?></td>
                                    <td><?php echo html_escape($w['email']); ?></td>
                                    <td class="text-right">
                                        <strong><?php echo currencyFormat($w["wallet_balance"]); ?></strong>
                                    </td>
                                    <td class="text-right">
                                        <?php echo currencyFormat($w["total_wallet_applied_session"]); ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($w['allocs_in_session'] == 0): ?>
                                            <span class="label label-danger">None</span>
                                        <?php else: ?>
                                            <?php echo (int) $w['allocs_in_session']; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo html_escape($w['updated_at']); ?></td>
                                    <td>
                                        <small><?php echo html_escape(implode(', ', array_filter(explode(',', $w['payment_gateway_reference'])))); ?></small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="4" class="text-right">Total</th>
                                    <th class="text-right"><?php echo currencyFormat($total_balance); ?></th>
                                    <th colspan="4"></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <p class="text-danger">
                        <i class="fa fa-exclamation-triangle"></i>
                        Rows highlighted in <span style="background:#f2dede;padding:2px 6px;">red</span>
                        have no fee allocation in the current session — the student may have transferred, left, or the payment arrived for a different session.
                        These balances cannot be auto-applied and require manual admin action.
                    </p>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>

<!-- Phase 6: Family DVA — Parent Wallet Reconciliation -->
<div class="row">
    <div class="col-md-12">
        <section class="panel">
            <header class="panel-heading">
                <h4 class="panel-title">Family DVA — Parent Wallet Reconciliation</h4>
                <span class="text-muted" style="font-size:13px;">
                    <?php echo count($parent_wallets); ?> parent(s) &nbsp;|&nbsp;
                    Total unspent: <strong><?php echo currencyFormat($parent_total_balance); ?></strong>
                </span>
            </header>
            <div class="panel-body">
                <?php if (empty($parent_wallets)): ?>
                    <div class="alert alert-success">
                        <i class="fa fa-check-circle"></i> No unspent parent wallet balances.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" id="parent-wallet-recon-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Parent</th>
                                    <th>Email</th>
                                    <th class="text-center">Children</th>
                                    <th class="text-right">Wallet Balance</th>
                                    <th class="text-right">Applied This Session</th>
                                    <th>Last Updated</th>
                                    <th>DVA References</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($parent_wallets as $i => $pw): ?>
                                <tr class="<?php echo ($pw['children_count'] == 0) ? 'danger' : ''; ?>">
                                    <td><?php echo $i + 1; ?></td>
                                    <td><?php echo html_escape($pw['parent_name']); ?></td>
                                    <td><?php echo html_escape($pw['email']); ?></td>
                                    <td class="text-center"><?php echo (int) $pw['children_count']; ?></td>
                                    <td class="text-right">
                                        <strong><?php echo currencyFormat($pw["wallet_balance"]); ?></strong>
                                    </td>
                                    <td class="text-right">
                                        <?php echo currencyFormat($pw["total_wallet_applied_session"]); ?>
                                    </td>
                                    <td><?php echo html_escape($pw['updated_at']); ?></td>
                                    <td>
                                        <small><?php echo html_escape(implode(', ', array_filter(explode(',', $pw['payment_gateway_reference'])))); ?></small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="4" class="text-right">Total</th>
                                    <th class="text-right"><?php echo currencyFormat($parent_total_balance); ?></th>
                                    <th colspan="3"></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <?php if (!empty($unmatched_gateway)): ?>
        <section class="panel appear-animation" data-appear-animation="<?=$global_config['animations']?>" data-appear-animation-delay="200">
            <header class="panel-heading">
                <h4 class="panel-title text-danger">
                    <i class="fas fa-exclamation-triangle"></i> Unmatched Gateway Payments
                    <small class="text-muted">(received by Paystack but not applied to any student)</small>
                </h4>
            </header>
            <div class="panel-body">
                <table class="table table-bordered table-condensed table-hover" id="unmatched-gateway-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Reference</th>
                            <th>Email</th>
                            <th>Sender</th>
                            <th>Narration</th>
                            <th class="text-right">Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($unmatched_gateway as $row): ?>
                        <tr>
                            <td><?=_d($row['paid_date'])?></td>
                            <td><code><?=htmlspecialchars($row['reference'])?></code></td>
                            <td><?=htmlspecialchars($row['customer_email'])?></td>
                            <td><?=htmlspecialchars($row['authorization_sender_name'])?></td>
                            <td><?=htmlspecialchars($row['authorization_narration'] ?: '—')?></td>
                            <td class="text-right text-danger"><?=currencyFormat($row['gateway_amount'])?></td>
                            <td><span class="label label-warning">Unmatched</span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php endif; ?>
    </div>
</div>

<script>
$(document).ready(function() {
    if ($.fn.DataTable) {
        $('#wallet-recon-table').DataTable({
            order: [[4, 'desc']],
            pageLength: 50,
            language: { search: 'Filter:' }
        });
        $('#parent-wallet-recon-table').DataTable({
            order: [[4, 'desc']],
            pageLength: 25,
            language: { search: 'Filter:' }
        });
        $('#unmatched-gateway-table').DataTable({
            order: [[0, 'desc']],
            pageLength: 25,
            language: { search: 'Filter:' }
        });
    }
});
</script>
