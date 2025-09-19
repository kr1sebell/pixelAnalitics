import React from 'react';

export default function CohortsChart({ data }) {
  if (!data.items || data.items.length === 0) {
    return (
      <div>
        <h2>Когорты</h2>
        <p>Недостаточно данных.</p>
      </div>
    );
  }

  const months = ['m0', 'm1', 'm2', 'm3', 'm4', 'm5', 'm6'];

  return (
    <div>
      <h2>Когортный анализ</h2>
      <div style={{ overflowX: 'auto' }}>
        <table style={{ width: '100%', borderCollapse: 'collapse' }}>
          <thead>
            <tr>
              <th style={cellStyle}>Когорта</th>
              {months.map((month) => (
                <th key={month} style={cellStyle}>{month.toUpperCase()}</th>
              ))}
            </tr>
          </thead>
          <tbody>
            {data.items.map((row) => (
              <tr key={row.cohort_month}>
                <td style={cellStyle}>{row.cohort_month}</td>
                {months.map((month) => (
                  <td key={month} style={cellStyle}>{row[month]}</td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}

const cellStyle = {
  borderBottom: '1px solid #eee',
  padding: '8px',
  textAlign: 'center'
};
