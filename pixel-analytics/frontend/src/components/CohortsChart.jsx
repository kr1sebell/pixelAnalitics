import React, { useEffect, useState } from 'react';
import { getCohorts } from '../api.js';

export default function CohortsChart() {
  const [limit, setLimit] = useState(6);
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => {
    setLoading(true);
    setError(null);
    getCohorts(limit)
      .then(setData)
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false));
  }, [limit]);

  const cohorts = data ? data.cohorts : [];
  const maxPeriods = cohorts.reduce((max, cohort) => Math.max(max, cohort.periods.length), 0);

  return (
    <div className="card">
      <div className="filters">
        <h3>Когорты (месяц регистрации)</h3>
        <select className="select" value={limit} onChange={(e) => setLimit(Number(e.target.value))}>
          {[3, 6, 9, 12].map((option) => (
            <option key={option} value={option}>Последние {option}</option>
          ))}
        </select>
      </div>
      {loading && <p>Загрузка…</p>}
      {error && <p>Ошибка: {error}</p>}
      {cohorts.length > 0 && (
        <table className="table">
          <thead>
            <tr>
              <th>Когорта</th>
              <th>Размер</th>
              {Array.from({ length: maxPeriods }).map((_, index) => (
                <th key={index}>М+{index}</th>
              ))}
            </tr>
          </thead>
          <tbody>
            {cohorts.map((cohort) => (
              <tr key={cohort.cohort}>
                <td>{cohort.cohort}</td>
                <td>{cohort.size}</td>
                {Array.from({ length: maxPeriods }).map((_, index) => {
                  const period = cohort.periods.find((p) => p.month_offset === index);
                  return (
                    <td key={index}>
                      {period ? (
                        <>
                          <div>{(period.retention * 100).toFixed(1)}%</div>
                          <small>{period.users}</small>
                        </>
                      ) : '—'}
                    </td>
                  );
                })}
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  );
}
