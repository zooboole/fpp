<?php
/**
 * This file is part of prolic/fpp.
 * (c) 2018 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FppTest;

use Fpp\Constructor;
use Fpp\Definition;
use Fpp\DefinitionCollection;
use Fpp\DefinitionType;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\TestCase;
use const Fpp\loadTemplate;
use const Fpp\replace;
use function Fpp\dump;
use function Fpp\locatePsrPath;

class DumpTest extends TestCase
{
    /**
     * @var vfsStream
     */
    private $root;

    /**
     * @var callable
     */
    private $dump;

    protected function setUp(): void
    {
        $this->root = vfsStream::setup();
        $root = $this->root->url();

        $prefixesPsr4 = [
            'Foo\\' => [
                $root . '/Foo',
            ],
            'Bar\\' => [
                $root . '/Bar',
            ],
        ];

        $locatePsrPath = function (Definition $definition, ?Constructor $constructor) use ($prefixesPsr4): string {
            return locatePsrPath($prefixesPsr4, [], $definition, $constructor);
        };

        $this->dump = function (DefinitionCollection $collection) use ($locatePsrPath): void {
            dump($collection, $locatePsrPath, loadTemplate, replace);
        };
    }

    /**
     * @test
     */
    public function it_dumps_simple_class(): void
    {
        $dump = $this->dump;

        $definition = new Definition(DefinitionType::data(), 'Foo', 'Bar', [new Constructor('String')]);
        $collection = $this->buildCollection($definition);

        $expected = <<<CODE
<?php

// this file is auto-generated by prolic/fpp
// don't edit this file manually

declare(strict_types=1);

namespace Foo;

final class Bar
{
    private \$value;

    public function __construct(string \$value)
    {
        \$this->value = \$value;
    }

    public function value(): string
    {
        return \$this->value;
    }
}

CODE;
        $dump($collection);
        $this->assertSame($expected, file_get_contents($this->root->url() . '/Foo/Bar.php'));
    }

    /**
     * @test
     */
    public function it_dumps_class_incl_its_child(): void
    {
        $dump = $this->dump;

        $definition = new Definition(
            DefinitionType::data(),
            'Foo',
            'Bar',
            [
                new Constructor('Foo\Bar'),
                new Constructor('Foo\Baz'),
            ]
        );

        $collection = $this->buildCollection($definition);

        $expected1 = <<<CODE
<?php

// this file is auto-generated by prolic/fpp
// don't edit this file manually

declare(strict_types=1);

namespace Foo;

class Bar
{
}

CODE;

        $expected2 = <<<CODE
<?php

// this file is auto-generated by prolic/fpp
// don't edit this file manually

declare(strict_types=1);

namespace Foo;

final class Baz extends Bar
{
}

CODE;

        $dump($collection);
        $this->assertSame($expected1, file_get_contents($this->root->url() . '/Foo/Bar.php'));
        $this->assertSame($expected2, file_get_contents($this->root->url() . '/Foo/Baz.php'));
    }

    /**
     * @test
     */
    public function it_dumps_a_marker()
    {
        $dump = $this->dump;

        $definition = new Definition(DefinitionType::marker(), 'Foo', 'Bar');
        $collection = $this->buildCollection($definition);

        $dump($collection);

        $expected = <<<CODE
<?php

// this file is auto-generated by prolic/fpp
// don't edit this file manually

declare(strict_types=1);

namespace Foo;

interface Bar
{
}

CODE;
        $this->assertSame($expected, file_get_contents($this->root->url() . '/Foo/Bar.php'));
    }

    /**
     * @test
     */
    public function it_dumps_a_marker_with_its_parent_marker()
    {
        $dump = $this->dump;

        $parentMarker = new Definition(DefinitionType::marker(), 'Foo', 'Baz');
        $marker = new Definition(DefinitionType::marker(), 'Foo', 'Bar', [], [], [], null, 'Baz');
        $collection = $this->buildCollection($parentMarker, $marker);

        $dump($collection);

        $expected1 = <<<CODE
<?php

// this file is auto-generated by prolic/fpp
// don't edit this file manually

declare(strict_types=1);

namespace Foo;

interface Baz
{
}

CODE;

        $expected2 = <<<CODE
<?php

// this file is auto-generated by prolic/fpp
// don't edit this file manually

declare(strict_types=1);

namespace Foo;

interface Bar extends Baz
{
}

CODE;
        $this->assertSame($expected1, file_get_contents($this->root->url() . '/Foo/Baz.php'));
        $this->assertSame($expected2, file_get_contents($this->root->url() . '/Foo/Bar.php'));
    }

    /**
     * @test
     */
    public function it_dumps_a_marker_with_its_parent_marker_located_in_another_namespace()
    {
        $dump = $this->dump;

        $parentMarker = new Definition(DefinitionType::marker(), 'Foo', 'MyMarkerA');
        $marker = new Definition(DefinitionType::marker(), 'Bar', 'MyMarkerB', [], [], [], null, 'Foo\\MyMarkerA');
        $collection = $this->buildCollection($parentMarker, $marker);

        $dump($collection);

        $expected1 = <<<CODE
<?php

// this file is auto-generated by prolic/fpp
// don't edit this file manually

declare(strict_types=1);

namespace Foo;

interface MyMarkerA
{
}

CODE;

        $expected2 = <<<CODE
<?php

// this file is auto-generated by prolic/fpp
// don't edit this file manually

declare(strict_types=1);

namespace Bar;

interface MyMarkerB extends \Foo\MyMarkerA
{
}

CODE;
        $this->assertSame($expected1, file_get_contents($this->root->url() . '/Foo/MyMarkerA.php'));
        $this->assertSame($expected2, file_get_contents($this->root->url() . '/Bar/MyMarkerB.php'));
    }

    /**
     * @test
     */
    public function it_dumps_marker_extending_an_existing_interface()
    {
        $dump = $this->dump;

        $marker = new Definition(DefinitionType::marker(), 'Bar', 'MyMarker', [], [], [], null, '\JsonSerializable');
        $collection = $this->buildCollection($marker);

        $dump($collection);

        $expected = <<<CODE
<?php

// this file is auto-generated by prolic/fpp
// don't edit this file manually

declare(strict_types=1);

namespace Bar;

interface MyMarker extends \JsonSerializable
{
}

CODE;

        $this->assertSame($expected, file_get_contents($this->root->url() . '/Bar/MyMarker.php'));
    }

    /**
     * @test
     */
    public function it_throws_exception_when_dumping_a_marker_extending_an_unexisting_interface()
    {
        $dump = $this->dump;

        $marker = new Definition(DefinitionType::marker(), 'Bar', 'MyMarker', [], [], [], null, '\XmlSerializable');
        $collection = $this->buildCollection($marker);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Marker Bar\MyMarker cannot extend unknown marker \XmlSerializable');

        $dump($collection);
    }

    /**
     * @test
     */
    public function it_throws_exception_when_dumping_a_marker_extending_a_class()
    {
        $dump = $this->dump;

        $marker = new Definition(DefinitionType::marker(), 'Bar', 'MyMarker', [], [], [], null, '\DateTime');
        $collection = $this->buildCollection($marker);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Marker Bar\MyMarker cannot extend unknown marker \DateTime');

        $dump($collection);
    }

    /**
     * @test
     */
    public function it_throws_exception_when_extending_unexisting_marker_in_current_namespace()
    {
        $dump = $this->dump;

        $parentMarker = new Definition(DefinitionType::marker(), 'Foo', 'MyMarkerA');
        $marker = new Definition(DefinitionType::marker(), 'Bar', 'MyMarkerB', [], [], [], null, 'MyMarkerA');
        $collection = $this->buildCollection($parentMarker, $marker);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Marker Bar\MyMarkerB cannot extend unknown marker Bar\MyMarkerA');

        $dump($collection);
    }

    /**
     * @test
     */
    public function it_throws_exception_when_extending_itself()
    {
        $dump = $this->dump;

        $marker = new Definition(DefinitionType::marker(), 'Bar', 'MyMarker', [], [], [], null, 'MyMarker');
        $collection = $this->buildCollection($marker);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Marker Bar\MyMarker cannot extend itself');

        $dump($collection);
    }

    /**
     * @test
     */
    public function it_throws_exception_when_extending_data()
    {
        $dump = $this->dump;

        $data = new Definition(DefinitionType::data(), 'Foo', 'MyData', [new Constructor('String')]);
        $marker = new Definition(DefinitionType::marker(), 'Foo', 'MyMarker', [], [], [], null, 'MyData');
        $collection = $this->buildCollection($data, $marker);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Marker Foo\MyMarker cannot extend Foo\MyData because it\'s not a marker');

        $dump($collection);
    }

    /**
     * @test
     */
    public function it_throws_exception_when_extending_exception()
    {
        $this->markTestIncomplete(
          'This test must be implemented when the exception keyword is added'
        );
    }

    private function buildCollection(Definition ...$definition): DefinitionCollection
    {
        $collection = new DefinitionCollection();

        foreach (func_get_args() as $arg) {
            $collection->addDefinition($arg);
        }

        return $collection;
    }
}
