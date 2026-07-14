<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="row">
  <div class="col-md-12">
    <section class="panel">
      <header class="panel-heading">
        <h4 class="panel-title"><i class="fas fa-check-circle text-success"></i> Duplicate Term Allocation Fix — Results</h4>
      </header>
      <div class="panel-body">

        <div class="row mb-md">
          <div class="col-md-3">
            <div class="panel panel-success text-center">
              <div class="panel-body"><h3 class="text-success"><?= count($fixed) ?></h3><small>Duplicates removed</small></div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="panel panel-warning text-center">
              <div class="panel-body"><h3 class="text-warning"><?= count($skipped) ?></h3><small>Skipped / errors</small></div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="panel panel-info text-center">
              <div class="panel-body">
                <a href="<?= base_url('fees/financial_exceptions') ?>" class="btn btn-info btn-block">
                  <i class="fas fa-arrow-left"></i> Financial Exceptions
                </a>
              </div>
            </div>
          </div>
        </div>

        <?php if (!empty($fixed)): ?>
        <h5>Fixed</h5>
        <div class="table-responsive">
          <table class="table table-bordered table-condensed table-sm">
            <thead>
              <tr>
                <th>#</th><th>Name</th><th>Reg No</th><th>Fee Group</th>
                <th>Primary Alloc ID</th><th>Deleted Alloc ID</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($fixed as $i => $r): ?>
              <tr>
                <td><?= $i + 1 ?></td>
                <td><?= html_escape($r['first_name'] . ' ' . $r['last_name']) ?></td>
                <td><?= html_escape($r['register_no']) ?></td>
                <td><?= html_escape($r['fee_group']) ?></td>
                <td><?= (int)$r['keep_id'] ?></td>
                <td><s class="text-danger"><?= (int)$r['delete_id'] ?></s></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>

        <?php if (!empty($skipped)): ?>
        <h5 class="text-warning">Skipped</h5>
        <div class="table-responsive">
          <table class="table table-bordered table-condensed table-sm">
            <thead>
              <tr><th>Name</th><th>Reg No</th><th>Alloc ID to delete</th></tr>
            </thead>
            <tbody>
              <?php foreach ($skipped as $r): ?>
              <tr>
                <td><?= html_escape($r['first_name'] . ' ' . $r['last_name']) ?></td>
                <td><?= html_escape($r['register_no']) ?></td>
                <td><?= (int)$r['delete_id'] ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>

        <?php if (empty($fixed) && empty($skipped)): ?>
        <div class="alert alert-success"><i class="fas fa-check"></i> No duplicate term allocations found — all clean.</div>
        <?php endif; ?>

      </div>
    </section>
  </div>
</div>
