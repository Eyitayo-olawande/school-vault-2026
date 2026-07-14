<?php defined('BASEPATH') or exit('No direct script access allowed');
$currency = $global_config['currency_symbol'] ?? '';
?>
<div class="row">
  <div class="col-md-12">
    <section class="panel">
      <header class="panel-heading">
        <h4 class="panel-title"><i class="fas fa-exclamation-circle text-danger"></i> Financial Exceptions</h4>
      </header>
      <div class="panel-body">

        <?php if (is_superadmin_loggedin()): ?>
        <div class="mb-sm">
          <a href="<?= base_url('fees/fix_duplicate_term_allocations') ?>"
             class="btn btn-danger btn-sm"
             onclick="return confirm('This will remove duplicate 3rd-term fee allocations and consolidate any payments. Proceed?')">
            <i class="fas fa-trash-alt"></i> Fix Duplicate Term Allocations
          </a>
        </div>
        <?php endif; ?>

        <!-- Nav tabs -->
        <ul class="nav nav-tabs mb-md" role="tablist">
          <li class="active"><a href="#tab-overpaid" data-toggle="tab">
            Overpayments <span class="badge"><?= count($overpayments) ?></span>
          </a></li>
          <li><a href="#tab-unpaid" data-toggle="tab">
            Zero-Payment Allocations <span class="badge"><?= count($unpaid_allocations) ?></span>
          </a></li>
          <li><a href="#tab-unmatched" data-toggle="tab">
            Unmatched DVA Payments <span class="badge"><?= count($unmatched_dva) ?></span>
          </a></li>
        </ul>

        <div class="tab-content">

          <!-- Overpayments -->
          <div role="tabpanel" class="tab-pane active" id="tab-overpaid">
            <?php if (empty($overpayments)): ?>
              <div class="alert alert-success"><i class="fas fa-check"></i> No overpayments found.</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-bordered table-condensed table-hover table-export">
                  <thead>
                    <tr>
                      <th>#</th>
                      <th>Name</th>
                      <th>Reg No</th>
                      <th>Class</th>
                      <th class="text-right">Invoiced</th>
                      <th class="text-right">Paid</th>
                      <th class="text-right text-danger">Overpaid by</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($overpayments as $i => $row): ?>
                    <tr>
                      <td><?= $i + 1 ?></td>
                      <td><?= html_escape($row['first_name'] . ' ' . $row['last_name']) ?></td>
                      <td><?= html_escape($row['register_no']) ?></td>
                      <td><?= html_escape($row['class_name']) ?></td>
                      <td class="text-right"><?= $currency . number_format($row['invoiced'], 2) ?></td>
                      <td class="text-right"><?= $currency . number_format($row['paid'], 2) ?></td>
                      <td class="text-right text-danger"><strong><?= $currency . number_format($row['overpaid'], 2) ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>

          <!-- Zero-payment allocations -->
          <div role="tabpanel" class="tab-pane" id="tab-unpaid">
            <?php if (empty($unpaid_allocations)): ?>
              <div class="alert alert-success"><i class="fas fa-check"></i> All allocations have at least one payment recorded.</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-bordered table-condensed table-hover table-export">
                  <thead>
                    <tr>
                      <th>#</th>
                      <th>Name</th>
                      <th>Reg No</th>
                      <th>Class</th>
                      <th>Section</th>
                      <th class="text-right">Invoiced</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($unpaid_allocations as $i => $row): ?>
                    <tr>
                      <td><?= $i + 1 ?></td>
                      <td><?= html_escape($row['first_name'] . ' ' . $row['last_name']) ?></td>
                      <td><?= html_escape($row['register_no']) ?></td>
                      <td><?= html_escape($row['class_name']) ?></td>
                      <td><?= html_escape($row['section_name']) ?></td>
                      <td class="text-right"><?= $currency . number_format($row['invoiced'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>

          <!-- Unmatched DVA payments -->
          <div role="tabpanel" class="tab-pane" id="tab-unmatched">
            <p class="text-muted">Paystack DVA payments with <code>status=success</code> that have no matching row in <code>fee_payment_history</code>.</p>
            <?php if (is_superadmin_loggedin()): ?>
            <a href="<?= base_url('feespayment/reprocess_dva_backlog') ?>"
               class="btn btn-warning btn-sm mb-sm"
               onclick="return confirm('Apply all unmatched DVA payments to student fee allocations? This cannot be undone.')">
              <i class="fas fa-sync-alt"></i> Reprocess All Unmatched Payments
            </a>
            <?php endif; ?>
            <?php if (empty($unmatched_dva)): ?>
              <div class="alert alert-success"><i class="fas fa-check"></i> All DVA payments are matched to fee records.</div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-bordered table-condensed table-hover table-export">
                  <thead>
                    <tr>
                      <th>#</th>
                      <th>Date</th>
                      <th>Reference</th>
                      <th>Email</th>
                      <th>Sender</th>
                      <th class="text-right">Amount (kobo)</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($unmatched_dva as $i => $row): ?>
                    <tr class="<?= ($i < 5) ? 'warning' : '' ?>">
                      <td><?= $i + 1 ?></td>
                      <td><?= html_escape($row['paid_date']) ?></td>
                      <td><code><?= html_escape($row['reference']) ?></code></td>
                      <td><?= html_escape($row['customer_email']) ?></td>
                      <td><?= html_escape($row['authorization_sender_name'] ?? '—') ?></td>
                      <td class="text-right"><?= number_format((float)$row['amount']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>

        </div><!-- /tab-content -->
      </div>
    </section>
  </div>
</div>
