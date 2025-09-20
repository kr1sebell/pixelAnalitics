import React, { useEffect, useState } from 'react';
import { getRfm } from '../api.js';

const mOptions = [1, 2, 3, 4, 5];

function cellColor(share) {
  const intensity = Math.min(1, share * 5);
  const alpha = 0.15 + intensity * 0.85;
  return `rgba(76, 175, 80, ${alpha.toFixed(2)})`;
}

export default function RfmHeatmap() {
  const [mClass, setMClass] = useState(3);
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => {
    setLoading(true);
    setError(null);
    const filter = `m>=${mClass}`;
    getRfm(filter, 200)
      .then(setData)
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false));
  }, [mClass]);

  const matrix = data ? data.matrix : null;

  return (
    <div className="card">
      <div className="filters">
        <h3>RFM тепловая карта</h3>
        <div>
          <label>Минимальный M класс:&nbsp;</label>
          <select className="select" value={mClass} onChange={(e) => setMClass(Number(e.target.value))}>
            {mOptions.map((option) => (
              <option key={option} value={option}>M ≥ {option}</option>
            ))}
          </select>
        </div>
      </div>
      {loading && <p>Загрузка…</p>}
      {error && <p>Ошибка: {error}</p>}
      {matrix && (
        <>
          <p>Доля выручки по классам R×F (фильтр по M).</p>
          <table className="table">
            <thead>
              <tr>
                <th>R \ F</th>
                {[1, 2, 3, 4, 5].map((f) => (
                  <th key={f}>F{f}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {[5, 4, 3, 2, 1].map((r) => (
                <tr key={r}>
                  <th>R{r}</th>
                  {[1, 2, 3, 4, 5].map((f) => {
                    const cell = matrix.cells[r][f];
                    const share = cell.share;
                    return (
                      <td key={f} style={{ background: cellColor(share) }}>
                        <div>{(share * 100).toFixed(1)}%</div>
                        <small>{cell.revenue.toLocaleString('ru-RU', { style: 'currency', currency: 'RUB' })}</small>
                      </td>
                    );
                  })}
                </tr>
              ))}
            </tbody>
          </table>
        </>
      )}
    </div>
  );
}
