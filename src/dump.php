<?php

declare(strict_types=1);

namespace Fpp;

const dump = '\Fpp\dump';

function dump(DefinitionCollection $collection, callable $loadTemplate, callable $replace): string
{
    $code = <<<CODE
<?php
// this file is auto-generated by prolic/fpp
// don't edit this file manually

declare(strict_types=1);


CODE;

    foreach ($collection->definitions() as $definition) {
        $template = $loadTemplate($definition);
        $classTemplate = $template->classTemplate();
        $bodyTemplate = '';

        foreach ($template->bodyTemplates() as $template) {
            $bodyTemplate .= $template . "\n";
        }

        if (! empty($bodyTemplate)) {
            $bodyTemplate = "\n$bodyTemplate";
        }

        $template = str_replace("        {{body}}\n", $bodyTemplate, $classTemplate);
        $code .= $replace($definition, $template);
    }

    return $code;
}
