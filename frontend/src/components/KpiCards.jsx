import React from 'react';

export default function KpiCards({ data }) {
  const metrics = [
    { label: 'Оборот', value: formatCurrency(data.revenue) },
    { label: 'Заказы', value: data.orders.toLocaleString('ru-RU') },
    { label: 'Средний чек', value: formatCurrency(data.avg_check) },
    { label: 'Ретеншн', value: (data.retention * 100).toFixed(1) + '%' }
  ];

  return (
    <div>
      <h2>Ключевые показатели</h2>
      <div style={{ display: 'grid', gap: '1rem' }}>
        {metrics.map((metric) => (
          <div key={metric.label}>
            <div style={{ fontSize: '0.9rem', color: '#666' }}>{metric.label}</div>
            <div style={{ fontSize: '1.6rem', fontWeight: 600 }}>{metric.value}</div>
          </div>
        ))}
      </div>
    </div>
  );
}

function formatCurrency(value) {
  return new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency: 'RUB',
    maximumFractionDigits: 0
  }).format(value || 0);
}
