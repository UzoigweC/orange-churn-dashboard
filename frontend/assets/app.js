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
  for (const [key, value] of data.entries()) obj[key] = value;
  return obj;
}

function setSampleValues() {
  Object.entries(sampleInput).forEach(([key, value]) => {
    const field = predictForm.querySelector(`[name="${key}"]`);
    if (field) field.value = value;
  });
}

function riskClassName(label) {
  if (!label) return 'neutral';
  const txt = label.toLowerCase();
  if (txt.includes('high')) return 'risk-high';
  if (txt.includes('medium')) return 'risk-medium';
  return 'risk-low';
}

async function runPrediction() {
  predictionResult.innerHTML = '<p>Running prediction...</p>';
  const payload = formToObject(predictForm);

  const response = await fetch(`${apiBase}/predict`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  });

  if (!response.ok) {
    predictionResult.innerHTML = '<p>Prediction failed. Please check that the API is deployed and that frontend/config.php points to the correct API URL.</p>';
    return;
  }

  const result = await response.json();
  const contributors = (result.top_contributors || []).map(item =>
    `<li><strong>${item.feature}</strong> (${item.selected_value}): ${Number(item.contribution).toFixed(4)}</li>`
  ).join('');

  predictionResult.innerHTML = `
    <div class="result-number">${Number(result.probability).toFixed(2)}</div>
    <div class="result-band ${riskClassName(result.recommended_risk_band)}">${result.recommended_risk_band}</div>
    <p><strong>Predicted class:</strong> ${result.predicted_class}</p>
    <p><strong>Threshold used:</strong> ${Number(result.threshold_used).toFixed(3)}</p>
    <h4>Top contributors</h4>
    <ul>${contributors}</ul>
    <p class="note">This score is produced from the six highest-importance anonymised features only and is intended as a dashboard-friendly manual scoring view.</p>
  `;
}

async function runBatchPrediction() {
  if (!batchFile.files.length) {
    batchStatus.textContent = 'Please choose a CSV file first.';
    return;
  }

  batchStatus.textContent = 'Running batch prediction...';
  const formData = new FormData();
  formData.append('file', batchFile.files[0]);

  const response = await fetch(`${apiBase}/predict-batch`, {
    method: 'POST',
    body: formData
  });

  if (!response.ok) {
    batchStatus.textContent = 'Batch prediction failed. Please confirm the CSV matches the template and that the API is reachable.';
    return;
  }

  const blob = await response.blob();
  const url = window.URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'orange_top6_dashboard_predictions.csv';
  document.body.appendChild(a);
  a.click();
  a.remove();
  window.URL.revokeObjectURL(url);
  batchStatus.textContent = 'Batch prediction completed. Your scored CSV has been downloaded.';
}

sampleBtn?.addEventListener('click', (e) => { e.preventDefault(); setSampleValues(); });
predictBtn?.addEventListener('click', (e) => { e.preventDefault(); runPrediction(); });
batchBtn?.addEventListener('click', (e) => { e.preventDefault(); runBatchPrediction(); });
