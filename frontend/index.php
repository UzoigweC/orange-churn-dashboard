<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/inc/helpers.php';

$modelRows = load_csv_assoc(__DIR__ . '/data/model_comparison_table.csv');
$featureRows = load_csv_assoc(__DIR__ . '/data/top_feature_table.csv');
$imbalanceRows = load_csv_assoc(__DIR__ . '/data/imbalance_strategy_table.csv');
$featureSelectionRows = load_csv_assoc(__DIR__ . '/data/feature_selection_table.csv');
$modelPayload = load_json_file(__DIR__ . '/data/model_payload.json');

$features = $modelPayload['features'] ?? [];
$numericFeatures = $modelPayload['numeric_features'] ?? [];
$categoricalFeatures = $modelPayload['categorical_features'] ?? [];
$numericOptions = $modelPayload['numeric_options'] ?? [];
$catOptions = $modelPayload['cat_options'] ?? [];
$metrics = $modelPayload['metrics'] ?? [];
$sampleInput = $modelPayload['sample_input'] ?? [];
$bestModel = $modelRows[0]['model'] ?? 'XGBoost';
$heroRoc = $modelRows[0]['test_roc_auc'] ?? ($metrics['roc_auc'] ?? '');
$heroPr = $modelRows[0]['test_pr_auc'] ?? ($metrics['pr_auc'] ?? '');
$heroP10 = $modelRows[0]['test_precision_at_10pct'] ?? '';

