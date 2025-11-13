<?php
declare(strict_types=1);

namespace gijsbos\Logging\Classes;

/**
 * LogEnabledClass
 */
class LogEnabledClass
{
    private string $logLevel;
    private string $logOutput;
    private null|bool $verbose; // Null, no prefs, Bool = prefs

    public function __construct(array $opts = [])
    {
        $this->logLevel = @$opts["logLevel"] ?? "";
        $this->logOutput = @$opts["logOutput"] ?? "file";
        $this->verbose = @$opts["verbose"];

        if(is_bool($this->verbose))
            $this->setVerbose($this->verbose);
    }

    public function getLogLevel()
    {
        return $this->logLevel;
    }

    public function getLogOutput()
    {
        return $this->logOutput;
    }

    public function setVerbose(bool $verbose)
    {
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
        return $this;
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
}