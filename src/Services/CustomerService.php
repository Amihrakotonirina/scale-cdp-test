<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\CustomerRepository;

final class CustomerService
{
    public function __construct(
        private CustomerRepository $customerRepository
    ) {
    }

    public function getProfile(int $id): ?array
    {
        return $this->customerRepository->findProfile($id);
    }
}