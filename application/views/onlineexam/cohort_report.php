<?php
$typeLabels = [1 => 'Single Choice', 2 => 'Multi Choice', 3 => 'True/False', 4 => 'Descriptive'];
$bandColors = ['0-20' => '#d9534f', '21-40' => '#f0ad4e', '41-60' => '#5bc0de', '61-80' => '#5cb85c', '81-100' => '#337ab7'];
$maxBand = max(array_values($summary['total'] > 0 ? $bands : [0 => 1])) ?: 1;
?>
<style>
.cr-tile{background:#f7f7f7;border:1px solid #e0e0e0;border-radius:4px;padding:14px 18px;text-align:center}
.cr-tile .big{font-size:28px;font-weight:700;line-height:1.1}
.cr-tile small{display:block;font-size:11px;color:#888;text-transform:uppercase;letter-spacing:.5px;margin-top:3px}
.cr-band-row{display:flex;align-items:center;gap:8px;margin-bottom:6px;font-size:12px}
.cr-band-label{width:55px;text-align:right;flex-shrink:0;color:#555}
.cr-band-bar-wrap{flex:1;background:#eee;border-radius:3px;height:18px;position:relative}
.cr-band-bar{height:18px;border-radius:3px;transition:width .4s}
.cr-band-count{width:30px;flex-shrink:0;color:#333;font-weight:600}
</style>

<div class="row">
    <div class="col-md-12">
        <section class="panel">
            <header class="panel-heading">
                <h4 class="panel-title">
                    <i class="fas fa-chart-column"></i>
                    Cohort Report &mdash; <?=htmlspecialchars($exam->title)?>
                </h4>
                <div class="panel-btn">
                    <a href="<?=base_url('onlineexam/result')?>" class="btn btn-default btn-circle btn-sm">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                    <button onclick="exportCSV()" class="btn btn-default btn-circle btn-sm ml-sm">
                        <i class="fas fa-download"></i> Export CSV
                    </button>
                </div>
            </header>
            <div class="panel-body">

                <!-- Summary tiles -->
                <div class="row mb-lg">
                    <div class="col-md-2 col-sm-4 col-xs-6 mb-sm">
                        <div class="cr-tile">
                            <div class="big"><?=$summary['total']?></div>
                            <small>Students</small>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4 col-xs-6 mb-sm">
                        <div class="cr-tile">
                            <div class="big" style="color:#5cb85c"><?=$summary['passCount']?></div>
                            <small>Passed</small>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4 col-xs-6 mb-sm">
                        <div class="cr-tile">
                            <div class="big" style="color:#d9534f"><?=($summary['total'] - $summary['passCount'])?></div>
                            <small>Failed</small>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4 col-xs-6 mb-sm">
                        <div class="cr-tile">
                            <div class="big"><?=$summary['avgScore']?>%</div>
                            <small>Avg Score</small>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4 col-xs-6 mb-sm">
                        <div class="cr-tile">
                            <div class="big" style="color:#337ab7"><?=$summary['highScore']?>%</div>
                            <small>Highest</small>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4 col-xs-6 mb-sm">
                        <div class="cr-tile">
                            <div class="big" style="color:#f0ad4e"><?=$summary['lowScore']?>%</div>
                            <small>Lowest</small>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Score distribution -->
                    <div class="col-md-4">
                        <h5 class="mb-sm"><i class="fas fa-chart-bar"></i> Score Distribution</h5>
                        <?php foreach ($bands as $label => $count): ?>
                        <div class="cr-band-row">
                            <div class="cr-band-label"><?=$label?>%</div>
                            <div class="cr-band-bar-wrap">
                                <div class="cr-band-bar" style="width:<?=($maxBand > 0 ? round($count/$maxBand*100) : 0)?>%;background:<?=$bandColors[$label]?>"></div>
                            </div>
                            <div class="cr-band-count"><?=$count?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Per-question difficulty -->
                    <div class="col-md-8">
                        <h5 class="mb-sm"><i class="fas fa-list-check"></i> Per-Question Difficulty</h5>
                        <div class="table-responsive" style="max-height:260px;overflow-y:auto">
                            <table class="table table-condensed table-bordered" style="font-size:12px">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Question</th>
                                        <th>Type</th>
                                        <th>Marks</th>
                                        <th>Answered</th>
                                        <th>% Correct</th>
                                        <th>Avg Time</th>
                                        <th>Difficulty</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($qStats as $i => $q):
                                    $dp = $q['diff_pct'];
                                    if ($dp === null)      { $dLabel = '—';    $dClass = 'default'; }
                                    elseif ($dp >= 70)    { $dLabel = 'Easy'; $dClass = 'success'; }
                                    elseif ($dp >= 40)    { $dLabel = 'Med';  $dClass = 'warning'; }
                                    else                  { $dLabel = 'Hard'; $dClass = 'danger'; }
                                    $avgT = $q['avg_time_secs'];
                                    $tStr = $avgT !== null ? sprintf('%d:%02d', intdiv($avgT,60), $avgT%60) : '—';
                                ?>
                                <tr>
                                    <td><?=$i+1?></td>
                                    <td style="max-width:200px;white-space:normal"><?=htmlspecialchars(mb_strimwidth($q['question'],0,80,'…'))?></td>
                                    <td><?=$typeLabels[$q['type']] ?? '—'?></td>
                                    <td><?=$q['marks']?></td>
                                    <td><?=$q['answered']?></td>
                                    <td><?=$dp !== null ? $dp.'%' : '—'?></td>
                                    <td><?=$tStr?></td>
                                    <td><span class="label label-<?=$dClass?>"><?=$dLabel?></span></td>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Student ranking -->
                <h5 class="mt-lg mb-sm"><i class="fas fa-ranking-star"></i> Student Ranking</h5>
                <div class="table-responsive">
                    <table class="table table-condensed table-bordered table-hover" id="rankingTable">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Student</th>
                                <th>Reg No</th>
                                <th>Marks</th>
                                <th>Score</th>
                                <th>Result</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($ranking as $r): ?>
                        <tr>
                            <td><?=$r['rank']?></td>
                            <td><?=htmlspecialchars($r['name'])?></td>
                            <td><?=htmlspecialchars($r['register_no'])?></td>
                            <td><?=$r['obtained']?> / <?=$r['total']?></td>
                            <td><?=$r['pct']?>%</td>
                            <td>
                                <?php if ($r['passed']): ?>
                                    <span class="label label-success">Passed</span>
                                <?php else: ?>
                                    <span class="label label-danger">Failed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </section>
    </div>
</div>

<script type="text/javascript">
function exportCSV() {
    var rows = [['Rank','Student','Reg No','Marks Obtained','Total Marks','Score (%)','Result']];
    $('#rankingTable tbody tr').each(function() {
        var cells = [];
        $(this).find('td').each(function(i) {
            cells.push('"' + $(this).text().trim().replace(/"/g,'""') + '"');
        });
        rows.push(cells);
    });
    var csv = rows.map(function(r){return r.join(',');}).join('\n');
    var blob = new Blob([csv], {type:'text/csv'});
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'cohort_report_<?=$exam->id?>.csv';
    document.body.appendChild(a); a.click(); document.body.removeChild(a);
}
</script>
