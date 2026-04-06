import json
import logging
from flask import Flask, request, jsonify
from flask_cors import CORS
from pyspark.sql import SparkSession
from pyspark.sql.functions import col, sum as _sum, when
from pyspark.sql.functions import col, lit, to_timestamp
from functools import reduce
import os
import sys 
sys.stdout.reconfigure(encoding='utf-8')

app = Flask(__name__)
CORS(app, resources={r"/api/*": {"origins": "http://comp4442groupprojectfinal-env.eba-8hpdjebw.us-east-1.elasticbeanstalk.com"}})

spark = None

logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler('/home/ec2-user/detail-records/api.log'),
        logging.StreamHandler()
    ]
)
logger = logging.getLogger(__name__)

# JDBC configuration
JDBC_URL = "jdbc:mysql://comp4442-group-project.co9yvkeoopsc.us-east-1.rds.amazonaws.com:3306/COMP4442_group_project"
JDBC_USER = "admin"
JDBC_PASSWORD = "Qweasdzxc1612"

def get_spark():
    global spark
    if spark is None:   
        jdbc_driver_path = "/home/ec2-user/detail-records/mysql-connector-j-8.0.33.jar"
        if not os.path.exists(jdbc_driver_path):
            jdbc_driver_path = os.path.join(os.path.dirname(__file__), "mysql-connector-j-8.0.33.jar")
        builder = SparkSession.builder \
            .appName("DriverBehaviorAPI") \
            .config("spark.sql.adaptive.enabled", "true") \
            .config("spark.sql.legacy.timeParserPolicy", "LEGACY") \
            .config("spark.driver.memory", "1g") \
            .config("spark.executor.memory", "1g") \
            

        if os.path.exists(jdbc_driver_path):
            builder = builder.config("spark.jars", jdbc_driver_path).config("spark.driver.extraClassPath", jdbc_driver_path)
            print(f"Loaded JDBC driver from: {jdbc_driver_path}")
        else:
            print(f"WARNING: JDBC driver not found at {jdbc_driver_path}")
            print("Please ensure mysql-connector-j-8.0.33.jar is in the current directory")
        
        spark = builder.getOrCreate()
        print("Spark session created successfully")
        
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
def read_tables(spark, table_names, start_time, end_time, driver_id, column=None):
    if not table_names:
        logger.warning("No table names provided")
        return None
                
    dfs = []
    successful = []
    failed = []

    if column is None:
        select_clause = "*"
    else:
        select_clause = ", ".join(column)

    for table_name in table_names:
       
        if driver_id == "all":
            query = f"SELECT {select_clause} FROM {table_name} WHERE record_time >= '{start_time}' AND record_time <= '{end_time}'"
        elif driver_id is None:
            query = f"SELECT driverID FROM {table_name} WHERE record_time >= '{start_time}' AND record_time <= '{end_time}'"
        else:
            query = f"SELECT {select_clause} FROM {table_name} WHERE record_time >= '{start_time}' AND record_time <= '{end_time}' AND driverID = '{driver_id}'"

        logger.info(f"Querying table: {table_name}")
        logger.info(f"Query: {query}")
        logger.info(f"Time range: {start_time} to {end_time}")
        try:
            df = spark.read.format("jdbc") \
                .option("driver", "com.mysql.cj.jdbc.Driver") \
                .option("url", JDBC_URL) \
                .option("query", query) \
                .option("user", JDBC_USER) \
                .option("password", JDBC_PASSWORD) \
                .load()
            
            row_count = df.count()
            logger.info(f"Successfully loaded table: {table_name} ({row_count} rows)")
            
            if row_count > 0:
                dfs.append(df)
                successful.append(table_name)
            else:
                logger.warning(f"Table {table_name} returned 0 rows")
                failed.append(table_name)
            
        except Exception as e:
            failed.append(table_name)
            logger.error(f"Failed to read {table_name}: {e}")
            continue
   

    if not dfs:
        logger.error("No tables could be read successfully.")
        return None

    if len(dfs) == 1:
        result_df = dfs[0]
    else:
        result_df = reduce(
            lambda x, y: x.unionByName(y, allowMissingColumns=True), 
            dfs
        )

    result_df = result_df.cache()
    logger.info(f"Union completed: {len(successful)} tables loaded successfully.")
    if failed:
        logger.warning(f"Failed tables: {failed}")

    return result_df

@app.route('/')
def health_check():
    return jsonify({"status": "healthy"}), 200
    
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
    
        df = read_tables(spark, tables_names, normalize_timestamp(start_time), normalize_timestamp(end_time), driver_id=None, column = None)
        if df is None:
            return jsonify({"success": False, "message": "No data found for the given period "+f"({start_time} to {end_time})"})
        
        driver_rows = df.select("driverID").distinct().orderBy("driverID").collect()
        drivers = [row.driverID for row in driver_rows]
        return jsonify({"success": True, "drivers": drivers})
        
    except Exception as e:
        logger.error(f"Error in get_drivers: {e}")
        return jsonify({"success": False, "message": f"Server error: {str(e)}"}), 400


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

    needed_columns = [
        "driverID", "carPlateNumber", "record_time",
        "isOverspeed", "overspeedTime", "isFatigueDriving",
        "isNeutralSlide", "neutralSlideTime", "isRapidlySpeedup",
        "isRapidlySlowdown", "isHthrottleStop", "isOilLeak"
    ]
    
    try:
        df = read_tables(spark, tables_names, normalize_timestamp(start_time), normalize_timestamp(end_time), driver_id,column=needed_columns)
        if df is None:
            return jsonify({"success": False, "message": "No data found for the given period "+f"({start_time} to {end_time}) in tables_names:{tables_names}"})
        
        df.printSchema()
        
        filtered = df
        
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
        
        rows = summary.collect()
        result = [row.asDict() for row in rows]
        
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
        return jsonify({"success": False, "message": f"Server error: {str(e)}"}), 400

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
        df = read_tables(spark, tables_names, normalize_timestamp(start_time), normalize_timestamp(end_time), driver_id, column=["record_time", "speed", "isOverspeed"])
        if df is None:
            return jsonify({"success": False, "message": "No data found for the given period "+f"({start_time} to {end_time})"})
        
        
        json_str = df.select("record_time", "speed", "isOverspeed") \
                  .orderBy("record_time") \
                  .toJSON() \
                  .collect()

        result = [json.loads(row) for row in json_str]

        return jsonify({
            "success": True,
            "data": result,
            "driver_id": driver_id
        })
    except Exception as e:
        print(f"Error in get_speed_data: {e}")
        return jsonify({"success": False, "message": f"Server error: {str(e)}"}), 400
    

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=8080, debug=True)
