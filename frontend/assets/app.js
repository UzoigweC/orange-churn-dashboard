const apiBase = window.APP_CONFIG.apiBaseUrl;
const sampleInput = window.APP_CONFIG.sampleInput || {};

const predictBtn = document.getElementById('predictBtn');
const sampleBtn = document.getElementById('sampleBtn');
const predictForm = document.getElementById('predictForm');
const predictionResult = document.getElementById('predictionResult');

function formToObject(form) {
  const data = new FormData(form);
  const out = {};
  for (const [key, value] of data.entries()) out[key] = value;
  return out;
}

function loadSampleValues() {
  Object.entries(sampleInput).forEach(([key, value]) => {
    const field = predictForm.querySelector(`[name="${key}"]`);
    if (!field || value === null || value === undefined) return;
    field.value = String(value);
  });
}

function bandClass(label) {
  if (!label) return 'neutral';
  const lower = label.toLowerCase();
  if (lower.includes('high')) return 'high';
  if (lower.includes('medium')) return 'medium';
  if (lower.includes('low')) return 'low';
  return 'neutral';
}

function renderResult(result) {
  const contributors = (result.top_contributors || []).map(item => {
    const contribution = Number(item.contribution || 0).toFixed(4);
    return `<li><span>${item.feature}</span><strong>${contribution}</strong></li>`;
  }).join('');

  predictionResult.innerHTML = `
    <div class="result-number">${Number(result.probability).toFixed(2)}</div>
    <div class="result-band ${bandClass(result.recommended_risk_band)}">${result.recommended_risk_band}</div>
    <p><strong>Predicted class:</strong> ${result.predicted_class} &nbsp;&nbsp; <strong>Threshold:</strong> ${Number(result.threshold_used).toFixed(4)}</p>
    <ul class="contrib-list">${contributors}</ul>
  `;
}

async function runPrediction() {
  predictionResult.innerHTML = `<div class="result-number">...</div><div class="result-band neutral">Running prediction</div><p>Please wait while the dashboard sends the selected values to the prediction API.</p>`;

  try {
    const response = await fetch(`${apiBase}/predict`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(formToObject(predictForm))
    });

    if (!response.ok) {
      predictionResult.innerHTML = `<div class="result-number">!</div><div class="result-band high">Prediction failed</div><p>The dashboard could not reach the prediction API. Confirm that the API service is running and that <code>frontend/config.php</code> points to the live API URL.</p>`;
      return;
    }

    const result = await response.json();
    renderResult(result);
  } catch (error) {
    predictionResult.innerHTML = `<div class="result-number">!</div><div class="result-band high">Connection error</div><p>${error.message}</p>`;
  }
}

sampleBtn?.addEventListener('click', loadSampleValues);
predictBtn?.addEventListener('click', runPrediction);

document.querySelectorAll('.side-nav a').forEach(link => {
  link.addEventListener('click', () => {
    document.querySelectorAll('.side-nav a').forEach(a => a.classList.remove('active'));
    link.classList.add('active');
  });
});
