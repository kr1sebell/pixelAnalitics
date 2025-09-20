import React, { useEffect, useRef, useState } from 'react';
import Chart from 'chart.js/auto';
import { getSegmentsCompare } from '../api.js';

const sexMap = {
  1: 'Женщины',
  2: 'Мужчины'
};

function formatSegmentLabel(segment) {
  const parts = [];
  if (segment.sex && sexMap[segment.sex]) {
    parts.push(sexMap[segment.sex]);
  }
  if (segment.age_bucket) {
    parts.push(segment.age_bucket);
  }
  if (segment.city) {
    parts.push(segment.city);
  }
  if (parts.length === 0) {
    return 'Без данных';
  }
  return parts.join(' / ');
}

export default function SegmentsCompare() {
  const [granularity, setGranularity] = useState('week');
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const chartRef = useRef(null);
  const canvasRef = useRef(null);

  useEffect(() => {
    setLoading(true);
    setError(null);
    getSegmentsCompare(granularity, 2)
      .then((res) => {
        setData(res);
      })
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false));
  }, [granularity]);

  useEffect(() => {
    if (!data || !canvasRef.current) {
      return;
    }
    if (chartRef.current) {
      chartRef.current.destroy();
    }
    const labels = data.segments.map(formatSegmentLabel);
    const current = data.segments.map((segment) => segment.orders_this);
    const previous = data.segments.map((segment) => segment.orders_prev);
    chartRef.current = new Chart(canvasRef.current, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Текущий период',
            data: current,
            backgroundColor: 'rgba(59, 130, 246, 0.6)'
          },
          {
            label: 'Предыдущий период',
            data: previous,
            backgroundColor: 'rgba(156, 163, 175, 0.6)'
          }
        ]
      },
      options: {
        responsive: true,
        interaction: { intersect: false, mode: 'index' },
        plugins: {
          legend: { position: 'bottom' }
        },
        scales: {
          x: { stacked: false },
          y: { beginAtZero: true }
        }
      }
    });
  }, [data]);

  return (
    <div className="card">
      <div className="filters">
        <h3>Сегменты</h3>
        <select className="select" value={granularity} onChange={(e) => setGranularity(e.target.value)}>
          <option value="week">Неделя</option>
          <option value="month">Месяц</option>
        </select>
      </div>
      {loading && <p>Загрузка…</p>}
      {error && <p>Ошибка: {error}</p>}
      {data && (
        <>
          <div className="chart-container">
            <canvas ref={canvasRef}></canvas>
          </div>
          <table className="table">
            <thead>
              <tr>
                <th>Сегмент</th>
                <th>Заказы</th>
                <th>Δ заказов</th>
                <th>Выручка</th>
                <th>Δ выручки</th>
                <th>AVG чек</th>
              </tr>
            </thead>
            <tbody>
              {data.segments.map((segment) => (
                <tr key={segment.segment_key}>
                  <td>{formatSegmentLabel(segment)}</td>
                  <td>{segment.orders_this} / {segment.orders_prev}</td>
                  <td>{segment.orders_diff} ({segment.orders_pct !== null ? (segment.orders_pct * 100).toFixed(1) + '%' : '—'})</td>
                  <td>{segment.revenue_this.toLocaleString('ru-RU', { style: 'currency', currency: 'RUB' })} / {segment.revenue_prev.toLocaleString('ru-RU', { style: 'currency', currency: 'RUB' })}</td>
                  <td>{segment.revenue_diff.toLocaleString('ru-RU', { style: 'currency', currency: 'RUB' })} ({segment.revenue_pct !== null ? (segment.revenue_pct * 100).toFixed(1) + '%' : '—'})</td>
                  <td>{segment.avg_check_this.toLocaleString('ru-RU', { style: 'currency', currency: 'RUB' })}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </>
      )}
    </div>
  );
}
