import React from 'react';

export default function RfmHeatmap({ data }) {
  const matrix = buildMatrix(data.items || []);

  return (
    <div>
      <h2>RFM тепловая карта</h2>
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(5, 1fr)', gap: '4px' }}>
        {matrix.map((row, rowIndex) =>
          row.map((cell, colIndex) => (
            <div
              key={`${rowIndex}-${colIndex}`}
              style={{
                padding: '12px',
                background: `rgba(37, 99, 235, ${cell.share})`,
                color: cell.share > 0.5 ? '#fff' : '#111',
                textAlign: 'center',
                borderRadius: '6px'
              }}
            >
              <div style={{ fontSize: '0.8rem' }}>R{5 - rowIndex} / F{colIndex + 1}</div>
              <div style={{ fontSize: '1.1rem', fontWeight: 600 }}>{(cell.share * 100).toFixed(1)}%</div>
            </div>
          ))
        )}
      </div>
    </div>
  );
}

function buildMatrix(items) {
  const totals = Array.from({ length: 5 }, () => Array.from({ length: 5 }, () => ({ value: 0, share: 0 })));
  let totalValue = 0;
  items.forEach((item) => {
    const rIndex = Math.max(0, Math.min(4, 5 - item.r_class));
    const fIndex = Math.max(0, Math.min(4, item.f_class - 1));
    totals[rIndex][fIndex].value += item.monetary_90d;
    totalValue += item.monetary_90d;
  });
  if (totalValue === 0) {
    return totals;
  }
  return totals.map((row) =>
    row.map((cell) => ({
      value: cell.value,
      share: cell.value / totalValue
    }))
  );
}
