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

function compareDelta($cur,$prev): string {
    if ($prev==0) return '';
    $delta=round((($cur-$prev)/$prev)*100,2);
    $cls=$delta>=0?'text-success':'text-danger';
    $sign=$delta>=0?'+':'';
    return "<span class='$cls small'>($sign$delta%)</span>";
}

// агрегаты
$allMetrics=[];
foreach(array_keys($dimensionList) as $dim){
    $allMetrics[$dim]=[
        'current'=>fetchMetrics($analytics,$dim,$start,$end),
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
        .product-card { border:1px solid #ddd; border-radius:6px; padding:4px 8px; margin:2px 0; background:#fafafa; font-size:0.85em; }
        .product-title { font-weight:500; }
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

    <ul class="nav nav-tabs" id="segTabs" role="tablist">
        <?php $i=0; foreach($allMetrics as $dim=>$block): ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?=$i==0?'active':''?>" id="tab-<?=$dim?>" data-bs-toggle="tab" data-bs-target="#content-<?=$dim?>" type="button" role="tab">
                    <?=$dimensionList[$dim]['label']?>
                </button>
            </li>
            <?php $i++; endforeach; ?>
    </ul>

    <div class="tab-content mt-3">
        <?php $i=0; foreach($allMetrics as $dim=>$block): ?>
            <div class="tab-pane fade <?=$i==0?'show active':''?>" id="content-<?=$dim?>" role="tabpanel">
                <h5>Текущий период: <?=$start->format('d.m.Y')?> — <?=$end->format('d.m.Y')?></h5>
                <div class="row mb-3">
                    <div class="col-md-6"><canvas id="bar_<?=$dim?>" height="120"></canvas></div>
                    <div class="col-md-6"><canvas id="pie_<?=$dim?>" height="120"></canvas></div>
                </div>

                <table class="table table-sm table-striped">
                    <thead>
                    <tr>
                        <th>Сегмент</th><th>Выручка</th><th>Заказы</th><th>Клиенты</th><th>Новые</th><th>Повторные</th><th>Повторяемость</th><th>Средний чек</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach($block['current'] as $cur):
                        $prev=null;
                        foreach($block['previous'] as $p){ if($p['dimension_value']===$cur['dimension_value']){$prev=$p;break;} }
                        ?>
                        <tr>
                            <td><?=htmlspecialchars(formatDimensionValue($dim,$cur['dimension_value']))?></td>
                            <td><?=number_format($cur['total_revenue'],0,',',' ')?> ₽ <?= $prev?compareDelta($cur['total_revenue'],$prev['total_revenue']):''?></td>
                            <td><?=$cur['total_orders']?> <?= $prev?compareDelta($cur['total_orders'],$prev['total_orders']):''?></td>
                            <td><?=$cur['total_customers']?> <?= $prev?compareDelta($cur['total_customers'],$prev['total_customers']):''?></td>
                            <td><?=$cur['new_customers']?></td>
                            <td><?=$cur['repeat_customers']?></td>
                            <td><?=$cur['repeat_rate']?>%</td>
                            <td><?=$cur['avg_receipt']?> ₽</td>
                        </tr>
                        <?php if(!empty($block['products'][$cur['dimension_value']])): ?>
                        <tr>
                            <td colspan="8">
                                <div class="fw-bold small mb-1">Топ товары:</div>
                                <div class="row">
                                    <?php foreach($block['products'][$cur['dimension_value']] as $p): ?>
                                        <div class="col-6 col-md-4">
                                            <div class="product-card">
                                                <div class="product-title"><?=htmlspecialchars($p['title'])?></div>
                                                <div><?=$p['quantity']?> шт · <?=number_format($p['revenue'],0,',',' ')?> ₽</div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if(empty($block['current'])): ?>
                        <tr><td colspan="8">Нет данных</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>

                <h6>Предыдущий период: <?=$prevStart->format('d.m.Y')?> — <?=$prevEnd->format('d.m.Y')?></h6>
                <small class="text-muted">Изменения отображены в скобках рядом с текущими значениями.</small>
            </div>
            <?php $i++; endforeach; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    <?php foreach($allMetrics as $dim=>$block): ?>
    new Chart(document.getElementById("bar_<?=$dim?>"),{
        type:'bar',
        data:{
            labels:<?=json_encode(array_map(fn($m)=>formatDimensionValue($dim,$m['dimension_value']),$block['current']))?>,
            datasets:[{label:'Выручка',data:<?=json_encode(array_column($block['current'],'total_revenue'))?>,backgroundColor:'#36a2eb'}]
        },
        options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true}}}
    });
    new Chart(document.getElementById("pie_<?=$dim?>"),{
        type:'pie',
        data:{
            labels:<?=json_encode(array_map(fn($m)=>formatDimensionValue($dim,$m['dimension_value']),$block['current']))?>,
            datasets:[{data:<?=json_encode(array_column($block['current'],'total_revenue'))?>,backgroundColor:['#36a2eb','#ff6384','#ffcd56','#4bc0c0','#9966ff','#ff9f40']}]
        },
        options:{responsive:true}
    });
    <?php endforeach; ?>
</script>
</body>
</html>
