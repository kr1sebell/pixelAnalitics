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

function fetchTopProducts($analytics,$dimension,DateTime $start,DateTime $end): array {
    $rows=$analytics->getAll(
        'SELECT dimension_value,products
           FROM analytics_dimension_top_products
          WHERE dimension=?s
            AND period_start BETWEEN ?s AND ?s',
        [$dimension,$start->format('Y-m-d'),$end->format('Y-m-d')]
    );
    $res=[];
    foreach($rows as $r){
        $prods=json_decode($r['products'],true)??[];
        foreach($prods as $p){
            $key=$r['dimension_value'];
            if(!isset($res[$key])) $res[$key]=[];
            $t=$p['title'];
            if(!isset($res[$key][$t])) $res[$key][$t]=$p;
            else{
                $res[$key][$t]['revenue']+=$p['revenue'];
                $res[$key][$t]['quantity']+=$p['quantity'];
            }
        }
    }
    foreach($res as $k=>$ps) $res[$k]=array_values($ps);
    return $res;
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
        $map=[1=>'Пн',2=>'Вт',3=>'Ср',4=>'Чт',5=>'Пт',6=>'Сб',7=>'Вс'];
        return $map[(int)$value]??'Не определено';
    }
    if($dimension==='payment_type'){
        $map=[0=>'Онлайн',1=>'Наличные',2=>'Терминал'];
        return $map[(int)$value]??'Не определено';
    }
    if($dimension==='city_id') return $cityMap[(int)$value]??('Город #'.(int)$value);
    return(string)$value;
}

// собираем все
$allMetrics=[];
foreach(array_keys($dimensionList) as $dim){
    $allMetrics[$dim]=[
        'metrics'=>fetchMetrics($analytics,$dim,$start,$end),
        'products'=>fetchTopProducts($analytics,$dim,$start,$end)
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
        body { font-size: 0.9rem; }
        .card { margin-bottom: 1rem; }
        .segment-row:nth-child(even) { background:#f9f9f9; }
        .segment-row { padding:6px 8px; border-bottom:1px solid #eee; }
        .product-chip {
            display:inline-block;
            background:#eef;
            border-radius:12px;
            padding:2px 8px;
            margin:2px;
            font-size:0.8em;
        }
    </style>
</head>
<body class="bg-light p-2">
<div class="container-fluid">
    <h3 class="mb-3">Маркетинговая аналитика</h3>

    <form method="get" class="row g-2 mb-3">
        <div class="col-auto">
            <input type="date" class="form-control form-control-sm" name="start" value="<?=$start->format('Y-m-d')?>">
        </div>
        <div class="col-auto">
            <input type="date" class="form-control form-control-sm" name="end" value="<?=$end->format('Y-m-d')?>">
        </div>
        <div class="col-auto">
            <button class="btn btn-sm btn-primary">Обновить</button>
        </div>
    </form>

    <div class="row">
        <?php foreach($allMetrics as $dim=>$block): ?>
            <div class="col-lg-6 col-md-12">
                <div class="card shadow-sm">
                    <div class="card-body p-2">
                        <h6 class="card-title"><?=$dimensionList[$dim]['label']?></h6>
                        <canvas id="chart_<?=$dim?>" height="100"></canvas>
                        <div class="mt-2">
                            <?php foreach($block['metrics'] as $m): ?>
                                <div class="segment-row">
                                    <div><strong><?=htmlspecialchars(formatDimensionValue($dim,$m['dimension_value']))?></strong></div>
                                    <div class="text-muted small">
                                        Выручка: <?=number_format($m['total_revenue'],0,',',' ')?> ₽ ·
                                        Заказы: <?=$m['total_orders']?> ·
                                        Клиенты: <?=$m['total_customers']?>
                                    </div>
                                    <?php if(!empty($block['products'][$m['dimension_value']])): ?>
                                        <div class="mt-1">
                                            <?php foreach($block['products'][$m['dimension_value']] as $p): ?>
                                                <span class="product-chip">
                          <?=htmlspecialchars($p['title'])?> (<?=$p['quantity']?> / <?=number_format($p['revenue'],0,',',' ')?>₽)
                        </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                            <?php if(empty($block['metrics'])): ?>
                                <p class="text-muted">Нет данных</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
    <?php foreach($allMetrics as $dim=>$block): ?>
    new Chart(document.getElementById("chart_<?=$dim?>"),{
        type:'bar',
        data:{
            labels:<?=json_encode(array_map(fn($m)=>formatDimensionValue($dim,$m['dimension_value']),$block['metrics']))?>,
            datasets:[{
                label:'Выручка',
                data:<?=json_encode(array_column($block['metrics'],'total_revenue'))?>,
                backgroundColor:'#4e79a7'
            }]
        },
        options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true}}}
    });
    <?php endforeach; ?>
</script>
</body>
</html>
