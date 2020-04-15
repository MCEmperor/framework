<?php

namespace Illuminate\Foundation\Console;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Encryption\Encrypter;

class KeyGenerateCommand extends Command
{
    use ConfirmableTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'key:generate
                    {--show : Display the key instead of modifying files}
                    {--force : Force the operation to run when in production}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set the application key';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $key = $this->generateRandomKey();

        if ($this->option('show')) {
            return $this->line('<comment>'.$key.'</comment>');
        }

        // Next, we will replace the application key in the environment file so it is
        // automatically setup for this developer. This key gets generated using a
        // secure random byte generator and is later base64 encoded for storage.
        if (! $this->setKeyInEnvironmentFile($key)) {
            $this->error('Application key not set.');
            return;
        }

        $this->laravel['config']['app.key'] = $key;

        $this->info('Application key set successfully.');
    }

    /**
     * Generate a random key for the application.
     *
     * @return string
     */
    protected function generateRandomKey()
    {
        return 'base64:'.base64_encode(
            Encrypter::generateKey($this->laravel['config']['app.cipher'])
        );
    }

    /**
     * Set the application key in the environment file.
     *
     * @param  string  $key
     * @return bool Whether writing to the file was successfully performed
     */
    protected function setKeyInEnvironmentFile($key)
    {
        $currentKey = $this->laravel['config']['app.key'];

        if (strlen($currentKey) !== 0 && (! $this->confirmToProceed())) {
            return false;
        }

        return $this->writeNewEnvironmentFileWith($key);
    }

    /**
     * Write a new environment file with the given key.
     *
     * @param  string  $key
     * @return bool Whether writing to the file was successfully performed
     */
    protected function writeNewEnvironmentFileWith($key)
    {
        $count = 0;
        $replacement = preg_replace(
            $this->keyReplacementPattern(),
            'APP_KEY='.$key,
            file_get_contents($this->laravel->environmentFilePath()),
            -1,
            $count
        );

        // No replacements are made, indicates failure
        if ($count === 0) {
            return false;
        }

        return file_put_contents($this->laravel->environmentFilePath(), $replacement) !== false;
    }

    /**
     * Get a regex pattern that will match env APP_KEY with any random key.
     *
     * @return string
     */
    protected function keyReplacementPattern()
    {
        $escaped = preg_quote('='.$this->laravel['config']['app.key'], '/');

        return "/^APP_KEY{$escaped}/m";
    }
}
