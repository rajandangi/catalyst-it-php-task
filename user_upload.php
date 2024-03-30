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
                self::$instance = new DBConfig($env['DB_HOST'], $env['DB_USER'], $env['DB_PASSWORD'], $env['DB_NAME']);
            }

            if (self::$instance === null) {
                echo "Please provide database credentials" . PHP_EOL . ">> Use --help for more information" . PHP_EOL;
                exit;
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
            echo "Database Connected successfully" . PHP_EOL;
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
            echo "Table $tableName dropped successfully" . PHP_EOL;
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
            echo "Table users created successfully" . PHP_EOL;
        } catch (PDOException $e) {
            die("Error creating table: " . $e->getMessage() . PHP_EOL);
        }
    }
    // insert user data
    public function insertUser(string $name, string $surname, string $email): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO users (name, surname, email) VALUES (:name, :surname, :email)");

        // bind and save data to database
        $stmt->bindValue(":name", $name, PDO::PARAM_STR);
        $stmt->bindValue(":surname", $surname, PDO::PARAM_STR);
        $stmt->bindValue(":email", $email, PDO::PARAM_STR);
        $stmt->execute();
    }
}

// Parse the CSV file and to insert into the database
class CSVProcessor
{
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

            // GEt the correct index of the columns
            $nameIndex = array_search('name', $header);
            $surnameIndex = array_search('surname', $header);
            $emailIndex = array_search('email', $header);
            if ($nameIndex === false || $surnameIndex === false || $emailIndex === false) {
                throw new Exception("Invalid CSV file format");
            }

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
                $this->db->insertUser($name, $surname, $email);
            }
            fclose($handle);

            echo "Success! All data imported successfully into the database." . PHP_EOL;
        } catch (Exception $e) {
            die("Error reading CSV file: " . $e->getMessage() . PHP_EOL);
        }
    }
}

// Helper Class to handle various utility functions
class Helper
{
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
            $this->consoleHelp();
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
            echo "Database connection error: " . $e->getMessage() . PHP_EOL;
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
        echo "Please provide the --file option or --create_table option to perform further actions." . PHP_EOL;
    }
    // Display Help menu
    public function consoleHelp(): void
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
new Main();