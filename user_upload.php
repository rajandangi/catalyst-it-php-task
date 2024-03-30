<?php
// Initialize the database configuration
class DBConfig
{
    private static ?DBConfig $instance = null;

    private function __construct(
        public string $host,
        public string $user,
        public string $password,
        public string $dbname
    ) {
        // Sanitize the database credentials
        $this->host = Helper::sanitizeString($host);
        $this->user = Helper::sanitizeString($user);
        $this->password = Helper::sanitizeString($password);
    }

    public static function getInstance(array $options): DBConfig
    {
        if (self::$instance === null) {
            if (isset($options['u']) && isset($options['p']) && isset($options['h'])) {
                //set if db credentials are provided in the command line
                self::$instance = new DBConfig($options['h'], $options['u'], $options['p'], 'catalyst');
            } elseif (file_exists('.env')) {
                //else, set if db credentials are provided in the .env file
                $env = parse_ini_file('.env');
                if ($env === false) {
                    die("Error reading .env file" . PHP_EOL);
                }
                self::$instance = new DBConfig($env['DB_HOST'], $env['DB_USER'], $env['DB_PASSWORD'], $env['DB_NAME']);
            }

            if (self::$instance === null) {
                die("Please provide database credentials" . PHP_EOL . ">> Use --help for more information" . PHP_EOL);
            }
        }
        return self::$instance;
    }
}

// Handle database connection and operations
class Database
{
    public readonly PDO $pdo;
    public function __construct(DBConfig $dbConfig)
    {
        try {
            // create new PDO connection
            $this->pdo = new PDO('mysql:host=' . $dbConfig->host . ';port=3306;dbname=' . $dbConfig->dbname, $dbConfig->user, $dbConfig->password);
            // set the PDO error mode to exception
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            Helper::consoleLog("Database Connected successfully");
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage() . PHP_EOL);
        }
    }
    // drop table table to rebuild
    public function dropTable(string $tableName): void
    {
        $sql = "DROP TABLE IF EXISTS $tableName";
        try {
            $this->pdo->query($sql);
            Helper::consoleLog("Table $tableName dropped successfully");
        } catch (PDOException $e) {
            die("Error dropping table: " . $e->getMessage() . PHP_EOL);
        }
    }
    // Create users table
    public function createUserTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) NOT NULL,
            surname VARCHAR(50) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE
        )ENGINE=InnoDB DEFAULT CHARSET=UTF8;";

        try {
            $this->pdo->query($sql);
            Helper::consoleLog("Table users created successfully");
        } catch (PDOException $e) {
            die("Error creating table: " . $e->getMessage() . PHP_EOL);
        }
    }
    // prepare user insert statement
    public function prepareInsertUser(): PDOStatement
    {
        return $this->pdo->prepare("INSERT INTO users (name, surname, email) VALUES (:name, :surname, :email)");
    }

    // insert user data in batch
    public function insertUsers(PDOStatement $stmt, array $batchData): void
    {
        foreach ($batchData as $data) {
            foreach ($data as $param => $value) {
                // Bind and Sanitize the data before saving to the database
                $stmt->bindValue($param, $value, PDO::PARAM_STR);
            }
            $stmt->execute(); // Execute the prepared statement with the current data
        }
    }
}

// Parse the CSV file and to insert into the database
class CSVProcessor
{
    private const BATCH_SIZE = 1000;
    public function __construct(
        private string $fileName,
        private Database $db,
        private bool $isDryRun = false,
    ) {
    }

