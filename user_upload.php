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

        if ($this->shouldCreateTable) {
            echo "Creating table" . PHP_EOL;
            exit;
        }
        if ($this->hasFile) {
            echo "Parsing file" . PHP_EOL;
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