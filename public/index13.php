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


function nf($value): string {
    return number_format((float)($value ?? 0), 0, ',', ' ');
}

function fetchMetrics($analytics,$dimension,DateTime $start,DateTime $end): array {
    $fieldMap = [
        'gender'       => 'u.gender',
        'occupation'   => 'u.occupation',
        'age_group'    => 'u.age_group',
        'city'         => 'u.city',
        'payment_type' => 'o.payment_type',
        'weekday'      => 'o.weekday',
        'city_id'      => 'o.city_id'
    ];

    if(!isset($fieldMap[$dimension])){
        throw new Exception("Unknown dimension: $dimension");
    }

    $field = $fieldMap[$dimension];

    return $analytics->getAll(
        "SELECT 
            $field AS dimension_value,
            COUNT(o.id) AS total_orders,
            SUM(o.total_sum) AS total_revenue,
            COUNT(DISTINCT o.source_user_id) AS total_customers,

            -- новые клиенты (первый заказ именно в выбранном периоде)
            COUNT(DISTINCT CASE 
                WHEN u.first_order_at BETWEEN ?s AND ?s 
                THEN o.source_user_id END
            ) AS new_customers,

            -- повторные клиенты (первый заказ был раньше периода)
            COUNT(DISTINCT CASE 
                WHEN u.first_order_at < ?s 
                THEN o.source_user_id END
            ) AS repeat_customers,

            -- средний чек
            ROUND(SUM(o.total_sum)/NULLIF(COUNT(o.id),0),2) AS avg_receipt,

            -- среднее количество позиций в заказе
            ROUND(AVG(o.total_items),2) AS avg_items,

            -- средняя частота заказов на клиента (за период)
            ROUND(COUNT(o.id)/NULLIF(COUNT(DISTINCT o.source_user_id),0),2) AS avg_frequency,
        
            -- нормализованная частота (в месяц)
            ROUND(
                (COUNT(o.id)/NULLIF(COUNT(DISTINCT o.source_user_id),0)) 
                / (DATEDIFF(?s,?s)/30), 2
            ) AS avg_frequency_month

         FROM analytics_orders o
         JOIN analytics_users u ON u.source_user_id=o.source_user_id
        WHERE o.order_date BETWEEN ?s AND ?s
        GROUP BY dimension_value
        ORDER BY total_revenue DESC",
        [
            $start->format('Y-m-d'), // для new_customers BETWEEN
            $end->format('Y-m-d'),
            $start->format('Y-m-d'), // для repeat_customers <
            $end->format('Y-m-d'),   // для DATEDIFF(end,start)
            $start->format('Y-m-d'),
            $start->format('Y-m-d'), // WHERE BETWEEN start
            $end->format('Y-m-d')    // WHERE BETWEEN end
        ]
    );
}


function fetchTotals($analytics, DateTime $start, DateTime $end): array {
    return $analytics->getRow(
        "SELECT 
            COUNT(o.id) AS total_orders,
            SUM(o.total_sum) AS total_revenue,
            COUNT(DISTINCT o.source_user_id) AS total_customers,
            ROUND(SUM(o.total_sum)/NULLIF(COUNT(o.id),0),2) AS avg_receipt
         FROM analytics_orders o
        WHERE o.order_date BETWEEN ?s AND ?s",
        [$start->format('Y-m-d'),$end->format('Y-m-d')]
    ) ?: ['total_orders'=>0,'total_revenue'=>0,'total_customers'=>0,'avg_receipt'=>0];
}

