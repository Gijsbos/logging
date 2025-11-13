<?php
declare(strict_types=1);

namespace gijsbos\Logging\Classes;

/**
 * LogEnabledClass
 */
class LogEnabledClass
{
    public function __construct(public string $logLevel = "", public string $logOutput = "file")
    { }

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