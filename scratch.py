import os
import pymysql

# Parse .env file
env_vars = {}
with open('.env', 'r', encoding='utf-8') as f:
    for line in f:
        line = line.strip()
        if not line or line.startswith('#'):
            continue
        if '=' in line:
            k, v = line.split('=', 1)
            env_vars[k.strip()] = v.strip()

host = env_vars.get('DB_HOST', 'localhost')
db_name = env_vars.get('DB_NAME', 'mashirikianosacc_mashirikiano')
user = env_vars.get('DB_USER', 'mashirikianosacc_mashirikianosacco')
password = env_vars.get('DB_PASS', '')

print(f"Connecting to host={host}, db={db_name}, user={user}")

try:
    conn = pymysql.connect(
        host=host,
        user=user,
        password=password,
        database=db_name,
        cursorclass=pymysql.cursors.DictCursor
    )
    with conn.cursor() as cursor:
        with open('database/loan_applications_migration.sql', 'r', encoding='utf-8') as sql_file:
            sql_content = sql_file.read()
            # Split queries by semicolon to execute them one by one
            queries = sql_content.split(';')
            for query in queries:
                query = query.strip()
                if query:
                    cursor.execute(query)
        conn.commit()
    conn.close()
    print("MIGRATION_SUCCESSFUL")
except Exception as e:
    print(f"MIGRATION_FAILED: {e}")
