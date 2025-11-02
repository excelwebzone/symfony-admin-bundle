<?php

namespace EWZ\SymfonyAdminBundle\Util;

use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Runs a console command from another console command.
 * - When $wait = true: runs synchronously; if $outputFile is null, output is discarded (no file).
 * - When $wait = false: starts detached; if $outputFile is null, output is discarded.
 */
final class CommandRunner
{
    /**
     * @param string      $command    e.g. "admin:report:export"
     * @param array       $params     assoc or indexed args/flags; flags use ['--flag'=>true] or ['--opt'=>'val']
     * @param string|null $outputFile path to redirect stdout/stderr; null => discard
     * @param bool        $wait       true => synchronous, returns exit code; false => background, returns null
     */
    public static function runCommand(string $command, array $params, ?string $outputFile = null, bool $wait = false): ?int
    {
        $phpPath = (new PhpExecutableFinder())->find();
        if (!$phpPath) {
            throw new \RuntimeException('Unable to locate PHP executable.');
        }

        // Convert command arguments to the string
        $parametersString = '';
        foreach ($params as $name => $value) {
            if (\is_string($name) && '-' === $name[0]) {
                if (true === $value) {
                    $parametersString .= ' '.$name;
                } elseif (false !== $value) {
                    $parametersString .= ' '.sprintf('%s=%s', $name, $value);
                }
            } else {
                $parametersString .= ' '.$value;
            }
        }

        $console = $_SERVER['argv'][0] ?? 'bin/console';
        $baseCmd = sprintf('%s %s %s%s', $phpPath, $console, $command, $parametersString);

        // Synchronous
        if ($wait) {
            // If caller asked for a file, redirect to it; else discard output (no file writes)
            if (null !== $outputFile) {
                $cmdline = sprintf('%s > %s 2>&1', $baseCmd, escapeshellarg($outputFile));
                $process = Process::fromShellCommandline($cmdline);
            } else {
                // No file capture; also don’t buffer output in memory
                $process = Process::fromShellCommandline($baseCmd);
                $process->disableOutput(); // drop stdout/stderr
            }
            $process->setTimeout(null);
            $process->run();

            return $process->getExitCode();
        }

        // Asynchronous (detached)
        if (\defined('PHP_WINDOWS_VERSION_BUILD')) {
            $redir = null !== $outputFile
                ? sprintf(' > %s 2>&1', $outputFile)
                : ' > NUL 2>&1';
            // COM WScript for true detachment
            $wsh = new \COM('WScript.shell');
            $wsh->Run($baseCmd.$redir, 0, false);

            return null;
        }

        // *nix detached
        $redir = null !== $outputFile
            ? sprintf(' > %s 2>&1', escapeshellarg($outputFile))
            : ' > /dev/null 2>&1';
        shell_exec(sprintf('%s%s & echo $!', $baseCmd, $redir));

        return null;
    }
}
