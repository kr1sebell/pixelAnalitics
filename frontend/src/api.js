import axios from 'axios';

const client = axios.create({
  baseURL: '/api'
});

export async function getHealth() {
  const { data } = await client.get('/health');
  return data.data;
}

export async function getKpi(period = '30d') {
  const { data } = await client.get('/kpi', { params: { period } });
  return data.data;
}

export async function getSegmentsCompare(periods = 2) {
  const { data } = await client.get('/segments/compare', { params: { periods } });
  return data.data;
}

export async function getRfm(filter = 'r>=4&f>=3&m>=3', limit = 25) {
  const { data } = await client.get('/rfm', { params: { filter, limit } });
  return data.data;
}

export async function getProductsDaily(range) {
  const { from, to } = range;
  const { data } = await client.get('/products/daily', { params: { from, to } });
  return data.data;
}

export async function getCohorts() {
  const { data } = await client.get('/cohorts');
  return data.data;
}