function fmt_option($value) {
    if ($value === null || $value === '') return '';
    if (is_numeric($value)) {
        $num = (float)$value;
        if (abs($num - round($num)) < 1e-9) {
            return (string)(int)round($num);
        }
        return rtrim(rtrim(number_format($num, 4, '.', ''), '0'), '.');
    }
    return (string)$value;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Orange Churn Dashboard</title>
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <div class="app-shell">
    <aside class="sidebar">
      <div class="brand">
        <div class="brand-dot"></div>
        <div>
          <h1>Orange Churn</h1>
          <p>Dashboard Prototype</p>
        </div>
      </div>

      <nav class="side-nav">
        <a href="#overview" class="active">Overview</a>
        <a href="#comparison">Model comparison</a>
        <a href="#features">Features</a>
        <a href="#imbalance">Imbalance</a>
        <a href="#prediction">Prediction</a>
      </nav>

      <div class="sidebar-note">
        <p>This interface uses anonymised Orange feature IDs and is designed as a decision-support prototype.</p>
      </div>
    </aside>

    <main class="main-panel">
      <section id="overview" class="panel hero-panel">
        <div class="window-bar">
          <span></span><span></span><span></span>
        </div>
        <div class="hero-headline">
          <div>
            <h2>Orange Churn Dashboard Prototype</h2>
            <p>Interactive prediction and model review interface for the Orange KDD 2009 churn workflow.</p>
          </div>
          <div class="pill-row">
            <span class="pill">API: <?= htmlspecialchars(API_BASE_URL) ?></span>
            <span class="pill">Threshold: <?= htmlspecialchars((string)($metrics['threshold'] ?? '')) ?></span>
          </div>
        </div>

        <div class="stats-grid">
          <div class="metric-card highlight">
            <span class="metric-label">Best model</span>
            <strong><?= htmlspecialchars($bestModel) ?></strong>
          </div>
          <div class="metric-card">
            <span class="metric-label">ROC-AUC</span>
            <strong><?= htmlspecialchars((string)$heroRoc) ?></strong>
          </div>
          <div class="metric-card">
            <span class="metric-label">PR-AUC</span>
            <strong><?= htmlspecialchars((string)$heroPr) ?></strong>
          </div>
          <div class="metric-card">
            <span class="metric-label">P@10</span>
            <strong><?= htmlspecialchars((string)$heroP10) ?></strong>
          </div>
        </div>

        <div class="overview-grid">
          <section id="comparison" class="subcard chart-card">
            <div class="subcard-header">
              <h3>Model comparison</h3>
              <span>Illustrative dashboard panel</span>
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
                  $short = $row['model'];
                  $short = str_replace(['XGBoost', 'Logistic regression', 'Random forest', 'Dummy baseline'], ['XGB', 'LR', 'RF', 'Dummy'], $short);
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
              <span>Selected model features</span>
            </div>
            <ul class="feature-list">
              <?php foreach (array_slice($featureRows, 0, 8) as $row): ?>
                <li>
                  <span class="feature-name"><?= htmlspecialchars($row['feature'] ?? '') ?></span>
                  <span class="feature-score">MI=<?= htmlspecialchars(number_format((float)($row['mutual_information_score'] ?? 0), 3)) ?></span>
                  <span class="feature-note">Name unavailable</span>
                </li>
              <?php endforeach; ?>
            </ul>
          </section>
        </div>
      </section>

      <section id="prediction" class="panel">
        <div class="section-header">
          <div>
            <h2>Prediction panel</h2>
            <p>Select values for the model inputs below. The dashboard uses drop-down controls for both numeric and categorical features so users do not have to type encoded values manually.</p>
          </div>
          <div class="button-row">
            <button id="sampleBtn" class="ghost-btn" type="button">Load demo values</button>
            <button id="predictBtn" class="primary-btn" type="button">Predict risk</button>
          </div>
        </div>

        <form id="predictForm" class="predict-grid">
          <?php foreach ($numericFeatures as $feature): ?>
            <label class="field-card">
              <span class="field-label"><?= htmlspecialchars($feature) ?></span>
              <select name="<?= htmlspecialchars($feature) ?>">
                <?php foreach (($numericOptions[$feature] ?? []) as $option): $formatted = fmt_option($option); ?>
                  <option value="<?= htmlspecialchars($formatted) ?>"><?= htmlspecialchars($formatted) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
          <?php endforeach; ?>

          <?php foreach ($categoricalFeatures as $feature): ?>
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

        <div class="prediction-output-grid">
          <section class="subcard result-card" id="predictionResultCard">
            <div class="subcard-header">
              <h3>Predicted churn risk</h3>
              <span>Model output</span>
            </div>
            <div id="predictionResult" class="result-placeholder">
              <div class="result-number">0.00</div>
              <div class="result-band neutral">No prediction yet</div>
              <p>Run the model after selecting values to see the probability, class, and strongest contributors.</p>
            </div>
          </section>

          <section id="imbalance" class="subcard">
            <div class="subcard-header">
              <h3>Imbalance strategy snapshot</h3>
              <span>Controlled experiment</span>
            </div>
            <div class="mini-table-wrap">
              <table class="mini-table">
                <thead>
                  <tr><th>Strategy</th><th>PR-AUC</th><th>Brier</th></tr>
                </thead>
                <tbody>
                <?php foreach ($imbalanceRows as $row): ?>
                  <tr>
                    <td><?= htmlspecialchars($row['strategy'] ?? '') ?></td>
                    <td><?= htmlspecialchars($row['test_pr_auc'] ?? '') ?></td>
                    <td><?= htmlspecialchars($row['test_brier'] ?? '') ?></td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </section>
        </div>
      </section>

      <section class="panel data-panels">
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
            <h3>Feature-selection comparison</h3>
            <span>Compact view for dashboard integration</span>
          </div>
          <div class="table-wrap">
            <table>
              <thead><tr><?php foreach (array_keys($featureSelectionRows[0] ?? []) as $header): ?><th><?= htmlspecialchars($header) ?></th><?php endforeach; ?></tr></thead>
              <tbody><?php foreach ($featureSelectionRows as $row): ?><tr><?php foreach ($row as $cell): ?><td><?= htmlspecialchars((string)$cell) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody>
            </table>
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
  <script src="assets/app.js"></script>
</body>
</html>
