<?php
$examEnd = strtotime($exam->exam_end);
$now     = time();
$active  = ($now >= strtotime($exam->exam_start) && $now <= $examEnd && $exam->publish_status == 1);
?>
<div class="row">
    <div class="col-md-12">
        <section class="panel">
            <header class="panel-heading">
                <h4 class="panel-title">
                    <i class="fas fa-satellite-dish"></i>
                    Live Monitor &mdash; <?=htmlspecialchars($exam->title)?>
                </h4>
                <div class="panel-btn">
                    <a href="<?=base_url('onlineexam/result')?>" class="btn btn-default btn-circle btn-sm">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                    <span id="lm-status" class="label <?=$active ? 'label-success' : 'label-default'?> ml-sm" style="font-size:12px;vertical-align:middle">
                        <?=$active ? 'LIVE' : 'ENDED'?>
                    </span>
                    <span id="lm-updated" class="text-muted ml-sm" style="font-size:11px"></span>
                </div>
            </header>
            <div class="panel-body">
                <div class="row mb-sm">
                    <div class="col-md-3">
                        <div class="widget-stat">
                            <small class="text-muted text-uppercase">Exam Ends</small>
                            <div style="font-size:15px;font-weight:600"><?=date('d M Y, h:i A', $examEnd)?></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted text-uppercase">Active</small>
                        <div style="font-size:22px;font-weight:700;color:#5cb85c" id="lm-count-active">—</div>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted text-uppercase">Submitted</small>
                        <div style="font-size:22px;font-weight:700;color:#337ab7" id="lm-count-submitted">—</div>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted text-uppercase">Total</small>
                        <div style="font-size:22px;font-weight:700" id="lm-count-total">—</div>
                    </div>
                </div>

                <table class="table table-condensed table-bordered table-hover" id="lm-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Student</th>
                            <th>Progress</th>
                            <th>Answered</th>
                            <th>Status</th>
                            <th>Submitted At</th>
                        </tr>
                    </thead>
                    <tbody id="lm-tbody">
                        <tr><td colspan="6" class="text-center text-muted">Loading&hellip;</td></tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>

<script type="text/javascript">
var lmExamID   = <?=(int)$examID?>;
var lmInterval = null;

function lmLoad() {
    $.getJSON(base_url + 'onlineexam/liveMonitorData/' + lmExamID, function(data) {
        if (!data || !data.students) return;
        var totalQ   = data.total_questions;
        var students = data.students;
        var active = 0, submitted = 0;
        var html = '';
        $.each(students, function(i, s) {
            var pct     = totalQ > 0 ? Math.round(s.answered / totalQ * 100) : 0;
            var barCol  = s.submitted ? '#337ab7' : '#5cb85c';
            var badge   = s.submitted
                ? "<span class='label label-primary'>Submitted</span>"
                : "<span class='label label-success'>Active</span>";
            var subTime = s.submitted_at
                ? s.submitted_at.replace('T', ' ').substring(0, 16)
                : '—';
            if (s.submitted) submitted++; else active++;
            html += '<tr>';
            html += '<td>' + (i+1) + '</td>';
            html += '<td>' + $('<span>').text(s.name).html() + '</td>';
            html += '<td style="min-width:120px">'
                  + '<div style="background:#eee;border-radius:3px;height:14px;position:relative">'
                  + '<div style="background:' + barCol + ';width:' + pct + '%;height:14px;border-radius:3px"></div>'
                  + '<span style="position:absolute;top:-1px;left:4px;font-size:10px;line-height:14px;color:#333">' + pct + '%</span>'
                  + '</div></td>';
            html += '<td>' + s.answered + ' / ' + totalQ + '</td>';
            html += '<td>' + badge + '</td>';
            html += '<td>' + subTime + '</td>';
            html += '</tr>';
        });
        if (!html) html = '<tr><td colspan="6" class="text-center text-muted">No students have started yet.</td></tr>';
        $('#lm-tbody').html(html);
        $('#lm-count-active').text(active);
        $('#lm-count-submitted').text(submitted);
        $('#lm-count-total').text(students.length);
        var now = new Date();
        $('#lm-updated').text('Updated ' + now.getHours() + ':' + ('0'+now.getMinutes()).slice(-2) + ':' + ('0'+now.getSeconds()).slice(-2));
    });
}

lmLoad();
lmInterval = setInterval(lmLoad, 30000);

// Pause polling when tab is hidden, resume on return
document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        clearInterval(lmInterval);
    } else {
        lmLoad();
        lmInterval = setInterval(lmLoad, 30000);
    }
});
</script>
