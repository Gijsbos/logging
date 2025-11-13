<?php
declare(strict_types=1);

namespace gijsbos\Logging\Library;

use gijsbos\Logging\Classes\LogEnabledClass;
use LogicException;
use ReflectionMethod;
use ReflectionParameter;

function write_log_to_file(string $message, string $type)
{
    $fileName = $type == "error" ? "error.log" : "api.log";

    if(!is_dir("logs"))
        mkdir("logs");

    file_put_contents("logs/$fileName", $message, FILE_APPEND);
}

function extract_object_log_params()
{
    $params = [];

    $backtraces = array_slice(debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 4), 3);

    if(count($backtraces))
    {
        $backtrace = reset($backtraces);

        $object = @$backtrace["object"];

        $class = $backtrace["class"];
        $callingClassArray = explode("\\", $class);
        $callingClass = end($callingClassArray);
        $logLevel = null;
        $logOutput = null;
        
        if($object === null)
        {
            $function = $backtrace["function"];
            $className = $backtrace["class"];
            $reflection = new ReflectionMethod($className, $function);

            $verbose = array_filter($reflection->getParameters(), fn($p) => $p->getName() == "verbose");

            if(($verbose = reset($verbose)) instanceof ReflectionParameter === false)
                throw new LogicException("Static method '$function' cannot use logging without the 'verbose' parameter");

            $verbose = @$backtrace["args"]["verbose"] ?? ($verbose->isDefaultValueAvailable() ? $verbose->getDefaultValue() : false);

            if($verbose)
            {
                $logLevel = "info";
            }
        }
        else if(is_object($backtrace["object"]))
        {
            if(is_subclass_of($object, LogEnabledClass::class))
            {
                if(
                    empty($object->logLevel) && (!is_string($object->logLevel) && strlen($object->logLevel) == 0)
                    ||
                    empty($object->logOutput) && (!is_string($object->logOutput) && strlen($object->logOutput) == 0)
                )
                    throw new \RuntimeException("LogEnabledClass not initialized at file " . $class);

                $logLevel = $object->logLevel;
                $logOutput = $object->logOutput;
            }
        }

        $params["callingClass"] = $callingClass;
        $params["logLevel"] = $logLevel;
        $params["logOutput"] = $logOutput;
    }

    return $params;
}

function write_log(string $message, string $type = "")
{
    $logParams = strlen($type) > 0 ? extract_object_log_params() : [];

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
            write_log_to_file($print, $type);
    }
}

function log_info(string $message)
{
    write_log($message, "info");
}

function log_infof(string $message, ...$params)
{
    write_log(sprintf($message, ...$params), "info");
}

function log_request(string $message)
{
    write_log($message, "request");
}

function log_requestf(string $message, ...$params)
{
    write_log(sprintf($message, ...$params), "request");
}

function log_debug(string $message)
{
    write_log($message, "debug");
}

function log_debugf(string $message, ...$params)
{
    write_log(sprintf($message, ...$params), "debug");
}

function log_error(string $message)
{
    write_log($message, "error");
}

function log_errorf(string $message, ...$params)
{
    write_log(sprintf($message, ...$params), "error");
}