from flask import Flask, request, jsonify
from flask_cors import CORS
from pyspark.sql import SparkSession
from pyspark.sql.functions import col, sum as _sum
import os

app = Flask(__name__)
CORS(app)

# JDBC configuration
JDBC_URL = "jdbc:mysql://comp4442-group-project.co9yvkeoopsc.us-east-1.rds.amazonaws.com:3306/COMP4442_group_project"
JDBC_USER = "admin"
JDBC_PASSWORD = "Qweasdzxc1612"

def get_spark():
    return SparkSession.builder \
        .appName("DriverBehaviorAPI") \
        .config("spark.sql.adaptive.enabled", "true") \
        .config("spark.sql.legacy.timeParserPolicy", "LEGACY") \
        .getOrCreate()
        
        
def handle_time(start_time, end_time):
    start_year = int(start_time[0:4])
    start_month = int(start_time[5:7])
    end_year = int(end_time[0:4])
    end_month = int(end_time[5:7])

    table_names = []
    current_year = start_year
    current_month = start_month
    
    while (current_year < end_year) or (current_year == end_year and current_month <= end_month):
        month_str = f"{current_month:02d}"
        table_names.append(f"detail_record_{current_year}_{month_str}")
        
        #when 12 month, go to next year
        if current_month == 12:
            current_month = 1
            current_year += 1
        else:
            current_month += 1
    
    return table_names

def read_tables(spark, table_names):
    if not table_names:
        return None
    
    dfs = []
    for table_name in table_names:
        try:
            df = spark.read.format("jdbc") \
                .option("url", JDBC_URL) \
                .option("dbtable", table_name) \
                .option("user", JDBC_USER) \
                .option("password", JDBC_PASSWORD) \
                .load()
            dfs.append(df)
        except Exception as e:
            print(f"Error reading {table_name}: {e}")
    
    if not dfs:
        return None
    
    # Union all dataframes
    result_df = dfs[0]
    for df in dfs[1:]:
        result_df = result_df.union(df)
    
    return result_df

@app.route('/api/get_drivers', methods=['POST'])
def get_drivers():
    data = request.get_json(silent=True) or {}
    start_time = data.get('start_time')
    end_time = data.get('end_time')
    
    if not start_time or not end_time:
        return jsonify({"success": False, "message": "Missing time parameters"})
    
    spark = get_spark()
    
    
    if(start_time[0:7] == end_time[0:7]):
        tables_names = start_time
    else:
        tables_names = handle_time(start_time, end_time)
    
    df = read_tables(spark, tables_names)
    if df is None:
        return jsonify({"success": False, "message": "No data found"})
    
    filtered = df.filter((col("record_time") >= start_time) & (col("record_time") <= end_time))
    drivers = filtered.select("driverID").distinct().orderBy("driverID").toPandas()['driverID'].tolist()
    
    spark.stop()
    return jsonify({"success": True, "drivers": drivers})

@app.route('/api/get_driving_behavior_information', methods=['POST'])
def get_driving_behavior_information():
    data = request.get_json(silent=True) or {}
    start_time = data.get('start_time')
    end_time = data.get('end_time')
    driver_id = data.get('driver_id')
    
    if not start_time or not end_time:
        return jsonify({"success": False, "message": "Missing time parameters"})
    
    spark = get_spark()
    if(start_time[0:7] == end_time[0:7]):
        tables_names = start_time
    else:
        tables_names = handle_time(start_time, end_time)
    
    df = read_tables(spark, tables_names)
    if df is None:
        return jsonify({"success": False, "message": "No data found"})
    
    filtered = df.filter((col("record_time") >= start_time) & (col("record_time") <= end_time))
    
    if driver_id and driver_id != "all":
        filtered = filtered.filter(col("driverID") == driver_id)
    
    summary = filtered.groupBy("driverID", "carPlateNumber").agg(
        _sum("isOverspeed").alias("count_overspeed"),
        _sum("overspeedTime").alias("time_overspeed"),
        _sum("isFatigueDriving").alias("count_fatigueDriving"),
        _sum("isNeutralSlide").alias("count_neutralSlide"),
        _sum("neutralSlideTime").alias("time_neutralSlide"),
        _sum("isRapidlySpeedup").alias("count_rapidSpeedUp"),
        _sum("isRapidlySlowdown").alias("count_rapidSlowDown"),
        _sum("isHthrottleStop").alias("count_hthrottleStop"),
        _sum("isOilLeak").alias("count_oilLeak")
    ).withColumn(
        "count_dangerEvent",
        col("count_overspeed") + col("count_fatigueDriving") + col("count_neutralSlide") +
        col("count_rapidSpeedUp") + col("count_rapidSlowDown") + 
        col("count_hthrottleStop") + col("count_oilLeak")
    )
    
    result = [row.asDict() for row in summary.collect()]
    
    # Convert None to 0
    for row in result:
        for key in ["count_overspeed", "time_overspeed", "count_fatigueDriving",
                    "count_neutralSlide", "time_neutralSlide", "count_rapidSpeedUp",
                    "count_rapidSlowDown", "count_hthrottleStop", "count_oilLeak", "count_dangerEvent"]:
            row[key] = row.get(key) or 0
    
    spark.stop()
    return jsonify({"success": True, "data": result})

@app.route('/api/get_speed_data', methods=['POST'])
def get_speed_data():
    data = request.get_json(silent=True) or {}
    start_time = data.get('start_time')
    end_time = data.get('end_time')
    driver_id = data.get('driver_id')
    
    if not start_time or not end_time or not driver_id:
        return jsonify({"success": False, "message": "Missing parameters"})
    
    spark = get_spark()
    if(start_time[0:7] == end_time[0:7]):
        tables_names = start_time
    else:
        tables_names = handle_time(start_time, end_time)
    
    df = read_tables(spark, tables_names)
    if df is None:
        return jsonify({"success": False, "message": "No data found"})
    
    filtered = df.filter(
        (col("record_time") >= start_time) &
        (col("record_time") <= end_time) &
        (col("driverID") == driver_id)
    )
    
    speed_data = filtered.select("record_time", "speed") \
                         .orderBy("record_time") \
                         .toPandas()
    
    result = speed_data.to_dict(orient='records')
    
    spark.stop()
    return jsonify({
        "success": True,
        "data": result,
        "driver_id": driver_id
    })

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=False)