function fetchTopProducts($analytics,$dimension,DateTime $start,DateTime $end): array {
    $fieldMap = [
        'gender'       => 'u.gender',
        'occupation'   => 'u.occupation',
        'age_group'    => 'u.age_group',
        'city'         => 'u.city',
        'payment_type' => 'o.payment_type',
        'weekday'      => 'o.weekday',
        'city_id'      => 'o.city_id'
    ];

    if(!isset($fieldMap[$dimension])){
        throw new Exception("Unknown dimension: $dimension");
    }

    $field = $fieldMap[$dimension];

    $rows=$analytics->getAll(
        "SELECT 
            $field AS dimension_value,
            i.product_title,
            SUM(i.quantity) AS quantity,
            SUM(i.revenue) AS revenue
         FROM analytics_order_items i
         JOIN analytics_orders o ON o.id=i.analytics_order_id
         JOIN analytics_users u ON u.source_user_id=o.source_user_id
        WHERE o.order_date BETWEEN ?s AND ?s
        GROUP BY dimension_value, i.product_title
        ORDER BY revenue DESC",
        [$start->format('Y-m-d'),$end->format('Y-m-d')]
    );

    $res=[];
    foreach($rows as $r){
        $key=$r['dimension_value'];
        if(!isset($res[$key])) $res[$key]=[];
        $res[$key][]=[
            'title'=>$r['product_title'],
            'quantity'=>(int)$r['quantity'],
            'revenue'=>(float)$r['revenue']
        ];
    }
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

function compare($curr,$prev):?float{
    if($prev==0) return null;
    return round((($curr-$prev)/$prev)*100,1);
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
$totals = fetchTotals($analytics,$start,$end);
$totalsPrev = fetchTotals($analytics,$prevStart,$prevEnd);
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
        .product-card { border:1px solid #ddd; border-radius:6px; padding:3px 10px; margin:3px 0; background:#fafafa; }
        .product-title { font-weight:500; }
        .product-meta { font-size:0.85em; color:#666; }
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

    <!-- Общие агрегаты -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm"><div class="card-body">
                    <h6>Выручка</h6>
                    <strong><?=number_format((float)$totals['total_revenue'],0,',',' ')?> ₽</strong><br>
                    <small>Было: <?=number_format((float)$totalsPrev['total_revenue'],0,',',' ')?> ₽</small>
                </div></div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm"><div class="card-body">
                    <h6>Заказы</h6>
                    <strong><?=(float)$totals['total_orders']?></strong><br>
                    <small>Было: <?=(float)$totalsPrev['total_orders']?></small>
                </div></div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm"><div class="card-body">
                    <h6>Клиенты</h6>
                    <strong><?=(float)$totals['total_customers']?></strong><br>
                    <small>Было: <?=(float)$totalsPrev['total_customers']?></small>
                </div></div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm"><div class="card-body">
                    <h6>Средний чек</h6>
                    <strong><?=number_format((float)$totals['avg_receipt'],0,',',' ')?> ₽</strong><br>
                    <small>Было: <?=number_format((float)$totalsPrev['avg_receipt'],0,',',' ')?> ₽</small>
                </div></div>
        </div>
    </div>

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
                            $prev = $prev[0] ?? [
                                    'total_revenue'=>0,
                                    'total_orders'=>0,
                                    'total_customers'=>0,
                                    'avg_receipt'=>0,
                                    'new_customers'=>0,
                                    'repeat_customers'=>0,
                                    'avg_frequency'=>0
                                ];
                            ?>
                            <div class="col-lg-3 col-md-6 mb-4">
                                <div class="card h-100 shadow-sm">
                                    <div class="card-body">
                                        <h5><?=htmlspecialchars(formatDimensionValue($dim,$val))?></h5>
                                        <p class="text-muted small">
                                            Текущий: <?=$start->format('d.m.Y')?> — <?=$end->format('d.m.Y')?><br>
                                            Предыдущий: <?=$prevStart->format('d.m.Y')?> — <?=$prevEnd->format('d.m.Y')?>
                                        </p>

                                        <table class="table table-sm align-middle">
                                            <tr>
                                                <th>Выручка</th>
                                                <td><?=number_format($m['total_revenue'],0,',',' ')?> ₽<br>
                                                    <small class="<?=compare($m['total_revenue'],$prev['total_revenue'])>0?'delta-up':(compare($m['total_revenue'],$prev['total_revenue'])<0?'delta-down':'delta-null')?>">
                                                        Было: <?=number_format($prev['total_revenue'],0,',',' ')?> ₽
                                                        (<?=compare($m['total_revenue'],$prev['total_revenue'])?>%)
                                                    </small>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Заказы</th>
                                                <td><?=$m['total_orders']?><br>
                                                    <small class="<?=compare($m['total_orders'],$prev['total_orders'])>0?'delta-up':(compare($m['total_orders'],$prev['total_orders'])<0?'delta-down':'delta-null')?>">
                                                        Было: <?=$prev['total_orders']?>
                                                        (<?=compare($m['total_orders'],$prev['total_orders'])?>%)
                                                    </small>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Клиенты</th>
                                                <td><?=$m['total_customers']?><br>
                                                    <small class="<?=compare($m['total_customers'],$prev['total_customers'])>0?'delta-up':(compare($m['total_customers'],$prev['total_customers'])<0?'delta-down':'delta-null')?>">
                                                        Было: <?=$prev['total_customers']?>
                                                        (<?=compare($m['total_customers'],$prev['total_customers'])?>%)
                                                    </small>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Средний чек</th>
                                                <td><?=number_format($m['avg_receipt'],0,',',' ')?> ₽<br>
                                                    <small class="<?=compare($m['avg_receipt'],$prev['avg_receipt'])>0?'delta-up':(compare($m['avg_receipt'],$prev['avg_receipt'])<0?'delta-down':'delta-null')?>">Было: <?=number_format($prev['avg_receipt'],0,',',' ')?> ₽
                                                        (<?=compare($m['avg_receipt'],$prev['avg_receipt'])?>%)
                                                    </small>
                                                </td>
                                            </tr>

                                            <tr>
                                                <th>Новые клиенты</th>
                                                <td><?=$m['new_customers']?><br>
                                                    <small class="<?=compare($m['new_customers'],$prev['new_customers'])>0?'delta-up':(compare($m['new_customers'],$prev['new_customers'])<0?'delta-down':'delta-null')?>">
                                                        Было: <?=$prev['new_customers']?>
                                                        (<?=compare($m['new_customers'],$prev['new_customers'])?>%)
                                                    </small>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Повторные клиенты</th>
                                                <td><?=$m['repeat_customers']?><br>
                                                    <small class="<?=compare($m['repeat_customers'],$prev['repeat_customers'])>0?'delta-up':(compare($m['repeat_customers'],$prev['repeat_customers'])<0?'delta-down':'delta-null')?>">
                                                        Было: <?=$prev['repeat_customers']?>
                                                        (<?=compare($m['repeat_customers'],$prev['repeat_customers'])?>%)
                                                    </small>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th>Частота заказов</th>
                                                <td><?=$m['avg_frequency']?><br>
                                                    <small class="<?=compare($m['avg_frequency'],$prev['avg_frequency'])>0?'delta-up':(compare($m['avg_frequency'],$prev['avg_frequency'])<0?'delta-down':'delta-null')?>">
                                                        Было: <?=$prev['avg_frequency']?>
                                                        (<?=compare($m['avg_frequency'],$prev['avg_frequency'])?>%)
                                                    </small>
                                                </td>
                                            </tr>

                                        </table>

                                        <canvas id="chart_<?=$dim?>_<?=$val?>" height="120"></canvas>

                                        <?php if(!empty($block['products'][$val])): ?>
                                            <div class="mt-3">
                                                <h6>Топ товары</h6>
                                                <div class="row">
                                                    <?php foreach($block['products'][$val] as $p): ?>
                                                        <div class="col-6">
                                                            <div class="product-card">
                                                                <div class="product-title"><?=htmlspecialchars($p['title'])?></div>
                                                                <div class="product-meta">
                                                                    <?=$p['quantity']?> шт · <?=number_format($p['revenue'],0,',',' ')?> ₽
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
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
