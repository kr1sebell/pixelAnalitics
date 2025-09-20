<?php
require __DIR__ . '/../bootstrap.php';

use Database\ConnectionManager;
use Segmentation\DimensionConfig;

$connectionManager = new ConnectionManager($config);
$analytics = $connectionManager->getAnalytics();

$dimensionList = DimensionConfig::list();
$startParam = $_GET['start'] ?? (new DateTime('-30 days'))->format('Y-m-d');
$endParam   = $_GET['end']   ?? (new DateTime())->format('Y-m-d');

$start = new DateTime($startParam);
$end   = new DateTime($endParam);
if ($start > $end) [$start,$end] = [$end,$start];

$periodLength = $start->diff($end)->days + 1;
$prevEnd = (clone $start)->modify('-1 day');
$prevStart = (clone $prevEnd)->modify('-' . ($periodLength - 1) . ' days');

function fetchMetrics($analytics,$dimension,DateTime $start,DateTime $end): array {
    return $analytics->getAll(
        'SELECT dimension_value,
                SUM(total_orders) AS total_orders,
                SUM(total_revenue) AS total_revenue,
                SUM(total_customers) AS total_customers,
                SUM(new_customers) AS new_customers,
                SUM(repeat_customers) AS repeat_customers,
                ROUND(AVG(repeat_rate),2) AS repeat_rate,
                ROUND(AVG(avg_receipt),2) AS avg_receipt,
                ROUND(AVG(avg_frequency),2) AS avg_frequency,
                ROUND(AVG(avg_items),2) AS avg_items
           FROM analytics_dimension_metrics
          WHERE dimension=?s
            AND period_start BETWEEN ?s AND ?s
          GROUP BY dimension_value
          ORDER BY total_revenue DESC',
        [$dimension,$start->format('Y-m-d'),$end->format('Y-m-d')]
    );
}

function fetchCityMap($analytics):array{
    $rows=$analytics->getAll('SELECT DISTINCT city_id,city_name FROM analytics_orders WHERE city_id IS NOT NULL');
    $map=[];
    foreach($rows as $r){
        if($r['city_id']) $map[(int)$r['city_id']]=$r['city_name']??('Город #'.$r['city_id']);
    }
    return $map;
}

$cityMap=fetchCityMap($analytics);

function formatDimensionValue($dimension,$value):string{
    global $cityMap;
    if($value===null||$value===''||$value==='unknown')return'Не определено';
    if($dimension==='gender') return $value==='male'?'Мужчины':($value==='female'?'Женщины':'Не определено');
    if($dimension==='weekday'){
        $map=[1=>'Понедельник',2=>'Вторник',3=>'Среда',4=>'Четверг',5=>'Пятница',6=>'Суббота',7=>'Воскресенье'];
        return $map[(int)$value]??'Не определено';
    }
    if($dimension==='payment_type'){
        $map=[0=>'Онлайн',1=>'Наличные',2=>'Терминал'];
        return $map[(int)$value]??'Не определено';
    }
    if($dimension==='city_id') return $cityMap[(int)$value]??('Город #'.(int)$value);
    return(string)$value;
}

function compare($curr,$prev):?float{
    if($prev==0) return null;
    return round((($curr-$prev)/$prev)*100,1);
}

