const defaultHeaders = {
  'Accept': 'application/json'
};

async function request(url) {
  const res = await fetch(url, { headers: defaultHeaders });
  if (!res.ok) {
    throw new Error(`API error ${res.status}`);
  }
  return res.json();
}

export function getKpi(period = '30d') {
  return request(`/api/kpi?period=${encodeURIComponent(period)}`);
}

export function getSegmentsCompare(granularity = 'week', periods = 2) {
  return request(`/api/segments/compare?group=sex_age_city&granularity=${granularity}&periods=${periods}`);
}

export function getRfm(filter = 'r>=4&f>=3&m>=3', limit = 100) {
  return request(`/api/rfm?filter=${encodeURIComponent(filter)}&limit=${limit}`);
}

export function getProductsDaily(from, to) {
  return request(`/api/products/daily?from=${from}&to=${to}`);
}

export function getCohorts(limit = 6) {
  return request(`/api/cohorts?limit=${limit}`);
}
