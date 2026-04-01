from pyspark.sql.functions import col, split, when, lit, trim, size
from pyspark.sql import SparkSession
import os
import sys
import re

ss = SparkSession.builder \
    .appName("importDataToSQL") \
    .master("local[*]") \
    .config("spark.jars", "/home/ec2-user/spark/jars/mysql-connector-j-8.0.33.jar") \
    .config("spark.driver.extraClassPath", "/home/ec2-user/spark/jars/mysql-connector-j-8.0.33.jar") \
    .config("spark.executor.extraClassPath", "/home/ec2-user/spark/jars/mysql-connector-j-8.0.33.jar") \
    .getOrCreate()


jdbc_url = "jdbc:mysql://comp4442-group-project.co9yvkeoopsc.us-east-1.rds.amazonaws.com:3306/COMP4442_group_project?rewriteBatchedStatements=true"
connection = {
        "user": "admin",
        "password": "Qweasdzxc1612",
        "driver": "com.mysql.cj.jdbc.Driver"
    }

TEMPLATE_TABLE = "detail_records_template"

def extract_table_name_from_filename(file_name):
    base_name = os.path.basename(file_name)        
    table_name = os.path.splitext(base_name)[0]
    return table_name[0:21]

def create_table_if_not_exists(table_name):
    try:
        # check table exists
        test_df = ss.read.format("jdbc") \
            .option("url", jdbc_url) \
            .option("dbtable", table_name) \
            .option("user", connection["user"]) \
            .option("password", connection["password"]) \
            .option("driver", connection["driver"]) \
            .load() \
            .limit(1)
        test_df.count()
        print(f"Table {table_name} already exists")
        return True
    except:
        # when table doesn't exist, create it from template
        print(f"Creating table {table_name} from template...")
        try:
            # get schema from template table
            template_df = ss.read.format("jdbc") \
                .option("url", jdbc_url) \
                .option("dbtable", TEMPLATE_TABLE) \
                .option("user", connection["user"]) \
                .option("password", connection["password"]) \
                .option("driver", connection["driver"]) \
                .load() \
                .limit(0)
            
            # create new table
            template_df.write \
                .mode("error") \
                .format("jdbc") \
                .option("url", jdbc_url) \
                .option("dbtable", table_name) \
                .option("user", connection["user"]) \
                .option("password", connection["password"]) \
                .option("driver", connection["driver"]) \
                .option("createTableOptions", "ENGINE=InnoDB DEFAULT CHARSET=utf8mb4") \
                .save()
            
            print(f"Table {table_name} created successfully")
            return True
        except Exception as e:
            print(f"Error creating table: {e}")
            return False
    
    
def parseData(df):
  #clean the data
  df = df.withColumn("value", trim(col("value")))
  df = df.filter(col("value") != "")

  #split the data and input to dataframe
  df = df.withColumn("parts", split(col("value"), ","))

  #count the nunmber of element
  df = df.withColumn("field_count", size(col("parts")))

  df_final = df.select(
      when(col("parts").getItem(0) != "", col("parts").getItem(0)).otherwise(lit(None)).alias("driverID"),

      when(col("parts").getItem(1) != "", col("parts").getItem(1)).otherwise(lit(None)).alias("carPlateNumber"),

      when(col("parts").getItem(2) != "", col("parts").getItem(2).cast("float")).otherwise(lit(None)).alias("Latitude"),

      when(col("parts").getItem(3) != "", col("parts").getItem(3).cast("float")).otherwise(lit(None)).alias("Longitude"),

      when(col("parts").getItem(4) != "", col("parts").getItem(4).cast("int")).otherwise(lit(None)).alias("Speed"),

      when(col("parts").getItem(5) != "", col("parts").getItem(5).cast("int")).otherwise(lit(None)).alias("Direction"),

      when(col("parts").getItem(6) != "", col("parts").getItem(6)).otherwise(lit(None)).alias("siteName"),

      when(col("parts").getItem(7) != "", col("parts").getItem(7)).otherwise(lit(None)).alias("Time"),

      # Some records only have 8 element
      when(col("field_count") > 8,
          when(col("parts").getItem(8) != "", col("parts").getItem(8).cast("int"))
          .otherwise(lit(0))
      ).otherwise(lit(0)).alias("isRapidlySpeedup"),

      when(col("field_count") > 9,
          when(col("parts").getItem(9) != "", col("parts").getItem(9).cast("int"))
          .otherwise(lit(0))
      ).otherwise(lit(0)).alias("isRapidlySlowdown"),

      when(col("field_count") > 10,
          when(col("parts").getItem(10) != "", col("parts").getItem(10).cast("int"))
          .otherwise(lit(0))
      ).otherwise(lit(0)).alias("isNeutralSlide"),

      when(col("field_count") > 11,
          when(col("parts").getItem(11) != "", col("parts").getItem(11).cast("int"))
          .otherwise(lit(0))
      ).otherwise(lit(0)).alias("isNeutralSlideFinished"),

      when(col("field_count") > 12,
          when(col("parts").getItem(12) != "", col("parts").getItem(12).cast("int"))
          .otherwise(lit(0))
      ).otherwise(lit(0)).alias("neutralSlideTime"),

      when(col("field_count") > 13,
          when(col("parts").getItem(13) != "", col("parts").getItem(13).cast("int"))
          .otherwise(lit(0))
      ).otherwise(lit(0)).alias("isOverspeed"),

      when(col("field_count") > 14,
          when(col("parts").getItem(14) != "", col("parts").getItem(14).cast("int"))
          .otherwise(lit(0))
      ).otherwise(lit(0)).alias("isOverspeedFinished"),

      when(col("field_count") > 15,
          when(col("parts").getItem(15) != "", col("parts").getItem(15).cast("int"))
          .otherwise(lit(0))
      ).otherwise(lit(0)).alias("overspeedTime"),

      when(col("field_count") > 16,
          when(col("parts").getItem(16) != "", col("parts").getItem(16).cast("int"))
          .otherwise(lit(0))
      ).otherwise(lit(0)).alias("isFatigueDriving"),

      when(col("field_count") > 17,
          when(col("parts").getItem(17) != "", col("parts").getItem(17).cast("int"))
          .otherwise(lit(0))
      ).otherwise(lit(0)).alias("isHthrottleStop"),

      when(col("field_count") > 18,
          when(col("parts").getItem(18) != "", col("parts").getItem(18).cast("int"))
          .otherwise(lit(0))
      ).otherwise(lit(0)).alias("isOilLeak"),

  )
  return df_final


def insertData(df, table_name):
    record_count = df.count()
    if record_count == 0:
        print("No records to insert!")
        return 0
    
    df.write \
        .mode("append") \
        .format("jdbc") \
        .option("url", jdbc_url) \
        .option("dbtable", table_name) \
        .option("user", connection["user"]) \
        .option("password", connection["password"]) \
        .option("driver", connection["driver"]) \
        .save()
    return record_count



def main():
    
    if len(sys.argv) < 2:
        print("Error: No file provided")
        sys.exit(1)
    
    table_name = extract_table_name_from_filename(sys.argv[1])
    
    if not create_table_if_not_exists(table_name):
        print("error in create_table_if_not_exists")
        sys.exit(1)
        
    df_final = parseData(ss.read.text(sys.argv[1]))

    count = insertData(df_final, table_name)
    print(f"Successfully inserted {count} records into {table_name}")
    
    ss.stop()

if __name__ == "__main__":
    main()