<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<section class="panel">
    <header class="panel-heading">
        <h2 class="panel-title">DVA Account Sync</h2>
    </header>
    <div class="panel-body">

        <?php $count = count($unsynced_students); ?>
        <div class="alert alert-info mb-md">
            <strong><?= $count ?></strong> student<?= $count !== 1 ? 's' : '' ?> in this session have no local DVA record.
            Click <strong>Fetch from Paystack</strong> to check which ones already have an account created on Paystack.
        </div>

        <div class="mb-md">
            <button id="btnFetch" class="btn btn-primary">
                <i class="fa fa-cloud-download"></i> Fetch from Paystack
            </button>
            <button id="btnImport" class="btn btn-success" style="display:none;">
                <i class="fa fa-save"></i> Import All Found (<span id="importCount">0</span>)
            </button>
            <span id="fetchSpinner" style="display:none; margin-left:10px;">
                <i class="fa fa-spinner fa-spin"></i> Querying Paystack…
            </span>
        </div>

        <!-- Results panel (hidden until fetch) -->
        <div id="resultsPanel" style="display:none;">
            <div class="row mb-md">
                <div class="col-sm-3">
                    <div class="panel panel-primary text-center" style="padding:15px;">
                        <div style="font-size:2em;font-weight:bold;" id="statUnsynced"><?= $count ?></div>
                        <div>Without DVA locally</div>
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="panel panel-info text-center" style="padding:15px;">
                        <div style="font-size:2em;font-weight:bold;" id="statPaystack">—</div>
                        <div>On Paystack</div>
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="panel panel-success text-center" style="padding:15px;">
                        <div style="font-size:2em;font-weight:bold;" id="statMatched">—</div>
                        <div>Ready to import</div>
                    </div>
                </div>
                <div class="col-sm-3">
                    <div class="panel panel-warning text-center" style="padding:15px;">
                        <div style="font-size:2em;font-weight:bold;" id="statNotFound">—</div>
                        <div>Not on Paystack</div>
                    </div>
                </div>
            </div>

            <h4>Found on Paystack — ready to import</h4>
            <div class="table-responsive mb-md">
                <table class="table table-bordered table-striped" id="matchedTable">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="checkAll" checked></th>
                            <th>Name</th>
                            <th>Class</th>
                            <th>Section</th>
                            <th>Reg No</th>
                            <th>Email matched</th>
                            <th>Account Number</th>
                            <th>Bank</th>
                        </tr>
                    </thead>
                    <tbody id="matchedBody"></tbody>
                </table>
            </div>

            <div id="notFoundPanel" style="display:none;">
                <h4>Not found on Paystack</h4>
                <div class="table-responsive">
                    <table class="table table-bordered" id="notFoundTable">
                        <thead>
                            <tr>
                                <th>Name</th><th>Class</th><th>Section</th><th>Reg No</th><th>Email tried</th>
                            </tr>
                        </thead>
                        <tbody id="notFoundBody"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Unsynced list (always visible) -->
        <?php if ($count): ?>
        <h4>Students without DVA this session</h4>
        <div class="table-responsive">
            <table class="table table-bordered table-condensed" id="unsyncedTable">
                <thead>
                    <tr>
                        <th>#</th><th>Name</th><th>Class</th><th>Section</th><th>Reg No</th><th>Email</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($unsynced_students as $i => $s): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?></td>
                        <td><?= htmlspecialchars($s['class_name']) ?></td>
                        <td><?= htmlspecialchars($s['section_name']) ?></td>
                        <td><?= htmlspecialchars($s['register_no']) ?></td>
                        <td><?= htmlspecialchars($s['email'] ?? '') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

    </div><!-- /.panel-body -->
</section>

