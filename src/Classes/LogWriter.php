<?php
declare(strict_types=1);

namespace gijsbos\Logging\Classes;

use LogicException;
use ReflectionMethod;
use ReflectionParameter;
use RuntimeException;

/**
 * LogWriter
 */
class LogWriter
{
    /**
     * __construct
     */
    public function __construct(public string $logFolder = "logs")
    { }

    /**
     * createLogMessage
     */
    private function createLogMessage(string $type, string $message, string $source = "")
    {
        $print = "";

        if(strlen($source))
            $print .= "[$source]";

        if(strlen($type) && $type !== "info")
            $print .= "[$type]";

        $print .= " $message";

        return cli_log($print);
    }

    /**
     * writeCollapsedLog
     */
    private function writeCollapsedLog(string $fileName, string $type, string $message, string $source = "")
    {
        if(!is_dir($this->logFolder))
            mkdir($this->logFolder, 0777, true);

        $filePath = "{$this->logFolder}/$fileName";

        // Create log message
        $newLineBase = $this->createLogMessage($type, $message, $source);

        // If file doesn't exist or is empty → write normally
        if (!file_exists($filePath) || filesize($filePath) === 0)
        {
            file_put_contents($filePath, $newLineBase . PHP_EOL, FILE_APPEND);
            return;
        }

        $fp = fopen($filePath, 'c+');
        if (!$fp)
        {
            throw new RuntimeException("Cannot open log file");
        }

        flock($fp, LOCK_EX);

        // Move to end
        fseek($fp, 0, SEEK_END);
        $fileSize = ftell($fp);

        $cursor = $fileSize - 1;
        $line = '';

        // Skip trailing newlines
        while ($cursor >= 0) {
            fseek($fp, $cursor);
            $char = fgetc($fp);
            if ($char !== "\n" && $char !== "\r") {
                break;
            }
            $cursor--;
        }

        // Read last line backwards
        while ($cursor >= 0) {
            fseek($fp, $cursor);
            $char = fgetc($fp);
            if ($char === "\n") {
                $cursor++;
                break;
            }
            $line = $char . $line;
            $cursor--;
        }

        $lastLineStart = max(0, $cursor);

        // Regex to parse last log line
        $pattern = '/^\[(.*?)\](?:\[(.*?)\])?(?:\[(.*?)\])? (.*?)(?: \(x(\d+)\))?$/';

        // Match line
        if (preg_match($pattern, $line, $matches))
        {
            [$match ,$lastTime, $lastSource, $lastType, $lastMessage, $count] = $matches;

            if (
                (strlen($lastType) > 0 && $lastSource === $source && $lastMessage === $message && $lastType === $type)
                ||
                ($lastSource === $source && $lastMessage === $message)
            )
            {
                // Same message → update line
                $count = $count ? ((int)$count + 1) : 2;
                $updatedLine = $this->createLogMessage($type, "$message (x$count)", $source);

                // 🔧 Truncate at the *start of the last line*
                ftruncate($fp, $lastLineStart);
                fseek($fp, $lastLineStart);
                fwrite($fp, $updatedLine . PHP_EOL);

                flock($fp, LOCK_UN);
                fclose($fp);
                return;
            }
        }

        // Different message → append new line
        fseek($fp, 0, SEEK_END);
        fwrite($fp, $newLineBase . PHP_EOL);

        flock($fp, LOCK_UN);
        fclose($fp);
    }

    /**
     * getTrace
     */
    private function getTrace() : false | array
    {
        foreach(($backtraces = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 8)) as $i => $trace)
        {
            $function = @$trace["function"] ?? "";

            if(str_starts_with($function, "log_"))
            {
                if(!array_key_exists($i+1, $backtraces))
                    return false;

                return $backtraces[$i+1];
            }
        }
        return false;
    }

    /**
     * extractObjectLogParams
     */
    private function extractObjectLogParams() : array
    {
        $params = [];

        $trace = $this->getTrace();

        if($trace == false) // No backtrace object
            return $params;

        $object = @$trace["object"];
        $function = @$trace["function"];
        $className = @$trace["class"];

        if($object === null)
        {
            $reflection = new ReflectionMethod($className, $function);

            $verbose = array_filter($reflection->getParameters(), fn($p) => $p->getName() == "verbose");

            if(($verboseParam = reset($verbose)) instanceof ReflectionParameter === false)
            {
                $opts = array_filter($reflection->getParameters(), fn($p) => $p->getName() == "opts");

                if(($optsParam = reset($opts)) instanceof ReflectionParameter === false)
                {
                    throw new LogicException("Static method '$function' cannot use logging without the 'verbose' or 'opts' parameter");
                }
                else
                {
                    $verbose = @$trace["args"][$optsParam->getPosition()]["verbose"] ?? false;
                }
            }
            else
            {
                $verbose = @$trace["args"][$verboseParam->getPosition()]["verbose"] ?? ($verboseParam->isDefaultValueAvailable() ? $verboseParam->getDefaultValue() : false);
            }            

            if($verbose)
            {
                $params["logLevel"] = "info";
                $params["logOutput"] = "console";
            }
        }
        else if(is_object($object))
        {
            if(is_subclass_of($object, LogEnabledClass::class))
            {
                if(
                    empty($object->logLevel) && (!is_string($object->logLevel) && strlen($object->logLevel) == 0)
                    ||
                    empty($object->logOutput) && (!is_string($object->logOutput) && strlen($object->logOutput) == 0)
                )
                    throw new \RuntimeException("LogEnabledClass not initialized at file " . $className);

                $callingClassArray = explode("\\", $className);
                $params["callingClass"] = end($callingClassArray);
                $params["logLevel"] = $object->logLevel;
                $params["logOutput"] = $object->logOutput;
                $params["logOutputFile"] = $object->logOutputFile;
            }
        }

        return $params;
    }

    /**
     * writeLog
     */
    public function writeLog(string $message, string $type = "")
    {
        $logParams = strlen($type) > 0 ? $this->extractObjectLogParams() : [];

        $logLevel = getenv("LOG_LEVEL") !== false ? getenv("LOG_LEVEL") : @$logParams["logLevel"] ?? "";
        $logOutput = getenv("LOG_OUTPUT") !== false ? getenv("LOG_OUTPUT") : @$logParams["logOutput"] ?? "file";
        $logOutputFile = getenv("LOG_OUTPUT_FILE") !== false ? getenv("LOG_OUTPUT_FILE") : @$logParams["logOutputFile"];
        $callingClass = @$logParams["callingClass"] ?? "";

        if(
            strlen($message) > 0
            &&
            (
                $type == "error"
                ||
                (
                    strlen($logLevel) > 0 // info or debug is set
                    &&
                    (
                        ($logLevel == "debug" && ($type == "debug" || $type == "info"))
                        ||
                        ($logLevel == "info" && $type == "info") // Log all when debug, log info when info
                    )
                )
            )
        ){
            if($logOutput == "console")
                print($this->createLogMessage($type, $message, $callingClass));
            else
                $this->writeCollapsedLog($logOutputFile ?? "$type.log", $type, $message, $callingClass);
        }
    }

    /**
     * write
     */
    public static function write(string $message, string $type = "")
    {
        (new self())->writeLog($message, $type);
    }
}