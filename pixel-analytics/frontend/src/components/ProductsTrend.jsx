import React, { useEffect, useRef, useState } from 'react';
import Chart from 'chart.js/auto';
import { getProductsDaily } from '../api.js';

function formatDate(date) {
  return date.toISOString().slice(0, 10);
}

export default function ProductsTrend() {
  const [from, setFrom] = useState(() => {
    const d = new Date();
    d.setDate(d.getDate() - 30);
    return formatDate(d);
  });
  const [to, setTo] = useState(() => formatDate(new Date()));
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const chartRef = useRef(null);
  const canvasRef = useRef(null);

  useEffect(() => {
    setLoading(true);
    setError(null);
    getProductsDaily(from, to)
      .then(setData)
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false));
  }, [from, to]);

  useEffect(() => {
    if (!data || !canvasRef.current) {
      return;
    }
    if (chartRef.current) {
      chartRef.current.destroy();
    }
    const labels = data.points.map((p) => p.date);
    chartRef.current = new Chart(canvasRef.current, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'Выручка',
            data: data.points.map((p) => p.revenue),
            borderColor: '#6366f1',
            tension: 0.3,
            fill: false
          },
          {
            label: 'Заказы',
            data: data.points.map((p) => p.orders),
            borderColor: '#22d3ee',
            tension: 0.3,
            fill: false,
            yAxisID: 'y1'
          }
        ]
      },
      options: {
        responsive: true,
        interaction: { mode: 'index', intersect: false },
        scales: {
          y: {
            beginAtZero: true,
            title: { display: true, text: 'Выручка, ₽' }
          },
          y1: {
            beginAtZero: true,
            position: 'right',
            grid: { drawOnChartArea: false },
            title: { display: true, text: 'Заказы' }
          }
        }
      }
    });
  }, [data]);

  return (
    <div className="card">
      <div className="filters">
        <h3>Продажи по дням</h3>
        <div>
          <label>С:&nbsp;</label>
          <input className="select" type="date" value={from} onChange={(e) => setFrom(e.target.value)} />
        </div>
        <div>
          <label>По:&nbsp;</label>
          <input className="select" type="date" value={to} onChange={(e) => setTo(e.target.value)} />
        </div>
      </div>
      {loading && <p>Загрузка…</p>}
      {error && <p>Ошибка: {error}</p>}
      {data && (
        <div className="chart-container">
          <canvas ref={canvasRef}></canvas>
        </div>
      )}
    </div>
  );
}
