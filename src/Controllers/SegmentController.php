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

    public function query(Request $request): Response
    {
        $input = $request->json();

        $result = $this->segmentService->query($input);

        return Response::json($result);
    }
}