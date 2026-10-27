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

    public function show(int $id): never
    {
        $profile = $this->customerService->getProfile($id);

        if ($profile === null) {
            Response::json([
                'error' => 'Customer not found.',
            ], 404);
        }

        Response::json($profile);
    }
}