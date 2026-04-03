<? php ?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
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
      <div id="monitorTab" class="tab-content" style="display: none;">
        <div class="chart-container">
          <h3 style="text-align: center; margin-bottom: 15px;" id="chartTitle">Driving Speed Monitor</h3>

          <!-- Fixed height container -->
          <div style="position: relative; height: 420px; width: 100%;">
            <canvas id="speedChart"></canvas>
          </div>

          <div id="diagraminfo" style="text-align: center; margin-top: 15px; color: #666;">
            <p>Speed monitoring is only available for individual drivers. • Auto-sliding every 30 seconds</p>
          </div>
        </div>
      </div>

    </div>
  </div>


  <!-- Upload Modal -->
  <div id="uploadModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3> Upload File</h3>
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
      // connect to upload.php
      try {
        const response = await fetch('upload.php', {
          method: 'POST',
          body: formData,
        });
        const result = await response.json();

        // get result success
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

            // handle for other errors
          } else {
            uploadStatus.innerHTML = errorMessage;
            uploadStatus.style.color = 'red';
            showAlert('Upload failed: ' + errorMessage, 'error');

            setTimeout(() => {
              closeUploadModal();
            }, 1000);
          }
        }
        // unexpected error 
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
    async function loadDrivers() {
      const startTime = document.getElementById('startTime').value;
      const endTime = document.getElementById('endTime').value;
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
        // update driver select options
        if (data.success) {
          const select = document.getElementById('driverSelect');
          select.innerHTML = '<option value="all">all driver</option>';

          data.drivers.forEach(driver => {
            const option = document.createElement('option');
            option.value = driver;
            option.textContent = `${driver}`;
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
        await loadDrivers();

      } catch (error) {
        console.error('Error:', error);
        showAlert('Request failed: ' + error.message, 'error');
      }
    });

    // display data in table
    function displaySummary(data) {
      const tbody = document.getElementById('summaryBody');
      if (!data || data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="12" style="text-align: center;">No data found</td></tr>';
        return;
      }

      tbody.innerHTML = data.map(row => `
        <tr>
          <td>${row.driverID || '-'}</td>
          <td>${row.carPlateNumber ? row.carPlateNumber : '-'}</td>
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


    // tab switching 
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

        updateSpeedChart(true);
      }
    }


    let speedChartInstance = null;
    let updateInterval = null;
    const SPEED_LIMIT = 80;
    let all_speed_data = [];
    let window_index = 0;
    const WINDOW_SIZE_MINUTES = 1;
    const SLIDE_INTERVAL = 30000;

    // get speed data and update diagram
    async function updateSpeedChart(resetWindow = true) {
      const startTime = document.getElementById('startTime').value;
      const endTime = document.getElementById('endTime').value;
      const driver_id = document.getElementById('driverSelect').value;

      // send request
      if (!startTime || !endTime || driver_id === "all") {
        document.getElementById('chartTitle').textContent = "Please select a specific driver";

        return;
      }

      try {
        //send request
        const response = await fetch('http://18.214.80.27:5000/api/get_speed_data', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            start_time: startTime,
            end_time: endTime,
            driver_id: driver_id
          })
        });

        const result = await response.json();
        document.getElementById('chartTitle').textContent = "";

        // check speed data 
        if (result.success && result.data && result.data.length > 0) {
          // store all speed data 
          all_speed_data = result.data.map(row => ({
            time: new Date(row.record_time),
            speed: parseFloat(row.speed) || 0
          }));
          showCurrentWindow();
        } else {
          // no speed data 
          document.getElementById('chartTitle').textContent = `No speed data for Driver ${driver_id}`;
        }

        document.getElementById('chartTitle').textContent = `Driver ${driver_id} -  Speed Monitoring`;

        if (resetWindow) {
          window_index = 0;
        }

        showCurrentWindow();

        // refresh every 30s
        if (updateInterval) clearInterval(updateInterval);
        updateInterval = setInterval(() => {
          window_index += 1;
          showCurrentWindow();
        }, SLIDE_INTERVAL);

      } catch (error) {
        console.error('Error fetching speed data:', error);
        document.getElementById('chartTitle').textContent = "Error loading speed data";
      }
    }

    // show current WINDOW_SIZE_MINUTES 
    function showCurrentWindow() {
      if (all_speed_data.length === 0) return;

      const windowMs = WINDOW_SIZE_MINUTES * 60 * 1000;
      const baseStartTime = document.getElementById('startTime').valueAsDate;
      let startIdx = window_index;

      const windowStartTime = new Date(baseStartTime.getTime() + window_index * 60 * 1000);
      const windowEndTime = new Date(windowStartTime.getTime() + windowMs);

      // loop back at the end
      const userEndTime = document.getElementById('endTime').valueAsDate;
      if (windowStartTime >= userEndTime) {
        window_index = 0;
        return;
      }

      // get data in current window
      const windowData = all_speed_data.filter(point =>
        point.time >= windowStartTime && point.time < windowEndTime
      );

      if (windowData.length === 0) {
        window_index++;
        return;
      }

      const labels = windowData.map(p =>
        p.time.toLocaleString('en', {
          hour: '2-digit',
          minute: '2-digit',
          second: '2-digit'
        })
      );

      const speeds = windowData.map(p => p.speed);

      // check overspeed
      const is_overspeed = speeds.some(s => s > SPEED_LIMIT);
      if (is_overspeed) {
        showAlert(`Driver ${document.getElementById('driverSelect').value} is SPEEDING!`, 'error');
      }

      // handle time format
      const timeFrom = windowStartTime.toLocaleString('en', {
        hour: '2-digit', minute: '2-digit', second: '2-digit'
      });
      const timeTo = windowEndTime.toLocaleString('en', {
        hour: '2-digit', minute: '2-digit', second: '2-digit'
      });

      const driver_id = document.getElementById('driverSelect').value;
      document.getElementById('chartTitle').textContent =
        `Driver ${driver_id} - ${timeFrom} - ${timeTo}`;

      // diagram
      if (!speedChartInstance) {
        const ctx = document.getElementById('speedChart').getContext('2d');
        speedChartInstance = new Chart(ctx, {
          type: 'line',
          data: {
            labels: labels,
            datasets: [
              {
                label: 'Speed (km/h)',
                data: speeds,
                borderColor: '#e74c3c',
                backgroundColor: 'rgba(231, 76, 60, 0.1)',
                borderWidth: 3,
                tension: 0.2,
                pointRadius: 2
              },
              {
                label: 'Speed Limit (80 km/h)',
                data: new Array(labels.length).fill(SPEED_LIMIT),
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
                title: { display: true, text: 'Time' }
              },
              y: {
                beginAtZero: true,
                title: { display: true, text: 'Speed (km/h)' },
                max: 200
              }
            },
            plugins: {
              legend: { position: 'top' }
            }
          }
        });
      } else {
        // refresh diagram data
        speedChartInstance.data.labels = labels;
        speedChartInstance.data.datasets[0].data = speeds;
        speedChartInstance.data.datasets[1].data = new Array(labels.length).fill(SPEED_LIMIT);
        speedChartInstance.update('none');
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