<!DOCTYPE html>
<html lang="en">

<head>

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


    .btn {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
  </style>
</head>

<body>
  <div class="container">
    <div class="header">
      <h1>COMP4442-group-project </h1>
      <p>Driver Behavior Analysis System</p>
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
          <button type="submit" class="btn">submit</button>
        </form>
      </div>

      <!-- tabs -->
      <div class="tabs">
        <button class="tab active" onclick="showTab('summary')">driving behavior information</button>
        <button class="tab" onclick="showTab('monitor')">driving speed visualization</button>
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
            </tbody>
          </table>
        </div>
      </div>

      <!-- diagram -->
      <div id="monitorTab" class="tab-content" style="display: none;">
        <div class="chart-container">

        </div>
      </div>
    </div>
  </div>

</body>

</html>