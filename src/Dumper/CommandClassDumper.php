<?php

declare(strict_types=1);

namespace Fpp\Dumper;

use Fpp\Definition;

final class CommandClassDumper implements Dumper
{
    public function dump(Definition $definition): string
    {
        $code = '';
        $indent = '';

        $messageName = $definition->messageName();

        if (null === $messageName) {
            $messageName = '\\' . $definition->name();
        }

        if ($definition->namespace() !== '') {
            $code = "namespace {$definition->namespace()} {\n    ";
            $indent = '    ';

            if (null === $definition->messageName()) {
                $messageName = '\\' . $definition->namespace() . $messageName;
            }
        }

        $code .= <<<CODE
final class {$definition->name()} extends \Prooph\Common\Messaging\Command implements \Prooph\Common\Messaging\PayloadConstructable
$indent{\n$indent    use \Prooph\Common\Messaging\PayloadTrait;

$indent    protected \$messageName = '$messageName';

$indent    public function __construct(
CODE;

        foreach ($definition->arguments() as $argument) {
            if ($argument->nullable()) {
                $code .= '?';
            }
            $code .= "{$argument->typeHint()} \${$argument->name()}, ";
        }

        if (! empty($definition->arguments())) {
            $code = substr($code, 0, -2);
        }

        $code .= ")\n$indent    {\n";

        $code .= "$indent        parent::__construct([\n";
        foreach ($definition->arguments() as $argument) {
            $code .= "$indent            '{$argument->name()}' => \${$argument->name()},\n";
        }
        $code .= "$indent        ]);\n";

        $code .= "$indent    }\n\n";

        foreach ($definition->arguments() as $argument) {
            $returnType = '';
            if ($argument->typeHint()) {
                if ($argument->nullable()) {
                    $returnType = '?';
                }
                $returnType = ': ' . $returnType . $argument->typeHint();
            }

            $code .= <<<CODE
$indent    public function {$argument->name()}()$returnType
$indent    {
$indent        return \$this->payload['{$argument->name()}'];
$indent    }


CODE;
        }

        $code = substr($code, 0, -1);
        $code .= "$indent}";

        if ($definition->namespace() !== '') {
            $code .= "\n}";
        }

        $code .= "\n\n";

        return $code;
    }
}