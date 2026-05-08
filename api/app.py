from flask import Flask, request, jsonify, send_file
from flask_cors import CORS
import csv
import io
import json
import math
import os
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent
PAYLOAD = json.loads((BASE_DIR / 'model_payload.json').read_text())

FEATURES = PAYLOAD['features']
NUMERIC = PAYLOAD['numeric_features']
CATEGORICAL = PAYLOAD['categorical_features']
MEDIANS = PAYLOAD['medians']
CAT_MAPS = PAYLOAD['cat_maps']
CAT_OPTIONS = PAYLOAD['cat_options']
NUMERIC_OPTIONS = PAYLOAD.get('numeric_options', {})
SCALER_MEAN = PAYLOAD['scaler_mean']
SCALER_SCALE = PAYLOAD['scaler_scale']
COEF = PAYLOAD['coef']
INTERCEPT = PAYLOAD['intercept']
THRESHOLD = PAYLOAD['threshold']
METRICS = PAYLOAD['metrics']
SAMPLE_INPUT = PAYLOAD['sample_input']

app = Flask(__name__)
CORS(app)


def _sigmoid(z: float) -> float:
    if z >= 0:
        ez = math.exp(-z)
        return 1.0 / (1.0 + ez)
    ez = math.exp(z)
    return ez / (1.0 + ez)


def _encode_record(record: dict):
    values = []
    for idx, feature in enumerate(NUMERIC):
        raw = record.get(feature, None)
        try:
            value = float(raw)
        except (TypeError, ValueError):
            value = MEDIANS.get(feature) or 0.0
        scale = SCALER_SCALE[idx] if SCALER_SCALE[idx] else 1.0
        scaled = (value - SCALER_MEAN[idx]) / scale
        values.append(scaled)

    for feature in CATEGORICAL:
        raw = record.get(feature, 'Missing')
        key = 'Missing' if raw in (None, '', 'nan', 'None') else str(raw)
        mapped = CAT_MAPS.get(feature, {}).get(key, 0)
        values.append(float(mapped))
    return values


def _predict_one(record: dict):
    x = _encode_record(record)
    z = INTERCEPT
    for c, v in zip(COEF, x):
        z += c * v
    probability = _sigmoid(z)
    predicted_class = 1 if probability >= THRESHOLD else 0

    if probability >= 0.25:
        band = 'High risk'
    elif probability >= 0.10:
        band = 'Medium risk'
    else:
        band = 'Low risk'

    contribs = []
    for feature, coef, value in zip(FEATURES, COEF, x):
        contribs.append({'feature': feature, 'contribution': coef * value})
    contribs = sorted(contribs, key=lambda item: abs(item['contribution']), reverse=True)[:5]

    return {
        'probability': round(probability, 6),
        'predicted_class': predicted_class,
        'recommended_risk_band': band,
        'threshold_used': THRESHOLD,
        'top_contributors': contribs,
    }


@app.get('/health')
def health():
    return jsonify({'status': 'ok', 'model_metrics': METRICS})


@app.get('/metadata')
def metadata():
    fields = []
    for feature in NUMERIC:
        fields.append({
            'name': feature,
            'label': feature,
            'type': 'select',
            'options': NUMERIC_OPTIONS.get(feature, []),
        })
    for feature in CATEGORICAL:
        fields.append({
            'name': feature,
            'label': feature,
            'type': 'select',
            'options': CAT_OPTIONS.get(feature, []),
        })

    return jsonify({
        'features': fields,
        'metrics': METRICS,
        'sample_input': SAMPLE_INPUT,
        'threshold': THRESHOLD,
    })


@app.post('/predict')
def predict():
    payload = request.get_json(force=True, silent=True) or {}
    return jsonify(_predict_one(payload))


@app.post('/predict-batch')
def predict_batch():
    if 'file' not in request.files:
        return jsonify({'error': 'No file uploaded.'}), 400

    uploaded = request.files['file']
    text = uploaded.read().decode('utf-8', errors='replace')
    reader = csv.DictReader(io.StringIO(text))

    rows = []
    for row in reader:
        pred = _predict_one(row)
        out = dict(row)
        out['predicted_probability'] = pred['probability']
        out['predicted_class'] = pred['predicted_class']
        out['risk_band'] = pred['recommended_risk_band']
        rows.append(out)

    if not rows:
        return jsonify({'error': 'The uploaded CSV did not contain any rows.'}), 400

    out_io = io.StringIO()
    writer = csv.DictWriter(out_io, fieldnames=list(rows[0].keys()))
    writer.writeheader()
    writer.writerows(rows)

    return send_file(
        io.BytesIO(out_io.getvalue().encode('utf-8')),
        mimetype='text/csv',
        as_attachment=True,
        download_name='orange_dashboard_predictions.csv'
    )


if __name__ == '__main__':
    port = int(os.environ.get('PORT', 5001))
    app.run(host='0.0.0.0', port=port, debug=False)
