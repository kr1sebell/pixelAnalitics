import React from 'react';
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Tooltip,
  Legend
} from 'chart.js';
import { Line } from 'react-chartjs-2';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Tooltip, Legend);

export default function ProductsTrend({ data }) {
  const labels = data.series.map((point) => point.date);
  const revenue = data.series.map((point) => point.revenue_sum);

  const chartData = {
    labels,
    datasets: [
      {
        label: 'Выручка',
        data: revenue,
        borderColor: '#2563eb',
        backgroundColor: 'rgba(37, 99, 235, 0.3)',
        tension: 0.3,
        fill: true
      }
    ]
  };

  return (
    <div>
      <h2>Дневной тренд продаж</h2>
      <Line data={chartData} options={{ responsive: true, plugins: { legend: { display: false } } }} />
    </div>
  );
}