    public function parseCSV(): void
    {
        try {
            if (!file_exists($this->fileName) || !is_readable($this->fileName)) {
                throw new Exception("CSV file does not exist or is not readable");
            }

            // Throw exception if file cannot be opened
            if (($handle = fopen($this->fileName, 'r')) === false) {
                throw new Exception("Could not open the file: $this->fileName");
            }

            // Get the first row (header) of the CSV file
            $header = fgetcsv($handle, 255);
            $header = array_map('trim', $header);

            // Get the correct index of the columns
            $nameIndex = array_search('name', $header);
            $surnameIndex = array_search('surname', $header);
            $emailIndex = array_search('email', $header);
            if ($nameIndex === false || $surnameIndex === false || $emailIndex === false) {
                throw new Exception("Invalid CSV file format");
            }

            // Initiate transaction with autocommit off
            $this->db->pdo->beginTransaction();

            // Prepare the insert statement
            $stmt = $this->db->prepareInsertUser();

            $batchData = [];
            $rowCount = 0;

            // Read the CSV file row by row
            while (($row = fgetcsv($handle, 255)) !== false) {
                $name = $row[$nameIndex];
                $surname = $row[$surnameIndex];
                $email = $row[$emailIndex];

                // Format the name and email
                $name = Helper::formatName($name);
                $surname = Helper::formatName($surname);
                $email = Helper::formatEmail($email);

                // Validate the name and email
                if (!Helper::formatName($name)) {
                    throw new Exception("Invalid name: $name");
                }
                if (!Helper::validateName($surname)) {
                    throw new Exception("Invalid surname: $surname");
                }
                if (!Helper::validateEmail($email)) {
                    throw new Exception("Invalid email: $email");
                }
                $batchData[] = [
                    ':name' => $name,
                    ':surname' => $surname,
                    ':email' => $email
                ];
                $rowCount++;
                // if batch size is reached, insert the batch into the database
                if ($rowCount % self::BATCH_SIZE === 0) {
                    $this->db->insertUsers($stmt, $batchData);
                    $batchData = []; // Reset the batch data
                }
            }

            // Insert the remaining data
            if (!empty($batchData)) {
                $this->db->insertUsers($stmt, $batchData);
            }

            fclose($handle);

            // Exit without inserting data into the database if it's a dry run
            if ($this->isDryRun) {
                $this->db->pdo->rollBack();
                Helper::consoleLog("Dry run completed. No data inserted into the database.");
                exit;
            }

            $this->db->pdo->commit();
            Helper::consoleLog("Success! All data imported successfully into the database.");

        } catch (Exception $e) {
            $this->db->pdo->rollBack();
            die("Data import failed with Error: " . $e->getMessage() . PHP_EOL);
        }
    }
}

// Helper Class to handle various utility functions
class Helper
{
    public static function sanitizeString(string $data): string
    {
        // Remove any HTML tags
        $data = strip_tags($data);

        // Escape special characters
        $data = htmlspecialchars(trim($data), ENT_QUOTES);

        // Remove extra whitespace
        $data = trim($data);

        return $data;
    }

    // Validate email
    public static function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    // validate name
    public static function validateName(string $name): bool
    {
        return preg_match('/^[A-Za-z\s-]+$/u', $name);
    }

    public static function formatName(string $string): string
    {
        // remove white space from the beginning and end of the string
        $string = trim($string, ' ');

        // remove extra white space in the middle of the string 
        $string = preg_replace('/\s+/', ' ', $string);

        // capitalize the first letter of each word
        $string = ucfirst($string);

        return $string;
    }

    public static function formatEmail(string $string): string
    {
        // remove white space and make lowercase
        return strtolower(trim($string));
    }

    public static function consoleLog(string $message): void
    {
        fwrite(STDOUT, $message . PHP_EOL);
    }

    // Display Help menu
    public static function consoleHelp(): void
    {
        $help = <<<HELP
        Usage: php user_upload.php [options]

        Options:
            --file [csv file name]      Name of the CSV to be parsed
            --create_table              Build the MySQL users table and no further action will be taken
            --dry_run                   Used with the --file option to run the script but not insert into the DB.
            -u [MySQL username]         MySQL username
            -p [MySQL password]         MySQL password
            -h [MySQL host]             MySQL host
            --help                      Output this help and exit
        HELP;
        echo $help . PHP_EOL;
        exit;
    }
}

// Main class to handle the main script
class Main
{
    private array $options;
    public bool $hasFile;
    public bool $shouldCreateTable;
    public bool $isDryRun;
    public Database $db;
    private DBConfig $dbConfig;
    public function __construct()
    {
        // Get options from CLI argument list
        $this->options = getopt("u:p:h:", ["file:", "create_table", "dry_run", "help"]);

        if (empty($this->options) || isset($this->options['help'])) {
            Helper::consoleHelp();
        }

        $this->dbConfig = DBConfig::getInstance($this->options);

        $this->hasFile = isset($this->options['file']);
        $this->shouldCreateTable = isset($this->options['create_table']);
        $this->isDryRun = isset($this->options['dry_run']);

        $this->handleActions();
    }

    // Handle the actions based on the options
    private function handleActions(): void
    {
        // Initialize database connection
        try {
            $this->db = new Database($this->dbConfig);
        } catch (Exception $e) {
            Helper::consoleLog("Database connection error: " . $e->getMessage());
            exit;
        }

        // Create users table and no further action will be taken
        if ($this->shouldCreateTable) {
            $this->db->dropTable('users');
            $this->db->createUserTable();
            exit;
        }

        // Parse the CSV file and insert into the database
        if ($this->hasFile) {
            $csvProcessor = new CSVProcessor($this->options['file'], $this->db, $this->isDryRun);
            $csvProcessor->parseCSV();
            exit;
        }

        Helper::consoleLog("Please provide the --file option or --create_table option to perform further actions.");
    }

}

// Run the main script
try {
    new Main();
} catch (Exception $e) {
    die("Error: " . $e->getMessage() . PHP_EOL);
}
