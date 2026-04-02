from flask import Flask, request, jsonify
from flask_cors import CORS
from pyspark.sql import SparkSession
from pyspark.sql.functions import col, sum as _sum, when
from pyspark.sql.functions import col, lit, to_timestamp
import os
import sys 
sys.stdout.reconfigure(encoding='utf-8')

app = Flask(__name__)
CORS(app, resources={r"/api/*": {"origins": "http://comp4442groupprojectfinal-env.eba-8hpdjebw.us-east-1.elasticbeanstalk.com"}})

spark = None

# JDBC configuration
JDBC_URL = "jdbc:mysql://comp4442-group-project.co9yvkeoopsc.us-east-1.rds.amazonaws.com:3306/COMP4442_group_project"
JDBC_USER = "admin"
JDBC_PASSWORD = "Qweasdzxc1612"

def get_spark():
    global spark
    if spark is None:
        spark = SparkSession.builder \
            .appName("DriverBehaviorAPI") \
            .config("spark.sql.adaptive.enabled", "true") \
            .config("spark.sql.legacy.timeParserPolicy", "LEGACY") \
            .getOrCreate()
    return spark

# base on start and end time to get the table names which need to query
def handle_time(start_time, end_time):
    start_time = start_time.split('T')[0]
    start_year = int(start_time[0:4])
    start_month = int(start_time[5:7])
    end_time = end_time.split('T')[0]
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

# handle time format
def normalize_timestamp(ts_str: str) -> str:

    if not ts_str:
        return ts_str
    
    ts = ts_str.replace('T', ' ')
    
    if len(ts) == 16 and ts[10] == ' ':  
        ts += ":00"
    
    return ts

# read multiple tables and union them into one dataframe
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
    
    # union all dataframes
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
    tables_names = handle_time(start_time, end_time)
    
    try:
    
        df = read_tables(spark, tables_names)
        if df is None:
            return jsonify({"success": False, "message": "No data found"})
        
        start_ts_str = normalize_timestamp(start_time)
        end_ts_str   = normalize_timestamp(end_time)

        filtered = df.filter(
            (col("record_time") >= to_timestamp(lit(start_ts_str), "yyyy-MM-dd HH:mm:ss")) &
            (col("record_time") <= to_timestamp(lit(end_ts_str),   "yyyy-MM-dd HH:mm:ss"))
        )

        drivers = filtered.select("driverID").distinct().orderBy("driverID").toPandas()['driverID'].tolist()
    
        return jsonify({"success": True, "drivers": drivers})
    
    except Exception as e:
        print(f"Error in get_drivers: {e}")
        return jsonify({"success": False, "message": f"Server error: {str(e)}"}), 500

@app.route('/api/get_driving_behavior_information', methods=['POST'])
def get_driving_behavior_information():
    data = request.get_json(silent=True) or {}
    start_time = data.get('start_time')
    end_time = data.get('end_time')
    driver_id = data.get('driver_id')
    
    if not start_time or not end_time:
        return jsonify({"success": False, "message": "Missing time parameters"})
    
    spark = get_spark()

    tables_names = handle_time(start_time, end_time)
    
    try:
        df = read_tables(spark, tables_names)
        if df is None:
            return jsonify({"success": False, "message": "No data found"})
        
        start_ts_str = normalize_timestamp(start_time)
        end_ts_str   = normalize_timestamp(end_time)

        print(f"Raw start_time: {start_time} | end_time: {end_time}")
        print(f"Normalized start: '{start_ts_str}' | end: '{end_ts_str}'")
        df.printSchema()
        
        filtered = df.filter(
            (col("record_time") >= to_timestamp(lit(start_ts_str), "yyyy-MM-dd HH:mm:ss")) &
            (col("record_time") <= to_timestamp(lit(end_ts_str),   "yyyy-MM-dd HH:mm:ss"))
        )
        
        if driver_id and driver_id != "all":
            filtered = filtered.filter(col("driverID") == driver_id)
        
        summary = filtered.groupBy("driverID", "carPlateNumber").agg(
            _sum(when(col("isOverspeed") == True, 1).otherwise(0)).alias("count_overspeed"),
            _sum("overspeedTime").alias("time_overspeed"),
            _sum(when(col("isFatigueDriving") == True, 1).otherwise(0)).alias("count_fatigueDriving"),
            _sum(when(col("isNeutralSlide") == True, 1).otherwise(0)).alias("count_neutralSlide"),
            _sum("neutralSlideTime").alias("time_neutralSlide"),
            _sum(when(col("isRapidlySpeedup") == True, 1).otherwise(0)).alias("count_rapidSpeedUp"),
            _sum(when(col("isRapidlySlowdown") == True, 1).otherwise(0)).alias("count_rapidSlowDown"),
            _sum(when(col("isHthrottleStop") == True, 1).otherwise(0)).alias("count_hthrottleStop"),
            _sum(when(col("isOilLeak") == True, 1).otherwise(0)).alias("count_oilLeak")
        ).withColumn(
            "count_dangerEvent",
            col("count_overspeed") + col("count_fatigueDriving") + col("count_neutralSlide") +
            col("count_rapidSpeedUp") + col("count_rapidSlowDown") + 
            col("count_hthrottleStop") + col("count_oilLeak")
        )
        
        result = [row.asDict() for row in summary.collect()]
        
        # convert none to 0
        for row in result:
            for key in ["count_overspeed", "time_overspeed", "count_fatigueDriving",
                        "count_neutralSlide", "time_neutralSlide", "count_rapidSpeedUp",
                        "count_rapidSlowDown", "count_hthrottleStop", "count_oilLeak", "count_dangerEvent"]:
                row[key] = row.get(key) or 0
                
                if "carPlateNumber" in row and row["carPlateNumber"]:
                    row["carPlateNumber"] = str(row["carPlateNumber"]).encode('utf-8').decode('utf-8')

        return jsonify({"success": True, "data": result})
    except Exception as e:
        print(f"Error in get_driving_behavior_information: {e}")
        return jsonify({"success": False, "message": f"Server error: {str(e)}"}), 500

@app.route('/api/get_speed_data', methods=['POST'])
def get_speed_data():
    data = request.get_json(silent=True) or {}
    start_time = data.get('start_time')
    end_time = data.get('end_time')
    driver_id = data.get('driver_id')
    
    if not start_time or not end_time or not driver_id:
        return jsonify({"success": False, "message": "Missing parameters"})
    
    spark = get_spark()
    tables_names = handle_time(start_time, end_time)
    
    try:
        df = read_tables(spark, tables_names)
        if df is None:
            return jsonify({"success": False, "message": "No data found"})
        
        start_ts_str = normalize_timestamp(start_time)
        end_ts_str   = normalize_timestamp(end_time)

        filtered = df.filter(
            (col("record_time") >= to_timestamp(lit(start_ts_str), "yyyy-MM-dd HH:mm:ss")) &
            (col("record_time") <= to_timestamp(lit(end_ts_str),   "yyyy-MM-dd HH:mm:ss")) &
            (col("driverID") == driver_id)
        )
        
        speed_data = filtered.select("record_time", "speed") \
                            .orderBy("record_time") \
                            .toPandas()
        
        result = speed_data.to_dict(orient='records')

        return jsonify({
            "success": True,
            "data": result,
            "driver_id": driver_id
        })
    except Exception as e:
        print(f"Error in get_speed_data: {e}")
        return jsonify({"success": False, "message": f"Server error: {str(e)}"}), 500
    
    
if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=True)