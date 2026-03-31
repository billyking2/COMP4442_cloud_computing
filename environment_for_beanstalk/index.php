<?php
// Handle file upload 
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  // Set JSON header 
  header('Content-Type: application/json');

  error_log(print_r($_FILES, true));
  error_log(print_r($_POST, true));
  $file = $_FILES['file'];

  // Check upload error
  $file_error_message = $file['error'];
  if ($file_error_message !== UPLOAD_ERR_OK) {
    $errors = [
      UPLOAD_ERR_INI_SIZE => 'File too large (server limit)',
      UPLOAD_ERR_FORM_SIZE => 'File too large (form limit)',
      UPLOAD_ERR_PARTIAL => 'File only partially uploaded',
      UPLOAD_ERR_NO_FILE => 'No file uploaded',
      UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
      UPLOAD_ERR_CANT_WRITE => 'Failed to write file',
      UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
    ];

    if (isset($errors[$file_error_message])) {
      $error_msg = $errors[$file_error_message];
    } else {
      $error_msg = 'Unknown error';
    }

    echo json_encode(['success' => false, 'message' => 'Upload failed: ' . $error_msg]);
    exit;
  }

  // check file type
  $fileType = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
  $allowed_types = ['csv', 'txt', ''];   // '' means no extension

  if (!in_array($fileType, $allowed_types)) {
    echo json_encode([
      'success' => false,
      'message' => 'Only .csv, .txt files or files without extension are allowed (got .' . $fileType . ')'
    ]);
    exit;
  }

  // Check file size (max 10MB)
  if ($file['size'] > 10 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'File too large (max 10MB)']);
    exit;
  }

  // check filename, only allow letters, numbers, underscores, dashes and dots
  if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $file['name'])) {
    echo json_encode([
      'success' => false,
      'message' => 'Filename can only contain letters, number, underscores, dashes and dots'
    ]);
    exit;
  }

  $pattern = '/^detail_record_\d{4}_\d{2}_\d{2}_\d{2}_\d{2}_\d{2}(\.(csv|txt))?$/i';

  if (!preg_match($pattern, $file['name'])) {
    echo json_encode([
      'success' => false,
      'message' => 'Filename must follow the format: detail_record_YYYY_MM_DD_HH_MM_SS.csv (or .txt)'
    ]);
    exit;
  }

  // send to ec2 server
  $remote_url = 'http://ec2-18-214-80-27.compute-1.amazonaws.com/api/upload_api.php';

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $remote_url);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, [
    'file' => new CURLFile($file['tmp_name'], $file['type'], $file['name'])
  ]);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_TIMEOUT, 60);

  $response = curl_exec($ch);
  $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $curl_error = curl_error($ch);
  curl_close($ch);

  if ($curl_error) {
    echo json_encode([
      'success' => false,
      'message' => 'Failed to connect to remote server(upload_api): ' . $curl_error
    ]);
    exit;
  }

  if ($http_code == 200) {

    echo $response;
  } else {
    echo json_encode([
      'success' => false,
      'message' => 'Failed to upload to remote server (HTTP ' . $http_code . ')',
      'response' => $response
    ]);
  }
  exit;
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <title>COMP4442-group-project</title>
  <style>
    /* global styles */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    /* set word style and background */
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      padding: 20px;
    }

    /* set container such that keep the format*/
    .container {
      max-width: 1400px;
      margin: 0 auto;
      background: white;
      border-radius: 20px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
      overflow: hidden;
    }

    /* set header word style and size */

    .header {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 30px;
      text-align: center;
    }

    .header h1 {
      font-size: 2.5em;
      margin-bottom: 10px;
    }

    .header p {
      font-size: 1.1em;
      opacity: 0.9;
    }

    .content {
      padding: 30px;
    }

    /*set timer style*/
    .time-selector {
      background: #f8f9fa;
      padding: 25px;
      border-radius: 15px;
      margin-bottom: 30px;
    }

    .time-selector h2 {
      color: #333;
      margin-bottom: 20px;
      font-size: 1.5em;
    }

    /* set Horizontal layout */
    .form-group {
      display: inline-block;
      margin-right: 20px;
      margin-bottom: 15px;
    }

    .form-group label {
      display: block;
      margin-bottom: 5px;
      color: #555;
      font-weight: 500;
    }


    .form-group input {
      padding: 10px 15px;
      border: 2px solid #ddd;
      border-radius: 8px;
      font-size: 16px;
      transition: all 0.3s;
    }

    .form-group input:focus {
      outline: none;
      border-color: #667eea;
    }


    .submit_btn {
      background: linear-gradient(135deg, #cd201d 0%, #e0ce09 100%);
      color: white;
      border: none;
      padding: 12px 30px;
      border-radius: 8px;
      font-size: 16px;
      cursor: pointer;
      transition: transform 0.2s;
      margin-top: 28px;
    }

    .upload_btn {
      background: linear-gradient(135deg, #cd201d 0%, #e0ce09 100%);
      color: white;
      border: none;
      padding: 12px 30px;
      border-radius: 8px;
      font-size: 16px;
      cursor: pointer;
      transition: transform 0.2s;
      margin-top: 28px;
    }

    /* when mouse on button, have animation */
    .btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
    }

    /* tab look */
    .tabs {
      display: flex;
      gap: 10px;
      margin-bottom: 25px;
      border-bottom: 2px solid #e0e0e0;
    }

    .tab {
      padding: 12px 25px;
      background: none;
      border: none;
      font-size: 16px;
      cursor: pointer;
      color: #666;
      transition: all 0.3s;
    }

    .tab.active {
      color: #667eea;
      border-bottom: 3px solid #667eea;
      font-weight: 600;
    }

    /* table look */
    .table-container {
      overflow-x: auto;
      margin-bottom: 30px;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      background: white;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
      border-radius: 10px;
      overflow: hidden;
    }

    th {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 12px;
      text-align: left;
      font-weight: 600;
    }

    tr:hover {
      background: #f8f9fa;
    }

    /* diagram container */
    .chart-container {
      margin: 30px 0;
      padding: 20px;
      background: white;
      border-radius: 10px;
    }

    /* upload modal */
    .modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.5);
    }

    .modal-content {
      background-color: white;
      margin: 10% auto;
      padding: 0;
      border-radius: 20px;
      width: 90%;
      max-width: 500px;
      box-shadow: 0 5px 30px rgba(0, 0, 0, 0.3);
    }

    .modal-header {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 20px;
      border-radius: 20px 20px 0 0;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .modal-header h3 {
      margin: 0;
    }

    .close-modal {
      font-size: 28px;
      font-weight: bold;
      cursor: pointer;
    }

    .upload-area {
      border: 2px dashed #ddd;
      border-radius: 10px;
      padding: 30px;
      text-align: center;
      cursor: pointer;
      transition: all 0.3s;
      margin-bottom: 20px;
    }

    .upload-area:hover {
      border-color: #667eea;
      background: #f8f9fa;
    }

    .file-info {
      font-size: 12px;
      color: #666;
      margin-top: 10px;
    }
  </style>
</head>

<body>
  <div class="container">
    <div class="header">
      <h1>COMP4442-group-project</h1>
      <p>Driver Behavior Analysis System</p>
      <button class="upload_btn" onclick="openUploadModal()" style="margin-top: 15px;"> Upload CSV</button>
    </div>

    <div class="content">


      <!-- select time range and driver -->
      <div class="time-selector">
        <h2> Select the time range </h2>
        <form id="timeForm">
          <div class="form-group">
            <label>start time:</label>
            <input type="datetime-local" id="startTime" name="startTime" required>
          </div>
          <div class="form-group">
            <label>end time:</label>
            <input type="datetime-local" id="endTime" name="endTime" required>
          </div>
          <div class="form-group">
            <label>choose driver:</label>
            <select id="driverSelect"
              style="padding: 10px 15px; border: 2px solid #ddd; border-radius: 8px; font-size: 16px;">
              <option value="all">all driver</option>
            </select>
          </div>
          <button type="submit" class="submit_btn">submit</button>
        </form>
      </div>

      <!-- tabs -->
      <div class="tabs">
        <button class="tab active" onclick="showTab('summary')">Driving behavior information</button>
        <button class="tab" onclick="showTab('monitor')">Driving speed visualization</button>
      </div>

      <!-- table -->
      <div id="summaryTab" class="tab-content">
        <div class="table-container">
          <table id="summaryTable">
            <thead>
              <tr>
                <th>driverID</th>
                <th>carPlateNumber</th>
                <th>speeding instances</th>
                <th>Total speeding time</th>
                <th>Fatigue driving instances</th>
                <th>Neutral Slide instances</th>
                <th>Neutral Slide time</th>
                <th>Rapid Speedup instances</th>
                <th>Rapid Slowdown instances</th>
                <th>Hthrottle Stop instances</th>
                <th>oil Leak instances</th>
                <th>Total number of dangerous events</th>
              </tr>
            </thead>
            <tbody id="summaryBody">
              <tr>
                <td colspan="12" style="text-align: center;">Please select time range and click Submit</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- diagram -->
      <!-- diagram -->
      <div id="monitorTab" class="tab-content" style="display: none;">
        <div class="chart-container">
          <h3 style="text-align: center; margin-bottom: 15px;" id="chartTitle">Driving Speed Monitor</h3>
          <canvas id="speedChart" height="100"></canvas>
        </div>
        <div style="text-align: center; margin-top: 15px; color: #666;">
          <p>Chart auto-updates every 30 seconds</p>
        </div>
      </div>

    </div>
  </div>


  <!-- Upload Modal -->
  <div id="uploadModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3> Upload CSV File</h3>
        <span class="close-modal" onclick="closeUploadModal()">&times;</span>
      </div>
      <div class="modal-body">
        <div id="uploadArea" class="upload-area" onclick="document.getElementById('csvFile').click()">
          <div>upload file</div>
          <div> (Only one file can be uploaded at a time.) </div>
          <div class="file-info">Support: csv,txt format, max 10MB</div>
        </div>
        <input type="file" name="file" id="csvFile" accept=".csv,.txt" style="display: none;"
          onchange="uploadFile(this.files[0])">
        <div id="uploadStatus" style="font-size: 12px; color: #666; text-align: center;"></div>
      </div>
    </div>
  </div>


  <script>

    // open upload
    function openUploadModal() {
      document.getElementById('uploadModal').style.display = 'block';
    }

    // close upload
    function closeUploadModal() {
      document.getElementById('uploadModal').style.display = 'none';
      document.getElementById('uploadArea').classList.remove('drag-over');
      document.getElementById('uploadStatus').innerHTML = '';
      document.getElementById('csvFile').value = '';
    }

    //get user file and upload 
    async function uploadFile(file) {
      if (!file) return;

      const formData = new FormData();
      formData.append('file', file);

      const uploadStatus = document.getElementById('uploadStatus');
      uploadStatus.innerHTML = 'Uploading...';

      try {
        const response = await fetch(window.location.href, {
          method: 'POST',
          body: formData,
        });
        const result = await response.json();

        if (result.success) {

          uploadStatus.innerHTML = result.message;
          uploadStatus.style.color = 'green';

          setTimeout(() => {
            closeUploadModal();
            loadDrivers();
            // Trigger data refresh
            const submitEvent = new Event('submit');
            document.getElementById('timeForm').dispatchEvent(submitEvent);
          }, 2000);
        } else {

          let errorMessage = result.message || result.error || 'Upload failed';

          //  handling for duplicate file errors
          if (errorMessage.includes('already exists')) {
            uploadStatus.innerHTML = errorMessage;
            uploadStatus.style.color = 'orange';

            showAlert(errorMessage, 'error');


            setTimeout(() => {
              if (uploadStatus.innerHTML.includes('already exists')) {
                uploadStatus.innerHTML = '';
              }
            }, 1000);
          } else {
            uploadStatus.innerHTML = errorMessage;
            uploadStatus.style.color = 'red';
            showAlert('Upload failed: ' + errorMessage, 'error');

            setTimeout(() => {
              closeUploadModal();
            }, 1000);
          }
        }
      } catch (error) {
        console.error('Upload error:', error);
        uploadStatus.innerHTML = ' Upload failed: ' + error.message;
        uploadStatus.style.color = 'red';
        showAlert('Upload failed: ' + error.message, 'error');

        setTimeout(() => {
          closeUploadModal();
        }, 1000);
      } finally {
        setTimeout(() => {
          uploadStatus.innerHTML = '';
        }, 1000);
      }
    }


    // data display functions
    function setDefaultDates() {
      const end = new Date();
      const start = new Date();
      start.setDate(start.getDate() - 7);

      document.getElementById('startTime').value = formatDateTime(start);
      document.getElementById('endTime').value = formatDateTime(end);
    }

    function formatDateTime(date) {
      return date.toISOString().slice(0, 16);
    }

    // get driver list and update driver select options
    async function loadDrivers(startTime = null, endTime = null) {
      try {
        const response = await fetch('http://18.214.80.27:5000/api/get_drivers', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            start_time: startTime,
            end_time: endTime
          })
        });


        const data = await response.json();

        if (data.success) {
          const select = document.getElementById('driverSelect');
          select.innerHTML = '<option value="all">all driver</option>';

          data.drivers.forEach(driver => {
            const option = document.createElement('option');
            option.value = driver;
            option.textContent = `Driver ${driver}`;
            select.appendChild(option);
          });
        }
      } catch (error) {
        console.error('Failed to load drivers:', error);
      }
    }

    // get driving behavior information 
    document.getElementById('timeForm').addEventListener('submit', async (e) => {
      e.preventDefault();

      const startTime = document.getElementById('startTime').value;
      const endTime = document.getElementById('endTime').value;
      const driverId = document.getElementById('driverSelect').value;

      if (!startTime || !endTime) {
        showAlert('Please select start and end time', 'error');
        return;
      }

      // call to get driving behavior information
      try {
        const response = await fetch('http://18.214.80.27:5000/api/get_driving_behavior_information', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            start_time: startTime,
            end_time: endTime,
            driver_id: driverId
          })

        });

        const data = await response.json();
        if (data.success) {
          displaySummary(data.data);
        } else {
          showAlert('Failed to get data: ' + (data.message || 'Unknown error'), 'error');
          tbody.innerHTML = '<tr><td colspan="12" style="text-align: center;">No data found</td></tr>';
        }
        await loadDrivers(startTime, endTime);

      } catch (error) {
        console.error('Error:', error);
        showAlert('Request failed: ' + error.message, 'error');
      }
    });


    function displaySummary(data) {
      const tbody = document.getElementById('summaryBody');
      if (!data || data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="12" style="text-align: center;">No data found</td></tr>';
        return;
      }

      tbody.innerHTML = data.map(row => `
        <tr>
          <td>${row.driverID || '-'}</td>
          <td>${row.car_plate_number || '-'}</td>
          <td>${row.count_overspeed || 0}</td>
          <td>${row.time_overspeed || 0}</td>
          <td>${row.count_fatigueDriving || 0}</td>
          <td>${row.count_neutralSlide || 0}</td>
          <td>${row.time_neutralSlide || 0}</td>
          <td>${row.count_rapidSpeedUp || 0}</td>
          <td>${row.count_rapidSlowDown || 0}</td>
          <td>${row.count_hthrottleStop || 0}</td>
          <td>${row.count_oilLeak || 0}</td>
          <td>${row.count_dangerEvent || 0}</td>
        </tr>
      `).join('');
    }



    function showTab(tabName) {
      const summaryTab = document.getElementById('summaryTab');
      const monitorTab = document.getElementById('monitorTab');
      const tabs = document.querySelectorAll('.tab');

      tabs.forEach(tab => tab.classList.remove('active'));

      if (tabName === 'summary') {
        summaryTab.style.display = 'block';
        tabs[0].classList.add('active');
        monitorTab.style.display = 'none';

        if (updateInterval) {
          clearInterval(updateInterval);
          updateInterval = null;
        }
      } else {

        summaryTab.style.display = 'none';
        monitorTab.style.display = 'block';
        tabs[1].classList.add('active');

        updateSpeedChart();

        if (updateInterval) clearInterval(updateInterval);

        updateInterval = setInterval(() => {
          updateSpeedChart();
        }, 30000);
      }
    }


    let speedChartInstance = null;
    let updateInterval = null;
    const SPEED_LIMIT = 80;

    async function updateSpeedChart() {
      const startTime = document.getElementById('startTime').value;
      const endTime = document.getElementById('endTime').value;
      const driverId = document.getElementById('driverSelect').value;

      // send request
      if (!startTime || !endTime || driverId === "all") {
        document.getElementById('chartTitle').textContent = "Please select a specific driver";
        return;
      }

      try {
        const response = await fetch('http://18.214.80.27:5000/api/get_speed_data', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            start_time: startTime,
            end_time: endTime,
            driver_id: driverId
          })
        });

        const result = await response.json();

        // check speed data 
        if (!result.success || !result.data || result.data.length === 0) {
          document.getElementById('chartTitle').textContent = `No speed data for Driver ${driverId}`;
          if (speedChartInstance) speedChartInstance.data.datasets[0].data = [];
          if (speedChartInstance) speedChartInstance.update();
          return;
        }

        document.getElementById('chartTitle').textContent = `Driver ${driverId} -  Speed Monitoring`;

        const record_times = result.data.map(row => row.record_time);
        const record_speeds = result.data.map(row => parseFloat(row.speed) || 0);


        // overspeed
        isSpeeding = record_speeds.some(speed => speed > SPEED_LIMIT);

        if (isSpeeding) {
          showAlert("Driver " + driverId + " is SPEEDING!", 'error');
        }

        // make diagram
        if (!speedChartInstance) {
          // first time initialization
          const ctx = document.getElementById('speedChart').getContext('2d');
          speedChartInstance = new Chart(ctx, {
            type: 'line',
            data: {
              labels: record_times,
              datasets: [
                {
                  label: 'Speed (km/h)',
                  data: record_speeds,
                  borderColor: '#e74c3c',
                  backgroundColor: 'rgba(231, 76, 60, 0.1)',
                  borderWidth: 3,
                  tension: 0.2,
                  pointRadius: 2
                },
                {
                  label: 'Speed Limit',
                  data: new Array(record_times.length).fill(SPEED_LIMIT),
                  borderColor: '#f1c40f',
                  borderWidth: 2,
                  borderDash: [5, 5],
                  pointRadius: 0
                }
              ]
            },
            options: {
              responsive: true,
              maintainAspectRatio: false,
              scales: {
                x: {
                  type: 'time',
                  time: {
                    unit: 'minute',
                    displayFormats: {
                      minute: 'HH:mm'
                    }
                  },
                  title: { display: true, text: 'Time' }
                },
                y: {
                  beginAtZero: true,
                  title: { display: true, text: 'Speed (km/h)' }
                }
              },
              plugins: {
                legend: { position: 'top' },
                tooltip: {
                  mode: 'index',
                  intersect: false
                }
              }
            }
          });
        } else {
          // update existing chart
          speedChartInstance.data.labels = record_times;
          speedChartInstance.data.datasets[0].data = record_speeds;
          speedChartInstance.data.datasets[1].data = new Array(record_times.length).fill(SPEED_LIMIT);
          speedChartInstance.update();
        }

      } catch (error) {
        console.error('Error fetching speed data:', error);
        document.getElementById('chartTitle').textContent = "Error loading speed data";
      }
    }



    function showAlert(message, type = 'error') {
      alert(message);
    }

    // initialize
    window.onload = () => {
      setDefaultDates();
      loadDrivers();
    };

    // close modal when clicking outside
    window.onclick = (event) => {
      const modal = document.getElementById('uploadModal');
      if (event.target === modal) {
        closeUploadModal();
      }
    };


  </script>
</body>

</html>