// агрегаты
$allMetrics=[];
foreach(array_keys($dimensionList) as $dim){
    $allMetrics[$dim]=[
        'current'=>fetchMetrics($analytics,$dim,$start,$end),
        'previous'=>fetchMetrics($analytics,$dim,$prevStart,$prevEnd)
    ];
}
?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Маркетинговая аналитика</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .delta-up { color:#0a0; font-weight:bold; }
        .delta-down { color:#c00; font-weight:bold; }
        .delta-null { color:#999; }
        .table-sm td, .table-sm th { padding: .3rem; }
    </style>
</head>
<body class="bg-light p-3">
<div class="container-fluid">
    <h1 class="mb-4">Маркетинговая аналитика</h1>

    <form method="get" class="row g-3 mb-4">
        <div class="col-md-3">
            <label class="form-label">Начало</label>
            <input type="date" class="form-control" name="start" value="<?=$start->format('Y-m-d')?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Конец</label>
            <input type="date" class="form-control" name="end" value="<?=$end->format('Y-m-d')?>">
        </div>
        <div class="col-md-3 d-flex align-items-end">
            <button class="btn btn-primary w-100">Обновить</button>
        </div>
    </form>

    <!-- Табы -->
    <ul class="nav nav-tabs" id="segmentTabs" role="tablist">
        <?php $first=true; foreach($allMetrics as $dim=>$block): ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?=$first?'active':''?>" id="tab-<?=$dim?>" data-bs-toggle="tab" data-bs-target="#pane-<?=$dim?>" type="button" role="tab">
                    <?=$dimensionList[$dim]['label']?>
                </button>
            </li>
            <?php $first=false; endforeach; ?>
    </ul>

    <div class="tab-content mt-3">
        <?php $first=true; foreach($allMetrics as $dim=>$block): ?>
            <div class="tab-pane fade <?=$first?'show active':''?>" id="pane-<?=$dim?>" role="tabpanel">
                <div class="row">
                    <?php foreach(array_chunk($block['current'],2) as $pair): ?>
                        <?php foreach($pair as $m):
                            $val=$m['dimension_value'];
                            $prev=array_values(array_filter($block['previous'],fn($x)=>$x['dimension_value']===$val));
                            $prev=$prev[0]??['total_revenue'=>0,'total_orders'=>0,'total_customers'=>0];
                            ?>
                            <div class="col-lg-6 col-md-12 mb-4">
                                <div class="card h-100 shadow-sm">
                                    <div class="card-body">
                                        <h5><?=htmlspecialchars(formatDimensionValue($dim,$val))?></h5>
                                        <p class="text-muted small">
                                            Текущий период: <?=$start->format('d.m.Y')?> — <?=$end->format('d.m.Y')?><br>
                                            Предыдущий: <?=$prevStart->format('d.m.Y')?> — <?=$prevEnd->format('d.m.Y')?>
                                        </p>

                                        <table class="table table-sm align-middle">
                                            <tr>
                                                <th>Выручка</th>
                                                <td>
                                                    <?=number_format($m['total_revenue'],0,',',' ')?> ₽<br>
                                                    <small class="<?=compare($m['total_revenue'],$prev['total_revenue'])>0?'delta-up':(compare($m['total_revenue'],$prev['total_revenue'])<0?'delta-down':'delta-null')?>">
                                                        Было: <?=number_format($prev['total_revenue'],0,',',' ')?> ₽
                                                        (<?=compare($m['total_revenue'],$prev['total_revenue'])?>%)
                                                    </small>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Заказы</th>
                                                <td>
                                                    <?=$m['total_orders']?><br>
                                                    <small class="<?=compare($m['total_orders'],$prev['total_orders'])>0?'delta-up':(compare($m['total_orders'],$prev['total_orders'])<0?'delta-down':'delta-null')?>">
                                                        Было: <?=$prev['total_orders']?>
                                                        (<?=compare($m['total_orders'],$prev['total_orders'])?>%)
                                                    </small>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Клиенты</th>
                                                <td>
                                                    <?=$m['total_customers']?><br>
                                                    <small class="<?=compare($m['total_customers'],$prev['total_customers'])>0?'delta-up':(compare($m['total_customers'],$prev['total_customers'])<0?'delta-down':'delta-null')?>">
                                                        Было: <?=$prev['total_customers']?>
                                                        (<?=compare($m['total_customers'],$prev['total_customers'])?>%)
                                                    </small>
                                                </td>
                                            </tr>
                                        </table>

                                        <canvas id="chart_<?=$dim?>_<?=$val?>" height="120"></canvas>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php $first=false; endforeach; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    <?php foreach($allMetrics as $dim=>$block): foreach($block['current'] as $m):
    $val=$m['dimension_value'];
    $prev=array_values(array_filter($block['previous'],fn($x)=>$x['dimension_value']===$val));
    $prev=$prev[0]??['total_revenue'=>0];
    ?>
    new Chart(document.getElementById("chart_<?=$dim?>_<?=$val?>"),{
        type:'bar',
        data:{
            labels:['Текущий','Предыдущий'],
            datasets:[{
                label:'Выручка',
                data:[<?=$m['total_revenue']?>,<?=$prev['total_revenue']?>],
                backgroundColor:['rgba(54,162,235,0.7)','rgba(200,200,200,0.7)']
            }]
        },
        options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true}}}
    });
    <?php endforeach; endforeach; ?>
</script>
</body>
</html>
