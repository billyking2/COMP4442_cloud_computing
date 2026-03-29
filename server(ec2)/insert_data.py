#!/usr/bin/env python3
import mysql.connector
from mysql.connector import Error
import sys
import os

# Database configuration (your MySQL database)
config = {
    'host': 'comp4442-group-project.co9yvkeoopsc.us-east-1.rds.amazonaws.com',  # This is your database host (could be localhost if MySQL is on same EC2, or RDS endpoint)
    'database': 'COMP4442_group_project',
    'user': 'admin',
    'password': 'Qweasdzxc1612',
    'port': 3306
}

def parse_line(line):
    """Parse a line from the text file and return a tuple for database insertion"""
    # Split the line by comma
    parts = line.strip().split(',')
    
    # Map the fields based on your file structure
    # The file has varying number of fields, so we need to handle missing values
    driverID = parts[0] if len(parts) > 0 else None
    carPlateNumber = parts[1] if len(parts) > 1 else None
    latitude = parts[2] if len(parts) > 2 and parts[2] else None
    longitude = parts[3] if len(parts) > 3 and parts[3] else None
    speed = parts[4] if len(parts) > 4 and parts[4] else None
    direction = parts[5] if len(parts) > 5 and parts[5] else None
    siteName = parts[6] if len(parts) > 6 and parts[6] else None
    record_time = parts[7] if len(parts) > 7 and parts[7] else None
    
    # Default values for the boolean/int fields
    isRapidlySpeedup = 0
    isRapidlySlowdown = 0
    isNeutralSlide = 0
    isNeutralSlideFinished = 0
    neutralSlideTime = 0
    isOverspeed = 0
    isOverspeedFinished = 0
    overspeedTime = 0
    isFatigueDriving = 0
    isHthrottleStop = 0
    isOilLeak = 0
    count_dangerEvent = None
    
    # Map additional fields if they exist (positions 8-19 based on your data)
    # Your data has varying lengths, so we need to handle the trailing fields
    if len(parts) > 8 and parts[8]:
        isRapidlySpeedup = int(parts[8]) if parts[8] else 0
    if len(parts) > 9 and parts[9]:
        isRapidlySlowdown = int(parts[9]) if parts[9] else 0
    if len(parts) > 10 and parts[10]:
        isNeutralSlide = int(parts[10]) if parts[10] else 0
    if len(parts) > 11 and parts[11]:
        isNeutralSlideFinished = int(parts[11]) if parts[11] else 0
    if len(parts) > 12 and parts[12]:
        neutralSlideTime = int(parts[12]) if parts[12] else 0
    if len(parts) > 13 and parts[13]:
        isOverspeed = int(parts[13]) if parts[13] else 0
    if len(parts) > 14 and parts[14]:
        isOverspeedFinished = int(parts[14]) if parts[14] else 0
    if len(parts) > 15 and parts[15]:
        overspeedTime = int(parts[15]) if parts[15] else 0
    if len(parts) > 16 and parts[16]:
        isFatigueDriving = int(parts[16]) if parts[16] else 0
    if len(parts) > 17 and parts[17]:
        isHthrottleStop = int(parts[17]) if parts[17] else 0
    if len(parts) > 18 and parts[18]:
        isOilLeak = int(parts[18]) if parts[18] else 0
    if len(parts) > 19 and parts[19]:
        count_dangerEvent = int(parts[19]) if parts[19] else None
    
    # Convert latitude and longitude to Decimal
    latitude = float(latitude) if latitude else None
    longitude = float(longitude) if longitude else None
    
    # Convert speed and direction to int
    speed = int(speed) if speed else None
    direction = int(direction) if direction else None
    
    return (driverID, carPlateNumber, latitude, longitude, speed, direction, 
            siteName, record_time, isRapidlySpeedup, isRapidlySlowdown, 
            isNeutralSlide, isNeutralSlideFinished, neutralSlideTime, 
            isOverspeed, isOverspeedFinished, overspeedTime, 
            isFatigueDriving, isHthrottleStop, isOilLeak, count_dangerEvent)

def insert_data_from_file(filename):
    """Read data from text file and insert into database"""
    connection = None
    base_name = os.path.basename(filename)        
    table_name = os.path.splitext(base_name)[0]
    
    try:
        # Connect to database
        connection = mysql.connector.connect(**config)
        
        if connection.is_connected():
            cursor = connection.cursor()
            
            # create table if it doesn't exist 
            create_table_query = f"""
            CREATE TABLE IF NOT EXISTS `{table_name}` 
            LIKE `detail_records_template`;
            """
            
            print(f"Checking/creating table: `{table_name}` (using template)")
            cursor.execute(create_table_query)
            print(f"✅ Table `{table_name}` is ready.")
            
            # SQL INSERT statement
            insert_query = f"""
            INSERT INTO `{table_name}` (
                driverID, carPlateNumber, latitude, longitude, speed, direction,
                siteName, record_time, isRapidlySpeedup, isRapidlySlowdown,
                isNeutralSlide, isNeutralSlideFinished, neutralSlideTime,
                isOverspeed, isOverspeedFinished, overspeedTime,
                isFatigueDriving, isHthrottleStop, isOilLeak, count_dangerEvent
            ) VALUES (
                %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s
            )
            """
            
            print(f"Inserting data into table: `{table_name}`")
            
            # Read and process the file
            records_to_insert = []
            line_count = 0
            error_count = 0
            
            with open(filename, 'r', encoding='utf-8') as file:
                for line_num, line in enumerate(file, 1):
                    line = line.strip()
                    if not line:  # Skip empty lines
                        continue
                    
                    try:
                        # Parse the line
                        record = parse_line(line)
                        records_to_insert.append(record)
                        line_count += 1
                        
                        # Optional: Insert in batches of 1000 to avoid memory issues
                        if len(records_to_insert) >= 1000:
                            cursor.executemany(insert_query, records_to_insert)
                            connection.commit()
                            print(f"Inserted {len(records_to_insert)} records...")
                            records_to_insert = []
                            
                    except Exception as e:
                        error_count += 1
                        print(f"Error parsing line {line_num}: {line}")
                        print(f"Error details: {e}")
            
            # Insert any remaining records
            if records_to_insert:
                cursor.executemany(insert_query, records_to_insert)
                connection.commit()
                print(f"Inserted final {len(records_to_insert)} records")
            
            print(f"\nSummary:")
            print(f"Total lines processed: {line_count}")
            print(f"Successfully inserted: {line_count - error_count}")
            print(f"Errors encountered: {error_count}")
            
            cursor.close()
            
    except Error as e:
        print(f"Database error: {e}")
        if connection:
            connection.rollback()
            
    except FileNotFoundError:
        print(f"Error: File '{filename}' not found")
        
    except Exception as e:
        print(f"Unexpected error: {e}")
        if connection:
            connection.rollback()
            
    finally:
        if connection and connection.is_connected():
            connection.close()
            print("MySQL connection closed")

if __name__ == "__main__":
    # read filename from command line
    if len(sys.argv) < 2:
        print("Error: No input file provided.")
        print("Usage: python3 insert_data.py <path_to_file>")
        sys.exit(1)

    filename = sys.argv[1]
    print(f"Starting import for file: {filename}")

    if not os.path.isfile(filename):
        print(f"Error: File does not exist: {filename}")
        sys.exit(1)

    insert_data_from_file(filename)