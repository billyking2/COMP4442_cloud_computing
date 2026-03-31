from flask import Flask, request, jsonify
from pyspark.sql import SparkSession
from pyspark.sql.functions import col, sum as _sum
import os

app = Flask(__name__)

spark = None
df = None

def handle_time(start_time, end_time):
    start_time_year = int(start_time[14:18])
    start_time_month = int(start_time[19:21])
    end_time_year = int(end_time[14:18])
    end_time_month = int(end_time[19:21])

    table_name = []
    
    while (start_time_year < end_time_year) or (start_time_year == end_time_year and start_time_month <= end_time_month):
        month_str = f"{start_time_month:02d}"
        table_name.append(f"{start_time_year}_{month_str}")

        if start_time_month == 12:
            start_time_month = 1
            start_time_year += 1
        else:
            start_time_month += 1

    return table_name

def build_union_query(table_list):
    if not table_list:
        return None
    selects = []
    for t in table_list:
        sql = f"SELECT * FROM detail_record_{t}"
        selects.append(sql)
    union_sql = " UNION ".join(selects)
    return f"({union_sql})"



def init_spark(table_name):
    global spark, df
    if spark is None:
        spark = SparkSession.builder \
            .appName("DriverBehaviorAPI") \
            .config("spark.sql.adaptive.enabled", "true") \
            .getOrCreate()

        # read from database
        jdbc_url = "jdbc:mysql://comp4442-group-project.co9yvkeoopsc.us-east-1.rds.amazonaws.com:3306/COMP4442_group_project"

        df = spark.read.format("jdbc") \
            .option("url", jdbc_url) \
            .option("dbtable", table_name) \
            .option("user", "admin") \
            .option("password", "Qweasdzxc1612") \
            .option("driver", "com.mysql.cj.jdbc.Driver") \
            .load()

       
        df.cache()

    return spark, df


@app.route('/api/get_drivers', methods=['POST'])
def get_drivers():
    data = request.get_json(silent=True) or {}
    start_time = data.get('start_time')
    end_time = data.get('end_time')
  
    if(start_time[0:21] ==end_time[0:21]):
        table_name = start_time
    else:
        table_name = build_union_query(handle_time(start_time, end_time))
    
    if table_name is not None:    
        _, current_df = init_spark(table_name)

    filtered = current_df
    if start_time and end_time:
        filtered = filtered.filter((col("record_time") >= start_time) & (col("record_time") <= end_time))

    drivers = filtered.select("driverID").distinct().orderBy("driverID").toPandas()['driverID'].tolist()

    return jsonify({"success": True, "drivers": drivers})


@app.route('/api/get_driving_behavior_information', methods=['POST'])
def get_driving_behavior_information():
    data = request.get_json(silent=True) or {}
    start_time = data.get('start_time')
    end_time = data.get('end_time')
    driver_id = data.get('driver_id')

      
    if(start_time[0:21] ==end_time[0:21]):
        table_name = start_time
    else:
        table_name = build_union_query(handle_time(start_time, end_time))
    
    if table_name is not None:    
      _, current_df = init_spark(table_name)

    filtered = current_df
    if start_time and end_time:
        filtered = filtered.filter((col("record_time") >= start_time) & (col("record_time") <= end_time))

    if driver_id and driver_id != "all":
        filtered = filtered.filter(col("driverID") == driver_id)

    # spark aggregation 
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

    # convert None to 0
    for row in result:
        for key in ["count_overspeed", "time_overspeed", "count_fatigueDriving",
                    "count_neutralSlide", "time_neutralSlide", "count_rapidSpeedUp",
                    "count_rapidSlowDown", "count_hthrottleStop", "count_oilLeak", "count_dangerEvent"]:
            row[key] = row.get(key) or 0

    return jsonify({
        "success": True,
        "data": result
    })


if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000)