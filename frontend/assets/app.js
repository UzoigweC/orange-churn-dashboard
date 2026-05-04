const apiBase = window.APP_CONFIG.apiBaseUrl;
const sampleInput = window.APP_CONFIG.sampleInput || {};

const predictBtn = document.getElementById('predictBtn');
const sampleBtn = document.getElementById('sampleBtn');
const predictForm = document.getElementById('predictForm');
const predictionResult = document.getElementById('predictionResult');
const batchBtn = document.getElementById('batchBtn');
const batchFile = document.getElementById('batchFile');
const batchStatus = document.getElementById('batchStatus');

function formToObject(form) {
  const data = new FormData(form);
  const obj = {};
  for (const [key, value] of data.entries()) {
    obj[key] = value;
  }
  return obj;
}

function setSampleValues() {
  Object.entries(sampleInput).forEach(([key, value]) => {
    const field = predictForm.querySelector(`[name="${key}"]`);
    if (!field) return;
    field.value = value;
  });
}

function riskClassName(label) {
  if (!label) return '';
  if (label.toLowerCase().includes('high')) return 'risk-high';
  if (label.toLowerCase().includes('medium')) return 'risk-medium';
  return 'risk-low';
}

async function runPrediction() {
  predictionResult.classList.add('hidden');
  predictionResult.innerHTML = '';

  const payload = formToObject(predictForm);
  const response = await fetch(`${apiBase}/predict`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  });

  if (!response.ok) {
    predictionResult.classList.remove('hidden');
    predictionResult.innerHTML = '<p>Prediction failed. Please check that the Python API is running and reachable.</p>';
    return;
  }

  const result = await response.json();
  const contributors = (result.top_contributors || []).map(item =>
    `<li><strong>${item.feature}</strong>: ${Number(item.contribution).toFixed(4)}</li>`
  ).join('');

  predictionResult.innerHTML = `
    <h3>Prediction result</h3>
    <p><strong>Predicted churn probability:</strong> ${Number(result.probability).toFixed(4)}</p>
    <p><strong>Predicted class:</strong> ${result.predicted_class}</p>
    <p><strong>Risk band:</strong> <span class="${riskClassName(result.recommended_risk_band)}">${result.recommended_risk_band}</span></p>
    <p><strong>Threshold used:</strong> ${Number(result.threshold_used).toFixed(4)}</p>
    <h4>Top contributing features</h4>
    <ul>${contributors}</ul>
    <p>This output should be used for prioritisation and review. Because the Orange features are anonymised, the output does not by itself establish business causality.</p>
  `;
  predictionResult.classList.remove('hidden');
}

async function runBatchPrediction(event) {
  event.preventDefault();
  if (!batchFile.files.length) {
    batchStatus.textContent = 'Please choose a CSV file first.';
    return;
  }

  const formData = new FormData();
  formData.append('file', batchFile.files[0]);
  batchStatus.textContent = 'Running batch prediction...';

  const response = await fetch(`${apiBase}/predict-batch`, {
    method: 'POST',
    body: formData
  });

  if (!response.ok) {
    batchStatus.textContent = 'Batch prediction failed. Please confirm that the API is running and that the CSV columns match the template.';
    return;
  }

  const blob = await response.blob();
  const url = window.URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'orange_dashboard_predictions.csv';
  document.body.appendChild(a);
  a.click();
  a.remove();
  window.URL.revokeObjectURL(url);
  batchStatus.textContent = 'Batch prediction completed. Your scored CSV has been downloaded.';
}

sampleBtn?.addEventListener('click', (event) => {
  event.preventDefault();
  setSampleValues();
});

predictBtn?.addEventListener('click', async (event) => {
  event.preventDefault();
  await runPrediction();
});

batchBtn?.addEventListener('click', runBatchPrediction);
