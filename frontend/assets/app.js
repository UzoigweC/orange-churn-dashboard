const apiBase = window.APP_CONFIG?.apiBaseUrl || '';
const sampleInput = window.APP_CONFIG?.sampleInput || {};

const predictBtn = document.getElementById('predictBtn');
const sampleBtn = document.getElementById('sampleBtn');
const predictForm = document.getElementById('predictForm');
const predictionResult = document.getElementById('predictionResult');
const batchBtn = document.getElementById('batchBtn');
const batchFile = document.getElementById('batchFile');
const batchStatus = document.getElementById('batchStatus');

function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function formToObject(form) {
  const data = new FormData(form);
  const obj = {};
  for (const [key, value] of data.entries()) obj[key] = value;
  return obj;
}

function setSampleValues() {
  if (!predictForm) return;
  Object.entries(sampleInput).forEach(([key, value]) => {
    const field = predictForm.querySelector(`[name="${key}"]`);
    if (field) field.value = value;
  });
}

function riskClassName(label) {
  if (!label) return 'neutral';
  const txt = String(label).toLowerCase();
  if (txt.includes('high')) return 'risk-high';
  if (txt.includes('medium')) return 'risk-medium';
  if (txt.includes('low')) return 'risk-low';
  return 'neutral';
}

function setPredictLoadingState(isLoading) {
  if (predictBtn) {
    predictBtn.disabled = isLoading;
    predictBtn.textContent = isLoading ? 'Predicting...' : 'Predict risk';
  }
  if (sampleBtn) sampleBtn.disabled = isLoading;
}

function setBatchLoadingState(isLoading) {
  if (batchBtn) {
    batchBtn.disabled = isLoading;
    batchBtn.textContent = isLoading ? 'Running...' : 'Run batch prediction';
  }
  if (batchFile) batchFile.disabled = isLoading;
}

function renderPredictionResult(result) {
  const probability = Number(result?.probability ?? 0);
  const threshold = Number(result?.threshold_used ?? 0);
  const predictedClass = result?.predicted_class ?? 0;
  const band = result?.recommended_risk_band || 'No band returned';
  const contributors = Array.isArray(result?.top_contributors) ? result.top_contributors : [];

  const contributorHtml = contributors.length
    ? contributors.map((item) => {
        const feature = escapeHtml(item.feature);
        const selectedValue = escapeHtml(item.selected_value);
        const contribution = Number(item.contribution ?? 0).toFixed(4);
        return `<li><strong>${feature}</strong> (${selectedValue}): ${contribution}</li>`;
      }).join('')
    : '<li>No contributor details returned.</li>';

  predictionResult.innerHTML = `
    <div class="result-number">${probability.toFixed(2)}</div>
    <div class="result-band ${riskClassName(band)}">${escapeHtml(band)}</div>
    <p><strong>Predicted class:</strong> ${escapeHtml(predictedClass)}</p>
    <p><strong>Threshold used:</strong> ${threshold.toFixed(3)}</p>
    <h4>Top contributors</h4>
    <ul>${contributorHtml}</ul>
    <p class="note">This score is produced from the six highest-importance anonymised features only and is intended as a dashboard-friendly manual scoring view.</p>
  `;
}

function renderPredictionError(message) {
  predictionResult.innerHTML = `
    <div class="result-number">Error</div>
    <div class="result-band neutral">Request failed</div>
    <p>${escapeHtml(message)}</p>
    <p>Check that the API service is online, that <code>frontend/config.php</code> contains the correct Railway API URL, and that the API domain opens successfully.</p>
  `;
}

async function runPrediction() {
  if (!predictionResult || !predictForm) return;

  predictionResult.innerHTML = `
    <div class="result-number">...</div>
    <div class="result-band neutral">Running prediction</div>
    <p>Please wait while the dashboard sends the selected values to the prediction API.</p>
  `;

  if (!apiBase) {
    renderPredictionError('API base URL is missing.');
    return;
  }

  const payload = formToObject(predictForm);
  setPredictLoadingState(true);

  try {
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 15000);

    const response = await fetch(`${apiBase}/predict`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
      signal: controller.signal
    });

    clearTimeout(timeout);

    if (!response.ok) {
      let errorText = `Prediction failed with status ${response.status}.`;
      try {
        const errorJson = await response.json();
        if (errorJson?.error) errorText = errorJson.error;
      } catch (_) {}
      renderPredictionError(errorText);
      return;
    }

    const result = await response.json();
    renderPredictionResult(result);
  } catch (error) {
    console.error('Prediction request failed:', error);
    if (error.name === 'AbortError') {
      renderPredictionError('The request timed out while waiting for the API response.');
    } else {
      renderPredictionError('The dashboard could not reach the prediction API.');
    }
  } finally {
    setPredictLoadingState(false);
  }
}

async function runBatchPrediction() {
  if (!batchStatus || !batchFile) return;
  if (!apiBase) {
    batchStatus.textContent = 'API base URL is missing.';
    return;
  }
  if (!batchFile.files.length) {
    batchStatus.textContent = 'Please choose a CSV file first.';
    return;
  }

  batchStatus.textContent = 'Running batch prediction...';
  setBatchLoadingState(true);

  try {
    const formData = new FormData();
    formData.append('file', batchFile.files[0]);

    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 30000);

    const response = await fetch(`${apiBase}/predict-batch`, {
      method: 'POST',
      body: formData,
      signal: controller.signal
    });

    clearTimeout(timeout);

    if (!response.ok) {
      let errorText = `Batch prediction failed with status ${response.status}.`;
      try {
        const errorJson = await response.json();
        if (errorJson?.error) errorText = errorJson.error;
      } catch (_) {}
      batchStatus.textContent = errorText;
      return;
    }

    const blob = await response.blob();
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'orange_top6_dashboard_predictions.csv';
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
    batchStatus.textContent = 'Batch prediction completed. Your scored CSV has been downloaded.';
  } catch (error) {
    console.error('Batch prediction failed:', error);
    if (error.name === 'AbortError') {
      batchStatus.textContent = 'The batch request timed out while waiting for the API response.';
    } else {
      batchStatus.textContent = 'The dashboard could not reach the prediction API.';
    }
  } finally {
    setBatchLoadingState(false);
  }
}

sampleBtn?.addEventListener('click', (e) => {
  e.preventDefault();
  setSampleValues();
});

predictBtn?.addEventListener('click', (e) => {
  e.preventDefault();
  runPrediction();
});

batchBtn?.addEventListener('click', (e) => {
  e.preventDefault();
  runBatchPrediction();
});
