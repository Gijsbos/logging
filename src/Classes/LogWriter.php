<?php
declare(strict_types=1);

namespace gijsbos\Logging\Classes;

use LogicException;
use ReflectionMethod;
use ReflectionParameter;

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
     * writeLogToFile
     */
    private function writeLogToFile(string $message, string $type)
    {
        $fileName = "$type.log";

        if(!is_dir($this->logFolder))
            mkdir($this->logFolder, 0777, true);

        file_put_contents("{$this->logFolder}/$fileName", $message, FILE_APPEND);
    }

    /**
     * getTrace
     */
    private function getTrace()
    {
        foreach(($backtraces = debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 8)) as $i => $trace)
        {
            $function = @$trace["function"] ?? "";

            if(str_starts_with($function, "log_"))
                return $backtraces[$i+1];
        }
        return null;
    }

    /**
     * extractObjectLogParams
     */
    private function extractObjectLogParams()
    {
        $params = [];

        $trace = $this->getTrace();

        $object = @$trace["object"];
        $function = @$trace["function"];
        $className = @$trace["class"];
        
        if($object === null)
        {
            $reflection = new ReflectionMethod($className, $function);

            $verbose = array_filter($reflection->getParameters(), fn($p) => $p->getName() == "verbose");

            if(($verbose = reset($verbose)) instanceof ReflectionParameter === false)
                throw new LogicException("Static method '$function' cannot use logging without the 'verbose' parameter");

            $verbose = @$trace["args"]["verbose"] ?? ($verbose->isDefaultValueAvailable() ? $verbose->getDefaultValue() : false);

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
            $print = "[".(new \DateTime())->format("Y-m-d H:i:s")."]";

            if(strlen($callingClass))
                $print .= "[$callingClass]";

            if(strlen($type) && $type !== "info")
                $print .= "[$type]";

            $print .= " $message\n";

            if($logOutput == "console")
                print($print);
            else
                $this->writeLogToFile($print, $type);
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