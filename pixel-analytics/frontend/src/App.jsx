import React from 'react';
import KpiCards from './components/KpiCards.jsx';
import SegmentsCompare from './components/SegmentsCompare.jsx';
import RfmHeatmap from './components/RfmHeatmap.jsx';
import ProductsTrend from './components/ProductsTrend.jsx';
import CohortsChart from './components/CohortsChart.jsx';

export default function App() {
  return (
    <div className="app-container">
      <h1>PixelAnalytics</h1>
      <p>Оперативная аналитика заказов на основе предрассчитанных сводок.</p>
      <div className="grid">
        <KpiCards />
        <RfmHeatmap />
      </div>
      <h2 className="section-title">Динамика и сегменты</h2>
      <div className="grid">
        <SegmentsCompare />
        <ProductsTrend />
      </div>
      <h2 className="section-title">Когорты</h2>
      <CohortsChart />
    </div>
  );
}
