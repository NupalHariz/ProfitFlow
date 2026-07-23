SELECT 'CREATE DATABASE profitflow_testing OWNER profitflow'
WHERE NOT EXISTS (
    SELECT FROM pg_database WHERE datname = 'profitflow_testing'
)\gexec
