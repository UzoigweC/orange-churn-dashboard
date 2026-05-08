<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/inc/helpers.php';

$modelRows = load_csv_assoc(__DIR__ . '/data/model_comparison_table.csv');
$featureRows = load_csv_assoc(__DIR__ . '/data/top_feature_table.csv');
$imbalanceRows = load_csv_assoc(__DIR__ . '/data/imbalance_strategy_table.csv');
$featureSelectionRows = load_csv_assoc(__DIR__ . '/data/feature_selection_table.csv');
$modelPayload = load_json_file(__DIR__ . '/data/model_payload.json');

$features = $modelPayload['features'] ?? [];
$catOptions = $modelPayload['cat_options'] ?? [];
$metrics = $modelPayload['metrics'] ?? [];
$sampleInput = $modelPayload['sample_input'] ?? [];
$bestModel = 'XGBoost (Top 6)';
$heroRoc = $metrics['roc_auc'] ?? '';
$heroPr = $metrics['pr_auc'] ?? '';
$heroThreshold = $metrics['threshold'] ?? '';
$topFeatureDisplay = array_slice($featureRows, 0, 6);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Orange Churn Dashboard - Top 6</title>
  <link rel="stylesheet" href="/assets/style.css?v=2">
</head>
<body>
  <div class="app-shell">
    <aside class="sidebar">
      <div class="brand">
        <div class="brand-dot"></div>
        <div>
          <h1>Orange Churn</h1>
          <p>Top-6 Dashboard</p>
        </div>
      </div>

      <nav class="side-nav">
        <a href="#overview" class="active">Overview</a>
        <a href="#comparison">Model comparison</a>
        <a href="#features">Top features</a>
        <a href="#prediction">Prediction</a>
        <a href="#batch">Batch scoring</a>
      </nav>

      <div class="sidebar-note">
        <p>This dashboard uses only the six highest-importance anonymised features for manual scoring, so users can select values instead of typing encoded inputs.</p>
      </div>
    </aside>

    <main class="main-panel">
      <section id="overview" class="panel hero-panel">
        <div class="window-bar"><span></span><span></span><span></span></div>
        <div class="hero-headline">
          <div>
            <h2>Orange Churn Dashboard Prototype</h2>
            <p>Hosted prediction interface built around the six strongest anonymised predictors from the Orange KDD 2009 dataset.</p>
          </div>
          <div class="pill-row">
            <span class="pill">API: <?= htmlspecialchars(API_BASE_URL) ?></span>
            <span class="pill">Threshold: <?= htmlspecialchars((string)$heroThreshold) ?></span>
          </div>
        </div>

        <div class="stats-grid">
          <div class="metric-card highlight">
            <span class="metric-label">Dashboard model</span>
            <strong><?= htmlspecialchars($bestModel) ?></strong>
          </div>
          <div class="metric-card">
            <span class="metric-label">ROC-AUC</span>
            <strong><?= htmlspecialchars(fmt_metric($heroRoc)) ?></strong>
          </div>
          <div class="metric-card">
            <span class="metric-label">PR-AUC</span>
            <strong><?= htmlspecialchars(fmt_metric($heroPr)) ?></strong>
          </div>
          <div class="metric-card">
            <span class="metric-label">Selected inputs</span>
            <strong>6</strong>
          </div>
        </div>

        <div class="overview-grid">
          <section id="comparison" class="subcard chart-card">
            <div class="subcard-header">
              <h3>Model comparison</h3>
              <span>Hold-out benchmark panel</span>
            </div>
            <div class="bar-chart">
              <?php
              $maxVal = 0.0;
              foreach ($modelRows as $row) {
                  $maxVal = max($maxVal, (float)($row['test_roc_auc'] ?? 0));
              }
              foreach ($modelRows as $row):
                  $value = (float)($row['test_roc_auc'] ?? 0);
                  $height = $maxVal > 0 ? max(14, (int)round(($value / $maxVal) * 180)) : 14;
                  $short = str_replace(['XGBoost', 'Logistic regression', 'Random forest', 'Dummy baseline'], ['XGB', 'LR', 'RF', 'Dummy'], $row['model']);
              ?>
                <div class="bar-wrap">
                  <span class="bar-value"><?= htmlspecialchars(number_format($value, 3)) ?></span>
                  <div class="bar" style="height: <?= $height ?>px"></div>
                  <span class="bar-label"><?= htmlspecialchars($short) ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          </section>

          <section id="features" class="subcard top-features-card">
            <div class="subcard-header">
              <h3>Top anonymised predictors</h3>
              <span>Manual scoring uses the top 6 only</span>
            </div>
            <ul class="feature-list compact">
              <?php foreach ($topFeatureDisplay as $row): ?>
                <li>
                  <span class="feature-name"><?= htmlspecialchars($row['feature'] ?? '') ?></span>
                  <span class="feature-score">MI=<?= htmlspecialchars(number_format((float)($row['mutual_information_score'] ?? 0), 3)) ?></span>
                  <span class="feature-note">Official name unavailable</span>
                </li>
              <?php endforeach; ?>
            </ul>
          </section>
        </div>
      </section>

      <section id="prediction" class="panel">
        <div class="section-header">
          <div>
            <h2>Manual prediction</h2>
            <p>The interface below uses six dropdown selectors only. This keeps the interaction simple and avoids asking users to type masked Orange category codes manually.</p>
          </div>
          <div class="button-row">
            <button id="sampleBtn" class="ghost-btn" type="button">Load demo values</button>
            <button id="predictBtn" class="primary-btn" type="button">Predict risk</button>
          </div>
        </div>

        <div class="meta-grid">
          <div><strong>API URL:</strong> <?= htmlspecialchars(API_BASE_URL) ?></div>
          <div><strong>Threshold:</strong> <?= htmlspecialchars((string)$heroThreshold) ?></div>
          <div><strong>Dashboard model ROC-AUC:</strong> <?= htmlspecialchars(fmt_metric($heroRoc, 6)) ?></div>
          <div><strong>Dashboard model PR-AUC:</strong> <?= htmlspecialchars(fmt_metric($heroPr, 6)) ?></div>
        </div>

        <form id="predictForm" class="predict-grid top6-grid">
          <?php foreach ($features as $feature): ?>
            <label class="field-card">
              <span class="field-label"><?= htmlspecialchars($feature) ?></span>
              <select name="<?= htmlspecialchars($feature) ?>">
                <?php foreach (($catOptions[$feature] ?? []) as $option): ?>
                  <option value="<?= htmlspecialchars($option) ?>"><?= htmlspecialchars($option) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
          <?php endforeach; ?>
        </form>

        <div class="prediction-output-grid single-right">
          <section class="subcard result-card" id="predictionResultCard">
            <div class="subcard-header">
              <h3>Predicted churn risk</h3>
              <span>Top-6 scoring output</span>
            </div>
            <div id="predictionResult" class="result-placeholder">
              <div class="result-number">0.00</div>
              <div class="result-band neutral">No prediction yet</div>
              <p>Choose values from the six selectors and run the model to see the probability, risk band, and strongest contributors.</p>
            </div>
          </section>
        </div>
      </section>

      <section id="batch" class="panel data-panels">
        <section class="subcard wide">
          <div class="subcard-header">
            <h3>Batch scoring</h3>
            <span>CSV input using the same six features</span>
          </div>
          <p>Upload a CSV file with the six selected variables, or download the template below.</p>
          <div class="button-row">
            <a class="ghost-btn link-btn" href="data/template_input.csv" download>Download CSV template</a>
          </div>
          <form id="batchForm" class="inline-form" enctype="multipart/form-data">
            <input type="file" id="batchFile" accept=".csv">
            <button id="batchBtn" class="primary-btn" type="button">Run batch prediction</button>
          </form>
          <div id="batchStatus" class="status"></div>
        </section>

        <section class="subcard wide">
          <div class="subcard-header">
            <h3>Model comparison table</h3>
            <span>Loaded from dissertation output CSV</span>
          </div>
          <div class="table-wrap">
            <table>
              <thead><tr><?php foreach (array_keys($modelRows[0] ?? []) as $header): ?><th><?= htmlspecialchars($header) ?></th><?php endforeach; ?></tr></thead>
              <tbody><?php foreach ($modelRows as $row): ?><tr><?php foreach ($row as $cell): ?><td><?= htmlspecialchars((string)$cell) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody>
            </table>
          </div>
        </section>

        <section class="subcard wide">
          <div class="subcard-header">
            <h3>Methodological trade-off panels</h3>
            <span>Feature selection and imbalance</span>
          </div>
          <div class="two-table-grid">
            <div class="table-wrap">
              <h4>Feature selection</h4>
              <table>
                <thead><tr><?php foreach (array_keys($featureSelectionRows[0] ?? []) as $header): ?><th><?= htmlspecialchars($header) ?></th><?php endforeach; ?></tr></thead>
                <tbody><?php foreach ($featureSelectionRows as $row): ?><tr><?php foreach ($row as $cell): ?><td><?= htmlspecialchars((string)$cell) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody>
              </table>
            </div>
            <div class="table-wrap">
              <h4>Imbalance strategies</h4>
              <table>
                <thead><tr><?php foreach (array_keys($imbalanceRows[0] ?? []) as $header): ?><th><?= htmlspecialchars($header) ?></th><?php endforeach; ?></tr></thead>
                <tbody><?php foreach ($imbalanceRows as $row): ?><tr><?php foreach ($row as $cell): ?><td><?= htmlspecialchars((string)$cell) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody>
              </table>
            </div>
          </div>
        </section>
      </section>
    </main>
  </div>

  <script>
    window.APP_CONFIG = {
      apiBaseUrl: <?= json_encode(API_BASE_URL) ?>,
      sampleInput: <?= json_encode($sampleInput) ?>
    };
  </script>
  <script src="/assets/app.js?v=4"></script>
</body>
</html>
