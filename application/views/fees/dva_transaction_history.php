<?php $currency_symbol = $global_config['currency_symbol']; ?>
<div class="row">
    <div class="col-md-12">
        <section class="panel">
            <header class="panel-heading">
                <h4 class="panel-title"><?=translate('select_ground')?></h4>
            </header>
            <?php echo form_open($this->uri->uri_string(), array('class' => 'validate')); ?>
            <div class="panel-body">
                <div class="row mb-sm">
                    <div class="col-md-6 mb-sm">
                        <div class="form-group">
                            <label class="control-label"><?=translate('date')?> <span class="required">*</span></label>
                            <div class="input-group">
                                <span class="input-group-addon"><i class="fas fa-calendar-check"></i></span>
                                <input type="text" class="form-control daterange" name="daterange"
                                    value="<?php echo set_value('daterange', date('Y/m/d', strtotime('-30 days')) . ' - ' . date('Y/m/d')); ?>"
                                    required />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <footer class="panel-footer">
                <div class="row">
                    <div class="col-md-offset-10 col-md-2">
                        <button type="submit" name="search" value="1" class="btn btn-default btn-block">
                            <i class="fas fa-filter"></i> <?=translate('filter')?>
                        </button>
                    </div>
                </div>
            </footer>
            <?php echo form_close(); ?>
        </section>

        <?php if (isset($transactions)): ?>
        <section class="panel appear-animation" data-appear-animation="<?=$global_config['animations']?>"
            data-appear-animation-delay="100">
            <header class="panel-heading">
                <h4 class="panel-title"><i class="fas fa-exchange-alt"></i> <?=translate('dva_transaction_history')?></h4>
            </header>
            <div class="panel-body">
                <div class="mb-md mt-md">
                    <div class="export_title"><?=translate('dva_transaction_history')?></div>
                    <table class="table table-bordered table-condensed table-hover mb-none tbr-top table-export">
                        <thead>
                            <tr>
                                <th><?=translate('date')?></th>
                                <th><?=translate('reference')?></th>
                                <th><?=translate('student')?></th>
                                <th><?=translate('register_no')?></th>
                                <th><?=translate('dva_account_number')?></th>
                                <th><?=translate('sender')?></th>
                                <th><?=translate('narration')?></th>
                                <th class="text-right"><?=translate('gateway_amount')?></th>
                                <th class="text-right"><?=translate('applied_amount')?></th>
                                <th><?=translate('status')?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $total_gateway = 0;
                            $total_applied = 0;
                            foreach ($transactions as $row):
                                $total_gateway += $row['gateway_amount'];
                                $total_applied += $row['applied_amount'];
                                $matched = ($row['allocation_count'] > 0);
                            ?>
                            <tr class="<?=(!$matched ? 'warning' : '')?>">
                                <td><?=_d($row['paid_date'])?></td>
                                <td><code><?=htmlspecialchars($row['reference'])?></code></td>
                                <td><?=htmlspecialchars($row['student_name'] ?: '—')?></td>
                                <td><?=htmlspecialchars($row['register_no'] ?: '—')?></td>
                                <td><?=htmlspecialchars($row['account_number'] ?: '—')?></td>
                                <td><?=htmlspecialchars($row['authorization_sender_name'])?></td>
                                <td><?=htmlspecialchars($row['authorization_narration'] ?: '—')?></td>
                                <td class="text-right"><?=currencyFormat($row['gateway_amount'])?></td>
                                <td class="text-right <?=($row['applied_amount'] < $row['gateway_amount'] ? 'text-warning' : 'text-success')?>">
                                    <?=currencyFormat($row['applied_amount'])?>
                                </td>
                                <td>
                                    <?php if (!$matched): ?>
                                    <span class="label label-warning">Unmatched</span>
                                    <?php elseif ($row['applied_amount'] < $row['gateway_amount']): ?>
                                    <span class="label label-warning">Partial</span>
                                    <?php else: ?>
                                    <span class="label label-success">Applied</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="7"><?=translate('total')?></th>
                                <th class="text-right"><?=currencyFormat($total_gateway)?></th>
                                <th class="text-right"><?=currencyFormat($total_applied)?></th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </section>
        <?php endif; ?>
    </div>
</div>
