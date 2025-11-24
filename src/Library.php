<?php
declare(strict_types=1);

use gijsbos\Logging\Classes\LogWriter;

function cli_log(string $message)
{
    return "[".(new DateTime())->format("Y-m-d H:i:s")."] $message";
}

function cli_logf(string $message, ...$params)
{
    return sprintf("[".(new DateTime())->format("Y-m-d H:i:s")."] $message", ...$params);
}

function log_info(string $message)
{
    LogWriter::write($message, "info");
}

function log_infof(string $message, ...$params)
{
    LogWriter::write(sprintf($message, ...$params), "info");
}

function log_request(string $message)
{
    LogWriter::write($message, "request");
}

function log_requestf(string $message, ...$params)
{
    LogWriter::write(sprintf($message, ...$params), "request");
}

function log_debug(string $message)
{
    LogWriter::write($message, "debug");
}

function log_debugf(string $message, ...$params)
{
    LogWriter::write(sprintf($message, ...$params), "debug");
}

function log_error(string $message)
{
    LogWriter::write($message, "error");
}

function log_errorf(string $message, ...$params)
{
    LogWriter::write(sprintf($message, ...$params), "error");
}