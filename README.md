# orange-churn-dashboard
A web-based customer churn prediction and decision-support dashboard using PHP frontend and Python API with the Orange KDD 2009 dataset.
# Orange Churn Prediction Dashboard (PHP + Python API)

This package gives you a **hostable dashboard** for your MSc project with two parts:

- **PHP frontend** for the hosted website/dashboard
- **Python Flask API** for the actual prediction engine

The dashboard uses your dissertation result CSV files for reporting and a compact deployable model trained on the Orange KDD 2009 data for **manual prediction** and **batch CSV prediction**.

---

## Package structure

- `frontend/` - upload this to your PHP hosting directory
- `api/` - run this as a Python service
- `frontend/data/` - dissertation result tables + model metadata

---

## What the dashboard can do

1. Show the **project result tables**
   - model comparison
   - top features
   - imbalance strategy comparison
   - feature-selection comparison

2. Accept **manual feature input** and return:
   - predicted churn probability
   - predicted class
   - risk band
   - top contributing features

3. Accept a **CSV upload** and return a scored CSV file

---

## Before you install

### Important hosting note

A standard low-cost PHP shared host usually runs PHP only. Your project needs **PHP + Python**.

You have **three practical hosting options**:

### Option 1. Best and simplest for this project
Host the **PHP frontend** and the **Python API** on the same VPS or cloud server.

Examples:
- Ubuntu VPS
- DigitalOcean
- Contabo
- Hetzner
- AWS Lightsail

### Option 2. Very workable
Host the PHP frontend on normal PHP hosting, and host the Python API separately on:
- Render
- Railway
- PythonAnywhere
- a VPS

Then change the API URL in `frontend/config.php`.

### Option 3. Local demo
Run the frontend with XAMPP / WAMP and the Python API on your laptop.

---

## Local installation (recommended first)

### Step 1. Install PHP environment
Use one of these:
- **XAMPP** on Windows/macOS
- **WAMP** on Windows
- **MAMP** on macOS
- or any PHP 8+ environment

### Step 2. Put the frontend in your PHP web root
Examples:
- XAMPP: `htdocs/orange_dashboard/`
- WAMP: `www/orange_dashboard/`

Copy the contents of the `frontend/` folder into that location.

### Step 3. Install Python 3.10+ if not already installed
Check:

```bash
python --version
```

### Step 4. Create a virtual environment for the API
Go into the `api/` folder and run:

```bash
python -m venv .venv
```

Activate it.

**Windows**

```bash
.venv\Scripts\activate
```

**Linux / macOS**

```bash
source .venv/bin/activate
```

### Step 5. Install API dependencies

```bash
pip install -r requirements.txt
```

### Step 6. Start the API

```bash
python app.py
```

If everything is fine, the API should run on:

```text
http://127.0.0.1:5001
```

### Step 7. Check the API quickly
Open this in your browser:

```text
http://127.0.0.1:5001/health
```

You should see JSON showing the model metrics.

### Step 8. Open the PHP dashboard
Open your local PHP URL, for example:

```text
http://localhost/orange_dashboard/index.php
```

Now the dashboard should load and the manual prediction form should work.

---

## Hosted installation

## A. Host the Python API

### Example using a VPS (Ubuntu)

1. Upload the `api/` folder to your server.
2. SSH into the server.
3. Create the virtual environment:

```bash
python3 -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
```

4. Start the API for testing:

```bash
python app.py
```

5. Put it behind a process manager such as `systemd`, `supervisor`, or `gunicorn` later.

### Example using PythonAnywhere / Render / Railway
Upload the `api/` folder as your Python app and set the start command according to the platform. The main file is:

```text
app.py
```

---

## B. Host the PHP frontend

Upload the `frontend/` folder contents into your PHP web root, for example:

- `public_html/`
- `htdocs/`
- `www/`

### Step 1. Update the API URL
Edit `frontend/config.php`.

For example:

```php
<?php
define('API_BASE_URL', 'https://your-python-api-domain.com');
```

### Step 2. Visit your hosted dashboard
Open your domain in the browser and test the form.

---

## How to use the dashboard

### Manual prediction

1. Open the **Manual prediction** section
2. Fill in the selected feature values
3. Click **Predict churn risk**
4. The page will show:
   - probability
   - predicted class
   - risk band
   - top contributors

### Batch prediction

1. Download the CSV template from the dashboard
2. Fill it with one or more rows
3. Upload it in the **Batch CSV** section
4. A scored CSV will download automatically

---

## Why the prediction form uses only selected features

The original Orange dataset has **230 anonymised variables**. A form with all 230 would be hard to use and hard to explain.

So this dashboard uses a **compact selected feature set** drawn from the project’s top-feature results. That makes the form much more suitable for:
- presentation
- hosting
- viva/demo use
- quick decision support

---

## How to change the model later

If you want to retrain or replace the model later, update the contents of:

- `api/model_payload.json`
- `frontend/data/model_payload.json`

The frontend form and the API both read from that metadata.

---

## Troubleshooting

### Problem: The dashboard loads, but prediction fails
Check:
- that the Python API is running
- that `frontend/config.php` has the correct API URL
- that your server allows the frontend to call the API

### Problem: Batch upload fails
Check:
- that the uploaded CSV uses the same columns as `data/template_input.csv`
- that the API is reachable

### Problem: CORS error in browser console
The API already enables CORS through Flask-CORS. If you still see issues, confirm that the frontend is calling the correct API domain.

---

## Dissertation wording suggestion

You can describe the system like this:

> The dashboard was implemented as a web-based decision-support interface using PHP for the hosted frontend and Python for the prediction service. The PHP layer provided the user interface and presentation of analytical outputs, while the Python API preserved consistency with the machine-learning workflow by executing the trained churn model and returning predicted probabilities, risk bands, and explanatory indicators.