<script>
(function () {
    var CSRF_NAME  = '<?= $this->security->get_csrf_token_name() ?>';
    var CSRF_COOKIE = '<?= addslashes($this->config->item('csrf_cookie_name')) ?>';
    var ENDPOINT   = '<?= base_url('fees/dva_sync') ?>';

    function getCsrf() {
        var m = document.cookie.match(new RegExp(CSRF_COOKIE + '=([^;]+)'));
        return m ? m[1] : '';
    }

    // matched rows cache (for import payload)
    var matchedRows = [];

    document.getElementById('checkAll').addEventListener('change', function () {
        var boxes = document.querySelectorAll('#matchedBody input[type=checkbox]');
        boxes.forEach(function (b) { b.checked = this.checked; }, this);
        updateImportCount();
    });

    function updateImportCount() {
        var n = document.querySelectorAll('#matchedBody input[type=checkbox]:checked').length;
        document.getElementById('importCount').textContent = n;
        document.getElementById('btnImport').style.display = n > 0 ? 'inline-block' : 'none';
    }

    document.getElementById('btnFetch').addEventListener('click', function () {
        document.getElementById('fetchSpinner').style.display = 'inline-block';
        document.getElementById('btnFetch').disabled = true;
        document.getElementById('resultsPanel').style.display = 'none';
        matchedRows = [];

        var body = new URLSearchParams();
        body.append(CSRF_NAME, getCsrf());
        body.append('action', 'fetch');

        fetch(ENDPOINT, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: body.toString()
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            document.getElementById('fetchSpinner').style.display = 'none';
            document.getElementById('btnFetch').disabled = false;

            if (!data.status) {
                alert('Error: ' + (data.message || 'Unknown error'));
                return;
            }

            matchedRows = data.matched || [];
            var unsynced    = data.total_unsynced || 0;
            var psTotal     = data.paystack_total || 0;
            var matchedN    = matchedRows.length;
            var notFoundN   = unsynced - matchedN;

            document.getElementById('statUnsynced').textContent = unsynced;
            document.getElementById('statPaystack').textContent = psTotal;
            document.getElementById('statMatched').textContent  = matchedN;
            document.getElementById('statNotFound').textContent = notFoundN;

            // Build matched tbody
            var mb = document.getElementById('matchedBody');
            mb.innerHTML = '';
            matchedRows.forEach(function (r, idx) {
                var tr = document.createElement('tr');
                tr.innerHTML =
                    '<td><input type="checkbox" class="row-check" data-idx="' + idx + '" checked></td>' +
                    '<td>' + esc(r.student_name)  + '</td>' +
                    '<td>' + esc(r.class)          + '</td>' +
                    '<td>' + esc(r.section)        + '</td>' +
                    '<td>' + esc(r.register_no)    + '</td>' +
                    '<td><small>' + esc(r.matched_email) + '</small></td>' +
                    '<td><strong>' + esc(r.account_number) + '</strong></td>' +
                    '<td>' + esc(r.bank)           + '</td>';
                mb.appendChild(tr);
            });
            mb.querySelectorAll('input[type=checkbox]').forEach(function (b) {
                b.addEventListener('change', updateImportCount);
            });

            // Not-found panel
            if (notFoundN > 0 && data.not_found) {
                var nfb = document.getElementById('notFoundBody');
                nfb.innerHTML = '';
                data.not_found.forEach(function (r) {
                    var tr = document.createElement('tr');
                    tr.innerHTML = '<td>' + esc(r.student_name) + '</td><td>' + esc(r.class) +
                        '</td><td>' + esc(r.section) + '</td><td>' + esc(r.register_no) +
                        '</td><td><small>' + esc(r.email_tried) + '</small></td>';
                    nfb.appendChild(tr);
                });
                document.getElementById('notFoundPanel').style.display = 'block';
            }

            document.getElementById('resultsPanel').style.display = 'block';
            updateImportCount();
        })
        .catch(function (e) {
            document.getElementById('fetchSpinner').style.display = 'none';
            document.getElementById('btnFetch').disabled = false;
            alert('Request failed: ' + e);
        });
    });

    document.getElementById('btnImport').addEventListener('click', function () {
        var selected = [];
        document.querySelectorAll('#matchedBody input[type=checkbox]:checked').forEach(function (b) {
            selected.push(matchedRows[parseInt(b.dataset.idx)]);
        });
        if (!selected.length) return;
        if (!confirm('Import ' + selected.length + ' DVA record(s) into the portal?')) return;

        document.getElementById('btnImport').disabled = true;

        var body = new URLSearchParams();
        body.append(CSRF_NAME, getCsrf());
        body.append('action', 'import');
        selected.forEach(function (r, i) {
            Object.keys(r).forEach(function (k) {
                body.append('rows[' + i + '][' + k + ']', r[k] != null ? r[k] : '');
            });
        });

        fetch(ENDPOINT, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: body.toString()
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            document.getElementById('btnImport').disabled = false;
            if (data.status) {
                alert('Done! Imported: ' + data.imported + ', Skipped (already existed): ' + data.skipped + '. The page will now reload.');
                window.location.reload();
            } else {
                alert('Import failed: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(function (e) {
            document.getElementById('btnImport').disabled = false;
            alert('Request failed: ' + e);
        });
    });

    function esc(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }
}());
</script>
