import React, { useEffect, useState } from 'react';
import { getKpi, getSegmentsCompare, getRfm, getProductsDaily, getCohorts } from './api.js';
import KpiCards from './components/KpiCards.jsx';
import SegmentsCompare from './components/SegmentsCompare.jsx';
import RfmHeatmap from './components/RfmHeatmap.jsx';
import ProductsTrend from './components/ProductsTrend.jsx';
import CohortsChart from './components/CohortsChart.jsx';

const defaultRange = {
  from: new Date(Date.now() - 29 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10),
  to: new Date().toISOString().slice(0, 10)
};

export default function App() {
  const [kpi, setKpi] = useState(null);
  const [segments, setSegments] = useState(null);
  const [rfm, setRfm] = useState(null);
  const [products, setProducts] = useState(null);
  const [cohorts, setCohorts] = useState(null);
  const [error, setError] = useState(null);

  useEffect(() => {
    async function load() {
      try {
        const [kpiData, segmentsData, rfmData, productData, cohortsData] = await Promise.all([
          getKpi('30d'),
          getSegmentsCompare(2),
          getRfm('r>=4&f>=3&m>=3', 25),
          getProductsDaily(defaultRange),
          getCohorts()
        ]);
        setKpi(kpiData);
        setSegments(segmentsData);
        setRfm(rfmData);
        setProducts(productData);
        setCohorts(cohortsData);
      } catch (err) {
        console.error(err);
        setError('Не удалось загрузить данные. Проверьте доступность API.');
      }
    }
    load();
  }, []);

  if (error) {
    return <div className="app-container">{error}</div>;
  }

  if (!kpi || !segments || !rfm || !products || !cohorts) {
    return <div className="app-container">Загрузка...</div>;
  }

  return (
    <div className="app-container">
      <h1>PixelAnalytics</h1>
      <div className="grid" style={{ gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))' }}>
        <div className="card">
          <KpiCards data={kpi} />
        </div>
        <div className="card">
          <SegmentsCompare data={segments} />
        </div>
        <div className="card">
          <RfmHeatmap data={rfm} />
        </div>
        <div className="card" style={{ gridColumn: '1 / -1' }}>
          <ProductsTrend data={products} />
        </div>
        <div className="card" style={{ gridColumn: '1 / -1' }}>
          <CohortsChart data={cohorts} />
        </div>
      </div>
    </div>
  );
}
