import React from 'react';
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
  Legend
} from 'chart.js';
import { Bar } from 'react-chartjs-2';

ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend);

export default function SegmentsCompare({ data }) {
  const labels = data.groups.map((group) => `${group.sex || 'N/A'} · ${group.age_bucket} · ${group.city}`);
  const revenueCurrent = data.groups.map((group) => group.current.revenue);
  const revenuePrevious = data.groups.map((group) => group.previous.revenue);

  const chartData = {
    labels,
    datasets: [
      {
        label: 'Текущая неделя',
        backgroundColor: '#2563eb',
        data: revenueCurrent
      },
      {
        label: 'Прошлая неделя',
        backgroundColor: '#a5b4fc',
        data: revenuePrevious
      }
    ]
  };

  return (
    <div>
      <h2>Сегменты: выручка WoW</h2>
      <Bar data={chartData} options={{ responsive: true, plugins: { legend: { position: 'bottom' } } }} />
    </div>
  );
}
