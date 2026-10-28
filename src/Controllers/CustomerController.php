<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Services\CustomerService;

final class CustomerController
{
    public function __construct(
        private CustomerService $customerService
    ) {
    }

    public function show(int $id): Response
    {
        $profile = $this->customerService->getProfile($id);

        if ($profile === null) {
            return Response::json([
                'error' => 'Customer not found.',
            ], 404);
        }

        return Response::json($profile);
    }
}