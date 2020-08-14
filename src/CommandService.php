<?php

namespace BinaryBuilds\NovaAdvancedCommandRunner;

use BinaryBuilds\NovaAdvancedCommandRunner\Dto\CommandDto;
use BinaryBuilds\NovaAdvancedCommandRunner\Dto\RunDto;
use BinaryBuilds\NovaAdvancedCommandRunner\Jobs\RunCommand;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * Class CommandService
 * @package BinaryBuilds\NovaAdvancedCommandRunner
 */
class CommandService
{
    /**
     * @var string
     */
    public static $TYPE_ARTISAN = 'artisan';

    /**
     * @var string
     */
    public static $TYPE_BASH = 'bash';

    /**
     * @param CommandDto $command
     * @param RunDto $run
     * @return RunDto
     */
    public static function runCommand( CommandDto $command, RunDto $run )
    {
        if( stripos( $command->getParsedCommand(), '--should-queue' ) ){

            [ $parsed_command, $connection, $queue ] = self::parseCommandForQueue( $command->getParsedCommand() );

            $command->setParsedCommand($parsed_command );
            $run->setType( $command->getType() );
            $run->setCommand( $parsed_command );

            if( $connection ){
                if( $connection !== 'sync' ){
                    return self::queueCommand( $command, $run, $queue, $connection );
                }
            } elseif(config('queue.default') !== 'sync'){
                return self::queueCommand( $command, $run, $queue, $connection );
            }
        }

        $run->setType( $command->getType() );
        $run->setCommand( $command->getParsedCommand() );
        $run->setRanAt( now()->timestamp );
        $start = microtime(true);

        try {
            $buffer = new \Symfony\Component\Console\Output\BufferedOutput();
            if($command->getType() === self::$TYPE_ARTISAN ){
                Artisan::call($command->getParsedCommand(), [], $buffer);
            } else if ($command->getType() === self::$TYPE_BASH ){
                Process::fromShellCommandline($command->getParsedCommand(), base_path(), null, null, null)
                    ->run(function ($type, $message) use ($buffer){
                        $buffer->writeln($message);
                    });
            } else {
                throw new \Exception('Unknown command type: '.$command->getType() );
            }
            $run->setResult( $buffer->fetch() );
            $run->setStatus( 'success' );
        } catch (\Exception $exception) {
            $run->setResult( $exception->getMessage() );
            $run->setStatus('error');
        }

        $run->setDuration( round((microtime(true) - $start), 4) );

        return $run;
    }

    /**
     * @param $command
     * @return array
     */
    public static function parseCommandForQueue( $command )
    {
        $command = str_replace('--should-queue', '', $command );

        $queue = null;
        $connection = null;

        $parsed = '';

        foreach ( explode(' ', $command ) as $argv ){
            if( Str::startsWith($argv, '--cr-queue=' ) ){
                $queue = str_replace('--cr-queue=', '',$argv );
                continue;
            }

            if( Str::startsWith($argv, '--cr-connection=' ) ){
                $connection = str_replace('--cr-connection=', '',$argv );
                continue;
            }

            $parsed .= ' '.$argv;
        }

        return [ $parsed, $connection, $queue ];
    }

    /**
     * @param CommandDto $command
     * @param RunDto $run
     * @param null $queue
     * @param null $connection
     * @return RunDto
     */
    public static function queueCommand(CommandDto $command, RunDto $run, $queue = null, $connection = null )
    {
        $job = RunCommand::dispatch( $command, $run )->delay( now()->addSeconds(5) );

        if( $queue ){
            $job->onQueue( $queue );
        }

        if( $connection ){
            $job->onConnection( $connection );
        }

        $run->setStatus('pending')
            ->setResult(__('This command is queued and waiting to be processed'));

        return $run;
    }

    /**
     * @return array
     */
    public static function getHistory()
    {
        return Cache::get('nova-advanced-command-runner-history', []);
    }

    /**
     * @param $history
     * @return mixed
     */
    public static function saveHistory( $history )
    {
        Cache::forever('nova-advanced-command-runner-history', $history);
        return $history;
    }
}