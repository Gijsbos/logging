<?php
declare(strict_types=1);

namespace gijsbos\Logging\Classes;

use ArgumentCountError;
use Error;

/**
 * LogEnabledClass
 *  Enables logging functions in classes.
 *  Can also act as dynamic data storage for metadata.
 */
class LogEnabledClass
{
    const MODE_DEFAULT = 0;
    const MODE_METADATA = 1;

    public array $opts;
    public string $logLevel;
    public string $logOutput;
    public null|string $logOutputFile;
    public null|bool $verbose; // Null, no prefs, Bool = prefs
    public null|bool $debug; // Null, no prefs, Bool = prefs
    public array $metadata;
    public int $mode;

    public function __construct(array $opts = [])
    {
        $this->opts = $opts;
        $this->logLevel = @$opts["logLevel"] ?? "";
        $this->logOutput = @$opts["logOutput"] ?? "file";
        $this->logOutputFile = @$opts["logOutputFile"];
        $this->verbose = @$opts["verbose"];
        $this->debug = @$opts["debug"];
        $this->metadata = @$opts["metadata"] ?? [];
        $this->mode = @$opts["mode"] ?? self::MODE_DEFAULT;

        if(is_bool($this->verbose))
            $this->setVerbose($this->verbose);

        if(is_bool($this->debug))
            $this->setDebug($this->debug);
    }

    public function getOpts()
    {
        return $this->opts;
    }

    public function setOpts(array $opts)
    {
        $this->opts = $opts;
    }

    public function getLogLevel()
    {
        return $this->logLevel;
    }

    public function getLogOutput()
    {
        return $this->logOutput;
    }

    public function isVerbose()
    {
        return $this->verbose === true;
    }

    public function isDebug()
    {
        return $this->debug === true;
    }

    public function setVerbose(?bool $verbose = null)
    {
        if(is_bool($verbose))
        {
            $this->verbose = $verbose;

            $this->opts["verbose"] = $verbose;

            if($verbose)
            {
                $this->logLevel = "info";
                $this->logOutput = "console";
            }
            else
            {
                $this->logLevel = "";
                $this->logOutput = "";
            }
        }
        
        return $this;
    }

    public function setDebug(?bool $debug = null)
    {
        if(is_bool($debug))
        {
            $this->debug = $debug;
            $this->opts["debug"] = $debug;

            if($debug)
            {
                $this->logLevel = "debug";
                $this->logOutput = "console";
            }
            else
            {
                $this->logLevel = "";
                $this->logOutput = "";
            }
        }
        
        return $this;
    }

    public function getMetadata()
    {
        return $this->metadata;
    }

    public function setMetadata(array $metadata)
    {
        $this->metadata = $metadata;
    }

    public function inheritLogSettingsFrom(LogEnabledClass $source)
    {
        $this->setLogLevel($source->getLogLevel());
        $this->setLogOutput($source->getLogOutput());
        return $this;
    }

    public function passOnLogSettings(LogEnabledClass $target)
    {
        $target->setLogLevel($this->logLevel);
        $target->setLogOutput($this->logOutput);
        return $this;
    }

    public function setLogLevel(string $logLevel)
    {
        $this->logLevel = $logLevel;
        return $this;
    }

    public function setLogOutput(string $logOutput)
    {
        $this->logOutput = $logOutput;
        return $this;
    }

    public function setLogOutputFile(string $logOutputFile)
    {
        $this->logOutputFile = $logOutputFile;
        return $this;
    }

    public function __call($name, $arguments)
    {
        if($this->mode !== self::MODE_METADATA && !method_exists($this, $name))
            throw new Error("Call to undefined method ".get_called_class()."::".$name."()");

        if(str_starts_with($name, "set"))
        {
            $variableName = lcfirst(substr($name, 3));

            if(count($arguments) == 0)
            {
                $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)[0];
                $file = $trace["file"];
                $line = $trace["line"];

                // Needs one 'argument' or throws
                throw new ArgumentCountError("Too few arguments to function ".get_called_class()."::".$name."(), 0 passed in $file on line $line and exactly 1 expected");
            }

            $this->metadata[$variableName] = $arguments[0];

            return $this;
        }

        else if(str_starts_with($name, "get"))
        {
            $variableName = lcfirst(substr($name, 3));

            return @$this->metadata[$variableName];
        }

        else if(str_starts_with($name, "has"))
        {
            $variableName = lcfirst(substr($name, 3));

            return @$this->metadata[$variableName] !== null;
        }
        else
            throw new Error("Call to undefined method ".get_called_class()."::".$name."()");
    }
}