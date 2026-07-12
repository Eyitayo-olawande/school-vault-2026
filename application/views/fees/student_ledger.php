<?php $currency_symbol = $global_config['currency_symbol']; ?>
<div class="row">
    <div class="col-md-12">

        <!-- Student Info Card -->
        <section class="panel">
            <header class="panel-heading">
                <h4 class="panel-title">
                    <i class="fas fa-user-graduate"></i>
                    <?php echo $basic['first_name'] . ' ' . $basic['last_name']; ?>
                    <small class="text-muted"><?php echo $basic['register_no']; ?></small>
                    <div class="panel-btn">
                        <a href="<?=base_url('fees/student_ledger/' . $student_id)?>" onclick="window.print(); return false;"
                            class="btn btn-xs btn-default btn-circle">
                            <i class="fas fa-print"></i> <?=translate('print')?>
                        </a>
                        <a href="javascript:history.back();" class="btn btn-xs btn-default btn-circle">
                            <i class="fas fa-arrow-left"></i> <?=translate('back')?>
                        </a>
                    </div>
                </h4>
            </header>
            <div class="panel-body">
                <div class="row">
                    <div class="col-md-3"><strong><?=translate('class')?>:</strong> <?=htmlspecialchars($basic['class_name'])?> (<?=htmlspecialchars($basic['section_name'])?>)</div>
                    <div class="col-md-3"><strong><?=translate('father_name')?>:</strong> <?=htmlspecialchars($basic['father_name'])?></div>
                    <div class="col-md-3"><strong><?=translate('school')?>:</strong> <?=htmlspecialchars($basic['school_name'])?></div>
                    <div class="col-md-3"><strong><?=translate('session')?>:</strong> <?=htmlspecialchars($session_label)?></div>
                </div>
            </div>
        </section>

        <!-- Fee Type Breakdown -->
        <section class="panel appear-animation" data-appear-animation="<?=$global_config['animations']?>" data-appear-animation-delay="100">
            <header class="panel-heading">
                <h4 class="panel-title"><i class="fas fa-list-alt"></i> <?=translate('fees_allocation')?></h4>
            </header>
            <div class="panel-body">
                <?php
                $grand_total    = 0;
                $grand_paid     = 0;
                $grand_discount = 0;
                $grand_fine     = 0;
                $grand_balance  = 0;
                ?>
                <table class="table table-bordered table-condensed mb-none">
                    <thead>
                        <tr>
                            <th><?=translate('fees_type')?></th>
                            <th><?=translate('due_date')?></th>
                            <th class="text-right"><?=translate('amount')?></th>
                            <th class="text-right"><?=translate('paid')?></th>
                            <th class="text-right"><?=translate('discount')?></th>
                            <th class="text-right"><?=translate('fine')?></th>
                            <th class="text-right"><?=translate('balance')?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($details as $row):
                            $grand_total    += $row['amount'];
                            $grand_paid     += $row['paid'];
                            $grand_discount += $row['discount'];
                            $grand_fine     += $row['fine'];
                            $grand_balance  += $row['balance'];
                        ?>
                        <tr>
                            <td><?=htmlspecialchars($row['name'])?></td>
                            <td><?=_d($row['due_date'])?></td>
                            <td class="text-right"><?=currencyFormat($row['amount'])?></td>
                            <td class="text-right"><?=currencyFormat($row['paid'])?></td>
                            <td class="text-right"><?=currencyFormat($row['discount'])?></td>
                            <td class="text-right"><?=currencyFormat($row['fine'])?></td>
                            <td class="text-right <?=($row['balance'] > 0 ? 'text-danger' : 'text-success')?>">
                                <?=currencyFormat($row['balance'])?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="active">
                            <th colspan="2"><?=translate('total')?></th>
                            <th class="text-right"><?=currencyFormat($grand_total)?></th>
                            <th class="text-right"><?=currencyFormat($grand_paid)?></th>
                            <th class="text-right"><?=currencyFormat($grand_discount)?></th>
                            <th class="text-right"><?=currencyFormat($grand_fine)?></th>
                            <th class="text-right <?=($grand_balance > 0 ? 'text-danger' : 'text-success')?>">
                                <?=currencyFormat($grand_balance)?>
                            </th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>

        <!-- Payment Transactions -->
        <section class="panel appear-animation" data-appear-animation="<?=$global_config['animations']?>" data-appear-animation-delay="200">
            <header class="panel-heading">
                <h4 class="panel-title"><i class="fas fa-receipt"></i> <?=translate('payment_transactions')?></h4>
            </header>
            <div class="panel-body">
                <?php if (empty($transactions)): ?>
                <p class="text-muted text-center"><?=translate('no_records_found')?></p>
                <?php else: ?>
                <table class="table table-bordered table-condensed table-hover mb-none">
                    <thead>
                        <tr>
                            <th><?=translate('receipt_no')?></th>
                            <th><?=translate('date')?></th>
                            <th><?=translate('fees_type')?></th>
                            <th><?=translate('payment_via')?></th>
                            <th class="text-right"><?=translate('amount')?></th>
                            <th class="text-right"><?=translate('discount')?></th>
                            <th class="text-right"><?=translate('fine')?></th>
                            <th><?=translate('collect_by')?></th>
                            <th><?=translate('status')?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $txn): ?>
                        <tr class="<?=($txn['status'] === 'reversed' ? 'text-muted' : '')?>">
                            <td><?=htmlspecialchars($txn['receipt_no'] ?: '—')?></td>
                            <td><?=_d($txn['date'])?></td>
                            <td><?=htmlspecialchars($txn['type_name'])?></td>
                            <td><?=htmlspecialchars($txn['payment_method'] ?: $txn['pay_via'])?></td>
                            <td class="text-right"><?=currencyFormat($txn['amount'])?></td>
                            <td class="text-right"><?=currencyFormat($txn['discount'])?></td>
                            <td class="text-right"><?=currencyFormat($txn['fine'])?></td>
                            <td><?php
                                if ($txn['collect_by'] === 'online' || $txn['collect_by'] === 'wallet') {
                                    echo ucfirst($txn['collect_by']);
                                } else {
                                    echo htmlspecialchars(get_type_name_by_id('staff', $txn['collect_by']));
                                }
                            ?></td>
                            <td>
                                <?php if ($txn['status'] === 'reversed'): ?>
                                <span class="label label-danger"><?=translate('reversed')?></span>
                                <?php else: ?>
                                <span class="label label-success"><?=translate('paid')?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </section>

    </div>
</div>
