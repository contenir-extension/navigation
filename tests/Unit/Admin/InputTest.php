<?php

declare(strict_types=1);

namespace Contenir\Extension\Navigation\Tests\Unit\Admin;

use Contenir\Extension\Navigation\Admin\Input;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
final class InputTest extends TestCase
{
    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function checkboxProvider(): array
    {
        return [
            'ticked'          => ['1', true],
            'ticked as on'    => ['on', true],
            'unticked'        => ['0', false],
            'not posted'      => [null, false],
            'posted as blank' => ['', false],
        ];
    }

    /**
     * @return array<string, array{array<string, mixed>, array<string, mixed>, int|null}>
     */
    public static function idProvider(): array
    {
        return [
            'from the query'               => [['menu' => '3'], [], 3],
            'from what was posted'         => [[], ['menu' => '4'], 4],
            'the query before the posting' => [['menu' => '3'], ['menu' => '4'], 3],
            'zero'                         => [['menu' => '0'], [], null],
            'not a number'                 => [['menu' => 'x3'], [], null],
            'negative'                     => [['menu' => '-3'], [], null],
            'none'                         => [[], [], null],
            'a list'                       => [['menu' => ['3']], [], null],
        ];
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $posted
     */
    private static function input(array $query, array $posted): Input
    {
        return Input::of(
            (new Psr17Factory())->createServerRequest('POST', '/')
                ->withQueryParams($query)
                ->withParsedBody($posted),
        );
    }

    #[Test]
    #[DataProvider('checkboxProvider')]
    public function aCheckboxIsTickedUnlessItIsBlankOrZero(mixed $posted, bool $checked): void
    {
        static::assertSame($checked, self::input([], null === $posted ? [] : ['active' => $posted])->checked('active'));
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $posted
     */
    #[Test]
    #[DataProvider('idProvider')]
    public function readsAnIdFromTheQueryOrWhatWasPosted(array $query, array $posted, ?int $id): void
    {
        static::assertSame($id, self::input($query, $posted)->id('menu'));
    }

    #[Test]
    public function readsNothingPostedFromABodyThatIsNotAForm(): void
    {
        $request = (new Psr17Factory())->createServerRequest('POST', '/')
            ->withParsedBody(null);

        static::assertSame('', Input::of($request)->posted('title'));
    }

    #[Test]
    public function readsWhatWasPostedTrimmed(): void
    {
        $input = self::input([], ['title' => '  Header  ', 'blank' => '   ', 'count' => 3]);

        static::assertSame(
            ['Header', '', null, '3', ''],
            [
                $input->posted('title'),
                $input->posted('blank'),
                $input->postedOrNull('blank'),
                $input->posted('count'),
                $input->posted('none'),
            ],
        );
    }
}
