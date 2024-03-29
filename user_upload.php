<?php
// Main class to handle the main script
class Main
{
    private array $options;
    public string $host;
    public string $user;
    public string $password;
    public string $dbname;
    public bool $hasFile;
    public bool $shouldCreateTable;
    public bool $idDryRun;

    public function __construct()
    {
        // Get options from CLI argument list
        $this->options = getopt("u:p:h:", ["file:", "create_table", "dry_run", "help"]);

        if (empty($this->options) || isset($this->options['help'])) {
            $this->consoleHelp();
        }

        if (isset($this->options['u']) && isset($this->options['p']) && isset($this->options['h'])) {
            $this->host = $this->options['h'];
            $this->user = $this->options['u'];
            $this->password = $this->options['p'];
            echo "MySQL username: {$this->user}" . PHP_EOL;
            echo "MySQL password: {$this->password}" . PHP_EOL;
            echo "MySQL host: {$this->host}" . PHP_EOL;
        } else {
            echo "Please provide the MySQL username, password and host" . PHP_EOL;
            exit;
        }

        $this->hasFile = isset($this->options['file']);
        $this->shouldCreateTable = isset($this->options['create_table']);
        $this->idDryRun = isset($this->options['dry_run']);

        $this->handleActions();
    }

    // Handle the actions based on the options
    private function handleActions(): void
    {
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