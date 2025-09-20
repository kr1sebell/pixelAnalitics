import React, { useEffect, useState } from 'react';
import { getKpi } from '../api.js';

const periods = ['7d', '30d', '90d'];

export default function KpiCards() {
  const [period, setPeriod] = useState(periods[1]);
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => {
    setLoading(true);
    setError(null);
    getKpi(period)
      .then(setData)
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false));
  }, [period]);

  return (
    <div className="card">
      <div className="filters">
        <h3>Ключевые показатели</h3>
        <select className="select" value={period} onChange={(e) => setPeriod(e.target.value)}>
          {periods.map((p) => (
            <option key={p} value={p}>{p}</option>
          ))}
        </select>
      </div>
      {loading && <p>Загрузка…</p>}
      {error && <p>Ошибка: {error}</p>}
      {data && (
        <div className="grid">
          <div>
            <div className="badge">Период</div>
            <p>{data.period.from} — {data.period.to}</p>
          </div>
          <div>
            <div className="badge">Оборот</div>
            <p>{data.revenue.toLocaleString('ru-RU', { style: 'currency', currency: 'RUB' })}</p>
          </div>
          <div>
            <div className="badge">Заказы</div>
            <p>{data.orders}</p>
          </div>
          <div>
            <div className="badge">Средний чек</div>
            <p>{data.avg_check.toLocaleString('ru-RU', { style: 'currency', currency: 'RUB' })}</p>
          </div>
          <div>
            <div className="badge">Повторные</div>
            <p>{(data.repeat_rate * 100).toFixed(1)}%</p>
          </div>
        </div>
      )}
    </div>
  );
}
