<?php

namespace Schemastud\Frame\Tests;

use PHPUnit\Framework\TestCase;
use Schemastud\Frame\Http\Controllers\FrameResourceController;
use Schemastud\Frame\Http\Controllers\FrameResourceFiltersController;
use Schemastud\Frame\Http\Controllers\FrameResourceSummaryController;
use Schemastud\Frame\Http\Controllers\ServesResourceSocket;

/**
 * The socket exports its own identity (A4-C4): every socket controller carries the
 * ServesResourceSocket marker, because consumers above frame (beam's write-gate helper and its
 * authorization audit) recognize the family through it. Un-marking a controller here silently
 * un-guards it there — which is exactly what this test refuses.
 */
class SocketMarkerTest extends TestCase
{
    public function test_every_socket_controller_carries_the_marker(): void
    {
        foreach ([
            FrameResourceController::class,
            FrameResourceFiltersController::class,
            FrameResourceSummaryController::class,
        ] as $controller) {
            $this->assertTrue(
                is_a($controller, ServesResourceSocket::class, true),
                "{$controller} must declare ServesResourceSocket — beam's write gate and audit rest on it",
            );
        }
    }
}
