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

// предыдущий период
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

function compareVal($cur,$prev):string {
    if($prev==0) return '';
    $delta=round((($cur-$prev)/$prev)*100,1);
    $cls=$delta>=0?'text-success':'text-danger';
    $sign=$delta>=0?'+':'';
    return "<span class='$cls small'>($sign{$delta}%)</span>";
}

// собираем все
$allMetrics=[];
foreach(array_keys($dimensionList) as $dim){
    $allMetrics[$dim]=[
        'metrics'=>fetchMetrics($analytics,$dim,$start,$end),
        'previous'=>fetchMetrics($analytics,$dim,$prevStart,$prevEnd),
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
        .segment-row:nth-child(even){background:#f9f9f9;}
        .segment-row{padding:6px 8px; border-bottom:1px solid #eee;}
        .product-card{border:1px solid #ddd; border-radius:4px; padding:4px 6px; margin:2px; background:#fafafa;}
        .product-title{font-weight:500; font-size:0.85em;}
        .product-meta{font-size:0.75em; color:#666;}
    </style>
</head>
<body class="bg-light p-3">
<div class="container-fluid">
    <h2 class="mb-3">Маркетинговая аналитика</h2>

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

    <!-- Табы -->
    <ul class="nav nav-tabs" role="tablist">
        <?php $i=0; foreach($allMetrics as $dim=>$block): ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?=$i==0?'active':''?>" data-bs-toggle="tab" data-bs-target="#tab_<?=$dim?>" type="button">
                    <?=$dimensionList[$dim]['label']?>
                </button>
            </li>
            <?php $i++; endforeach; ?>
    </ul>

    <div class="tab-content mt-3">
        <?php $i=0; foreach($allMetrics as $dim=>$block): ?>
            <div class="tab-pane fade <?=$i==0?'show active':''?>" id="tab_<?=$dim?>">
                <div class="card shadow-sm mb-3">
                    <div class="card-body p-2">
                        <h5><?=$dimensionList[$dim]['label']?></h5>
                        <canvas id="chart_<?=$dim?>" height="120"></canvas>

                        <h6 class="mt-3">Разбивка (<?=$start->format('d.m.Y')?> — <?=$end->format('d.m.Y')?>)</h6>
                        <?php foreach($block['metrics'] as $m):
                            $prev=null;
                            foreach($block['previous'] as $p){ if($p['dimension_value']===$m['dimension_value']){$prev=$p; break;} }
                            ?>
                            <div class="segment-row">
                                <div><strong><?=htmlspecialchars(formatDimensionValue($dim,$m['dimension_value']))?></strong></div>
                                <div class="small">
                                    Выручка: <?=number_format($m['total_revenue'],0,',',' ')?> ₽ <?= $prev?compareVal($m['total_revenue'],$prev['total_revenue']):''?> ·
                                    Заказы: <?=$m['total_orders']?> <?= $prev?compareVal($m['total_orders'],$prev['total_orders']):''?> ·
                                    Клиенты: <?=$m['total_customers']?> <?= $prev?compareVal($m['total_customers'],$prev['total_customers']):''?>
                                </div>
                                <?php if(!empty($block['products'][$m['dimension_value']])): ?>
                                    <div class="mt-1 d-flex flex-wrap">
                                        <?php foreach($block['products'][$m['dimension_value']] as $p): ?>
                                            <div class="product-card">
                                                <div class="product-title"><?=htmlspecialchars($p['title'])?></div>
                                                <div class="product-meta"><?=$p['quantity']?> шт · <?=number_format($p['revenue'],0,',',' ')?> ₽</div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        <?php if(empty($block['metrics'])): ?>
                            <p class="text-muted small">Нет данных</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php $i++; endforeach; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    <?php foreach($allMetrics as $dim=>$block): ?>
    new Chart(document.getElementById("chart_<?=$dim?>"),{
        type:'bar',
        data:{
            labels:<?=json_encode(array_map(fn($m)=>formatDimensionValue($dim,$m['dimension_value']),$block['metrics']))?>,
            datasets:[{label:'Выручка',data:<?=json_encode(array_column($block['metrics'],'total_revenue'))?>,backgroundColor:'#36a2eb'}]
        },
        options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true}}}
    });
    <?php endforeach; ?>
</script>
</body>
</html>
