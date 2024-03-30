# User Upload Script

The `user_upload.php` script is a command-line PHP script that processes a CSV file containing user data and inserts the data into a MySQL database. The script is capable of creating a database table, validating input data, and performing dry runs.

## Prerequisites

- PHP 8.1.x or higher
- MySQL 5.7 or higher (also compatible with MariaDB 10.x)
- Access to a MySQL database with appropriate privileges
- The script is tested on Ubuntu 22.04

## Installation

### Database Credentials

First, create a database named `catalyst` in the MySQL database. Then, connect to the database using one of the following two methods:

 **1. Using command-line options:**

- `-u`: MySQL username.
- `-p`: MySQL password.
- `-h`: MySQL host.

In this case, you will need to always specify these database credentials to create a table, import user data, and perform dry runs. See [Examples](#examples) below.

**2. Using a `.env` File**

Alternatively, create a `.env` file in the root directory of the project with the following content or copy content from `.env-example` file.

```env
DB_HOST=localhost 
DB_NAME=catalyst
DB_USER=root 
DB_PASSWORD=root
```

Replace `localhost`, `root`, `root`, and `catalyst` with the actual database `host`, `username`, `password`, and `any_database_name` you have created on MySQL.

### Usage
---
To use the script, navigate to the project directory and execute the `user_upload.php` script with the desired options.

**Command-Line Options**

- `--file [csv file name]`: Parses the specified CSV file and inserts the data into the database.
- `--create_table`: Creates (or rebuilds, if it already exists) the `users` table in the database.
- `--dry_run`: Executes the script but does not alter the database (to be used with `--file`).
- `--help`: Displays the help information.

### Examples

```bash
# Display help
php user_upload.php --help

# Parse CSV and insert data into the database
php user_upload.php --file users.csv -u username -p password -h localhost

# Create or rebuild the users table
php user_upload.php --create_table -u username -p password -h localhost

# Dry run (parse CSV but do not insert into the database)
php user_upload.php --file users.csv --dry_run -u username -p password -h localhost
```

However, if using a `.env` file:
```bash
# Display help
php user_upload.php --help

# Parse CSV and insert data into the database
php user_upload.php --file users.csv

# Create or rebuild the users table
php user_upload.php --create_table

# Dry run (parse CSV but do not insert into the database)
php user_upload.php --file users.csv --dry_run
```

## Assumptions

- The CSV file must have a header row with the fields: `name`, `surname`, `email`.
- The script assumes the CSV file is correctly formatted and invalid CSV files will be ignored.
- The `--create_table` directive drops the users table if it already exists as part of the rebuild.
- Users are imported in batches to handle large CSV files. The script has been tested with up to 100,000 records.
- No CSV library or `.env` library is used to keep the script lightweight.
- Different classes can be separated into individual files to be more organized, but currently, all code is in one file to focus on functionality. Folder and file structure organization has been deprioritized.


## Use of AI
AI-generated Python code is used to generate a CSV file with 100,000 records. Visit this [Python repository](https://github.com/rajandangi/csv-generator) to access the code.