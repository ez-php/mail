<?php

declare(strict_types=1);

namespace EzPhp\Mail\Console;

use EzPhp\Console\CommandInterface;
use EzPhp\Console\Input;

/**
 * Class MakeMailableCommand
 *
 * `make:mailable <Name>` — writes `app/Mail/<Name>.php`, a Mailable subclass that
 * configures itself in its constructor (the template-method pattern Mailable is
 * documented for). Send it with `Mail::send(new <Name>(...))`.
 *
 * @package EzPhp\Mail\Console
 */
final readonly class MakeMailableCommand implements CommandInterface
{
    /**
     * @param string|null   $appPath     The application's `app/` directory; null = `<cwd>/app`.
     * @param resource|null $errorStream Where errors go; null = STDERR (injectable for tests).
     */
    public function __construct(
        private ?string $appPath = null,
        private mixed $errorStream = null,
    ) {
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'make:mailable';
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return 'Create a new Mailable class';
    }

    /**
     * @return string
     */
    public function getHelp(): string
    {
        return 'Usage: ez make:mailable <ClassName>';
    }

    /**
     * @param list<string> $args
     *
     * @return int
     */
    public function handle(array $args): int
    {
        $name = (new Input($args))->argument(0);

        if ($name === null || preg_match('/^[A-Za-z][A-Za-z0-9]*$/', $name) !== 1) {
            fwrite($this->errorStream ?? STDERR, "Usage: ez make:mailable <ClassName>\n");

            return 1;
        }

        $dir = ($this->appPath ?? (getcwd() ?: '.') . DIRECTORY_SEPARATOR . 'app') . DIRECTORY_SEPARATOR . 'Mail';
        $fullPath = $dir . DIRECTORY_SEPARATOR . "$name.php";

        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        if (file_exists($fullPath)) {
            fwrite($this->errorStream ?? STDERR, "Mailable already exists: $name.php\n");

            return 1;
        }

        if (file_put_contents($fullPath, $this->stub($name)) === false) {
            fwrite($this->errorStream ?? STDERR, "Failed to create mailable: $name.php\n");

            return 1;
        }

        echo "Created: app/Mail/$name.php\n";

        return 0;
    }

    /**
     * @param string $name
     *
     * @return string
     */
    private function stub(string $name): string
    {
        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace App\\Mail;

            use EzPhp\\Mail\\Mailable;

            /**
             * Configures itself in the constructor; send with Mail::send(new $name(...)).
             */
            final class $name extends Mailable
            {
                /**
                 * @param string \$address Recipient address
                 * @param string \$name    Recipient display name
                 */
                public function __construct(string \$address, string \$name = '')
                {
                    \$this->to(\$address, \$name)
                        ->subject('')
                        ->text('');
                }
            }

            PHP;
    }
}
