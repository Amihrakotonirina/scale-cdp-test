<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\SegmentService;

final class SegmentController
{
    public function __construct(
        private SegmentService $segmentService
    ) {
    }

    public function query(Request $request): never
    {
        $input = $request->json();

        $result = $this->segmentService->query($input);

        Response::json($result);
    }
}