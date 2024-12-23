<?php

namespace Opis\Closure\Test\PHP82;

use Opis\Closure\Test\SyntaxTestCase;


// Test

use ExternalRequest as Req;
use HTMLRequest as HReq;

class SyntaxTest extends SyntaxTestCase
{
    public function closureProvider(): iterable
    {
        yield [
            'Test DNF',
            static function ((HReq & RequestInterface\Req) | Req $request) {return $request?->proces();},
            <<<'PHP'
namespace Opis\Closure\Test\PHP82;
use ExternalRequest as Req,
    HTMLRequest as HReq;
return static function ((HReq & RequestInterface\Req) | Req $request) {return $request?->proces();};
PHP,
        ];
        yield [
            'Test readonly anonymous class',
            static fn() => new #[XAttr()] readonly class(){},
                <<<'PHP'
namespace Opis\Closure\Test\PHP82;
return static fn() => new #[XAttr()] readonly class(){};
PHP,
        ];
    }
}